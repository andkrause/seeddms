# LLM Classifier — Foundry Responses and PHP SDK Design

Status: Approved by the user on 2026-11-17.

## Intent

Update `ext/llmclassifier` to use Microsoft Foundry's OpenAI v1 Responses API
with API-key authentication, replacing the hand-written cURL transport with
`openai-php/client`. The user prefers explicit configuration and runs a current
PHP version. All work, including this specification, happens in the isolated
`llmclassifier-foundry-sdk` Git worktree.

## Approaches

- **Selected:** Community SDK with separate API-format and authentication
  settings. Reduces custom transport/serialization code and supports Responses
  alongside existing Chat Completions. Requires Composer-managed dependencies
  and PHP 8.2+.
- Retain cURL with explicit settings: fewer dependencies and preserves PHP 8.0,
  but leaves more API-specific transport and parsing code to maintain.
- Provider presets: simpler UI, but couples authentication to API format. The
  user prefers independent, explicit settings instead.

## Goals and constraints

- Use `openai-php/client` (current verified stable release: 0.22.0), with a
  committed dependency lock and PHP minimum 8.2 reflected in metadata/docs.
- Package production Composer dependencies with the installable extension so
  the SeedDMS server does not need Composer. Choose the build/package mechanism
  in the implementation plan after inspecting existing build scripts. Do not
  add an unrelated application-wide dependency migration.
- Keep `SeedDMS_LLMClient` as a small adapter for settings, request construction,
  classification result normalization, and existing null-on-error logging.
- Preserve classification prompts, PDF extraction, folder scoping, hooks, and
  document metadata behavior. No agents, streaming, tools, retries, OAuth token
  acquisition, or unrelated cleanup.

## Configuration and compatibility

Add separate settings (with English and German descriptions):

1. `llm_api_format`: Responses, generic Chat Completions, or legacy Azure Chat
   Completions.
2. `llm_auth_method`: `api-key`, Bearer, or none.

Explicit choices always win; do not infer new configuration from hostnames.
For existing installations with the new settings unset, retain the old Azure
hostname detection and header selection solely as a compatibility fallback.
Document this fallback clearly rather than presenting it as the recommended
configuration. Invalid explicit choices must fail with a clear error, not
silently select another API or authentication method.

Responses accepts a base URL ending in `/openai/v1` or the portal's full URL
ending in `/openai/v1/responses`; normalize the latter to the SDK base URI,
without appending a duplicate resource path. Generic Chat Completions retains
the existing base-URL convention; legacy Azure retains the resource-root
convention and its deployment path and configured/fallback API version.

For Foundry: select Responses and `api-key`, enter the portal endpoint and the
deployment name in `llm_model`. Do not append a dated `api-version` to v1.

For `api-key` authentication, set the SDK's custom `api-key` header and do not
call `withApiKey()`, which would add Bearer authentication. Bearer uses the
SDK's Bearer mechanism; none sends neither authentication header. This does
not implement Entra token acquisition or refresh.

Allow an explicit blank temperature to omit the parameter (including numeric
zero correctly when supplied). An absent setting retains the existing 0.3
default. Document that reasoning models may require omission.

## Request and result handling

- Responses: send the deployment as `model` and the existing system/user
  prompts as Responses input. Use `text.format` with a strict JSON Schema:
  required `name` string, `categories` array of strings, and `keywords` array
  of strings; no additional properties. Use a model supporting structured
  outputs. No automatic downgrade to another output format on failure.
- Set `store: false` for Responses: each classification is independent and
  does not require stored conversation history. This is a proposed builder
  default, not a claim of zero service retention.
- Extract assistant output through the SDK's Responses text helper/typed
  output items, verifying actual SDK behavior with fixtures rather than
  assuming a top-level `output_text` field exists in all REST responses.
  Decode classification JSON and return the existing array result. Refusals,
  incomplete/failed responses, missing text, or invalid JSON return null and
  log an actionable error without applying partial metadata.
- Chat Completions retains messages, JSON-object output format, existing
  model/deployment handling, and `choices` text extraction via the SDK.
- Preserve the 120-second request timeout. Keep secrets out of logs.

## Verification and completion criteria

Use fake HTTP responses and captured outgoing requests; no live credentials
are required. Verify:

- Foundry base and full endpoint normalization, Responses path, model/input,
  strict output schema, omitted api-version, and `store: false`.
- `api-key` is present and Authorization absent in key mode; Bearer mode sends
  only Authorization; none sends neither header.
- Successful Responses normalization, output items preceding assistant text,
  refusals/incomplete responses, invalid JSON, HTTP and connection failures.
- Generic OpenAI, legacy Azure deployment/version routing, and unauthenticated
  Ollama requests remain compatible; missing settings preserve old behavior.
- Temperature omission, zero value, and legacy default.
- Production package autoloads without Composer on the server; dependency
  loading is checked for compatibility with SeedDMS's existing autoloader.
- PHP syntax checks and all new tests pass in an available PHP 8.2+ runtime.
  Local PHP is currently unavailable; runtime provisioning belongs to the plan.

Update `README.md`, `changelog.md`, translated configuration descriptions,
and extension version/requirements as part of the implementation. No files
under `ext/` may change before explicit approval of this specification.

## Evidence

- Microsoft Responses API, including API-key REST examples and `text.format`:
  https://learn.microsoft.com/en-us/azure/foundry/openai/how-to/responses
- v1 lifecycle (no dated API version required):
  https://learn.microsoft.com/en-us/azure/foundry/openai/api-version-lifecycle
- SDK configuration and supported resources:
  https://github.com/openai-php/client
- SDK PHP requirement and release metadata:
  https://packagist.org/packages/openai-php/client

Public documentation was checked during design; no private resource or live
API call was accessed. SDK support does not guarantee availability of a
particular model or structured output feature in the user's deployment.
