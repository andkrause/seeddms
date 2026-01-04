<?php
/**
 * LLM Document Classifier Extension for SeedDMS
 *
 * Automatically classifies PDF documents using Large Language Models.
 * Supports OpenAI-compatible APIs including OpenAI, Azure OpenAI, and Ollama.
 *
 * Features:
 * - Automatic document naming based on content
 * - Category assignment from configured SeedDMS categories
 * - Keyword extraction with optional restriction to configured keywords
 * - Folder-based activation scope
 * - Default category assignment
 *
 * @author     Andreas Krause
 * @copyright  2026 Andreas Krause
 * @license    GPL-2.0+
 * @package    SeedDMS
 * @subpackage Extensions
 * @version    1.0.0
 */

/**
 * Main Extension Class
 *
 * Registers hooks for document processing. The extension hooks into the
 * document upload workflow to automatically classify new PDF documents.
 */
class SeedDMS_ExtLLMClassifier extends SeedDMS_ExtBase {

    /**
     * Initialize the extension
     *
     * Registers the postAddDocument hook to classify documents upon upload.
     *
     * @return void
     */
    public function init() {
        $GLOBALS['SEEDDMS_HOOKS']['controller']['addDocument'][] = 
            new SeedDMS_ExtLLMClassifier_AddDocument();
    }

    /**
     * Main entry point (required by SeedDMS but not used)
     *
     * @return void
     */
    public function main() {
        // Not used - classification happens via hooks
    }
}

/**
 * LLM API Client
 *
 * Handles communication with OpenAI-compatible chat completion APIs.
 * Supports both standard OpenAI API and Azure OpenAI deployments.
 *
 * Azure endpoints are detected automatically by checking for
 * 'openai.azure.com' or 'cognitiveservices.azure.com' in the URL.
 */
class SeedDMS_LLMClient {

    /** @var string API endpoint URL */
    private $endpoint;

    /** @var string API key for authentication */
    private $apiKey;

    /** @var string Model name or Azure deployment name */
    private $model;

    /** @var string|null API version (required for Azure) */
    private $apiVersion;

    /** @var object|null Logger instance */
    private $logger;

    /** @var bool Whether this is an Azure OpenAI endpoint */
    private $isAzure;

    /**
     * Constructor
     *
     * @param string      $endpoint   API endpoint URL
     * @param string      $apiKey     API key for authentication
     * @param string      $model      Model name or Azure deployment name
     * @param string|null $apiVersion API version (Azure only)
     * @param object|null $logger     Logger instance for error logging
     */
    public function __construct($endpoint, $apiKey, $model, $apiVersion = null, $logger = null) {
        $this->endpoint = rtrim($endpoint, '/');
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->apiVersion = $apiVersion;
        $this->logger = $logger;

        // Detect Azure OpenAI by endpoint URL
        $this->isAzure = (
            strpos($this->endpoint, 'openai.azure.com') !== false ||
            strpos($this->endpoint, 'cognitiveservices.azure.com') !== false
        );
    }

