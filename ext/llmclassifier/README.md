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
- **Multi-Provider Support**: Works with OpenAI, Azure OpenAI, Ollama, and other compatible APIs

## Requirements

- SeedDMS 6.0.0 or higher
- PHP 8.0 or higher
- `pdftotext` utility (part of poppler-utils)
- Access to an OpenAI-compatible LLM API

## Installation

1. Copy the `llmclassifier` folder to your SeedDMS extensions directory:
   ```
   /var/seeddms/seeddms60x/www/ext/llmclassifier/
   ```

2. Enable the extension in SeedDMS:
   - Go to **Admin → Extension Manager**
   - Find "LLM Document Classifier"
   - Click the enable button

3. Configure the extension (see Configuration section below)

## Configuration

Access extension settings via **Admin → Extension Manager → LLM Document Classifier → Configure**

### Core Settings

| Setting | Description |
|---------|-------------|
| **Enable LLM Classification** | Master switch to enable/disable the extension |
| **LLM API Endpoint** | URL of the LLM API (see provider examples below) |
| **API Key** | Your API key for authentication |
| **Model / Deployment Name** | Model to use (e.g., `gpt-4o`) or Azure deployment name |
| **API Version (Azure only)** | Required for Azure OpenAI (e.g., `2024-02-15-preview`) |

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

### OpenAI

```
Endpoint: https://api.openai.com/v1
API Key: sk-...
Model: gpt-4o
API Version: (leave empty)
```

### Azure OpenAI

```
Endpoint: https://<resource-name>.openai.azure.com
API Key: <your-azure-api-key>
Model: <your-deployment-name>
API Version: 2024-02-15-preview
```

The extension automatically detects Azure endpoints by checking for `openai.azure.com` or `cognitiveservices.azure.com` in the URL and adjusts authentication accordingly.

### Ollama (Local)

```
Endpoint: http://localhost:11434/v1
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

