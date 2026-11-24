# LLM Classifier Diagnostics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reveal safe, actionable LLM failure causes and test whether unchanged configuration survives intermittent failures.

**Architecture:** Keep the existing adapter and per-upload classifier lifecycle. Extract diagnostics from typed SDK exceptions, response metadata and structured transport context rather than raw exception messages. Use mocked SDK requests and isolated SeedDMS settings persistence tests; do not introduce speculative caching changes.

**Tech Stack:** PHP >=8.2, locked openai-php/client 0.22.0, Guzzle 7, PHPUnit 11, Docker.

**Spec:** `docs/superpowers/specs/2026-11-24-llmclassifier-design.md` (approved).

## Execution evidence

- Task 1: baseline 102 tests/548 assertions; new diagnostics reproduced 13 failures before implementation; then 123 tests/655 assertions passed (PHP 8.5.12).
- Task 2: 125 tests/707 assertions passed. Actual SeedDMS settings save/reload passed in an isolated container, including success → timeout → success with unchanged settings and fresh requests after saved changes. No config-cache defect reproduced; production intermittency remains unverified.
- Task 3: PHP 8.2.35 and 8.5.12 each passed 125 tests/707 assertions; changed PHP syntax checks and `git diff --check` passed. Production archive built with the existing script invoked via bash (not executable in the checkout); package smoke passed with SeedDMS's host autoloader loaded first. Release numbering remains unchanged, with an unreleased changelog entry.

## Global Constraints

- Preserve classification-array-or-null behavior and the 120-second timeout.
- Failed classifications must never change document metadata.
- Never log keys, Authorization headers, URL credentials/query strings, bodies, prompts or document text in failure diagnostics.
- Withhold unknown free-form details; no blind exception-message logging.
- No retries, configuration reload without evidence, live LLM calls or deployment changes.
- Update the extension README and changelog; keep the intermittent production issue open if its cause is not reproduced.

## Review Focus

- Nested transport exceptions without cURL context must remain useful without leaking their messages (Task 1).
- Provider metadata and correlation headers are untrusted; only bounded, allowlisted tokens may be logged (Task 1).
- SDK 429/5xx exceptions expose a response rather than getStatusCode() (Task 1).
- A failed call must not corrupt subsequent request configuration or allow partial metadata (Task 2).
- Fresh classifiers must reflect actual SeedDMS save/reload, including optional and falsey settings semantics (Task 2).

---

### Task 1: Safe failure diagnostics

**Files:** Modify `ext/llmclassifier/class.llmclassifier.php` (LLM client only), `ext/llmclassifier/tests/LLMClientTest.php`.

**Interfaces:** Preserve `chatCompletion($systemPrompt, $userMessage)` returning array|null. Add private `describeFailure(\Throwable $error): string` for safe diagnostic construction and private `safeDiagnosticToken($value): ?string` accepting only bounded ASCII identifier tokens, rejecting the configured API key. No public API changes.

- [ ] Write table-driven tests asserting cURL numbers 6/7/28/35/60 produce DNS/connect/timeout/TLS/certificate descriptions from wrapped Guzzle handler context. Test a cause with no context: class name visible, raw message withheld.
- [ ] Write HTTP tests asserting 401/429/503 statuses, safe provider code/type for error responses, and bounded correlation IDs from response headers. Assert malicious metadata containing whitespace, control characters, URLs, configured keys or long values is withheld. Keep existing secret/document suppression tests.
- [ ] Write result tests asserting Responses incomplete reason/status, provider error code and Chat finish reason are distinguishable, while refusal text and malformed output remain withheld.
- [ ] Run PHPUnit against the unchanged implementation using a Docker Composer/PHP runtime with extension-local locked dependencies. Confirm the diagnostic assertions fail for missing details, not dependency/setup failures. Record the baseline suite result.
- [ ] Implement diagnostic helpers. Walk the exception chain for structured Guzzle context; map known cURL numbers to fixed descriptions and retain safe cause class names. Obtain status and selected headers from SDK response-bearing exceptions; obtain typed provider code/type without copying bodies. Validate every untrusted diagnostic token before logging.
- [ ] Replace the generic catch log with safe diagnostics. Add validated status/reason/code fields to unsuccessful Responses/Chat branches; preserve null returns and existing validation behavior.
- [ ] Run the full PHPUnit suite, expecting all diagnostic and prior tests to pass. Commit the task's code and tests.

### Task 2: Settings lifecycle and intermittent recovery investigation

**Files:** Modify `ext/llmclassifier/tests/ConfigurationTest.php`, `ext/llmclassifier/tests/LLMClientTest.php`, `ext/llmclassifier/tests/temperature-persistence.php`.

**Interfaces:** Use existing constructor settings and injected Guzzle mock handlers; inspect captured request URI, headers and decoded payload. Do not change configuration semantics unless a failing reproduction identifies a real defect.

- [ ] Add `testUnchangedConfigurationSurvivesIntermittentFailure`: queue success, wrapped connection failure and success on the same adapter. Assert array/null/array results and identical URI, auth header and payload across all three calls; check no mutation after the failed call.
- [ ] Add repeated fresh-classifier request capture with unchanged settings, then change endpoint/model/key/optional settings and construct a fresh classifier. Assert each request reflects its supplied settings and that omitted controls remain omitted. Retain malformed-classification metadata guard coverage.
- [ ] Extend the isolated real SeedDMS save/reload script to alternate successful/failed/successful mock requests with unchanged persisted settings and verify identical request configuration. Save updated nonsecret test settings, reload and assert fresh requests use the changes. Touch only the script's temporary settings copy.
- [ ] Run PHPUnit and the persistence script using the built SeedDMS runtime in an isolated container, never against the running deployment's settings. Passing lifecycle tests are investigation evidence, not evidence that production transport failures are resolved.
- [ ] If a cache defect is reproduced, stop and document the precise stale-state path before proposing its smallest fix and failing test. Otherwise record that no config-cache defect was reproduced. Commit tests and evidence notes in the plan.

### Task 3: Documentation and release verification

**Files:** Modify `ext/llmclassifier/README.md`, `ext/llmclassifier/changelog.md`; update this plan's evidence/checklist. Use existing `build-scripts/package-llmclassifier.sh` without changing release numbering for this diagnostic patch unless requested.

**Interfaces:** Document actual emitted diagnostic fields and meanings, unchanged timeout and no retries. Describe how to collect a failure log and correlate it with provider/network evidence without supplying credentials or document contents.

- [ ] Update troubleshooting guidance for DNS/connect/TLS/timeout, HTTP 401/429/5xx and incomplete/token-limit results. Distinguish extension metadata refresh from API request configuration. State that recovery without restart alone does not prove a caching defect.
- [ ] Add an unreleased changelog entry covering diagnostics and regression coverage; explicitly document that the intermittent production cause remains unverified if local tests do not reproduce it.
- [ ] Run full PHPUnit, PHP syntax checks over changed PHP files, actual SeedDMS persistence checks and `git diff --check`. Record exact results and runtime versions; do not claim checks that could not run.
- [ ] Build a production archive into a dedicated temporary output directory and run the existing packaged-extension smoke check against it; ensure dependencies are bundled and tests/dev dependencies excluded.
- [ ] Obtain whole-change review using the selected execution workflow, resolve supported findings, and rerun affected tests. Commit documentation and report verified fixes separately from the open production investigation. Do not push, merge or deploy.
