<?php

declare(strict_types=1);

return [
    'form' => [
        'receipt' => [
            'subject' => 'We received your submission',
            'greeting' => 'Hello :name,',
            'body' => 'We received your :form submission and it is now in the SPU review queue.',
            'context' => 'Related item',
            'reference' => 'Reference number',
            'footer' => 'This receipt confirms submission only. A final decision will be communicated after review.',
        ],
        'admin' => [
            'subject' => 'New submission :reference',
            'heading' => 'New public submission',
            'reference' => 'Reference number',
            'form' => 'Form',
            'applicant' => 'Applicant',
            'email' => 'Email',
            'context' => 'Related item',
            'action' => 'Open the admin inbox to review this submission.',
        ],
        'status' => [
            'subject' => 'Your submission status was updated',
            'greeting' => 'Hello :name,',
            'body' => 'The status of your SPU submission has been updated.',
            'reference' => 'Reference number',
            'status' => 'New status',
        ],
    ],
    'contact' => [
        'received' => [
            'subject' => 'We received your message',
            'greeting' => 'Hello :name,',
            'body' => 'Your message was received by the Syrian Private University contact team.',
            'reference' => 'Reference number',
        ],
        'admin' => [
            'subject' => 'New contact message :reference',
            'heading' => 'New contact message',
            'reference' => 'Reference number',
            'applicant' => 'Sender',
            'email' => 'Email',
            'subject' => 'Subject',
        ],
        'status' => [
            'subject' => 'Your contact message status was updated',
            'greeting' => 'Hello :name,',
            'body' => 'The status of your contact message has been updated.',
            'reference' => 'Reference number',
            'status' => 'New status',
        ],
    ],
];
