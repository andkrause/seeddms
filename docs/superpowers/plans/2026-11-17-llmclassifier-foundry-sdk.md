# LLM Classifier Foundry SDK Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Support Foundry Responses with explicit API/authentication configuration through a packaged PHP SDK, preserving existing installations.

**Architecture:** Keep `SeedDMS_LLMClient` as the extension's adapter but delegate HTTP and typed response handling to `openai-php/client`. An extension-local Composer project supplies a concrete Guzzle HTTP client; Docker and standalone archives bundle its production dependencies. Tests capture real SDK requests using Guzzle's mock handler.

**Tech Stack:** PHP >=8.2, openai-php/client 0.22.0, Guzzle 7, PHPUnit 11, Composer 2, Docker.

**Spec:** `docs/superpowers/specs/2026-11-17-llmclassifier-design.md` (approved).

## Global Constraints

- All changes and commits stay in the current isolated worktree; create a named branch before implementation commits because the native worktree starts detached.
- Use `openai-php/client` with a committed dependency lock; PHP minimum 8.2.
- Package production Composer dependencies with the installable extension; no Composer required on the SeedDMS server.
- Explicit choices always win; old hostname inference is only an unset-setting compatibility fallback.
- Preserve classification prompts, PDF extraction, folder scoping, hooks, and metadata behavior.
- No agents, streaming, tools, retries, OAuth token acquisition, or unrelated cleanup in the extension.
- Responses uses strict schema output, `store: false`, and no dated api-version.
- Preserve the 120-second request timeout; keep secrets out of logs.
- Do not use live API credentials for verification.

## Review Focus

1. A full portal URL with trailing slash must not become `/responses/responses` (Task 2).
2. Explicit authentication must override old Azure host detection; none must send no credentials (Task 2).
3. Blank temperature must remain omitted through settings wiring; zero must not become a default (Task 3).
4. Reasoning/refusal output before text, or incomplete results, must not apply partial classification (Task 2).
5. A package loaded after another Composer autoloader must load without missing dependencies (Task 1/4); overlapping dependency versions remain an integration risk to document and smoke-test with actual SeedDMS if available.

---

### Task 1: Reproducible SDK dependencies and test harness

**Files:** Create `ext/llmclassifier/composer.json`, `ext/llmclassifier/composer.lock`, `ext/llmclassifier/tests/bootstrap.php`, `ext/llmclassifier/tests/DependencyTest.php`, `ext/llmclassifier/phpunit.xml`; modify `.gitignore`.

**Interfaces:** Production autoloader at `ext/llmclassifier/vendor/autoload.php`; tests define a minimal `SeedDMS_ExtBase` stub and load the class file. Tests may supply a concrete `Psr\Http\Client\ClientInterface` to the adapter added in Task 2.

- [ ] Create branch `feat/llmclassifier-foundry-sdk` from the current specification commit. Run `git status --short --branch`; expect only the approved spec/plan updates before committing them.
- [ ] Add dependency tests asserting `class_exists(OpenAI::class)`, `class_exists(GuzzleHttp\Client::class)`, and successful loading with a pre-registered autoloader. Run with Docker PHP before dependency installation; expect missing autoloader/classes.
- [ ] Create the extension-local manifest: require PHP `^8.2`, SDK `0.22.0`, Guzzle `^7.9`; require-dev PHPUnit `^11.5`; add `composer test` invoking PHPUnit. Ignore extension `vendor/`, PHPUnit cache, and generated `dist/` only. Do not commit vendored dependencies.
- [ ] Resolve and lock dependencies with Composer 2 in Docker (e.g. `docker run --rm -v "$PWD/ext/llmclassifier:/app" -w /app composer:2 install --no-interaction`). Use PHP 8.2 as Composer's platform target and verify the resolved requirements with a real PHP 8.2 runtime, not only Composer's current image.
- [ ] Run `docker run --rm -v "$PWD:/workspace" -w /workspace/ext/llmclassifier php:8.2-cli vendor/bin/phpunit`. Expect dependency tests passing. The current repository has no existing PHP test suite; record that rather than claiming baseline coverage.
- [ ] Commit manifest, lock, ignore rules, and harness: `test: add locked PHP SDK dependencies and harness`.

