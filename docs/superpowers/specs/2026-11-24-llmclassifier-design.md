# LLM Classifier — Intermittent Failures and Safe Diagnostics

Status: Proposed; implementation requires explicit user approval.

## Intent and evidence

Investigate intermittent LLM failures with unchanged configuration and make
failure logs actionable without exposing credentials or document content.

- The upload hook constructs a classifier for each document. Its settings
  snapshot is instance-local; the default HTTP client is constructed per call.
- The installed SeedDMS Settings constructor reads settings.xml and rebuilds
  extension settings. No cross-request classifier configuration cache was found.
- A reproduction against the locked production SDK confirms that a simulated
  cURL timeout loses its cause. HTTP 429 and 503 also lose their status because
  their SDK exceptions expose a response, not getStatusCode().
- The screenshot alone cannot establish the intermittent production cause.
  DNS, TLS, connection and timeout failures remain possibilities, not findings.

## Approaches

Recommended: fix safe diagnostics and add settings/request lifecycle regression
coverage first. Use the resulting production evidence to identify any remaining
environmental failure. This avoids asserting that cache invalidation solves a
transport failure.

Alternatives considered: unconditional configuration reload/cache invalidation
(no stale cache demonstrated); automatic retries (may duplicate costly calls
and mask the cause). Neither is included.

## Design

- Preserve the existing classification-array-or-null interface and 120-second
  timeout. Failed classifications must never change document metadata.
- Log HTTP status from SDK response-bearing exceptions, including 429/5xx,
  and safe provider error codes/types and correlation IDs where available.
- Unwrap transport causes. Identify cURL errors such as DNS, connect, TLS and
  timeout by structured handler context and a controlled description; include
  the cURL error number. Do not blindly log exception messages or raw bodies.
- Make incomplete Responses and abnormal Chat finish reasons identifiable
  through safe status/reason fields. Preserve refusal and malformed-output safety.
- Do not log API keys, Authorization headers, URL credentials/query strings,
  request/response bodies, prompts or document text in these diagnostics.
  Unknown free-form details remain withheld rather than treated as safe.
- Add tests for timeout/DNS/TLS/connection failures, 401/429/5xx, safe provider
  details and secret suppression. Cover repeated calls with unchanged settings,
  recovery after a failed call, and fresh classifier construction from updated
  settings. Check settings save/reload using the actual SeedDMS implementation.
- Do not introduce a config reload unless regression evidence demonstrates a
  stale-config path. If a different runtime cause is found, report it before
  expanding this fix.
- Update the extension README and changelog with diagnostics, investigation
  limits and troubleshooting guidance. No live LLM calls or deployment changes.

## Verification and follow-up

Use the locked SDK with fake HTTP responses and captured requests, run the full
PHPUnit suite and PHP syntax checks, and verify actual SeedDMS save/reload in an
isolated container with temporary settings only. Check the production package.
If intermittent failures cannot be reproduced locally, explicitly report that
limitation and request a new diagnostic log from the affected deployment.
