# LLM Document Classifier for SeedDMS

Automatically classify PDF documents using Large Language Models (LLMs). This extension analyzes document content and automatically assigns descriptive names, categories, and keywords.

## Features

- **Automatic Document Naming**: Generates clear, descriptive names based on document content
- **Category Assignment**: Assigns categories from your configured SeedDMS categories
- **Keyword Extraction**: Extracts relevant keywords for improved searchability
- **Tax Document Detection**: Automatically tags tax-related documents with "Steuer" keyword
- **Folder Scoping**: Limit classification to specific folder trees
- **Default Category**: Automatically assign a category to all classified documents
- **Keyword Restriction**: Optionally limit keywords to pre-configured lists
- **Multi-Provider Support**: Microsoft Foundry Responses, OpenAI, legacy Azure OpenAI, Ollama, and compatible APIs via `openai-php/client`

## Requirements

- SeedDMS 6.0.0 or higher
- PHP 8.2 or higher
- Packaged SDK dependencies (`vendor/` included; see installation below)
- `pdftotext` utility (part of poppler-utils)
- Access to an OpenAI-compatible LLM API

## Installation

1. Build a copy-ready package with Docker (Composer runs only in the build container):
   ```sh
   bash build-scripts/package-llmclassifier.sh
   ```
   Extract `dist/llmclassifier-1.1.1.tar.gz` and copy the complete `llmclassifier`
   folder, including `vendor/`, to your SeedDMS extensions directory:
   ```
   /var/seeddms/seeddms60x/www/ext/llmclassifier/
   ```

2. Enable the extension in SeedDMS:
   - Go to **Admin → Extension Manager**
   - Find "LLM Document Classifier"
   - Click the enable button

3. Configure the extension (see Configuration section below)

When upgrading by copying files or rebuilding the image, use **Admin → Extension
Manager → Refresh** before configuring. SeedDMS caches extension metadata in its
data directory; replacing `conf.php` alone does not refresh the visible fields.

The repository's Dockerfile bundles locked production dependencies automatically.
Source checkouts are not copy-ready until dependencies are installed. For local
development, run `composer install` in `ext/llmclassifier`, then `composer test`.
The SDK is community-maintained; dependencies are extension-local, but PHP class
names are shared with SeedDMS. If other extensions load incompatible versions of
Guzzle or PSR packages, verify the combined installation before deployment.

## Configuration

Access extension settings via **Admin → Extension Manager → LLM Document Classifier → Configure**

### Core Settings

| Setting | Description |
|---------|-------------|
| **Enable LLM Classification** | Master switch to enable/disable the extension |
| **LLM API Endpoint** | URL of the LLM API (see provider examples below) |
| **API Key** | Your API key for authentication |
| **Model / Deployment Name** | Model to use (e.g., `gpt-4o`) or Azure deployment name |
| **API Format** | Responses, Chat Completions, or Legacy Azure Chat Completions; empty retains old detection |
| **Authentication** | `api-key`, Bearer, or None; independent of API format |
| **Temperature** | Empty/missing omits the parameter; use `0.0` for zero or e.g. `0.3` for explicit temperature |
| **Reasoning Effort** | Optional, model-dependent effort level; empty leaves the service default |
| **Maximum Output Tokens** | Optional positive integer ceiling on reasoning plus visible output tokens; empty sends no explicit cap |
| **API Version (legacy Azure only)** | Used only for the legacy deployment-based API |

### Scope Settings

| Setting | Description |
|---------|-------------|
| **Limit Extension to Folder** | Only classify documents in this folder and subfolders. Leave empty for all folders. |
| **Default Category** | Category automatically added to all classified documents. Not offered to LLM for selection. |

### Classification Settings

| Setting | Description |
|---------|-------------|
| **Maximum Title Length** | Maximum characters for document names (default: 100) |
| **Restrict to Configured Keywords** | Only use keywords from SeedDMS keyword categories |

### Technical Settings

| Setting | Description |
|---------|-------------|
| **Path to pdftotext** | Full path to pdftotext binary (default: `/usr/bin/pdftotext`) |
| **Max Text Length for LLM** | Maximum characters to send to LLM (default: 4000) |
| **Additional Prompt Instructions** | Custom instructions appended to the system prompt |

## LLM Provider Configuration

### Microsoft Foundry (Responses v1)

```
Endpoint: https://<resource>.services.ai.azure.com/openai/v1/responses
API Format: Responses
Authentication: API Key (api-key)
API Key: <your-foundry-api-key>
Model: <your-deployment-name>
Temperature: (leave empty)
Reasoning Effort: (leave empty initially for Luna)
Maximum Output Tokens: (leave empty unless you want an explicit ceiling)
API Version: (leave empty; ignored for Responses v1)
```

The base URL ending in `/openai/v1` is also accepted. Select a deployment that
supports Responses and structured JSON Schema output. The extension requests
`name`, `categories`, and `keywords` as structured output. Each request uses
`store: false` because classification needs no conversation history; this is
not a guarantee of zero service-side retention.

