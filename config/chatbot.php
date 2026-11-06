<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SPU AI assistant (public chat widget + knowledge base)
|--------------------------------------------------------------------------
|
| The assistant answers visitors' questions from the files editors upload in
| the admin (Administration › AI assistant). Retrieval and generation run in
| this application: the files are split into passages, each passage is
| embedded once with OpenAI, and a question is answered from the closest
| passages. Nothing else is deployed; shared cPanel hosting cannot run the
| Python/Qdrant stack the standalone chatbot uses.
|
| Values here are defaults. Everything an editor may change (on/off, model,
| API key, welcome text, limits) is stored by ChatbotSettingsService and
| overrides these, so a change never needs a deploy.
|
*/

return [

    // Master switch from the environment. When false the widget never renders
    // and the endpoint answers "unavailable" whatever the admin setting says.
    'enabled' => (bool) env('CHATBOT_ENABLED', true),

    'openai' => [
        'base_url' => rtrim((string) env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
        // Fallback only: the key saved in the admin (encrypted) wins.
        'api_key' => env('OPENAI_API_KEY'),
        'connect_timeout' => (int) env('CHATBOT_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('CHATBOT_TIMEOUT', 45),
        'embedding_timeout' => (int) env('CHATBOT_EMBEDDING_TIMEOUT', 60),
    ],

    // GPT-5.4 mini: the strongest model in OpenAI's complimentary tier (up to
    // 10M tokens a day free for organizations that share traffic with
    // OpenAI), run with reasoning off for fast answers.
    'default_model' => env('CHATBOT_MODEL', 'gpt-5.4-mini'),

    // Asked when the main model is rate limited, out of quota, unavailable
    // or refuses the request (never on a rejected key). GPT-6 Luna: $0.10 /
    // 1M input, $0.01 cached, $0.50 / 1M output (November 2026).
    'fallback_model' => env('CHATBOT_FALLBACK_MODEL', 'gpt-6-luna'),

    /*
     * Models editors can pick, with USD prices per 1M tokens, used for the
     * cost figures on the admin page. `reasoning` models take a reasoning
     * effort and no temperature. Every listed model supports structured
     * output, which the scope guard relies on. `free_daily` marks models in
     * OpenAI's complimentary daily tier (10M tokens a day across them, for
     * organizations with data sharing on); the prices are what usage beyond
     * that costs, so the admin page's figures are an upper bound for them.
     */
    'models' => [
        'gpt-5.4-mini' => ['label' => 'GPT-5.4 mini (recommended, free daily tier)', 'input' => 0.75, 'cached_input' => 0.075, 'output' => 4.50, 'reasoning' => true, 'free_daily' => true],
        'gpt-6-luna' => ['label' => 'GPT-6 Luna (cheap, newest)', 'input' => 0.10, 'cached_input' => 0.01, 'output' => 0.50, 'reasoning' => true, 'free_daily' => false],
        'gpt-5.4-nano' => ['label' => 'GPT-5.4 nano (free daily tier)', 'input' => 0.20, 'cached_input' => 0.02, 'output' => 1.25, 'reasoning' => true, 'free_daily' => true],
        'gpt-5-mini' => ['label' => 'GPT-5 mini (free daily tier)', 'input' => 0.25, 'cached_input' => 0.025, 'output' => 2.00, 'reasoning' => true, 'free_daily' => true],
        'gpt-5-nano' => ['label' => 'GPT-5 nano (free daily tier, cheapest)', 'input' => 0.05, 'cached_input' => 0.005, 'output' => 0.40, 'reasoning' => true, 'free_daily' => true],
        'gpt-6.1-terra' => ['label' => 'GPT-6.1 Terra (reads scanned pages)', 'input' => 1.00, 'cached_input' => 0.10, 'output' => 5.00, 'reasoning' => true, 'free_daily' => false],
        'gpt-4.1-nano' => ['label' => 'GPT-4.1 nano (free daily tier)', 'input' => 0.10, 'cached_input' => 0.025, 'output' => 0.40, 'reasoning' => false, 'free_daily' => true],
    ],

    // Lowest effort each model accepts: fastest and cheapest. GPT-6 and
    // GPT-5.4+ models take "none"; GPT-5 takes "minimal".
    'reasoning_effort' => [
        'default' => 'minimal',
        'gpt-6-luna' => 'none',
        'gpt-5.4-mini' => 'none',
        'gpt-5.4-nano' => 'none',
        'gpt-6.1-terra' => 'none',
    ],

    /*
     * Misuse protection (see App\Support\Chatbot\ChatbotGuard and
     * Docs/AI_ASSISTANT.md). The assistant only talks about the university:
     * coding, homework, translation, role-play and attempts to change its
     * rules are refused, mostly before any paid call is made.
     */
    'guard' => [
        // OpenAI's moderation endpoint is free; it screens each question.
        'moderation' => (bool) env('CHATBOT_MODERATION', true),
        'moderation_model' => 'omni-moderation-latest',
        // Refused questions cost points: 1 for an innocent off-topic question,
        // 2 for code, rule-bypass, abuse or harmful content. A visitor
        // reaching `strikes` points within the window is paused; a second
        // pause on the same day lasts `repeat_block_hours`. One IP address
        // is paused at `ip_strikes` points.
        'strikes' => 4,
        'ip_strikes' => 16,
        'strike_window_minutes' => 60,
        'block_minutes' => 60,
        'repeat_block_hours' => 24,
        // Hosts answers may link to (besides this site's own).
        'link_hosts' => ['spu.edu.sy'],
    ],

    /*
     * Scanned pages (a PDF of page images, or an uploaded photo) are read by
     * a vision model, one page per call, `concurrency` calls at a time. A web
     * request reads for at most `request_seconds` and the rest continues on
     * the next refresh of the admin list or the scheduler's
     * chatbot:continue-indexing run (every minute, `command_seconds`), so a
     * 60-page scan never hits a request timeout. Each page is read once; its
     * text is kept next to the file and reused when the file is re-indexed.
     */
    'ocr' => [
        // GPT-6.1 Terra, OpenAI's model for visual reasoning: $1 / $5 per 1M
        // tokens, about one US cent a page. GPT-5.4 mini misread Arabic scans
        // in ways that change the meaning ("at most" for "at least"). When
        // the key cannot use it, pages are read with the main model instead.
        // Empty: always the main model.
        'model' => env('CHATBOT_OCR_MODEL', 'gpt-6.1-terra'),
        'concurrency' => (int) env('CHATBOT_OCR_CONCURRENCY', 8),
        'request_seconds' => 40,
        // The admin list's refresh reads one batch; keep it short so the page
        // stays responsive while a scan is being read.
        'poll_seconds' => 15,
        'command_seconds' => 50,
        // Time one batch may take, used to decide whether another fits.
        'batch_seconds' => 45,
        'timeout' => 120,
        'max_output_tokens' => 8000,
        // A page that fails this many times is given up (left empty).
        'max_attempts' => 3,
    ],

    'embedding' => [
        'model' => 'text-embedding-3-small',
        // 512 of the model's 1536 dimensions: a third of the storage and of
        // the scoring time, for a small loss in ranking quality. Changing it
        // requires re-indexing every file (the admin page offers it).
        'dimensions' => 512,
        'price_per_million' => 0.02,
        'batch_size' => 96,
    ],

    'knowledge' => [
        'disk' => 'local',
        'directory' => 'chatbot/documents',
        // PHP's upload_max_filesize on this host is 16M; Livewire's temporary
        // upload rule (config/livewire.php) matches it.
        'max_upload_kb' => 16384,
        'extensions' => ['pdf', 'docx', 'xlsx', 'txt', 'md', 'csv', 'jpg', 'jpeg', 'png', 'webp'],
        'max_documents' => 500,
        'max_chunks_per_document' => 1500,
        'chunk_characters' => 1100,
        'chunk_overlap' => 160,
        // Uploaded files of at least this many characters get a "document
        // card" (ChatbotDocumentCardWriter): one extra passage with what the
        // document is and its key facts, so a deadline is found with the
        // document's name. Written once (saved next to the file), by the
        // reading model; about one US cent a document. 0 turns it off.
        'card_min_characters' => 2500,
        // What the card is written from: the first and the last characters.
        'card_head_characters' => 24000,
        'card_tail_characters' => 8000,
        'card_retry_ms' => 4000,
        'card_model' => env('CHATBOT_CARD_MODEL', ''),
    ],

    /*
     * The website's own published pages as knowledge (chatbot:sync-website,
     * run by every deploy and from the admin). Starts at the section pages and
     * follows links inside the main content `depth` levels, within `allow`
     * and outside `deny`. Pages that list people by name (students, staff
     * lists), news, projects and publications are left out.
     */
    'website' => [
        'enabled' => (bool) env('CHATBOT_WEBSITE_SYNC', true),
        // Defaults to APP_CANONICAL_URL / APP_URL.
        'base_url' => env('CHATBOT_WEBSITE_URL'),
        'seeds' => ['', 'about', 'admissions', 'faculties', 'centers', 'campus-life', 'e-services', 'contact'],
        'depth' => 2,
        'max_pages' => 400,
        'max_characters' => 30000,
        'timeout' => 20,
        'allow' => '#^/(ar|en)(/(about|admissions|faculties|centers|campus-life|e-services|contact|virtual-tour)(/[a-z0-9-]+)*)?$#',
        'deny' => '#/(news|events|research|projects|alumni|valedictorians|honor-list|top-students|graduates|members|staff|profile|search|preview|staff-intake|forms|course|leadership/[a-z0-9-]+)(/|$)#',
    ],

    'retrieval' => [
        // Passages in the visitor's language rank a little higher.
        'same_language_boost' => 0.03,
        // Each question is first rewritten into the documents' wording, in
        // Arabic and English, by a small model call (SearchRewrite; about
        // 300 tokens, cached a week per question), and passages are scored
        // against the question and the rewrite. Empty model: the main one.
        'rewrite' => (bool) env('CHATBOT_SEARCH_REWRITE', true),
        'rewrite_model' => env('CHATBOT_REWRITE_MODEL', ''),
        'rewrite_timeout' => 8,
        // Passages sent to the model with each question.
        'top_k' => 6,
        // Vector candidates re-ranked with keyword overlap before the cut.
        'candidates' => 40,
        // Below this cosine similarity a passage is not considered relevant.
        'min_similarity' => 0.22,
        'keyword_weight' => 0.18,
        'max_passage_characters' => 1400,
    ],

    'conversation' => [
        'max_message_characters' => 1000,
        'max_history_turns' => 6,
        'max_history_characters' => 1500,
        'max_output_tokens' => 1200,
        // Identical first questions are answered from cache until the
        // knowledge base or the settings change.
        'answer_cache_minutes' => 720,
    ],

    'limits' => [
        // Flood guard per IP address in front of everything (route
        // middleware). Generous, because a campus network puts many students
        // behind one address; fair use per visitor is below.
        'per_minute' => (int) env('CHATBOT_RATE_PER_MINUTE', 30),
        'per_day' => (int) env('CHATBOT_RATE_PER_DAY', 600),
        // Site-wide daily cap; editors can change it in the admin.
        'daily_requests' => 3000,
    ],

    /*
     * Fair use per visitor (browser session), checked in ChatbotService so the
     * visitor gets a clear "ask again in N minutes". The hourly and daily
     * numbers are defaults editors can change; one IP address may use
     * `ip_multiplier` times a visitor's share (shared campus networks).
     */
    'fair_use' => [
        'per_minute' => 5,
        'hourly' => 20,
        'daily' => 40,
        'ip_multiplier' => 8,
        // The same question this many times in ten minutes is spam.
        'repeat_limit' => 3,
    ],
];
