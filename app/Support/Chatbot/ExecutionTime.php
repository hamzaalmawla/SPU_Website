<?php

declare(strict_types=1);

namespace App\Support\Chatbot;

/**
 * Gives slow work (indexing, website sync) enough time without ever adding a
 * limit where there is none.
 *
 * set_time_limit() replaces the limit for the rest of the process. On the
 * command line the limit is 0 (unlimited), and calling set_time_limit(300)
 * there would kill a long artisan command, a deploy step or the test suite
 * five minutes later.
 */
final class ExecutionTime
{
    public static function atLeast(int $seconds): void
    {
        $current = (int) ini_get('max_execution_time');

        if ($current === 0 || $current >= $seconds) {
            return;
        }

        @set_time_limit($seconds);
    }
}
