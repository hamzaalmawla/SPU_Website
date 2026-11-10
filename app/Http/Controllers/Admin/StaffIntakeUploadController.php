<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Contracts\Staff\StaffIntakeReviewServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StaffIntakeUploadController extends Controller
{
    public function __construct(
        private readonly StaffIntakeReviewServiceInterface $reviewService,
    ) {}

    public function __invoke(Request $request, int $staffIntakeRequest, string $kind): StreamedResponse
    {
        $actorId = $request->user()?->getAuthIdentifier();
        abort_unless(is_int($actorId) || (is_string($actorId) && ctype_digit($actorId)), 403);

        try {
            $upload = $this->reviewService->upload($staffIntakeRequest, $kind, (int) $actorId);
        } catch (AuthorizationException) {
            abort(403);
        } catch (ModelNotFoundException|\RuntimeException) {
            abort(404);
        }

        return Storage::disk($upload->disk)->download($upload->path, $upload->downloadName, [
            'Content-Type' => $upload->mimeType,
            'Content-Length' => (string) $upload->size,
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
