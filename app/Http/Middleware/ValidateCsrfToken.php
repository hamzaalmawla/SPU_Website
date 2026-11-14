<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken as BaseValidateCsrfToken;

/**
 * Laravel's CSRF check, without the XSRF-TOKEN cookie.
 *
 * Laravel sets XSRF-TOKEN on every response so that JavaScript (axios) can
 * read it, which is why it can never be HttpOnly (ZAP "Cookie No HttpOnly
 * Flag"). Nothing on this site reads it: the public scripts, the chatbot and
 * Livewire in the admin panel all send the token from the csrf-token meta tag
 * or Livewire's own page config, in the X-CSRF-TOKEN header. Forms post the
 * _token field. Dropping the cookie removes a token JavaScript could read and
 * changes nothing that is checked.
 */
final class ValidateCsrfToken extends BaseValidateCsrfToken
{
    /**
     * @var bool
     */
    protected $addHttpCookie = false;
}
