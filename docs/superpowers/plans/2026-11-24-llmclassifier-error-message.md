# LLM Classifier Provider Error Message Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox syntax.

**Goal:** Show the provider's explanation for API errors (e.g. 401) in the SeedDMS log, safely redacted.

**Architecture:** Extend `SeedDMS_LLMClient::describeFailure` with a sanitized `message="..."` field; pass the request prompts so echoed content is withheld.

**Tech Stack:** PHP >=8.2, openai-php/client 0.22.0, PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-11-24-llmclassifier-design.md` (section "Approved correction: provider error messages").

## Global Constraints

- Message truncated to 300 chars; key, token-like strings, URL query/credentials, control characters removed.
- Message withheld if it contains a 24-character fragment of the prompts sent.
- No retries, no config changes, null-return behavior unchanged.

## Review Focus

- Azure 401 text must be visible (Task 1).
- Non-identifier `code` such as "401" or "PermissionDenied" must be visible (Task 1).
- Echoed document fragments must be withheld (Task 1).

### Task 1: Sanitized provider messages

**Files:** Modify `ext/llmclassifier/class.llmclassifier.php`, `ext/llmclassifier/tests/LLMClientTest.php`, `ext/llmclassifier/README.md`, `ext/llmclassifier/changelog.md`.

**Interfaces:** private `sanitizeProviderMessage(string $message, string $systemPrompt, string $userMessage): ?string` (null = withheld); `describeFailure(\Throwable $error, string $systemPrompt = '', string $userMessage = '')`.

- [ ] Write failing tests: Azure 401 message visible with `message="Access denied due to invalid subscription key..."`; `code=401` visible; key/Bearer token/URL query/control chars redacted; long message truncated to 300; message echoing a 24+ char document fragment withheld; update existing secret tests whose message text is no longer blanket-withheld.
- [ ] Run PHPUnit; expect only the new tests to fail.
- [ ] Implement sanitization and wire into the ErrorException branch and catch block.
- [ ] Run PHPUnit on PHP 8.2 and 8.5 and the SeedDMS persistence/package smoke checks; update README/changelog; commit.