    /**
     * Send a chat completion request to the LLM
     *
     * @param string $systemPrompt System instructions for the LLM
     * @param string $userMessage  User message containing the document content
     *
     * @return array|null Parsed JSON response or null on error
     */
    public function chatCompletion($systemPrompt, $userMessage) {
        // Build URL based on provider type
        if ($this->isAzure) {
            $apiVersion = $this->apiVersion ?: '2024-02-15-preview';
            $url = sprintf(
                '%s/openai/deployments/%s/chat/completions?api-version=%s',
                $this->endpoint,
                urlencode($this->model),
                urlencode($apiVersion)
            );
        } else {
            $url = $this->endpoint . '/chat/completions';
        }

        // Build request payload
        $payload = [
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage]
            ],
            'temperature' => 0.3,
            'response_format' => ['type' => 'json_object']
        ];

        // Azure uses deployment name in URL, not in payload
        if (!$this->isAzure) {
            $payload['model'] = $this->model;
        }

        // Build headers with appropriate authentication
        $headers = ['Content-Type: application/json'];
        if (!empty($this->apiKey)) {
            if ($this->isAzure) {
                $headers[] = 'api-key: ' . $this->apiKey;
            } else {
                $headers[] = 'Authorization: Bearer ' . $this->apiKey;
            }
        }

        // Execute request
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        // Handle curl errors
        if ($curlErrno !== 0) {
            $this->logError("API connection failed: [$curlErrno] $curlError");
            return null;
        }

        // Handle HTTP errors
        if ($httpCode !== 200) {
            $this->logError("API returned HTTP $httpCode");
            $this->logError("URL: $url");
            $this->logError("Response: $response");
            return null;
        }

        // Parse response
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logError("Failed to parse API response: " . json_last_error_msg());
            return null;
        }

        // Extract content from response
        if (!isset($data['choices'][0]['message']['content'])) {
            $this->logError("Invalid API response structure");
            return null;
        }

        $content = $data['choices'][0]['message']['content'];
        $result = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logError("Failed to parse LLM JSON output: " . json_last_error_msg());
            $this->logError("Raw content: $content");
            return null;
        }

        return $result;
    }

    /**
     * Log an error message
     *
     * @param string $message Error message to log
     *
     * @return void
     */
    private function logError($message) {
        if ($this->logger) {
            $this->logger->log("[LLMClassifier] $message", PEAR_LOG_ERR);
        }
    }
}

/**
 * PDF Text Extractor
 *
 * Extracts text content from PDF files using the pdftotext utility.
 * Validates the pdftotext binary on construction and provides
 * clear error messages if unavailable.
 */
class SeedDMS_PDFExtractor {

    /** @var string Default path to pdftotext binary */
    const DEFAULT_PDFTOTEXT_PATH = '/usr/bin/pdftotext';

    /** @var string Path to pdftotext binary */
    private $pdftotextPath;

    /** @var object|null Logger instance */
    private $logger;

    /** @var string|null Initialization error message */
    private $initError;

    /**
     * Constructor
     *
     * @param string|null $pdftotextPath Path to pdftotext binary (optional)
     * @param object|null $logger        Logger instance for error logging
     */
    public function __construct($pdftotextPath = null, $logger = null) {
        $this->logger = $logger;
        $this->initError = null;
        $this->pdftotextPath = !empty($pdftotextPath) 
            ? $pdftotextPath 
            : self::DEFAULT_PDFTOTEXT_PATH;

        // Validate pdftotext binary
        if (!file_exists($this->pdftotextPath)) {
            $this->initError = "pdftotext binary not found at: " . $this->pdftotextPath;
            $this->logWarning($this->initError);
        } elseif (!is_executable($this->pdftotextPath)) {
            $this->initError = "pdftotext binary is not executable: " . $this->pdftotextPath;
            $this->logWarning($this->initError);
        }
    }

    /**
     * Check if the extractor is ready to use
     *
     * @return bool True if pdftotext is available and executable
     */
    public function isReady() {
        return $this->initError === null;
    }

    /**
     * Get initialization error message
     *
     * @return string|null Error message or null if ready
     */
    public function getError() {
        return $this->initError;
    }

    /**
     * Extract text from a PDF file
     *
     * @param string $filePath  Path to the PDF file
     * @param int    $maxLength Maximum characters to return (default: 4000)
     *
     * @return string|null Extracted text or null on error
     */
    public function extractText($filePath, $maxLength = 4000) {
        if (!$this->isReady()) {
            $this->logWarning("Cannot extract text: " . $this->initError);
            return null;
        }

        if (!file_exists($filePath)) {
            $this->logWarning("PDF file not found: $filePath");
            return null;
        }

        if (!is_readable($filePath)) {
            $this->logWarning("PDF file not readable: $filePath");
            return null;
        }

        // Create temporary file for output
        $tempFile = tempnam(sys_get_temp_dir(), 'llmclassifier_');
        if ($tempFile === false) {
            $this->logWarning("Failed to create temporary file");
            return null;
        }

        // Execute pdftotext
        $cmd = sprintf(
            '%s -layout %s %s 2>&1',
            escapeshellcmd($this->pdftotextPath),
            escapeshellarg($filePath),
            escapeshellarg($tempFile)
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->logWarning("pdftotext failed (code $returnCode): " . implode("\n", $output));
            @unlink($tempFile);
            return null;
        }

        // Read extracted text
        $text = @file_get_contents($tempFile);
        @unlink($tempFile);

        if ($text === false || empty(trim($text))) {
            $this->logWarning("No text content extracted from PDF");
            return null;
        }

        // Normalize whitespace and trim
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        // Truncate if necessary
        if (mb_strlen($text) > $maxLength) {
            $text = mb_substr($text, 0, $maxLength) . '...';
        }

        return $text;
    }

