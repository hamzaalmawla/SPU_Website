# AI assistant (public chat host)

A small 3D host on every public page (a cartoon university host in a navy
suit with a ruby brooch, round glasses, swept-back hair, a mustache and a
goatee) opens a chat panel where visitors ask
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
  9. search rewrite: a small call (main model, temperature 0, cached a week)
     rewrites the question into the documents' wording, ONE LINE PER THING
     ASKED ("Arabic || English", at most 3; `SearchRewrite`); skipped on failure
10. retrieval (hybrid, one search per part): every passage is scored by
     meaning (cosine, text-embedding-3-small, 512 dims) and by rare words
     (BM25-style IDF share); candidates are the best 40 of the first and
     20 of the second; ranked by cosine + 0.18 × words; parts take turns,
     copies of a passage count once; top 6 (10 for several parts)
11. model call (Responses API, store:false, temperature 0, structured
     output {category, answer, memory, coverage}); main model, then the
     fallback model on failure
11b. coverage not "full" ── search again with 14 passages and ask once
     more; keep the answer that covers more (corrective retrieval)
12. category not university/greeting   ── refuse (site's own message)
13. output guard: code, leaked instructions ── refuse; links only to
     university hosts
14. answer + source titles + signature of the question/answer pair; the
     answer cache keeps only answers with coverage "full"
```

Every refusal shows the same polite message, so probing does not reveal which
layer stopped it. The admin "Try it" box shows the reason.

## Same question, same answer (2026-11-09)

Visitors saw the same question answered differently, and a question answered
a minute earlier "not known" in a new chat. The causes and what fixed each:

| Cause | Fix |
|---|---|
| The search rewrite and the answer were sampled (no temperature), so each wording of a question drew its own search and its own answer | Temperature 0 on both (GPT-5.4 mini takes it with reasoning "none"; a model that refuses it is asked again without and remembered for a day, `OpenAiClient`) |
| "I don't have that information" was cached for 12 hours as the first answer to that question, while the same words inside a conversation (not cached) were answered | Only answers whose `coverage` is "full" are cached; the cache key carries a version (`ANSWER_VERSION`) so answers from before a change are not served |
| Words only re-ranked the 40 best passages by meaning: a name or extension in a long directory list was never a candidate, so "who is X?" depended on how the rewrite happened to be worded | Words are a retrieval channel of their own over every passage, weighted by rarity (BM25 IDF), as in hybrid search; the two scores are combined by a weighted sum, which Bruch et al. (2023, "An Analysis of Fusion Functions for Hybrid Retrieval") found as good or better than rank fusion |
| A short follow-up was searched glued to the previous question even after the rewrite had already resolved it, so a new subject asked briefly ("من هو عامر خورشيد؟") was searched as the old one | The previous question is added only when there is no rewrite |
| A question with two parts took all six passages from its main part; the second was "not available" | The rewrite splits the question into parts and each part is searched on its own, taking turns for the slots (query decomposition, as in RAG-Fusion / multi-query retrieval); the rules require an answer per part and applying a source's formula to the visitor's numbers |
| A document held a decree's original text next to the amended one; a passage cut from the original read as the rule (decree 167's 50% exemption, replaced by 30% / 50% in decree 448) | The superseded text was taken out of `Docs/assistant-knowledge/07-…`; the rules say to answer from the text in force and to give every case with its condition |
| When the passages missed, the answer was simply "not known" | Corrective retrieval (after CRAG, Yan et al. 2024): coverage "none" or "partial" searches again wider and keeps the better answer |

| A rule's cases run past a passage's end (30% in one passage, 50% in the next), so a search found one case | The best 3 passages bring the passage after them in their file ("sentence window" retrieval, `chatbot.retrieval.neighbors`) |
| The rule "never carry a fact from one procedure's document to another" made the model refuse the exam regulation's GPA formula for a transfer question | Rules for every student (grading scale, GPA to percentage and grade, attendance) may answer any part, named by their regulation |

Copies of the same passage in several uploads (an old and a new directory)
are given to the model once. Corrected files go in with `chatbot:import-documents
--replace` (approval line `replace=yes`), which stops using older uploads of
the same title.

## Evaluation, contextual retrieval and reranking (2026-11-09)

Checked against the 2025–2026 literature (surveys in Springer and MDPI 2026,
"Reconstructing Context" 2025, "RAG vs. GraphRAG" 2025, CAG 2025, reranker
studies); the report for the university is linked from the session notes.

**Evaluation (`chatbot:evaluate`).** 32 golden questions in
`database/seeders/Data/chatbot/evaluation-questions.json`, Arabic and English,
single- and multi-part, each with the facts its answer must contain (checked
by hand against `Docs/assistant-knowledge`) and the documents they come from.
The command asks them of the live assistant as an admin test (whole pipeline,
no cache, no limits) and prints, per question and in total: answers right (every
fact present), facts found, right documents found, average time and the cost of
the run. `--rerank=off --rerank=on` runs both and compares them. Reports are
saved under `storage/app/private/chatbot/evaluations`. Scoring is plain string
matching (`EvaluationScorer`: folded spellings, whole numbers), so runs are
comparable. On the host: approval file `chatbot-evaluate.approved` (`rerank=off,on`,
`label=`). Add a question whenever a visitor finds a wrong answer.

**Contextual retrieval.** When an uploaded file or typed text is indexed, each
passage gets one or two sentences saying where it sits (document, article,
subject, "continues clause د"), written by the main model from the document
card and the text around the passage (`ChatbotPassageContextWriter`,
temperature 0, 8 at a time). The context is embedded and word-indexed with the
passage (Anthropic's "contextual embeddings" and "contextual BM25") and shown
to the answering model as "Where it sits". Stored in `chatbot_chunks.context`,
cached a year per passage, so re-indexing pays only for changed passages.
Website pages get none (short, titled). Existing files get contexts by
re-indexing: `chatbot:reindex-documents --write` (approval file
`chatbot-contextualize.approved`, `actor=auto`, `evaluate=yes` to score after).

**Reranking (off by default).** `chatbot.retrieval.rerank` (`CHATBOT_RERANK`)
sends the best 24 fused candidates to the main model, which returns the
passage numbers that answer the question, every part covered, text in force
first; the fused order fills any slot it skipped, and a failure keeps the fused
order. One more call per question (about a second). Switch it on only when
`chatbot:evaluate --rerank=off --rerank=on` shows more answers right.

**Not adopted, and why.** Whole knowledge in the prompt (CAG): too many tokens
per question for the free tier. GraphRAG: better on multi-hop and global
questions, worse on exact-detail lookups, which are most of ours, and it needs
graph storage the host lacks. A larger or Arabic-specific embedding model:
slower search on PHP or a GPU server. Claim-level verification: an extra call
per answer; revisit when the evaluation shows wrong facts the passages do not
support.

## The university at a glance

Retrieval hands the model the few passages closest to a question. A question
about *all* of something ("ما الكليات كافة في الجامعة؟") matches a few faculty
pages, and the answer then named only those. So every answer's instructions
also carry the complete, published lists (`ChatbotDirectoryServiceInterface`):
every faculty and center from the faculties hub and the centers landing (the
data their pages show), each with its Arabic and English name, its page and a
one-line summary, about 600 tokens, read again every ten minutes. The rules say
these lists are official and complete and that a question about all, how many
or which faculties or centers names every one of them. A faculty or center
added or renamed also retires the cached answers (the answer cache key carries
the directory's hash). The lists sit in the instructions, which change only
when the directory does, so OpenAI's prompt caching still covers them.

The directory opens with the **current leadership**: the president, the vice
presidents and every dean, as the leadership page (`/about/leadership`) shows
them, each with the Arabic and English name. Older pages and files still carry
former office-holders' names, mostly as the signature of a circular, policy or
decision (the quality policy, the exam instructions, the 2014–2015 council
decisions), and retrieval finds exactly those for "who is the president?". The
rules say who holds a post now comes from this list, that a name in the sources
is whoever held the post when that text was issued, and that a source's date is
when it was issued, not today. A change on the leadership page reaches the
assistant within ten minutes and retires the cached answers with the rest of
the directory.

## Conversation memory

The assistant follows a whole conversation without storing it on the server
(the "summary buffer" pattern of conversational RAG):

| Layer | What it holds | How it travels |
|---|---|---|
| Recent turns | The last 4 question-and-answer pairs, verbatim (`max_history_turns` 8 messages, at most 1500 characters each and `max_history_total_characters` 6000 together, newest kept first) | The browser sends them back; each pair is HMAC-signed (`ConversationSigner`), so a forged or edited turn is dropped with its partner |
| Conversation notes | The model's own notes on everything said (what the visitor is looking for, their situation, what was answered, open questions), at most ~80 words, no personal data | Written with every answer as the `memory` field of the structured reply; sealed with Laravel's encrypter (`ConversationMemory`: APP_KEY, bound to its purpose and language, expires after `memory_hours` 24) and kept by the browser in `sessionStorage` |

- The notes reach the model inside the question turn as `<conversation_notes>`,
  fenced as data like the sources. The rules say earlier answers may be referred
  to, but every new fact still comes from the latest sources.
- The **search rewrite** sees the notes and the latest two pairs
  (`SearchRewrite::input`), so "and the fees?" or "what about the second one?"
  becomes a whole search ("رسوم كلية الصيدلة / Pharmacy tuition") before
  retrieval. A first question is rewritten exactly as before (cached a week).
- Notes that come back empty, or carrying code or instruction text
  (`ChatbotGuard::inspectAnswer`), keep the previous notes. Phones, ID numbers
  and emails are removed before sealing (`ConversationMemory::clean`).
- Only a conversation's first question is answered from the shared answer
  cache; a cached answer carries its notes. Refusals return no new notes, so
  the browser keeps the last ones. "New conversation" clears both.

## Voice

Visitors can talk with the host and hear the answers. It is a cascaded
pipeline (speech to text, the normal answer, text to speech), so every guard,
limit, source and the conversation memory apply to spoken questions exactly
as to typed ones; a speech-to-speech model would bypass them.

```text
mic ─ AudioWorklet → PCM 24 kHz ─ WebSocket ─▶ OpenAI Realtime transcription (gpt-live-transcribe)
        key: POST /{locale}/assistant/live-session (60 s, transcription-only session)
        words appear while the visitor speaks; 0.9 s of silence commits the turn,
        the final text arrives ~0.75 s later
    └ MediaRecorder (Opus/WebM, AAC/MP4 on Safari) records alongside; if the live text
      does not come: POST /{locale}/assistant/transcriptions (gpt-transcribe, fallback
      gpt-4o-mini-transcribe)
    ◀─ the text appears as the visitor's message
    ─ POST /{locale}/assistant/messages (as typed) ─▶ answer + speech signature
    ─ POST /{locale}/assistant/speech {text, signature, segment, stream} ─▶ ChatbotVoiceService::speak
          SpeechText: Markdown → spoken sentences, cut into growing segments (120, 240, 480, then ≤900 chars)
          gpt-4o-mini-tts, voice from the settings (default cedar), stream_format audio:
          raw PCM sent on chunk by chunk (X-Accel-Buffering: no); mp3 on slow connections
    ◀─ audio/l16 played while it downloads, every segment on one Web Audio timeline;
       the first two are fetched together, two stay in flight
```

**Measured on the live site (2026-11-07), end of speech to first word:**

| Stage | Before | Now |
|---|---|---|
| Silence before the turn ends | 1.3 s | 0.9 s |
| Question written down | 4.4 s (upload + file transcription) | 0.75 s (live, after the commit) |
| Answer (server: screen ~1.0–1.4, search ~0.5–1.2, model ~1.5–1.8) | 3–15 s, long written answers | 3–4.4 s, short spoken answers |
| First sound of the voice | ~3.1–3.5 s (whole mp3) | ~1.0–1.6 s (first PCM chunk) |
| **Total** | **~12–25 s** | **~6 s** |

Answers carry `Server-Timing` (screen, search, model, total) for these measurements.

- **Live transcription.** The browser streams the microphone straight to
  OpenAI (the visitor's address is seen by OpenAI, as it is for the
  recording upload's content); the CSP `connect-src` admits only
  `wss://api.openai.com`, and only while `chatbot.voice.live_transcription`
  is on. The key this site mints (`ChatbotVoiceService::liveSession`) opens a
  transcription-only session (no instructions, no tools, nothing that can
  answer), expires within 60 s if unused, and follows the same pauses, shares
  (4 a minute, 20 an hour) and daily cap; each session is counted
  (`voice_live_sessions`, estimated at a minute of audio). One connection
  serves a whole voice conversation and audio is sent only while listening.
  The browser authenticates with the subprotocols `realtime` and
  `openai-insecure-api-key.<key>` and sends `session.update` once open.
- **Why not speech-to-speech (Realtime, GPT-Live).** It would answer with
  its own words: the moderation, scope categories, output guard, sources and
  conversation notes would be bypassed, GPT-Live is "trained to paraphrase"
  the facts a backend gives it, Arabic is not among its documented voices,
  and its guardrails need a long-lived server connection this host's few PHP
  workers cannot hold. The chained pipeline, streamed at each stage, keeps
  every rule and is within a few seconds of it.

- **Latency.** Measured on the host (2026-11-07): writing a question down
  takes about 4 s, an answer 5 to 15 s (as typed), and each speech call 3 to
  10 s, much the same whatever its length (2.5 s and 12 s of audio took the
  same time). What keeps a spoken answer quick:
  - **Spoken questions get spoken answers.** The widget sends `voice: true`;
    the question turn is marked spoken and the rules ask for under about 70
    words in two to four plain sentences, the key facts first, with an offer
    to say more (`max_spoken_output_tokens` 600). A long written answer would
    take longer to write, to voice and to listen to. Spoken and typed answers
    are cached apart; the fixed rules are the same, so prompt caching holds.
  - **Screening and rewriting at once.** The moderation check and the search
    rewrite go out together (`OpenAiClient::moderateAndRespond`), saving the
    shorter of the two on every question, typed or spoken.
  - **Growing segments, two in flight.** The first segment is short so the
    voice starts soon; each next one is at most twice the one before, and
    the browser asks for the first two together and keeps two in flight, so
    each is ready before the one before it ends. At most two speech requests
    per visitor run at once, which keeps the host's few PHP workers free.
  - **An honest wait.** Until the first sound plays the bar says "Preparing
    the voice…" and the host keeps his thinking pose; he talks only while
    the voice sounds.

- **Hands-free conversation.** The microphone starts a voice conversation: the
  host listens (pink stand light, head tilted, the meter follows the voice),
  writes the question down, answers aloud (the mouth follows the audio level:
  lip-sync through an AnalyserNode), then listens again. It ends when the
  visitor taps stop or cancel, presses Escape, types, closes the panel, leaves
  the tab, or says nothing for 9 seconds. Tapping the microphone while he
  speaks interrupts him and he listens. Every answer also has a Listen button.
- **Only the assistant's own words are spoken.** Every reply carries
  `speech`, an HMAC of its exact text (`ConversationSigner::signSpeech`); the
  speech endpoint refuses anything else (403), so it cannot be used as a free
  text-to-speech service.
- **The browser's voice is the fallback.** When the server cannot speak
  (voice off, the daily cap, a visitor's share, an outage, or the model's
  shutdown date) the endpoint answers JSON with a non-2xx status and the
  widget reads the answer with `speechSynthesis`, picking the best installed
  voice for the language.
- **OpenAI shuts the `/audio/speech` models down on 2027-01-06**
  (`gpt-4o-mini-tts`, `tts-1`, `tts-1-hd`). Its named replacement,
  `gpt-realtime-2.1-mini`, works only through the Realtime API (raw PCM, about
  48 KB a second). `chatbot.voice.speech.retires_on` switches the server voice
  off on that date by itself (`VoiceModels::speech()`), the admin settings show
  a warning before it and a notice after, and answers are then read by the
  browser. To keep a university voice, configure a new speech model
  (`CHATBOT_SPEECH_MODEL`, `CHATBOT_SPEECH_RETIRES_ON`) before then.
- **Audio is never stored**: the recording goes from PHP's upload temp file
  to OpenAI and is deleted with the request; usage keeps counts only.
- **Silence is not a question**: transcripts that are only what transcription
  models write for noise ("Thank you for watching", "اشتركوا في القناة") come
  back as `empty`.
- **Limits.** Per visitor (session, with the address getting
  `ip_multiplier` shares): 8 recordings a minute and 30 an hour, 40 segments a
  minute and 200 an hour. Site-wide: "Voice requests per day" in the settings
  (recordings plus answers read aloud, default 1500). Route limiter
  `chatbot-voice`: 90 a minute and 3000 a day per IP. A paused visitor cannot
  talk either. Recordings up to 3 MB and 60 seconds.
- **Cost.** About $0.0045 a minute to listen and $0.015 a minute to speak; both
  are in the admin's cost figures (`chatbot_usage_days.voice_*`).
- **Browser support.** Recording needs a secure page, `getUserMedia`,
  MediaRecorder and Web Audio (all current browsers, Safari 14.1+); the
  microphone button is hidden elsewhere. `Permissions-Policy` allows
  `microphone=(self)` (middleware and `public/.htaccess`); the browser still
  asks the visitor first. Playback uses Web Audio, so the CSP needs no
  `media-src`. The voice code (`chatVoice.js`, ~3 KB gzipped) loads on the
  first voice control.

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

- The launcher (host, teaser) is HTML in the layout and styled by
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
- The 3D host (`characterScene.js`, ~140 KB gzipped, built from shaped
  primitives with no model file, lit by a generated studio environment) loads
  only after an interaction with the launcher, and animates only while awake;
  otherwise the drawn host stays. The drawing is one SVG `<g id="spu-host">`
  in the layout, used by the launcher and the panel's avatar.
- The host does what the robot before it did: idle (breathes, sways, blinks,
  pupils and head follow the pointer, a glint crosses the glasses), thinking
  (a hand to his goatee, eyes up, sparks round his head, the stand glows
  amber), talking (mouth and eyebrows move, green stand), happy (squint and
  a hop), and a wave. With reduced motion it stands still and only changes
  pose between states.

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
  suggested questions, extra instructions, site-wide and per-visitor limits,
  and Voice (on/off, the voice, voice requests per day).
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
(output is most of a page's cost). The model is `CHATBOT_OCR_MODEL`, default
`gpt-6.1-terra` (OpenAI's visual-reasoning model, $1 / $5 per 1M tokens,
about one US cent a page). GPT-5.4 mini was tried first on the ministry's
CamScanner decrees and changed their meaning ("على الأكثر" for "على الأقل",
"تغيير القيد" dropped), which is not acceptable for admission rules. When the
key cannot use the reading model (400/403/404), the page is read by the main
model instead. Saved page text records the reading model asked for; when
that setting changes, Re-index reads the pages again.

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

**Document cards.** An uploaded file of at least 2,500 characters gets one
extra passage first, "ملخص الوثيقة ومعلوماتها الأساسية" (`ChatbotDocumentCardWriter`):
what the document is and its key facts (dates, fees, who it is for, how to
apply) quoted from its text by the reading model, written from the first
24,000 and the last 8,000 characters (dates and how to apply sit at the end of
an announcement), with one retry after a rate limit. An announcement's deadline
otherwise sits in a passage that says "this announcement" and never names
it, so a question naming the competition found other passages first and the
model took another document's dates. The card is saved next to the file
(`<file>.card.json`, keyed by the text's hash) and written again only when
the text changes; a failure indexes the file without one. Website pages and
typed text get none. The answer rules also forbid carrying a fact from one
document over to a procedure another document covers.

## Operations

- Migrations `2026_11_03_000001_create_chatbot_tables`,
  `2026_11_06_000001_add_scan_progress_to_chatbot_documents` and
  `2026_11_07_000001_add_voice_usage_to_chatbot_usage_days` are schema-only
  (no approval file needed).
- `CHATBOT_VOICE_ENABLED=false` hides the voice controls whatever the admin says.
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

- Contracts: `app/Contracts/Chatbot/*` (service, voice, fair use, knowledge, settings, usage, OpenAI client)
- Services: `app/Services/Chatbot/*` (`ChatbotScanReader` reads scanned pages)
- Pure helpers: `app/Support/Chatbot/*` (extractor, PDF layout, spreadsheets, table rows, chunker, vectors, keywords, prompt, guard, signer, conversation memory, speech text, voice models)
- Public: `ChatbotController`, `ChatbotTranscriptionController`, `ChatbotSpeechController` and their requests, `resources/views/public/layout/chatbot.blade.php`,
  `resources/js/chatbot/*` (widget, voice, Markdown renderer, state, Three.js host), `resources/css/frontend/chatbot.css`
- Admin: `app/Filament/Pages/ManageChatbot.php`, `resources/views/filament/pages/manage-chatbot.blade.php`, `resources/views/filament/chatbot/*`
- Tests: `tests/Unit/Chatbot/*`, `tests/Feature/Chatbot/*`, `tests/js/chatbot.test.js`
