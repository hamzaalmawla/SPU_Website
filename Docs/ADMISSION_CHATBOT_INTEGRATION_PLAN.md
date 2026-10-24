# SPU Admission Chatbot Integration Plan

## Decision

Do not copy the existing React application into Laravel or embed it in an iframe. Keep the Python RAG system as a separately hardened private service, and build a native floating chat launcher and panel in the Laravel public layout. Browsers should call a same-origin Laravel endpoint; Laravel should call only the private chatbot gateway.

This preserves the current Laravel AR/EN shell, RTL/LTR behavior, CSP, accessibility, rate limiting, audit conventions, and cPanel compatibility without exposing Qdrant or internal Python services.

## Reviewed Source

Repository: `C:\Users\hamza\SPU-Admission-Chatbot-main`

The repository contains:

- FastAPI Data Loader on port `5001`.
- FastAPI Data Splitting service on port `5002`.
- FastAPI BGE-M3 Embedding Store on port `5003`.
- FastAPI RAG service on port `5004`.
- FastAPI QA Chatting service on port `5005`.
- Qdrant on ports `6333` and `6334`.
- A React 18/Vite frontend under `Services/spu-ai-connect-main`.
- OpenAI Responses API generation, defaulting to `gpt-4.1-mini`.
- Local `BAAI/bge-m3` dense/sparse embeddings.

## Current Launch Blockers

The chatbot repository must not be exposed publicly in its current state:

- `Data/manifest.json` has no knowledge documents.
- The React frontend imports missing `src/lib/api` and `src/lib/utils` modules and cannot currently build.
- Document `visibility` metadata is stored but not enforced during retrieval.
- Public conversation-list and conversation-delete endpoints create cross-conversation privacy risk.
- Internal retrieval, embedding, diagnostics, Qdrant, and model-intensive endpoints are unauthenticated if their ports are reachable.
- Every internal Docker service and both Qdrant ports are host-published.
- Rate limiting is process-local and trusts arbitrary `X-Forwarded-For` input.
- Conversation state has no expiry and disappears on restart.
- Questions, retrieved chunks, and conversation history are sent to OpenAI; privacy and retention approval is required.
- Raw upstream exceptions can reach clients.
- No repository license file was found. University ownership and permission to reuse the code must be recorded.
- The Docker profile assumes resources and process control that normal shared cPanel hosting cannot provide.

## Target Architecture

```text
Public browser
    -> Laravel POST /{locale}/admission-chat
    -> AdmissionChatServiceInterface
    -> authenticated private HTTPS chatbot gateway
    -> QA service
    -> RAG service
    -> embedding service
    -> Qdrant
    -> OpenAI
```

Only the gateway may be reachable from Laravel. Ports `5001-5004`, `6333`, and `6334` must remain private. The standalone chatbot admin UI must not be deployed publicly.

## Phase 1: Harden The Chatbot

1. Confirm code ownership, package/model licenses, OpenAI terms, and operational ownership.
2. Add approved admissions documents and checksums to `Data/manifest.json`.
3. Enforce `visibility=public` in every public retrieval query.
4. Remove public conversation enumeration and arbitrary deletion.
5. Replace caller-owned conversation IDs with signed, expiring, isolated tokens.
6. Add conversation expiry and bounded storage.
7. Require a Laravel service credential at the public QA gateway.
8. Remove public host bindings from internal services and Qdrant.
9. Disable public `cache_bypass`, retrieval tuning, diagnostics, and embedding endpoints.
10. Trust forwarded client data only from the known reverse proxy.
11. Replace raw errors with stable error codes.
12. Distinguish healthy `not_found` answers from service outages.
13. Add concurrency, body-size, output-token, and provider-spend limits.
14. Build AR/EN evaluation datasets from approved university material.
15. Test prompt injection, source poisoning, private-document leakage, and conversation isolation.

## Phase 2: Deploy Private AI Infrastructure

Use a VPS or managed container host, not shared cPanel, for Qdrant and the Python services.

Required controls:

- Private Docker network.
- TLS gateway reachable from the Laravel server.
- Firewall or IP allowlist plus application-level request signing.
- Secret injection without committed `.env` files.
- Qdrant snapshots and source-manifest backups.
- Health, latency, provider-error, rate-limit, and spend monitoring.
- Documented re-index, restore, and rollback procedures.

