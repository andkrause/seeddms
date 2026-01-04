<?php
/**
 * Language Strings for LLM Document Classifier Extension
 *
 * Provides translations for the extension configuration interface.
 *
 * @author     Andreas Krause
 * @copyright  2026 Andreas Krause
 * @license    GPL-2.0+
 * @package    SeedDMS
 * @subpackage Extensions
 * @version    1.0.0
 */

// =============================================================================
// German (Germany)
// =============================================================================

$__lang['de_DE'] = array(
    // Extension info
    'llmclassifier' => 'LLM Dokumenten-Klassifizierer',
    'llmclassifier_desc' => 'Klassifiziert PDF-Dokumente automatisch mit Hilfe von KI',

    // Core settings
    'llm_enabled' => 'LLM-Klassifizierung aktivieren',
    'llm_enabled_desc' => 'Aktiviert die automatische Dokumentenklassifizierung beim Upload',
    'llm_endpoint' => 'LLM API-Endpunkt',
    'llm_endpoint_desc' => 'URL des API-Endpunkts (z.B. https://api.openai.com/v1 oder Azure-Endpunkt)',
    'llm_api_key' => 'API-Schlüssel',
    'llm_api_key_desc' => 'Authentifizierungsschlüssel für den LLM-Dienst',
    'llm_model' => 'Modell / Deployment-Name',
    'llm_model_desc' => 'Modellname (z.B. gpt-4o) oder Azure Deployment-Name',
    'llm_api_version' => 'API-Version (nur Azure)',
    'llm_api_version_desc' => 'API-Version für Azure OpenAI (z.B. 2024-02-15-preview)',

    // Scope settings
    'limit_folder' => 'Erweiterung auf Ordner beschränken',
    'limit_folder_desc' => 'Nur Dokumente in diesem Ordner und Unterordnern klassifizieren. Leer = alle Ordner.',
    'default_category' => 'Standard-Kategorie',
    'default_category_desc' => 'Wird automatisch allen klassifizierten Dokumenten hinzugefügt (nicht dem LLM zur Auswahl angeboten)',

    // Classification settings
    'max_title_length' => 'Maximale Titellänge',
    'max_title_length_desc' => 'Maximale Zeichenanzahl für Dokumentennamen (Standard: 100)',
    'restrict_keywords' => 'Auf konfigurierte Schlüsselwörter beschränken',
    'restrict_keywords_desc' => 'Nur Schlüsselwörter aus SeedDMS-Schlüsselwortkategorien verwenden',

    // Technical settings
    'pdftotext_path' => 'Pfad zu pdftotext',
    'pdftotext_path_desc' => 'Vollständiger Pfad zum pdftotext-Programm (Standard: /usr/bin/pdftotext)',
    'max_text_length' => 'Maximale Textlänge für LLM',
    'max_text_length_desc' => 'Maximale Zeichenanzahl für LLM-Kontext (Standard: 4000)',
    'additional_prompt' => 'Zusätzliche Prompt-Anweisungen',
    'additional_prompt_desc' => 'Benutzerdefinierte Anweisungen, die dem System-Prompt hinzugefügt werden',

    // UI strings
    'classification_result' => 'KI-Klassifizierungsergebnis',
    'suggested_name' => 'Vorgeschlagener Name',
    'suggested_categories' => 'Vorgeschlagene Kategorien',
    'suggested_keywords' => 'Vorgeschlagene Schlüsselwörter',
);

// =============================================================================
// English (Great Britain)
// =============================================================================

$__lang['en_GB'] = array(
    // Extension info
    'llmclassifier' => 'LLM Document Classifier',
    'llmclassifier_desc' => 'Automatically classifies PDF documents using AI',

    // Core settings
    'llm_enabled' => 'Enable LLM Classification',
    'llm_enabled_desc' => 'Enable automatic document classification on upload',
    'llm_endpoint' => 'LLM API Endpoint',
    'llm_endpoint_desc' => 'API endpoint URL (e.g., https://api.openai.com/v1 or Azure endpoint)',
    'llm_api_key' => 'API Key',
    'llm_api_key_desc' => 'Authentication key for the LLM service',
    'llm_model' => 'Model / Deployment Name',
    'llm_model_desc' => 'Model name (e.g., gpt-4o) or Azure deployment name',
    'llm_api_version' => 'API Version (Azure only)',
    'llm_api_version_desc' => 'API version for Azure OpenAI (e.g., 2024-02-15-preview)',

    // Scope settings
    'limit_folder' => 'Limit Extension to Folder',
    'limit_folder_desc' => 'Only classify documents in this folder and subfolders. Empty = all folders.',
    'default_category' => 'Default Category',
    'default_category_desc' => 'Automatically added to all classified documents (not offered to LLM for selection)',

    // Classification settings
    'max_title_length' => 'Maximum Title Length',
    'max_title_length_desc' => 'Maximum characters for document names (default: 100)',
    'restrict_keywords' => 'Restrict to Configured Keywords',
    'restrict_keywords_desc' => 'Only use keywords from SeedDMS keyword categories',

    // Technical settings
    'pdftotext_path' => 'Path to pdftotext',
    'pdftotext_path_desc' => 'Full path to pdftotext binary (default: /usr/bin/pdftotext)',
    'max_text_length' => 'Max Text Length for LLM',
    'max_text_length_desc' => 'Maximum characters for LLM context (default: 4000)',
    'additional_prompt' => 'Additional Prompt Instructions',
    'additional_prompt_desc' => 'Custom instructions appended to the system prompt',

    // UI strings
    'classification_result' => 'AI Classification Result',
    'suggested_name' => 'Suggested Name',
    'suggested_categories' => 'Suggested Categories',
    'suggested_keywords' => 'Suggested Keywords',
);