### Task 2: SDK-backed transport with explicit modes

**Files:** Modify `ext/llmclassifier/class.llmclassifier.php` (only `SeedDMS_LLMClient`); create `ext/llmclassifier/tests/LLMClientTest.php` and `ext/llmclassifier/tests/fixtures/responses-success.json`, `chat-success.json`, `responses-incomplete.json`, `responses-refusal.json`.

**Interfaces:** Preserve `chatCompletion($systemPrompt, $userMessage)` returning classification array or null. Extend constructor compatibly to `__construct($endpoint, $apiKey, $model, $temperature = 0.3, $apiVersion = null, $logger = null, $apiFormat = null, $authMethod = null, $httpClient = null)`; temperature accepts float or null, optional HTTP client implements PSR-18. Mode values are `responses`, `chat_completions`, `azure_chat_completions`; auth values `api-key`, `bearer`, `none`. Null/empty mode settings invoke legacy fallback.

- [ ] Inspect the installed, locked SDK factory, Responses response types and `outputText()` behavior. Build fixtures matching its actual required response fields and output items, not the abbreviated Microsoft documentation sample.
- [ ] Write captured-request tests with `GuzzleHttp\Handler\MockHandler` and history middleware. Assert POST, `/openai/v1/responses`, exactly `api-key: test-key` with no Authorization, deployment in model, original system/user content in `input`, `store === false`, no api-version, strict `text.format` schema for name/categories/keywords. Test both base endpoint and full portal endpoint, with and without trailing slash.
- [ ] Write table-driven tests for chat modes: generic `/v1/chat/completions` includes model/Bearer; legacy Azure `/openai/deployments/<encoded-model>/chat/completions?api-version=<version>` omits model and defaults to `2024-02-15-preview`; Ollama with no key omits auth. Explicit format and auth override even a recognized Azure hostname. Invalid explicit values return null with a clear error.
- [ ] Write response tests asserting returned classification equals `['name' => 'Invoice', 'categories' => ['Invoices'], 'keywords' => ['Steuer']]`. Exercise reasoning items before text, refusal, incomplete/failed status, absent text, invalid inner/outer JSON, HTTP 401/429/500, connection exception; assert null on each failure and no secret in captured logs. Test auth none with a configured key and Bearer on a Foundry host; test null temperature omission, numeric zero, and default 0.3.
- [ ] Run PHPUnit; expect request/Responses tests to fail against the old cURL implementation (including missing injectable transport).
- [ ] Replace only adapter transport with SDK calls. Load extension-local vendor autoload lazily when creating a client; missing dependencies log a helpful installation error and return null without disabling unrelated SeedDMS code. Use Guzzle with timeout 120 as the default PSR-18 client, SDK factory custom header for api-key without `withApiKey()`, Bearer only for bearer auth, none for none. Normalize full Responses endpoint to base URI. Set legacy Azure base URI/query explicitly. Use Responses input roles, strict schema and SDK output extraction; retain chat payload/semantics. Validate completed Responses status before decoding; catch relevant SDK/transport errors without logging credentials or raw document contents.
- [ ] Run `docker run --rm -v "$PWD:/workspace" -w /workspace/ext/llmclassifier php:8.2-cli vendor/bin/phpunit`; expect all tests passing. Capture timeout via a tested default-client construction seam if request history cannot observe it.
- [ ] Commit: `feat: migrate LLM transport to SDK with Responses support`.

### Task 3: Settings wiring and migration documentation

**Files:** Modify `ext/llmclassifier/conf.php`, `lang.php`, `class.llmclassifier.php` (classifier constructor), `README.md`, `changelog.md`; create `ext/llmclassifier/tests/ConfigurationTest.php`.

