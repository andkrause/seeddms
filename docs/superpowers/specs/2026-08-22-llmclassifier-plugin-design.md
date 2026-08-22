# LLM Document Classifier SeedDMS Extension — Specification

> Reverse-engineered specification for `ext/llmclassifier` (v1.0.x, 2026-01-04/06).
> Sources: `ext/llmclassifier/{conf.php, class.llmclassifier.php, lang.php, README.md, changelog.md}`
> and the official SeedDMS extension documentation (see Appendix A).

---

## 1. Overview

A SeedDMS extension that automatically classifies uploaded PDF documents using any
OpenAI-compatible chat-completions API (OpenAI, Azure OpenAI, Ollama, etc.). On upload it
extracts PDF text and applies LLM-suggested **name**, **categories**, and **keywords** to
the document.

| | |
|---|---|
| Extension ID | `llmclassifier` |
| Version | 1.0.1 (changelog) / 1.0.0 (conf.php header) |
| Author | Andreas Krause |
| License | GPL-2.0+ |
| Requires | PHP ≥ 8.0, SeedDMS ≥ 6.0.0, `pdftotext` binary (poppler-utils), cURL |
| Languages | de_DE, en_GB |

## 2. Architecture

Five classes in a single file (`class.llmclassifier.php`):

| Class | Responsibility |
|---|---|
| `SeedDMS_ExtLLMClassifier extends SeedDMS_ExtBase` | Extension entry point; `init()` registers the hook: `$GLOBALS['SEEDDMS_HOOKS']['controller']['addDocument'][] = new SeedDMS_ExtLLMClassifier_AddDocument();`. `main()` is empty (hook-driven only). |
| `SeedDMS_ExtLLMClassifier_AddDocument` | Hook handler `postAddDocument($controller, $document)` — orchestrates the classification pipeline |
| `SeedDMS_DocumentClassifier` | Core logic: config access, folder scoping, prompt building, applying results to document metadata |
| `SeedDMS_LLMClient` | HTTP client for OpenAI-compatible chat completions (cURL, 120 s timeout) |
| `SeedDMS_PDFExtractor` | Wraps the external `pdftotext` utility (`pdftotext -layout <in> <tmpout>` via shell) |

## 3. Configuration Schema

Defined in `conf.php` as `$EXT_CONF['llmclassifier']`, persisted by SeedDMS under
`$settings->_extensions['llmclassifier']`.

### 3.1 Core Settings

| Key | Type | Default / Notes |
|---|---|---|
| `llm_enabled` | checkbox | Master switch; extension inactive unless set together with `llm_endpoint` |
| `llm_endpoint` | input (250) | e.g. `https://api.openai.com/v1`; trailing `/` is stripped |
| `llm_api_key` | password (100) | Optional (Ollama needs none); omitted from headers if empty |
| `llm_model` | input (100) | Fallback default in code: `gpt-4o` |
| `llm_temperature` | input (10) | Cast to float; default `0.3` (valid range per API: 0.0–2.0) |
| `llm_api_version` | input (100) | Azure only; code fallback `2024-02-15-preview` |

### 3.2 Scope Settings

| Key | Type | Default / Notes |
|---|---|---|
| `limit_folder` | select (`internal: folders`, allow_empty) | Restricts processing to this folder tree. Array-tolerant: first element used if array. `0` = no limit |
| `default_category` | select (`internal: categories`, allow_empty) | Auto-applied to every classified document; excluded from the category list offered to the LLM. Same array handling as above |

### 3.3 Classification Settings

| Key | Type | Default / Notes |
|---|---|---|
| `max_title_length` | input (10) | Default `100`; over-long LLM names are applied anyway, only a WARNING is logged (never truncated) |
| `restrict_keywords` | checkbox | Limit keywords to those configured in SeedDMS keyword categories |

### 3.4 Technical Settings

| Key | Type | Default / Notes |
|---|---|---|
| `pdftotext_path` | input (150) | Default `/usr/bin/pdftotext`; validated at construction (exists + executable) |
| `max_text_length` | input (10) | Max characters of extracted text sent to the LLM; default `4000` |
| `additional_prompt` | textarea (6×200) | Appended to the system prompt under `ADDITIONAL INSTRUCTIONS:` |

All settings have `de_DE` and `en_GB` translation strings in `lang.php`
(`<key>` + `<key>_desc` pairs), plus UI strings `classification_result`,
`suggested_name`, `suggested_categories`, `suggested_keywords`.

Metadata block also declares: `icon.svg`, `changelog.md`, class file/name mapping,
language file, and dependency constraints.

