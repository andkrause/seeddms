# Changelog

All notable changes to the LLM Document Classifier extension will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2026-01-06
### Added
- **Temperature Setting**: Exposed the temperature option in configuration, allowing fine-grained control of LLM randomness/creativity and support for `mini` models


## [1.0.0] - 2026-01-04

### Added

- **Automatic PDF Classification**: Analyzes uploaded PDF documents and automatically assigns metadata
- **Document Naming**: Generates clear, descriptive names based on document content
- **Category Assignment**: Assigns categories from configured SeedDMS document categories
- **Keyword Extraction**: Extracts relevant keywords for improved searchability
- **Tax Document Detection**: Automatically includes "Steuer" keyword for tax-related documents

### LLM Provider Support

- **OpenAI**: Full support for OpenAI API (GPT-4, GPT-4o, etc.)
- **Azure OpenAI**: Full support with automatic endpoint detection and api-key authentication
- **Ollama**: Support for local LLMs via Ollama's OpenAI-compatible API
- **Other Providers**: Any OpenAI-compatible API endpoint

### Configuration Options

- **Folder Restriction**: Limit classification to specific folder trees
- **Default Category**: Automatically assign a category to all classified documents
- **Keyword Restriction**: Optionally limit keywords to pre-configured SeedDMS keyword categories
- **Maximum Title Length**: Configurable document name length limit
- **Maximum Text Length**: Configurable text extraction limit for LLM context
- **Additional Prompt**: Append custom instructions to the classification prompt
- **pdftotext Path**: Configurable path to pdftotext binary

### Technical

- Hooks into document upload workflow via `postAddDocument` controller hook
- Comprehensive logging with INFO, WARNING, and ERROR levels
- Proper error handling for API failures, missing files, and configuration issues
- Session storage for classification results
- Multi-language support (German and English)
