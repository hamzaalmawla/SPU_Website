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

## Page weight and loading

The assistant must never slow a page down:

- The launcher (robot, teaser) is HTML in the layout and styled by
  `chatbot.css` in the main stylesheet. It shows from the first paint wherever
  JavaScript runs (`html.js`, set by the inline script in `<head>`), so it
  never appears late (which would cost Speed Index), and is hidden without
  JavaScript (it could do nothing).
- `resources/js/chatbot/bootstrap.js` (in `app.js`, under 1 KB) fetches the
  widget's script and the panel's styles (`chatbotWidget.js` with
  `chatbot-panel.css`, ~10 KB gzipped) on the first interaction: a pointer,
  touch, key or scroll anywhere, or the launcher. A first click opens the
  panel when they arrive. A visitor already in a conversation gets it back when
  the browser is idle. A page that is only loaded (a crawler, a speed test)
  never fetches them.
- The 3D robot (`robotScene.js`, ~140 KB gzipped) loads only after an
  interaction with the launcher, and animates only while awake; otherwise the
  flat SVG robot stays.

## Admin

`ManageChatbot` (`chatbot.view` to open, `chatbot.update` for every change;
super admins have both, other roles get it under Administration › Roles).

- Upload PDF (text or scanned), DOCX, XLSX, CSV, TXT, MD, or a photo of a
  document (JPG, PNG, WebP), up to 16 MB (PHP's `upload_max_filesize` on the
  host), 10 at a time, or add typed text. See "How files are read" below.
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

## How files are read

Everything ends up as Markdown-like text with one idea per line, so a passage
cut from the middle of a file still makes sense. Table rows always become
`column: value ; column: value` lines under a single `| header |` line
(`App\Support\Chatbot\TableRows`), whatever format the table came from.

| Source | Reader | Cost |
|---|---|---|
| PDF with a text layer | smalot/pdfparser, then `PdfLayoutText` | free |
| PDF of scanned pages, photos | `ChatbotScanReader` (OpenAI vision, the saved key) | ~1.5k input + the page's text as output, per page |
| Word (.docx) | `DocumentTextExtractor::docx` (headings, tables) | free |
| Excel (.xlsx) | `SpreadsheetText` (every visible sheet, merged cells filled, dates, rounded floats) | free |
| CSV, TXT, MD | `DocumentTextExtractor` | free |

**Arabic PDFs in visual order.** Excel and Word "Save as PDF" draw Arabic one
glyph at a time from left to right; in stream order every word comes out
reversed (`لودجلا` for `الجدول`) and table cells run together. `PdfLayoutText`
rebuilds each line from glyph positions (right to left on Arabic lines,
numbers and Latin kept left to right), turns wide gaps into ` | ` cell
boundaries, and keeps the parser's own text unless the rebuild reads clearly
better (`arabicOrderScore`: words starting with "ال" or ending with "ة" count
for, the reverse against). It frees the parser's per-page position cache as
it goes: a 200-page table reads in under 40 MB.

**Scans.** A PDF page with fewer than 40 letters and an image at least 400 px
on its short side is a scan (a scanner app's watermark text does not count).
Its largest images (JPEG as is; Flate samples wrapped as PNG; JBIG2, CCITT
and JPEG 2000 are skipped) go to the vision model one page per Responses call,
`chatbot.ocr.concurrency` (8) at a time, `store: false`, `detail: high`. The
model writes the page as Markdown with plain Markdown tables; the column
names are added to each row afterwards in PHP, which halves the output tokens
(output is most of a page's cost). The model is `CHATBOT_OCR_MODEL`, or the
assistant's main model (GPT-5.4 mini, in OpenAI's free daily tier).

Each page's text is saved next to the file (`<file>.scan.json`) as soon as
it is read, and reused on re-index, so a page is paid for once. The upload
request reads for up to `request_seconds` (40); the document then shows
"Reading scanned pages: n of N" and stays out of answers until every page is
read. The rest is read by `chatbot:continue-indexing` (scheduled every minute,
in the background, `command_seconds` 50) and by the admin list's 5-second
refresh while it is open (`poll_seconds` 15). A per-document cache lock keeps
two readers off the same file. A page that fails `max_attempts` (3) times is
left out and the file says so; a rejected key or an empty account fails the
file with the reason. Reading is counted in Usage (tokens and cost).

## Operations

- Migrations `2026_11_03_000001_create_chatbot_tables` and
  `2026_11_06_000001_add_scan_progress_to_chatbot_documents` are schema-only
  (no approval file needed).
- The scheduler cron (`schedule:run` every minute) must be running for long
  scans to finish without the admin page open.
- Files live on the `local` disk under `storage/app/private/chatbot/documents`,
  which the deploy never touches. Back them up with `storage/`.
- Changing `chatbot.embedding.dimensions` or model marks every file "needs
  re-index"; use **Re-index what needs it**.
- `CHATBOT_ENABLED=false` in `.env` hides the widget whatever the admin says.
- Logs carry only failure kinds and statuses, never questions, answers or keys.
- Rotate the OpenAI key in Settings; removing it hides the widget at once.

## Code map

- Contracts: `app/Contracts/Chatbot/*` (service, knowledge, settings, usage, OpenAI client)
- Services: `app/Services/Chatbot/*` (`ChatbotScanReader` reads scanned pages)
- Pure helpers: `app/Support/Chatbot/*` (extractor, PDF layout, spreadsheets, table rows, chunker, vectors, keywords, prompt, guard, signer)
- Public: `ChatbotController`, `ChatbotMessageRequest`, `resources/views/public/layout/chatbot.blade.php`,
  `resources/js/chatbot/*` (widget, Markdown renderer, state, Three.js robot), `resources/css/frontend/chatbot.css`
- Admin: `app/Filament/Pages/ManageChatbot.php`, `resources/views/filament/pages/manage-chatbot.blade.php`, `resources/views/filament/chatbot/*`
- Tests: `tests/Unit/Chatbot/*`, `tests/Feature/Chatbot/*`, `tests/js/chatbot.test.js`