## 4. Runtime Flow (controller hook `postAddDocument`)

1. Resolve `dms`, `settings`, and optional `logger` from controller params; abort if no DMS.
2. Instantiate `SeedDMS_DocumentClassifier`; skip if not enabled
   (`isEnabled()` = `llm_enabled` non-empty **and** `llm_endpoint` non-empty).
3. **Folder scope check** (`isDocumentInAllowedFolder`): walk the parent chain from the
   document's folder; pass if any ancestor ID equals `limit_folder` (always passes when no
   limit configured). Failures logged at INFO.
4. **Classify** (`classifyDocument`):
   - `$document->getLatestContent()`; skip unless MIME type is exactly `application/pdf`.
   - Skip with ERROR if PDF extractor not ready.
   - File path = `dms->contentDir . $content->getPath()`; must exist.
   - Extract text via pdftotext → normalize whitespace (`\s+` → single space) → trim →
     truncate at `max_text_length` characters with a `...` suffix (multibyte-safe).
     Fail on empty result.
   - Gather candidate category names: all DMS document categories minus the default category.
   - If keyword restriction enabled: gather all keywords from all keyword categories/lists (trimmed).
   - Build prompts (§5.2), call the LLM (§5.1), log raw JSON result.
5. **Apply** (`applyClassification`) — each step independent; returns true if anything changed:
   - **Name**: set if non-empty and different from current name.
   - **Keywords**: accept JSON array or comma-separated string; trim each;
     filter against the configured keyword list case-insensitively (`mb_strtolower`) if
     restricted (rejected keywords logged at INFO); append to existing keywords as CSV
     (`existing, new`). Skip if nothing remains after filtering.
   - **Categories**: match suggested names case-insensitively (`strcasecmp`) against DMS
     categories; add ones not already assigned via `$document->addCategories([$cat])`.
   - **Default category**: add by ID if configured and not already present.
6. Store the raw classification array in `$_SESSION['llmclassifier_result'][docId]`
   (for UI display; matches the `suggested_*` language keys).
7. The hook always returns `null`.

Classification runs synchronously inside the upload request (blocking; up to ~120 s API timeout).

## 5. LLM Interface

### 5.1 Transport (`SeedDMS_LLMClient::chatCompletion`)

**Provider detection**: Azure iff endpoint URL contains `openai.azure.com` or
`cognitiveservices.azure.com`.

| Aspect | OpenAI-compatible | Azure OpenAI |
|---|---|---|
| Request URL | `{endpoint}/chat/completions` | `{endpoint}/openai/deployments/{model}/chat/completions?api-version={v}` |
| Auth header | `Authorization: Bearer {key}` | `api-key: {key}` |
| Payload `model` field | included | omitted (deployment name is in the URL) |

Payload:

```json
{
  "messages": [
    {"role": "system", "content": "<system prompt>"},
    {"role": "user",   "content": "<document text>"}
  ],
  "temperature": 0.3,
  "response_format": {"type": "json_object"},
  "model": "<model>"
}
```

Transport rules:
- cURL POST, `Content-Type: application/json`, 120 s timeout, no SSL option overrides.
- Requires HTTP 200; curl error, non-200 status, invalid outer JSON, missing
  `choices[0].message.content`, or invalid inner JSON all return `null` with ERROR logs.

### 5.2 Prompts

**System prompt template** (values interpolated):

```
You are a document classification assistant. Analyze the PDF document and provide:

1. **name**: A clear, descriptive name (in the document's language, max {max_title_length} characters)
2. **categories**: Select from this list: {category_names_json}
3. **keywords**: Relevant search keywords (in the document's language)
   -- or, when keyword restriction is active --
3. **keywords**: Select ONLY from this list: {configured_keywords_json}

IMPORTANT: For tax-related documents (invoices, receipts, expenses), include "Steuer" in keywords if available.

Respond with valid JSON only:
{"name": "Document Name", "categories": ["Category"], "keywords": ["keyword1", "keyword2"]}
```

followed, when configured, by:

```

ADDITIONAL INSTRUCTIONS:
{additional_prompt}
```

**User message template:**

```
Classify this document. Current filename: "{current_name}"

Document content:
---
{extracted_text}
---

Provide JSON with name, categories, and keywords.
```

### 5.3 Response Contract

Expected JSON object: `name: string`, `categories: string[]`, `keywords: string[]`.
A comma-separated `keywords` string is tolerated (split + trimmed).

## 6. PDF Text Extraction

- Binary validated on construction: must exist and be executable; otherwise the extractor
  reports not-ready and classification aborts.