Start with non-streaming `POST /chat`. Streaming through PHP-FPM/LiteSpeed should be enabled only after staging proves that buffering, worker occupancy, disconnect cancellation, and concurrency are safe.

## Phase 3: Laravel Gateway

Planned application files:

- `app/Contracts/AdmissionChatServiceInterface.php`
- `app/DTOs/AdmissionChatRequestDTO.php`
- `app/DTOs/AdmissionChatResponseDTO.php`
- `app/DTOs/AdmissionChatSourceDTO.php`
- `app/Services/AdmissionChatService.php`
- `app/Http/Requests/Public/AdmissionChatRequest.php`
- `app/Http/Controllers/Public/AdmissionChatController.php`
- service binding in `app/Providers/AppServiceProvider.php`
- localized route in `routes/web.php`
- feature flag, gateway URL, service secret, and timeouts in configuration

The controller must remain thin. The service must:

- Clamp message length and reject sensitive unsupported payload fields.
- Send the current Laravel locale explicitly.
- Authenticate server-to-server requests.
- Use bounded connect and total timeouts.
- Implement a short circuit breaker and no unbounded retries.
- Map timeout, unavailable, invalid, and rate-limited responses to stable public DTOs.
- Return only approved citation titles and short labels, never raw source chunks or internal filenames.
- Avoid logging full questions, answers, transcripts, personal data, secrets, or source text.

Add a dedicated Laravel limiter by IP and anonymous session/conversation token. Do not reuse the general public-form limiter.

## Phase 4: Floating Public Widget

Add one native Blade/Alpine widget to `resources/views/layouts/public.blade.php` so it is present across approved public pages but absent from admin and preview layouts.

Planned frontend files:

- `resources/views/public/layout/admission-chat.blade.php`
- `resources/js/alpine/admissionChat.js`
- scoped styles under the existing frontend CSS build
- `lang/ar/chatbot.php`
- `lang/en/chatbot.php`

Launcher requirements:

- Fixed circular button using logical `inset-inline-end` positioning.
- Localized accessible label.
- `aria-haspopup="dialog"` and accurate `aria-expanded` state.
- Minimum 44-by-44 pixel target.
- Does not obscure fixed navigation, consent banners, or form actions.

Panel requirements:

- Desktop panel around 380-440 pixels wide.
- Mobile full-height sheet using `100dvh` and safe-area insets.
- Visual viewport handling so the composer remains above the soft keyboard.
- Dialog semantics, focus trap, Escape close, and focus return to launcher.
- Textual loading/error states and a polite live region.
- Enter sends; Shift+Enter inserts a line break; explicit send button remains available.
- Laravel locale controls language and direction. The widget must not mutate the document language.
- `dir="auto"` for mixed-language user messages.
- Reduced-motion mode disables pulse, slide, bounce, and smooth scrolling.
- Restricted Markdown or structured plain text; raw HTML and arbitrary images are forbidden.
- External links restricted to approved schemes and rendered with safe `rel` attributes.
- Official admissions phone/email fallback when the service is unavailable.
- Visible warning not to submit names, IDs, grades, phone numbers, or application-sensitive data.

The same-origin gateway allows the current public CSP to keep `connect-src 'self'`; no iframe or third-party script origin is required.

## Phase 5: Testing And Release Gates

Required Laravel tests:

- Request validation and rate limiting.
- Service contract and HTTP client fakes.
- Timeout, malformed response, upstream `429`, and outage mapping.
- No secrets or full prompts in logs.
- AR/EN locale propagation.
- Keyboard open/send/close/focus restoration.
- Mobile panel and reduced-motion JavaScript behavior.
- CSP remains same-origin.

Required chatbot tests:

- Public-only retrieval enforcement.
- Conversation isolation and expiry.
- Gateway authentication.
- Prompt-injection resistance.
- AR/EN grounded-answer evaluation.
- Qdrant/OpenAI outage distinction.
- Cost and concurrency controls.

Production release requires a staging rehearsal through the actual cPanel-to-gateway network path, an AI service rollback plan, privacy approval, and an official admissions fallback that works when JavaScript or the AI service fails.

## Deferred Decision

No chatbot files have been copied into the Laravel repository yet. Implementation should begin only after the Phase 1 ownership, source-data, visibility, privacy, and private-hosting gates are approved.