    /**
     * Log a warning message
     *
     * @param string $message Warning message to log
     *
     * @return void
     */
    private function logWarning($message) {
        if ($this->logger) {
            $this->logger->log("[LLMClassifier] $message", PEAR_LOG_WARNING);
        }
    }
}

/**
 * Document Classifier
 *
 * Main classification logic. Coordinates text extraction, LLM API calls,
 * and document metadata updates. Reads configuration from SeedDMS
 * extension settings.
 */
class SeedDMS_DocumentClassifier {

    /** @var SeedDMS_LLMClient LLM API client */
    private $llmClient;

    /** @var SeedDMS_PDFExtractor PDF text extractor */
    private $pdfExtractor;

    /** @var object SeedDMS DMS instance */
    private $dms;

    /** @var object SeedDMS settings */
    private $settings;

    /** @var object|null Logger instance */
    private $logger;

    /** @var string Additional prompt instructions */
    private $additionalPrompt;

    /** @var array Extension settings cache */
    private $extSettings;

    /**
     * Constructor
     *
     * @param object      $settings SeedDMS settings object
     * @param object      $dms      SeedDMS DMS instance
     * @param object|null $logger   Logger instance
     */
    public function __construct($settings, $dms, $logger) {
        $this->settings = $settings;
        $this->dms = $dms;
        $this->logger = $logger;
        $this->extSettings = $settings->_extensions['llmclassifier'] ?? [];

        // Initialize LLM client
        $this->llmClient = new SeedDMS_LLMClient(
            $this->extSettings['llm_endpoint'] ?? '',
            $this->extSettings['llm_api_key'] ?? '',
            $this->extSettings['llm_model'] ?? 'gpt-4o',
            $this->extSettings['llm_api_version'] ?? null,
            $logger
        );

        // Initialize PDF extractor
        $this->pdfExtractor = new SeedDMS_PDFExtractor(
            $this->extSettings['pdftotext_path'] ?? null,
            $logger
        );

        $this->additionalPrompt = $this->extSettings['additional_prompt'] ?? '';
    }

    /**
     * Check if the extension is enabled
     *
     * @return bool True if enabled and configured
     */
    public function isEnabled() {
        return !empty($this->extSettings['llm_enabled']) 
            && !empty($this->extSettings['llm_endpoint']);
    }

    /**
     * Check if keyword restriction is enabled
     *
     * @return bool True if keywords should be restricted to configured list
     */
    public function isRestrictKeywords() {
        return !empty($this->extSettings['restrict_keywords']);
    }

    /**
     * Check if a document is within the allowed folder tree
     *
     * @param object $document SeedDMS document object
     *
     * @return bool True if document is in allowed folder tree
     */
    public function isDocumentInAllowedFolder($document) {
        $limitFolderId = $this->getLimitFolderId();

        // No restriction configured
        if ($limitFolderId <= 0) {
            return true;
        }

        $folder = $document->getFolder();
        if (!$folder) {
            $this->logInfo("Cannot determine document folder");
            return false;
        }

        // Walk up the folder tree to check for match
        $currentFolder = $folder;
        while ($currentFolder) {
            if ($currentFolder->getID() == $limitFolderId) {
                return true;
            }
            $currentFolder = $currentFolder->getParent();
        }

        $this->logInfo(sprintf(
            "Document folder '%s' (ID: %d) is outside the allowed folder tree",
            $folder->getName(),
            $folder->getID()
        ));

        return false;
    }

