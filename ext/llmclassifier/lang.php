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
 * @version    1.1.1
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
    'llm_endpoint_desc' => 'Responses: Basis-URL oder vollständige /responses-URL. Chat Completions: Basis-URL. Legacy Azure: Ressourcen-URL.',
    'llm_api_format' => 'API-Format',
    'llm_api_format_desc' => 'Format ausdrücklich auswählen. Leer = bisherige automatische Azure-Erkennung für bestehende Installationen.',
    'llm_auth_method' => 'Authentifizierung',
    'llm_auth_method_desc' => 'Foundry-Schlüssel: api-key. Leer = bisherige Header-Auswahl. Keine = ohne Authentifizierung.',
    'llmclassifier_format_responses' => 'Responses',
    'llmclassifier_format_chat_completions' => 'Chat Completions',
    'llmclassifier_format_azure_chat_completions' => 'Legacy Azure Chat Completions',
    'llmclassifier_auth_api_key' => 'API-Schlüssel (api-key)',
    'llmclassifier_auth_bearer' => 'Bearer',
    'llmclassifier_auth_none' => 'Keine',
    'llm_api_key' => 'API-Schlüssel',
    'llm_api_key_desc' => 'Authentifizierungsschlüssel für den LLM-Dienst',
    'llm_model' => 'Modell / Deployment-Name',
    'llm_model_desc' => 'Modellname (z.B. gpt-4o) oder Azure Deployment-Name',
    'llm_temperature' => 'Temperatur (0.0 - 2.0)',
    'llm_temperature_desc' => 'Leer oder fehlend = Parameter nicht senden. 0.0 = Null. Für explizite Temperatur z.B. 0.3 eintragen; omit bleibt als Alias unterstützt.',
    'llm_reasoning_effort' => 'Reasoning-Aufwand',
    'llm_reasoning_effort_desc' => 'Leer = Modellstandard. Unterstützte Werte hängen vom Modell ab; keine genaue Token-, Zeit- oder Kostenzusage. Für Luna zunächst leer lassen.',
    'llm_max_output_tokens' => 'Maximale Ausgabetokens',
    'llm_max_output_tokens_desc' => 'Positive Ganzzahl; leer = kein explizites Limit. Beinhaltet Reasoning und sichtbare Ausgabe. Zu kleine Limits verhindern vollständige Klassifizierung.',
    'llmclassifier_effort_none' => 'Kein Reasoning (none)',
    'llmclassifier_effort_minimal' => 'Minimal',
    'llmclassifier_effort_low' => 'Niedrig (low)',
    'llmclassifier_effort_medium' => 'Mittel (medium)',
    'llmclassifier_effort_high' => 'Hoch (high)',
    'llmclassifier_effort_xhigh' => 'Sehr hoch (xhigh)',
    'llmclassifier_effort_max' => 'Maximum (max)',
    'llm_api_version' => 'API-Version (nur Legacy Azure)',
    'llm_api_version_desc' => 'Nur für Legacy Azure Chat Completions; Responses v1 benötigt keine datierte API-Version.',

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
    'llm_endpoint_desc' => 'Responses: base URL or full /responses URL. Chat Completions: base URL. Legacy Azure: resource URL.',
    'llm_api_format' => 'API Format',
    'llm_api_format_desc' => 'Choose explicitly. Empty retains old automatic Azure detection for existing installations.',
    'llm_auth_method' => 'Authentication',
    'llm_auth_method_desc' => 'Foundry keys: api-key. Empty retains old header selection. None sends no authentication.',
    'llmclassifier_format_responses' => 'Responses',
    'llmclassifier_format_chat_completions' => 'Chat Completions',
    'llmclassifier_format_azure_chat_completions' => 'Legacy Azure Chat Completions',
    'llmclassifier_auth_api_key' => 'API Key (api-key)',
    'llmclassifier_auth_bearer' => 'Bearer',
    'llmclassifier_auth_none' => 'None',
    'llm_api_key' => 'API Key',
    'llm_api_key_desc' => 'Authentication key for the LLM service',
    'llm_model' => 'Model / Deployment Name',
    'llm_model_desc' => 'Model name (e.g., gpt-4o) or Azure deployment name',
    'llm_temperature' => 'Temperature (0.0 - 2.0)',
    'llm_temperature_desc' => 'Empty or missing omits the parameter. Use 0.0 for zero. Enter e.g. 0.3 for explicit temperature; omit remains a supported alias.',
    'llm_reasoning_effort' => 'Reasoning Effort',
    'llm_reasoning_effort_desc' => 'Empty uses model default. Values are model-dependent, not exact token, latency or cost budgets. Leave empty initially for Luna.',
    'llm_max_output_tokens' => 'Maximum Output Tokens',
    'llm_max_output_tokens_desc' => 'Positive integer; empty sends no explicit cap. Includes reasoning and visible output. Small limits may prevent complete classification.',
    'llmclassifier_effort_none' => 'None (explicit)',
    'llmclassifier_effort_minimal' => 'Minimal',
    'llmclassifier_effort_low' => 'Low',
    'llmclassifier_effort_medium' => 'Medium',
    'llmclassifier_effort_high' => 'High',
    'llmclassifier_effort_xhigh' => 'Extra high (xhigh)',
    'llmclassifier_effort_max' => 'Maximum (max)',
    'llm_api_version' => 'API Version (legacy Azure only)',
    'llm_api_version_desc' => 'Only for legacy Azure Chat Completions; Responses v1 needs no dated API version.',

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