- Command: `{pdftotext} -layout {input.pdf} {tempfile} 2>&1` executed via `exec()`;
  input/output paths escaped with `escapeshellarg` (binary path via `escapeshellcmd`);
  output written to `tempnam(sys_get_temp_dir(), 'llmclassifier_')`, removed afterwards.
- Non-zero exit code, unreadable output, or whitespace-only text ⇒ failure (WARNING log).
- Post-processing: collapse whitespace, trim, multibyte truncate to `max_text_length` (+ `...`).

## 7. Error Handling & Logging

- All messages prefixed `[LLMClassifier]`, written through the PEAR-compatible logger at
  INFO / WARNING / ERR.
- Every failure path returns gracefully (`null` / `false`); a failed classification never
  fails the document upload.
- ERROR: curl failures, non-200 HTTP responses, JSON parse failures (outer and inner),
  invalid response structure, missing content/file, extractor unavailable, DB setter
  failures (`setName`/`setKeywords` returning false), default category not found.
- WARNING: over-limit titles, rejected keywords, restriction without configured keywords,
  extraction problems, no categories configured.
- INFO: pipeline progress, skips (disabled, wrong folder, non-PDF), final result JSON.

## 8. Packaging & Installation

Directory layout shipped inside the Docker image at `www/ext/llmclassifier/`:

```
conf.php                  extension metadata + config options (Extension Manager UI)
class.llmclassifier.php   all five classes (entry point, hook, classifier, LLM client, extractor)
lang.php                  de_DE / en_GB translations
changelog.md              Keep-a-Changelog format
icon.svg                  icon shown in Extension Manager
README.md                 user documentation
```

Installation: copy folder into `www/ext/`, enable via **Admin → Extension Manager**
(configuration cached there; re-open manager after manual `conf.php` edits).

## 9. Known Behaviors & Edge Cases

- Non-PDF documents are silently skipped (INFO log).
- Keyword restriction with zero configured keywords falls back to unrestricted (WARNING).
- Over-long LLM titles are applied as-is (no truncation); warning only.
- `limit_folder` / `default_category` tolerate both scalar and array setting values.
- The hardcoded `"Steuer"` tax-keyword instruction is always present in the system prompt
  regardless of configuration.
- Session result storage assumes an active session (guarded with `isset($_SESSION)`).
- No retry/backoff on transient API failures; single attempt per upload.

## Appendix A: SeedDMS Extension (Plugin) Development Reference

This section documents how SeedDMS plugins ("extensions") work, based on the official
manual and the `README.Extensions` file shipped in the SeedDMS source.

**References**

- Official manual, "Extensions" chapter:
  https://seeddms.dalescott.net/manual/extensions.html (mirrors seeddms.org docs)
- `README.Extensions` in the SeedDMS source tree:
  https://github.com/rachmari/seeddms/blob/master/README.Extensions
  (canonical copy lives at the root of every SeedDMS release tarball)
- Sample plugin source (the `example` extension demonstrating hooks, bundled with SeedDMS):
  https://sourceforge.net/p/seeddms/code/ci/master/tree/ext/
  (local installation: `www/ext/example/`)

### A.1 Concepts

Since SeedDMS 5.0.0, extensions can modify behaviour without touching core code. They can:

1. **Hook into operations** — controllers (`op/op.*.php`) and views (`out/out.*.php`)
   call registered hook objects at defined points.
2. **Modify internal variables** — e.g. add translations.
3. **Replace core classes and overload files** — if an extension ships its own
   `views/` or `controllers/` class files, they replace the stock ones.

### A.2 Location & Distribution

- All extensions live under `www/ext/<extension-name>/`.
- Distribution format: a ZIP of the *contents* of the extension directory named
  `<extensionname>-<x.y.z>.zip` (the archive must **not** contain the directory itself).
- Installed/uploaded/enabled/disabled via the admin-only **Extension Manager**
  (**Admin → Extension Manager**). It shows compatibility state (green = working,
  yellow = disabled, red = broken/incompatible), changelogs, and can install from a
  configured remote repository. Configuration is cached — reopen the manager after
  editing files manually.

### A.3 Required Files

Each extension must contain:

| File | Purpose |
|---|---|
| `conf.php` | Central configuration; populates the global `$EXT_CONF['<name>']` array |
| `changelog.md` | Markdown changelog (name configurable via `'changelog'` key) |
| `icon.png` / `icon.svg` | Icon shown in the Extension Manager (name configurable via `'icon'` key) |

Typically also:

