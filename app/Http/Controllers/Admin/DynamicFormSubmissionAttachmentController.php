<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Contracts\Form\DynamicFormSubmissionReviewServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DynamicFormSubmissionAttachmentController extends Controller
{
    public function __construct(
        private readonly DynamicFormSubmissionReviewServiceInterface $reviewService,
    ) {}

    public function __invoke(Request $request, int $submission, string $field): StreamedResponse
    {
        $actorId = $request->user()?->getAuthIdentifier();
        abort_unless(is_int($actorId) || (is_string($actorId) && ctype_digit($actorId)), 403);

        try {
            $download = $this->reviewService->resolveAttachment($submission, $field, (int) $actorId);
        } catch (\InvalidArgumentException|\RuntimeException) {
            abort(404);
        }

        return Storage::disk('local')->download($download->path, $download->downloadName, [
            'Content-Type' => $download->mimeType,
            'Content-Length' => (string) $download->size,
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
