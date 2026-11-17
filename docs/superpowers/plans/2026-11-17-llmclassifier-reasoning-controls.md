# LLM Classifier Reasoning Controls Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans for the user's selected native execution method. Track steps with checkboxes.

**Goal:** Make blank/missing temperature omit the parameter and expose optional reasoning effort and total output-token ceilings, with accurate documentation.

**Architecture:** Extend the existing SDK adapter and SeedDMS settings wiring without changing classification behavior or model detection. Map optional fields by explicit API format and verify captured HTTP requests plus real SeedDMS settings persistence.

**Tech Stack:** PHP 8.2+, locked openai-php/client 0.22.0, Guzzle, PHPUnit, existing Docker/package builders.

**Spec:** `docs/superpowers/specs/2026-11-17-llmclassifier-design.md`, approved reasoning-controls amendment.

## Global Constraints

- Work only in the existing feature worktree; no running-container settings changes or live API calls.
- Empty or absent temperature omits it in all API modes; `omit` remains a compatibility alias; `0.0` persists explicit zero.
- Effort and token cap are optional with no guessed defaults or model autodetection.
- Responses uses `reasoning.effort` and `max_output_tokens`; Chat uses `reasoning_effort` and `max_completion_tokens`.
- Effort values: none, minimal, low, medium, high, xhigh, max; support depends on the deployed model.
- Invalid nonempty effort/cap must fail locally with no outgoing request. Token cap must be a positive integer representable by PHP; reject fractions, scientific notation, negatives, zero and overflow.
- Preserve 120-second timeout, completion/refusal checks, secret-safe logging and locked dependencies.
- Document compatibility changes, Luna effort uncertainty, reasoning-token counting and cache Refresh. Version/package 1.1.1.

## Review Focus

1. Missing temperature after SeedDMS saving must omit the field (Task 1 persistence test).
2. Effort `none` is explicit, not an empty default (Task 1 captured request).
3. Zero, overflow and malformed token caps must not send a request (Task 1 invalid cases).
4. Blank reasoning settings must not send null/empty fields (Task 1 all-mode matrix).
5. Legacy providers may reject new fields; leave fields absent unless configured and do not silently retry (Task 1 matrix, Task 2 docs).

### Task 1: Optional controls through settings and request transport

**Files:** Modify `ext/llmclassifier/class.llmclassifier.php`, `conf.php`, `lang.php`, `tests/LLMClientTest.php`, `tests/ConfigurationTest.php`, `tests/temperature-persistence.php`.

**Interfaces:** Preserve the constructor's existing positional parameters, then append `$reasoningEffort = null, $maxOutputTokens = null` after `$httpClient`. Change default `$temperature` to null. Settings keys `llm_reasoning_effort` and `llm_max_output_tokens` pass raw values to the adapter for validation. Missing temperature defaults to null in classifier wiring; blank/omit -> null, numeric -> float.

- [ ] Read current spec and test utilities; run the existing suite in PHP 8.2 as baseline.
- [ ] Write request tests named `testReasoningControlsMapByApiFormat`, asserting Responses body `reasoning === ['effort'=>'low']` and `max_output_tokens === 1200`, Chat body `reasoning_effort === 'low'` and `max_completion_tokens === 1200`; assert alternative names/max_tokens absent. Run each format with explicit settings and unset/empty controls. Include each effort value, particularly none.
- [ ] Add `testInvalidReasoningControlsDoNotSend`: invalid effort, token caps 0/-1/1.5/'1e3'/'garbage'/overflow produce null, a log, empty captured request history. Positive integers and digit strings become numeric JSON integers; trim whitespace. No additional retry behavior.
- [ ] Update temperature tests: direct default, missing settings and blank after actual save/reload omit field; explicit 0.3 and 0.0 preserved. Add real save/reload effort/cap cases to the existing integration script, asserting configured payload after reloading and absence for blank settings.
- [ ] Add configuration tests for effort select with empty choice, translated option labels, cap input and both settings wiring. Run `docker run --rm -v "$PWD:/workspace" -w /workspace/ext/llmclassifier php:8.2-cli vendor/bin/phpunit`; expect failures for missing controls/default behavior. Run persistence script in built image with checkout mounted; expect old missing-temperature fallback failure.
- [ ] Implement minimal adapter validation and payload mapping, constructor/default change, settings dropdown/input and English/German descriptions. Preserve omit alias. Error logs name invalid settings without secrets or request data.
- [ ] Run full suite PHP 8.2 and PHP 8.5; run `docker run --rm --entrypoint php -v "$PWD/ext/llmclassifier:/workspace/ext/llmclassifier:ro" seeddms:foundry-sdk /workspace/ext/llmclassifier/tests/temperature-persistence.php`. Expect all cases passing.
- [ ] Commit `feat: expose optional reasoning controls and omit unset temperature`.

### Task 2: Documentation, release and bundled artifact

**Files:** Modify `ext/llmclassifier/README.md`, `changelog.md`, `conf.php`, class/lang version docblocks, `tests/ConfigurationTest.php`, `build-scripts/package-llmclassifier.sh`.

**Interfaces:** Produce `dist/llmclassifier-1.1.1.tar.gz` with locked production dependencies; unchanged standalone install layout.

- [ ] Update release test to 1.1.1; run and observe failure before version change.
- [ ] Set coherent 1.1.1 release metadata. Update package filename and README install references. Document empty/missing temperature omission, explicit 0.3 migration and 0.0 persistence, retained omit alias; remove outdated implicit-default recommendations.
- [ ] Document effort/cap field mapping and model-specific support; leave effort empty initially for Luna; effort is not a numeric budget, token ceiling includes hidden reasoning and visible text, small caps can prevent complete classification. No default token values or claims of guaranteed cost/latency. Preserve cache Refresh instructions and no token acquisition claims.
- [ ] Run suites on PHP 8.2/8.5, all changed PHP syntax checks, `git diff --check`, and standalone builder. Unpack 1.1.1 package and run existing package smoke script. Expect all passing.
- [ ] Build `seeddms:foundry-sdk`, run package smoke with actual SeedDMS autoloader first, and real persistence script with checkout mounted. Record external build failures if any; no success claims without output.
- [ ] Commit `docs: document reasoning limits and release classifier 1.1.1`.

## Final handoff

- [ ] Native execution ends with one independent read-only review of this follow-up range; verify supported findings and add failing regression tests before fixes.
- [ ] Report verified results, artifact path, migration behavior and model-specific limits; do not merge/push/remove worktree or change live settings without authorization.