    /**
     * Classify a document using the LLM
     *
     * Extracts text from the PDF, sends it to the LLM for classification,
     * and returns the structured result.
     *
     * @param object $document SeedDMS document object
     *
     * @return array|null Classification result or null on error
     */
    public function classifyDocument($document) {
        $docId = $document->getID();
        $this->logInfo("Starting classification for document ID: $docId");

        // Get latest document content
        $content = $document->getLatestContent();
        if (!$content) {
            $this->logError("No content found for document $docId");
            return null;
        }

        // Verify document is a PDF
        $mimeType = $content->getMimeType();
        if ($mimeType !== 'application/pdf') {
            $this->logInfo("Skipping non-PDF document (type: $mimeType)");
            return null;
        }

        // Verify PDF extractor is ready
        if (!$this->pdfExtractor->isReady()) {
            $this->logError("PDF extractor not ready: " . $this->pdfExtractor->getError());
            return null;
        }

        // Build file path
        $filePath = $this->dms->contentDir . $content->getPath();
        $this->logInfo("File path: $filePath");

        if (!file_exists($filePath)) {
            $this->logError("File not found: $filePath");
            return null;
        }

        // Extract text from PDF
        $text = $this->pdfExtractor->extractText($filePath, $this->getMaxTextLength());
        if (!$text) {
            $this->logError("Text extraction failed for document $docId");
            return null;
        }

        $this->logInfo("Extracted " . strlen($text) . " characters");

        // Get categories for LLM (excluding default category)
        $categoryNames = $this->getCategoryNamesForLLM();
        $this->logInfo("Available categories: " . count($categoryNames));

        // Get keywords if restriction is enabled
        $configuredKeywords = [];
        if ($this->isRestrictKeywords()) {
            $configuredKeywords = $this->getConfiguredKeywords();
            $this->logInfo("Keyword restriction enabled: " . count($configuredKeywords) . " keywords");
        }

        // Build prompts
        $systemPrompt = $this->buildSystemPrompt($categoryNames, $configuredKeywords);
        $userMessage = $this->buildUserMessage($text, $document->getName());

        // Call LLM API
        $this->logInfo("Calling LLM API...");
        $result = $this->llmClient->chatCompletion($systemPrompt, $userMessage);

        if (!$result) {
            $this->logError("LLM API call failed");
            return null;
        }

        $this->logInfo("Classification result: " . json_encode($result, JSON_UNESCAPED_UNICODE));
        return $result;
    }

    /**
     * Apply classification results to a document
     *
     * Updates document name, keywords, and categories based on the
     * classification result.
     *
     * @param object $document       SeedDMS document object
     * @param array  $classification Classification result from LLM
     *
     * @return bool True if any changes were made
     */
    public function applyClassification($document, $classification) {
        if (!$classification || !is_array($classification)) {
            $this->logError("Invalid classification data");
            return false;
        }

        $updated = false;
        $docId = $document->getID();

        // Apply document name
        $updated = $this->applyName($document, $classification) || $updated;

        // Apply keywords
        $updated = $this->applyKeywords($document, $classification) || $updated;

        // Apply LLM-suggested categories
        $updated = $this->applyCategories($document, $classification) || $updated;

        // Apply default category
        $updated = $this->applyDefaultCategory($document) || $updated;

        if ($updated) {
            $this->logInfo("Document $docId updated successfully");
        } else {
            $this->logInfo("No changes applied to document $docId");
        }

        return $updated;
    }

    // =========================================================================
    // Private Configuration Helpers
    // =========================================================================

    /**
     * Get maximum text length to send to LLM
     *
     * @return int Maximum text length (default: 4000)
     */
    private function getMaxTextLength() {
        $value = (int)($this->extSettings['max_text_length'] ?? 4000);
        return $value > 0 ? $value : 4000;
    }

    /**
     * Get maximum title length
     *
     * @return int Maximum title length (default: 100)
     */
    private function getMaxTitleLength() {
        $value = (int)($this->extSettings['max_title_length'] ?? 100);
        return $value > 0 ? $value : 100;
    }

