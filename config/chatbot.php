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

    // gpt-5-nano is the cheapest OpenAI chat model ($0.05 / 1M input,
    // $0.40 / 1M output, November 2026) and answers from supplied passages well.
    'default_model' => env('CHATBOT_MODEL', 'gpt-5-nano'),

    /*
     * Models editors can pick, with USD prices per 1M tokens, used for the
     * cost figures on the admin page. `reasoning` models take a reasoning
     * effort and no temperature.
     */
    'models' => [
        'gpt-5-nano' => ['label' => 'GPT-5 nano (cheapest)', 'input' => 0.05, 'cached_input' => 0.005, 'output' => 0.40, 'reasoning' => true],
        'gpt-4.1-nano' => ['label' => 'GPT-4.1 nano', 'input' => 0.10, 'cached_input' => 0.025, 'output' => 0.40, 'reasoning' => false],
        'gpt-4o-mini' => ['label' => 'GPT-4o mini', 'input' => 0.15, 'cached_input' => 0.075, 'output' => 0.60, 'reasoning' => false],
        'gpt-5.4-nano' => ['label' => 'GPT-5.4 nano', 'input' => 0.20, 'cached_input' => 0.02, 'output' => 1.25, 'reasoning' => true],
        'gpt-5-mini' => ['label' => 'GPT-5 mini (higher quality)', 'input' => 0.25, 'cached_input' => 0.025, 'output' => 2.00, 'reasoning' => true],
    ],

    // Lowest effort the model accepts: fastest and cheapest. gpt-5.4 models
    // use "none" for the same thing.
    'reasoning_effort' => [
        'default' => 'minimal',
        'gpt-5.4-nano' => 'none',
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
        // Livewire's temporary upload limit on this host is 12 MB.
        'max_upload_kb' => 12288,
        'extensions' => ['pdf', 'docx', 'txt', 'md', 'csv'],
        'max_documents' => 500,
        'max_chunks_per_document' => 1500,
        'chunk_characters' => 1100,
        'chunk_overlap' => 160,
    ],

    'retrieval' => [
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
        'per_minute' => (int) env('CHATBOT_RATE_PER_MINUTE', 8),
        'per_day' => (int) env('CHATBOT_RATE_PER_DAY', 80),
        // Site-wide daily cap; editors can change it in the admin.
        'daily_requests' => 3000,
    ],
];