- `lang.php` — translation arrays keyed by locale (e.g. `$__lang['en_GB']`).
- `class.<name>.php` — main class extending `SeedDMS_ExtBase`. If it defines `init()`,
  it is called on **every HTTP request**. The class is instantiated with the current
  configuration and the logger passed to its constructor.

### A.4 `conf.php` Format

```php
<?php
$EXT_CONF['example'] = array(
  'title' => 'Example Extension',
  'description' => 'This sample extension demonstrate the use of various hooks',
  'disable' => false,
  'version' => '1.0.1',
  'releasedate' => '2018-03-21',
  'author' => array('name'=>'Uwe Steinmann', 'email'=>'uwe@steinmann.cx', 'company'=>'MMK GmbH'),
  'config' => array(
      // user-configurable options rendered by the Extension Manager UI:
      // 'key' => array('title'=>..., 'type'=>'input'|'checkbox'|'password'|'select'|'textarea',
      //                'internal'=>'folders'|'categories', 'allow_empty'=>true, ...)
  ),
  'constraints' => array(
    'depends' => array('php' => '5.6.40-', 'seeddms' => '5.1.0-'),
  ),
  'icon' => 'icon.png',
  'changelog' => 'changelog.md',
  'class' => array(
    'file' => 'class.example.php',
    'name' => 'SeedDMS_ExtExample'
  ),
  'language' => array(
    'file' => 'lang.php',
  ),
);
```

Config values chosen in the manager are stored per-extension in the settings and read at
runtime via `$settings->_extensions['<extension-name>'][<key>]`.

### A.5 Minimal Main Class

```php
<?php
/**
 * Example extension
 *
 * @author  Uwe Steinmann <uwe@steinmann.cx>
 * @package SeedDMS
 * @subpackage example
 */
class SeedDMS_ExtExample extends SeedDMS_ExtBase {
  function init() {
  }
}
```

`init()` is where hooks get registered.

### A.6 Hooks

SeedDMS maintains a global hook registry `$GLOBALS['SEEDDMS_HOOKS']` with the elements
`'view'` and `'controller'`. Each entry holds instances of custom classes whose methods
are invoked at the corresponding point in the application code.

Controller hooks (called from `op/op.*.php`):

```php
$GLOBALS['SEEDDMS_HOOKS']['controller']['removeFolder'][] = new SeedDMS_ExtExample_RemoveFolder;

class SeedDMS_ExtExample_RemoveFolder {
    // method named after the operation, e.g. postRemoveFolder($controller, ...)
};
```

View hooks (called from `out/out.*.php`):

```php
$GLOBALS['SEEDDMS_HOOKS']['view']['viewFolder'][] = new SeedDMS_ExtExample_ViewFolder;

class SeedDMS_ExtExample_ViewFolder {
    // e.g. showFolderHeader(...), showDocumentDetails(...)
};
```

A special controller hook, **`initDMS`**, fires right after `SeedDMS_Core_DMS` is
instantiated and allows deeper integration. Controller hook handlers receive the
controller as first argument and can pull shared objects from it via
`$controller->getParam('dms'|'settings'|'logger'|...)` / `$controller->hasParam(...)`
— this is how `llmclassifier` obtains the DMS, settings, and logger.

### A.7 Sample Plugin

The SeedDMS source ships a sample extension (`example`, class `SeedDMS_ExtExample`) that
demonstrates `conf.php` structure, `init()` hook registration for both view and
controller hooks, and `lang.php` usage:

- SourceForge (upstream VCS): https://sourceforge.net/p/seeddms/code/ci/master/tree/ext/
- Documented walkthrough with the exact snippets reproduced above:
  https://seeddms.dalescott.net/manual/extensions.html#structure-of-an-extension
- Any installed instance: `www/ext/example/`

`ext/llmclassifier` follows precisely this structure: `conf.php` metadata +
config schema, `lang.php` translations, an entry class extending `SeedDMS_ExtBase`
registering one controller hook (`addDocument`), and a hook handler class named
`SeedDMS_ExtLLMClassifier_AddDocument` implementing `postAddDocument($controller, $document)`.

## Appendix B: Provider Configuration Examples

| Provider | Endpoint | API key | Model | API version |
|---|---|---|---|---|
| OpenAI | `https://api.openai.com/v1` | `sk-...` | `gpt-4o` | *(empty)* |
| Azure OpenAI | `https://<resource>.openai.azure.com` | Azure key | deployment name | `2024-02-15-preview` |
| Ollama (local) | `http://localhost:11434/v1` | *(empty)* | e.g. `llama3.2` | *(empty)* |
