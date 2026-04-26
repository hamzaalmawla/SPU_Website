<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PublicContactRequest;
use Illuminate\Http\JsonResponse;

final class PublicContactController extends Controller
{
    public function __invoke(PublicContactRequest $request, string $locale): JsonResponse
    {
        return response()->json([
            'submitted' => true,
            'locale' => $locale,
            'email' => $request->validated('email'),
        ]);
    }
}
