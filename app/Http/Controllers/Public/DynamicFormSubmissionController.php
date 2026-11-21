<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Form\DynamicFormSubmissionServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\DynamicFormSubmissionRequest;
use Illuminate\Http\JsonResponse;

final class DynamicFormSubmissionController extends Controller
{
    public function __invoke(
        DynamicFormSubmissionRequest $request,
        DynamicFormSubmissionServiceInterface $submissionService,
        string $locale,
        string $form,
    ): JsonResponse {
        $submissionService->submit($request->toDto($form, $locale));

        return response()->json([
            'message' => $locale === 'ar' ? 'تم إرسال النموذج بنجاح.' : 'Form submitted successfully.',
        ], 201);
    }
}