**Interfaces:** Settings `llm_api_format` and `llm_auth_method` map directly to Task 2 constructor arguments. Existing model, endpoint, key, api-version keys remain. Missing temperature -> 0.3; explicit blank -> null; `'0'` -> 0.0.

- [ ] Inspect SeedDMS's supported static select option format in its upstream source available from the repository's `SEEDDMS_URL` (do not guess config metadata). Prefer documented selects; if unavailable, use existing input fields with explicit allowed values/descriptions.
- [ ] Write failing configuration tests asserting allowed mode/auth values, English/German labels, PHP minimum 8.2 and coherent release metadata. Test classifier wiring with missing/new settings, blank/zero temperature through actual request capture; use a minimal DMS/settings stub and temporary pdftotext executable stub where needed without altering production PDF behavior.
- [ ] Run PHPUnit; expect failures for absent fields and blank-temperature conversion.
- [ ] Add settings with an empty/unset legacy compatibility choice; explicit selections stay independent. Wire the constructor and preserve blank temperature before casting. Update temperature/API-version descriptions and examples. Bump extension metadata, class/docblock versions and changelog consistently to 1.1.0 dated 2026-11-17. Document full/base Foundry URL, deployment, Responses + api-key, blank temperature for unsupported models, structured-output model requirement, store:false meaning, legacy fallback, SDK packaging requirements, and no automated Entra token refresh.
- [ ] Run all PHPUnit tests and syntax checks in PHP 8.2; expect all passing.
- [ ] Commit: `feat: expose explicit API format and authentication settings`.

### Task 4: Production packaging and whole-change verification

**Files:** Modify `Dockerfile` at extension-copy section; create `build-scripts/package-llmclassifier.sh`; extend `ext/llmclassifier/tests/DependencyTest.php`; update README install/build instructions.

**Interfaces:** `build-scripts/package-llmclassifier.sh [output-directory]` produces `llmclassifier-1.1.0.tar.gz` containing top-level `llmclassifier/` plus production `vendor/`, no tests/dev dependencies. Default output `dist/`; source checkout vendor is not pruned or overwritten. Docker image includes the same locked production dependencies.

- [ ] Write a packaging smoke check: unpack archive into a dedicated temporary directory, register an existing autoloader, load its vendor autoloader and class file with SeedDMS base stub, and assert SDK/Guzzle availability without Composer installed. Assert archive excludes PHPUnit/tests and includes licenses. Run before adding builder; expect missing package command/dependencies.
- [ ] Add a dedicated Composer 2 Docker build stage that installs the extension lock with `--no-dev --prefer-dist --no-interaction --optimize-autoloader`; final image copies vendor from that stage alongside extension files. Do not install Composer in the final image or change unrelated s6/PHP setup.
- [ ] Implement standalone package script using Docker/Composer in a staging directory, explicit file selection and archive creation; clean only its own temporary staging directory via trap. Include production dependency licenses. Document Docker requirement and copy-ready archive installation.
- [ ] Build package, run unpacked-package smoke check with `php:8.2-cli`, and run full PHPUnit suite in PHP 8.2 and current deployment-compatible PHP 8.5. Run syntax checks over changed extension PHP files. Expect all passing.
- [ ] Build existing Docker image with `docker build -t seeddms:foundry-sdk .` and smoke-test SDK loading in final image with entrypoint overridden. If external image/download access blocks the full build, record the exact blocker; do not claim integration verified. Inspect actual SeedDMS vendor/autoload and select metadata during this build where possible.
- [ ] Review `git diff --check`, cumulative diff, production package contents, and secret/log behavior. Commit: `build: bundle SDK dependencies in extension deployments`.

## Final handoff

- [ ] Obtain independent whole-branch review using the chosen execution workflow; resolve supported findings and rerun affected tests.
- [ ] Report commit list, named branch and worktree, verified runtime versions/results, package path, migration settings, and any integration verification limits.
- [ ] Do not merge, remove the worktree, push, or contact the live Foundry resource without user authorization.