    /**
     * Get folder ID for extension scope limitation
     *
     * @return int Folder ID (0 = no limitation)
     */
    private function getLimitFolderId() {
        $value = $this->extSettings['limit_folder'] ?? 0;
        if (is_array($value)) {
            $value = !empty($value) ? (int)$value[0] : 0;
        }
        return (int)$value;
    }

    /**
     * Get default category ID
     *
     * @return int Category ID (0 = no default)
     */
    private function getDefaultCategoryId() {
        $value = $this->extSettings['default_category'] ?? 0;
        if (is_array($value)) {
            $value = !empty($value) ? (int)$value[0] : 0;
        }
        return (int)$value;
    }

    // =========================================================================
    // Private Category Helpers
    // =========================================================================

    /**
     * Get category names for LLM selection (excludes default category)
     *
     * @return array List of category names
     */
    private function getCategoryNamesForLLM() {
        $categoryNames = [];
        $categories = $this->dms->getDocumentCategories();
        $defaultCategoryId = $this->getDefaultCategoryId();

        if (!$categories || !is_array($categories)) {
            $this->logWarning("No categories found in SeedDMS");
            return $categoryNames;
        }

        foreach ($categories as $cat) {
            // Exclude default category from LLM selection
            if ($defaultCategoryId > 0 && $cat->getID() == $defaultCategoryId) {
                $this->logInfo("Excluding default category from LLM: " . $cat->getName());
                continue;
            }
            $categoryNames[] = $cat->getName();
        }

        return $categoryNames;
    }

    // =========================================================================
    // Private Keyword Helpers
    // =========================================================================