Leave temperature empty for models that reject it. Both empty and missing
temperature omit the parameter in every API mode. The older `omit` value remains
accepted but is no longer needed. SeedDMS drops the string `0` during saving;
use `0.0` for persistent explicit zero.

### Reasoning effort and token ceilings

The two controls are independent and optional. Empty values send neither a
guessed default nor `null`; the service/model chooses its defaults.

| API Format | Effort request field | Output ceiling request field |
|------------|----------------------|------------------------------|
| Responses | `reasoning.effort` | `max_output_tokens` |
| Chat Completions | `reasoning_effort` | `max_completion_tokens` |
| Legacy Azure Chat Completions | `reasoning_effort` | `max_completion_tokens` |

Effort choices are `none`, `minimal`, `low`, `medium`, `high`, `xhigh`, and `max`.
This is a union of documented values: **not every model supports every value or
the effort parameter itself**. Explicit `none` is not the same as leaving the
setting empty. Configurable effort support for `gpt-6-luna` is unverified; leave
it empty initially. For older Azure API versions and other compatible providers,
confirm support before setting either control. Unsupported parameters are not
silently removed or retried with different settings.

The token ceiling must be a positive integer and includes hidden reasoning,
visible answer text, and generated formatting tokens. It is not a separate
thinking-token budget or a guaranteed spending cap. Too-small ceilings can
exhaust output capacity before valid classification JSON is complete; incomplete
results are rejected without applying metadata. Effort influences reasoning
behavior, not an exact token allocation, latency, or cost. The separate HTTP
timeout remains 120 seconds.

Invalid explicit caps prevent an API request. SeedDMS normally discards the
literal `0` when saving; the extension preserves it internally as `00` so it
can still reject the invalid setting after reload. Positive leading-zero values
are interpreted as decimal, not octal.

See Microsoft's [reasoning model guidance](https://learn.microsoft.com/en-us/azure/foundry/openai/how-to/reasoning)
for model-specific support and token accounting.

API-key mode sends only `api-key`, not Bearer authentication. Bearer remains
available for other services or a manually supplied access token; the extension
does not obtain or refresh Microsoft Entra tokens.

### Upgrading existing installations

Unset API Format and Authentication preserve the old Azure hostname detection
and header choice. Explicit settings override that fallback independently.
For Foundry, set both fields explicitly as above. Legacy Azure keeps its
deployment URL and dated API version; Responses does not use that URL format.
Install the complete package including `vendor/`, not just the PHP files.
As of **1.1.1**, missing temperature no longer sends the implicit `0.3` used by
earlier versions. Enter `0.3` explicitly if you want that behavior. After deploying,
refresh extension metadata, reopen settings, and save your desired controls.
Malformed or incomplete classification objects are rejected before metadata is
changed. Chat Completions providers must return `finish_reason: stop`; filtered,
truncated, tool-call, or missing finish reasons are not applied. Legacy
comma-separated keyword strings remain supported in Chat Completions mode.

### OpenAI

```
Endpoint: https://api.openai.com/v1
API Format: Chat Completions
Authentication: Bearer
API Key: sk-...
Model: gpt-4o
API Version: (leave empty)
```

### Azure OpenAI

```
Endpoint: https://<resource-name>.openai.azure.com
API Format: Legacy Azure Chat Completions
Authentication: API Key (api-key)
API Key: <your-azure-api-key>
Model: <your-deployment-name>
API Version: 2024-02-15-preview
```

Only unset settings retain automatic detection of `openai.azure.com` or
`cognitiveservices.azure.com`; explicit choices do not depend on the hostname.

### Ollama (Local)

```
Endpoint: http://localhost:11434/v1
API Format: Chat Completions
Authentication: None
API Key: (leave empty)
Model: llama3.2
API Version: (leave empty)
```

## How It Works

1. **Document Upload**: When a PDF is uploaded to SeedDMS, the extension hook is triggered
2. **Folder Check**: If folder restriction is configured, checks if document is in allowed tree
3. **Text Extraction**: Uses `pdftotext` to extract text content from the PDF
4. **LLM Analysis**: Sends text to the configured LLM with available categories and keywords
5. **Result Application**: Updates document name, categories, and keywords based on LLM response

## Keyword Restriction

When enabled, the extension will only apply keywords that are configured in SeedDMS:

1. Go to **Admin → Keyword Categories**
2. Create keyword categories and add keywords
3. Enable "Restrict to Configured Keywords" in extension settings
4. The LLM will only suggest keywords from your configured list

Rejected keywords are logged for review.

## Default Category

The default category feature allows you to automatically tag all AI-classified documents:

1. Create a category in SeedDMS (e.g., "AI Processed")
2. Select it as "Default Category" in extension settings
3. This category is automatically added but NOT offered to the LLM for selection

Use cases:
- Mark documents for human review
- Track AI-processed documents
- Workflow automation triggers

## Additional Prompt Instructions

Customize LLM behavior by adding instructions that are appended to the standard prompt:

**Examples:**

```
Always use formal German for document names.
```

```
For invoices, include the invoice number in the document name.
Prioritize keywords related to our core business: manufacturing, logistics, quality.
```

