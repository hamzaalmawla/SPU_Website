<?php

declare(strict_types=1);

return [
    'defaults' => [
        'name' => 'SPU Assistant',
        'welcome' => "Hello! I'm the virtual assistant of the Syrian Private University. Ask me about admission, faculties, tuition, student services or campus life.",
        'suggestions' => [
            'What are the admission requirements?',
            'Which faculties does the university have?',
            'How much are the tuition fees?',
            'How can I contact the university?',
        ],
    ],
    'replies' => [
        'limited' => 'You have asked many questions in a short time. You can ask again in about :minutes minutes.',
        'refused' => 'I can only help with questions about the Syrian Private University: admission, faculties, fees, student services and campus life. What would you like to know?',
        'blocked' => 'The assistant cannot take more questions from you right now. Please try again later.',
        'unavailable' => 'The assistant is not available right now.',
        'daily_limit' => 'The assistant has answered as many questions as it can today. Please try again tomorrow.',
        'busy' => 'The assistant is busy right now. Please try again in a minute.',
        'error' => 'Sorry, something went wrong while preparing the answer. Please try again.',
        'contact' => 'You can also reach the university at :contact.',
    ],
    'widget' => [
        'open' => 'Ask the university assistant',
        'teaser' => 'Hi! Have a question? Ask me.',
        'dismiss_teaser' => 'Hide',
        'close' => 'Close the assistant',
        'new_chat' => 'Start a new conversation',
        'status' => 'Answers from official university documents',
        'placeholder' => 'Type your question…',
        'send' => 'Send',
        'label' => 'Your question',
        'thinking' => 'Preparing the answer…',
        'sources' => 'Sources',
        'page' => 'p.',
        'copy' => 'Copy the answer',
        'copied' => 'Copied',
        'you' => 'You',
        'disclaimer' => 'AI answers can contain mistakes; check important details with the university. Please do not share personal information.',
        'too_long' => 'Please keep your question under :max characters.',
        'rate_limited' => 'You are sending messages too quickly. Please wait a moment and try again.',
        'offline' => 'The answer could not be fetched. Check your connection and try again.',
        'expired' => 'This page has expired. Reload it to keep chatting.',
        'suggestions' => 'Suggested questions',
    ],
];