    /**
     * Get all configured keywords from SeedDMS keyword categories
     *
     * @return array List of configured keywords
     */
    private function getConfiguredKeywords() {
        $keywords = [];

        try {
            $categories = $this->dms->getAllKeywordCategories();

            if ($categories && is_array($categories)) {
                foreach ($categories as $category) {
                    $keywordLists = $category->getKeywordLists();
                    if ($keywordLists && is_array($keywordLists)) {
                        foreach ($keywordLists as $kw) {
                            if (!empty($kw['keywords'])) {
                                $keywords[] = trim($kw['keywords']);
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->logError("Failed to fetch keywords: " . $e->getMessage());
        }

        return $keywords;
    }

    /**
     * Filter keywords to only include those configured in SeedDMS
     *
     * @param array $keywords Keywords to filter
     *
     * @return array Filtered keywords
     */
    private function filterKeywords($keywords) {
        if (!$this->isRestrictKeywords()) {
            return $keywords;
        }

        $configured = $this->getConfiguredKeywords();
        if (empty($configured)) {
            $this->logWarning("Keyword restriction enabled but no keywords configured");
            return $keywords;
        }

        // Build case-insensitive lookup map
        $configuredMap = [];
        foreach ($configured as $kw) {
            $configuredMap[mb_strtolower($kw)] = $kw;
        }

        $filtered = [];
        $rejected = [];

        foreach ($keywords as $keyword) {
            $lower = mb_strtolower($keyword);
            if (isset($configuredMap[$lower])) {
                $filtered[] = $configuredMap[$lower];
            } else {
                $rejected[] = $keyword;
            }
        }

        if (!empty($rejected)) {
            $this->logInfo("Rejected keywords: " . implode(', ', $rejected));
        }

        return $filtered;
    }

    // =========================================================================
    // Private Apply Helpers
    // =========================================================================

    /**
     * Apply document name from classification
     *
     * @param object $document       SeedDMS document object
     * @param array  $classification Classification result
     *
     * @return bool True if name was updated
     */
    private function applyName($document, $classification) {
        if (empty($classification['name'])) {
            return false;
        }

        $newName = $classification['name'];

        // Skip if name unchanged
        if ($newName === $document->getName()) {
            return false;
        }

        // Log warning if LLM exceeded the requested limit (but don't truncate)
        $maxLength = $this->getMaxTitleLength();
        if (mb_strlen($newName) > $maxLength) {
            $this->logWarning(sprintf(
                "LLM generated title exceeds configured limit (%d > %d chars)",
                mb_strlen($newName),
                $maxLength
            ));
        }

        $result = $document->setName($newName);
        if ($result === false) {
            $this->logError("Failed to set document name");
            return false;
        }

        $this->logInfo("Updated name: $newName");
        return true;
    }

    /**
     * Apply keywords from classification
     *
     * @param object $document       SeedDMS document object
     * @param array  $classification Classification result
     *
     * @return bool True if keywords were updated
     */
    private function applyKeywords($document, $classification) {
        if (empty($classification['keywords'])) {
            return false;
        }

        // Normalize keywords to array
        $keywords = is_array($classification['keywords'])
            ? $classification['keywords']
            : array_map('trim', explode(',', $classification['keywords']));

        // Filter if restriction is enabled
        $keywords = $this->filterKeywords(array_map('trim', $keywords));

        if (empty($keywords)) {
            $this->logInfo("No keywords to apply after filtering");
            return false;
        }

        // Merge with existing keywords
        $existing = $document->getKeywords();
        $newKeywords = implode(', ', $keywords);

        if (!empty($existing)) {
            $newKeywords = $existing . ', ' . $newKeywords;
        }

        $result = $document->setKeywords($newKeywords);
        if ($result === false) {
            $this->logError("Failed to set document keywords");
            return false;
        }

        $this->logInfo("Updated keywords");
        return true;
    }

    /**
     * Apply categories from classification
     *
     * @param object $document       SeedDMS document object
     * @param array  $classification Classification result
     *
     * @return bool True if any categories were added
     */
    private function applyCategories($document, $classification) {
        if (empty($classification['categories'])) {
            return false;
        }

        // Normalize to array
        $categoryNames = is_array($classification['categories'])
            ? $classification['categories']
            : [$classification['categories']];

        // Get all available categories
        $allCategories = $this->dms->getDocumentCategories();
        if (!$allCategories) {
            $this->logWarning("No categories available in SeedDMS");
            return false;
        }

        // Get existing category IDs
        $existingIds = [];
        foreach ($document->getCategories() as $cat) {
            $existingIds[] = $cat->getID();
        }

        // Find matching categories
        $updated = false;
        foreach ($categoryNames as $name) {
            foreach ($allCategories as $cat) {
                if (strcasecmp($cat->getName(), $name) === 0) {
                    if (!in_array($cat->getID(), $existingIds)) {
                        $document->addCategories([$cat]);
                        $this->logInfo("Added category: " . $cat->getName());
                        $existingIds[] = $cat->getID();
                        $updated = true;
                    }
                    break;
                }
            }
        }

        return $updated;
    }

    /**
     * Apply default category to document
     *
     * @param object $document SeedDMS document object
     *
     * @return bool True if default category was added
     */
    private function applyDefaultCategory($document) {
        $defaultId = $this->getDefaultCategoryId();
        if ($defaultId <= 0) {
            return false;
        }

        $defaultCategory = $this->dms->getDocumentCategory($defaultId);
        if (!$defaultCategory) {
            $this->logError("Default category (ID: $defaultId) not found");
            return false;
        }

        // Check if already assigned
        foreach ($document->getCategories() as $cat) {
            if ($cat->getID() == $defaultId) {
                $this->logInfo("Default category already assigned");
                return false;
            }
        }

        $document->addCategories([$defaultCategory]);
        $this->logInfo("Added default category: " . $defaultCategory->getName());
        return true;
    }

    // =========================================================================
    // Private Prompt Builders
    // =========================================================================

    /**
     * Build system prompt for LLM
     *
     * @param array $categoryNames     Available category names
     * @param array $configuredKeywords Configured keywords (if restricted)
     *
     * @return string System prompt
     */
    private function buildSystemPrompt($categoryNames, $configuredKeywords = []) {
        $maxTitleLength = $this->getMaxTitleLength();
        $categoriesJson = json_encode($categoryNames, JSON_UNESCAPED_UNICODE);

        // Build keyword instruction
        $keywordInstruction = "3. **keywords**: Relevant search keywords (in the document's language)";
        if (!empty($configuredKeywords)) {
            $keywordsJson = json_encode($configuredKeywords, JSON_UNESCAPED_UNICODE);
            $keywordInstruction = "3. **keywords**: Select ONLY from this list: $keywordsJson";
        }

        $prompt = <<<PROMPT
You are a document classification assistant. Analyze the PDF document and provide:

1. **name**: A clear, descriptive name (in the document's language, max $maxTitleLength characters)
2. **categories**: Select from this list: $categoriesJson
$keywordInstruction

IMPORTANT: For tax-related documents (invoices, receipts, expenses), include "Steuer" in keywords if available.

Respond with valid JSON only:
{"name": "Document Name", "categories": ["Category"], "keywords": ["keyword1", "keyword2"]}
PROMPT;

        // Append additional instructions if configured
        if (!empty($this->additionalPrompt)) {
            $prompt .= "\n\nADDITIONAL INSTRUCTIONS:\n" . $this->additionalPrompt;
        }

        return $prompt;
    }

    /**
     * Build user message containing document content
     *
     * @param string $text        Extracted document text
     * @param string $currentName Current document name
     *
     * @return string User message
     */
    private function buildUserMessage($text, $currentName) {
        return <<<MSG
Classify this document. Current filename: "$currentName"

Document content:
---
$text
---

Provide JSON with name, categories, and keywords.
MSG;
    }

    // =========================================================================
    // Private Logging Helpers
    // =========================================================================

    /**
     * Log an info message
     *
     * @param string $message Message to log
     *
     * @return void
     */
    private function logInfo($message) {
        if ($this->logger) {
            $this->logger->log("[LLMClassifier] $message", PEAR_LOG_INFO);
        }
    }

    /**
     * Log a warning message
     *
     * @param string $message Message to log
     *
     * @return void
     */
    private function logWarning($message) {
        if ($this->logger) {
            $this->logger->log("[LLMClassifier] $message", PEAR_LOG_WARNING);
        }
    }

    /**
     * Log an error message
     *
     * @param string $message Message to log
     *
     * @return void
     */
    private function logError($message) {
        if ($this->logger) {
            $this->logger->log("[LLMClassifier] $message", PEAR_LOG_ERR);
        }
    }
}

/**
 * Document Upload Hook
 *
 * Called after a document is uploaded to SeedDMS. Triggers automatic
 * classification for PDF documents.
 */
class SeedDMS_ExtLLMClassifier_AddDocument {

    /**
     * Post-upload hook handler
     *
     * @param object $controller SeedDMS controller instance
     * @param object $document   Newly uploaded document
     *
     * @return null
     */
    public function postAddDocument($controller, $document) {
        // Get required objects from controller
        $dms = $controller->getParam('dms');
        $settings = $controller->getParam('settings');
        $logger = $controller->hasParam('logger') 
            ? $controller->getParam('logger') 
            : null;

        if ($logger) {
            $logger->log(
                "[LLMClassifier] Document uploaded: ID " . $document->getID(),
                PEAR_LOG_INFO
            );
        }

        // Validate DMS availability
        if (!$dms) {
            if ($logger) {
                $logger->log("[LLMClassifier] DMS not available", PEAR_LOG_ERR);
            }
            return null;
        }

        // Initialize classifier
        $classifier = new SeedDMS_DocumentClassifier($settings, $dms, $logger);

        // Check if extension is enabled
        if (!$classifier->isEnabled()) {
            if ($logger) {
                $logger->log("[LLMClassifier] Extension disabled", PEAR_LOG_INFO);
            }
            return null;
        }

        // Check folder restriction
        if (!$classifier->isDocumentInAllowedFolder($document)) {
            if ($logger) {
                $logger->log("[LLMClassifier] Document outside allowed folder", PEAR_LOG_INFO);
            }
            return null;
        }

        // Classify document
        $result = $classifier->classifyDocument($document);

        if ($result) {
            // Apply classification
            $applied = $classifier->applyClassification($document, $result);

            if ($logger) {
                $logger->log(
                    "[LLMClassifier] Classification " . ($applied ? "applied" : "no changes"),
                    PEAR_LOG_INFO
                );
            }

            // Store result in session for UI access
            if (isset($_SESSION)) {
                $_SESSION['llmclassifier_result'][$document->getID()] = $result;
            }
        }

        return null;
    }
}