```
If a document appears to be a contract, always include "Vertrag" in keywords.
```

## Logging

The extension logs all operations to the SeedDMS log. Look for entries with `[LLMClassifier]` prefix.

**Log Levels:**
- **INFO**: Normal operations (classification started, completed, skipped)
- **WARNING**: Non-fatal issues (no keywords configured, PDF extraction warnings)
- **ERROR**: Failures (API errors, file not found, configuration issues)

**Example log entries:**

```
[LLMClassifier] Document uploaded: ID 1234
[LLMClassifier] Starting classification for document ID: 1234
[LLMClassifier] File path: /var/seeddms/data/1048576/1234/1.pdf
[LLMClassifier] Extracted 2500 characters
[LLMClassifier] Available categories: 8
[LLMClassifier] Calling LLM API...
[LLMClassifier] Classification result: {"name":"Invoice 2024-001","categories":["Finance"],"keywords":["Invoice","Steuer"]}
[LLMClassifier] Updated name: Invoice 2024-001
[LLMClassifier] Added category: Finance
[LLMClassifier] Updated keywords
[LLMClassifier] Added default category: AI Processed
[LLMClassifier] Classification applied
```

## Troubleshooting

### Extension not classifying documents

1. Check that "Enable LLM Classification" is checked
2. Verify the LLM endpoint and API key are correct
3. Check the SeedDMS log for error messages
4. Ensure the document is a PDF
5. If folder restriction is set, verify the document is in the allowed folder tree

### pdftotext errors

1. Verify pdftotext is installed: `which pdftotext`
2. Check the configured path matches the actual location
3. Ensure the binary is executable
4. On Debian/Ubuntu: `apt-get install poppler-utils`

### LLM API errors

1. Check HTTP response codes in the log
2. For Azure: Verify deployment name and API version
3. For OpenAI: Verify API key has sufficient credits
4. For Ollama: Ensure the service is running and model is pulled

Failure diagnostics include the SDK exception class, HTTP status (including
429 and 5xx), and validated provider error `code`/`type` when the SDK exposes
them. Validated `x-request-id`, `apim-request-id` and `x-ms-request-id` headers
can help correlate a failure with provider logs. Raw exception messages and
response bodies are deliberately withheld because they may echo credentials
or document text. Unknown transport causes include their exception class;
structured cURL errors include a number and controlled description, for example:

```text
LLM API request failed (OpenAI\Exceptions\TransporterException, cause=GuzzleHttp\Exception\ConnectException, cURL 28: Request timed out)
```

| Diagnostic | Investigate |
|---|---|
| cURL 5 / 6 | Proxy/endpoint DNS resolution from the deployment's network |
| cURL 7 | Connection availability, routing, firewall and proxy settings |
| cURL 28 | Request timed out; check provider latency, network and model workload |
| cURL 35 / 60 | TLS handshake or certificate trust; do not disable verification |
| HTTP 401 | Authentication method, key validity or manually supplied token expiry |
| HTTP 429 | Provider rate limits/quota and concurrent requests |
| HTTP 5xx | Provider or gateway availability; correlate request IDs |
| `status=incomplete`, `reason=max_output_tokens` | Output ceiling exhausted; review the configured cap and reasoning workload |
| `finish_reason=length` / `content_filter` | Chat token limit or content filtering; partial results are not applied |

The HTTP timeout remains 120 seconds, and the extension does not automatically
retry failed calls. A subsequent upload may succeed without restarting.

### Intermittent failures with unchanged settings

SeedDMS loads settings for each request and the upload hook constructs a fresh
classifier. Its settings snapshot is local to that classifier, not a persistent
cross-request cache. Extension Manager Refresh updates cached **extension
metadata** after an upgrade; it is not a remedy for intermittent HTTP failures.
Recovery without a restart does not, by itself, establish a caching defect.

Regression checks cover success → transport failure → success with identical
request configuration, and actual SeedDMS settings save/reload. No config-cache
defect has been reproduced in these checks; the reported production failure
cause remains unverified. After deploying the diagnostic fix, collect the
failure timestamp and the `[LLMClassifier]` diagnostic line and correlate any
request ID with provider logs. Do not share keys, prompts, document contents or
the complete settings file. Other existing informational logs can contain
document information; share only the relevant failure diagnostics.

### Keywords not being applied

1. If "Restrict to Configured Keywords" is enabled, verify keywords are configured in SeedDMS
2. Check the log for "Rejected keywords" entries
3. The LLM may be suggesting keywords that don't match your configured list

### No text extracted from PDF

1. Verify the PDF contains text (not just images)
2. If the PDF is image-based, you need OCR preprocessing
3. Check file permissions on the document storage directory

## Security Considerations

- API keys are stored in SeedDMS configuration (encrypted at rest if configured)
- Document content is sent to the configured LLM provider
- For sensitive documents, consider using a local LLM (Ollama) or Azure with data residency guarantees
- The extension only processes PDF documents

## License

GPL-2.0+

## Author

Andreas Krause

## Version History

See [changelog.md](changelog.md) for version history.
