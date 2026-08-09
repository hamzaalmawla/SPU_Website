<?php

declare(strict_types=1);

return [
    'sections' => ['message' => 'Message', 'workflow' => 'Review workflow'],
    'statuses' => ['new' => 'New', 'in_review' => 'In review', 'resolved' => 'Resolved', 'closed' => 'Closed'],
    'actions' => ['mark_unread' => 'Mark unread', 'assign_to_me' => 'Assign to me', 'internal_notes' => 'Internal notes', 'start_review' => 'Start review', 'resolve' => 'Resolve', 'close' => 'Close'],
    'notifications' => ['marked_unread' => 'Message marked unread', 'assigned' => 'Message assigned', 'notes_saved' => 'Internal notes saved', 'transitioned' => 'Message status updated', 'failed' => 'Message was not updated'],
    'fields' => ['reference' => 'Reference number', 'assigned_to' => 'Assigned reviewer', 'internal_notes' => 'Internal notes', 'status' => 'Status', 'submitted' => 'Submitted', 'email_delivery' => 'Email delivery'],
    'values' => ['unassigned' => 'Unassigned', 'none' => 'None'],
];
