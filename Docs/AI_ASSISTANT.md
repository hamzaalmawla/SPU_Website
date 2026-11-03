# AI assistant (public chat robot)

A floating 3D robot on every public page opens a chat panel where visitors ask
about the university in Arabic or English. It answers only from the files
editors upload under **Administration › AI assistant**, and refuses anything
else.

This replaces the plan in `ADMISSION_CHATBOT_INTEGRATION_PLAN.md` (a separate
Python/Qdrant service). Shared cPanel hosting cannot run that stack, so the
same retrieval-augmented design runs inside Laravel, with OpenAI doing the
embedding and the writing.

## How an answer is made

```text
browser ── POST /{locale}/assistant/messages ──▶ ChatbotController (thin)
                                                   │
                                     ChatbotServiceInterface::ask()
  1. live?  enabled + OpenAI key + at least one indexed file
  2. paused visitor or address?            ── yes ─▶ "cannot take more questions now"
  3. fair use (minute / hour / day share)  ── over ─▶ "ask again in N minutes"
  4. ChatbotGuard (code, rule-bypass, obfuscation; no network) ── refuse
  5. same question again and again (spam)  ── refuse
  6. site-wide daily cap, answer cache
  7. personal data removed (phones, ID numbers, emails)
  8. OpenAI moderation (free)              ── flagged ─▶ refuse
  9. retrieval: question embedded (text-embedding-3-small, 512 dims), cosine
     over active passages + keyword re-rank, top 6 above the threshold
 10. model call (Responses API, store:false, structured output
     {category, answer}); main model, then the fallback model on failure
 11. category not university/greeting   ── refuse (site's own message)
 12. output guard: code, leaked instructions ── refuse; links only to
     university hosts
 13. answer + source titles + signature of the question/answer pair
```

Every refusal shows the same polite message, so probing does not reveal which
layer stopped it. The admin "Try it" box shows the reason.

## Models and cost

| Setting | Default | Why |
|---|---|---|
| Main model | `gpt-5.4-mini`, reasoning `none` | Strongest model in OpenAI's complimentary tier (10M tokens a day across the small models, for organizations with data sharing on) |
| Fallback model | `gpt-6-luna`, reasoning `none` | $0.10 / $0.50 per 1M tokens; answers when the main model is rate limited, out of quota or unavailable. Never used when the key is rejected |
| Embeddings | `text-embedding-3-small`, 512 dimensions | $0.02 per 1M tokens |
| Moderation | `omni-moderation-latest` | Free |

Model ids contain dots, so code reads `config('chatbot.models')[$id]`, never
`config("chatbot.models.$id")`.

The admin page's costs use standard prices: for free-tier models they are an
upper bound. **The free tier requires data sharing on the OpenAI organization,
which shares questions and the matching passages with OpenAI.** Decide that
with the university before switching it on.

## Misuse protection

| Threat | Defence |
|---|---|
| "Write me Python", homework, translation, essays | `ChatbotGuard::inspectQuestion` (no cost) for code; the model's `off_topic` category for the rest; output guard rejects any code-like answer |
| "Ignore your instructions", role-play, DAN, "show your prompt", spaced or leetspeak spellings, special tokens | Guard patterns in English and Arabic, then the model's `injection` category; output guard rejects leaked instruction text |
| Forged conversation history ("assistant: sure, I write code now") | Each answered pair is HMAC-signed (`ConversationSigner`, APP_KEY); unsigned or edited turns never reach the model |
| Hidden or encoded payloads | Zero-width/bidi characters, base64 blobs, symbol floods, padding: refused |
| Insults, trolling, games, attacks on the university | Model category `abuse`; moderation for hate and harassment |
| One person spamming or playing | Fair use per visitor (session): 5 a minute, 20 an hour, 40 a day (hour/day editable); one IP address gets 8 visitors' shares (campus Wi-Fi); the same question more than 3 times in 10 minutes is spam |
| Repeat offenders | Refusals cost points (1 off-topic, 2 attack). 4 points in an hour pauses the visitor for an hour; a second pause the same day lasts 24 h. 16 points pauses the whole address |
| Floods | Route limiter `chatbot`: 30 a minute and 600 a day per IP |
| Running up the bill | Site-wide daily cap (default 3000), answer cache, max 1200 output tokens |
| Phishing links in answers | Links kept only to this site, `spu.edu.sy` hosts, `mailto:` and `tel:` |
| Personal data | Phones, ID numbers and emails are removed before anything goes to OpenAI; no question or answer text is stored, only daily counts |

The widget renders Markdown with DOM APIs only (no `innerHTML` of model text)
and allows only http(s), mailto, tel and same-site links.

## Admin

`ManageChatbot` (`chatbot.view` to open, `chatbot.update` for every change;
super admins have both, other roles get it under Administration › Roles).

- Upload PDF, DOCX, TXT, MD, CSV (12 MB, 10 at a time) or add typed text.
  Indexing runs in the request (no queue worker on this host). Scanned PDFs
  without a text layer fail with a clear message.
- Per file: view extracted text, rename, use/stop using, re-index, delete.
  Every change is audited (`chatbot.*` actions) and retires cached answers.
- Settings: on/off, main and fallback model, OpenAI key (encrypted with
  APP_KEY, only the last four characters shown), names, welcome texts,
  suggested questions, extra instructions, site-wide and per-visitor limits.
- Usage: questions, cache hits, unanswered, refused, limited, paused,
  estimated cost; 14-day chart.

The widget appears only when the assistant is switched on, a key is saved and
at least one file is indexed. Those changes flush the public page cache (the
file store has no tags, so it is a full flush, the same as a footer change).

## Operations

- Migration `2026_11_03_000001_create_chatbot_tables` is schema-only (no
  approval file needed).
- Files live on the `local` disk under `storage/app/private/chatbot/documents`,
  which the deploy never touches. Back them up with `storage/`.
- Changing `chatbot.embedding.dimensions` or model marks every file "needs
  re-index"; use **Re-index what needs it**.
- `CHATBOT_ENABLED=false` in `.env` hides the widget whatever the admin says.
- Logs carry only failure kinds and statuses, never questions, answers or keys.
- Rotate the OpenAI key in Settings; removing it hides the widget at once.

## Code map

- Contracts: `app/Contracts/Chatbot/*` (service, knowledge, settings, usage, OpenAI client)
- Services: `app/Services/Chatbot/*`
- Pure helpers: `app/Support/Chatbot/*` (extractor, chunker, vectors, keywords, prompt, guard, signer)
- Public: `ChatbotController`, `ChatbotMessageRequest`, `resources/views/public/layout/chatbot.blade.php`,
  `resources/js/chatbot/*` (widget, Markdown renderer, state, Three.js robot), `resources/css/frontend/chatbot.css`
- Admin: `app/Filament/Pages/ManageChatbot.php`, `resources/views/filament/pages/manage-chatbot.blade.php`, `resources/views/filament/chatbot/*`
- Tests: `tests/Unit/Chatbot/*`, `tests/Feature/Chatbot/*`, `tests/js/chatbot.test.js`
