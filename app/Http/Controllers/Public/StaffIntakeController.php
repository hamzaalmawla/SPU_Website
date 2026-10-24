<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Staff\StaffIntakeServiceInterface;
use App\DTOs\Staff\StaffMatchSuggestionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\StaffIntakeSubmitRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The link a faculty sends its academic staff: find your profile by name,
 * correct it (or enter a new one), and send it for review.
 */
final class StaffIntakeController extends Controller
{
    public function __construct(
        private readonly StaffIntakeServiceInterface $intake,
    ) {}

    public function show(Request $request, string $locale, string $token): View
    {
        $link = $this->intake->resolveLink($token);
        abort_if($link === null, 404);

        $typed = is_string($request->query('q')) ? trim($request->query('q')) : '';
        if ($typed === '') {
            $this->intake->recordOpened($token, $request->ip(), $request->userAgent());
        }

        return view('public.staff-intake.search', [
            'locale' => $locale,
            'token' => $token,
            'link' => $link,
            'typed' => $typed,
            'suggestions' => $typed !== '' ? $this->intake->suggestions($token, $typed, $locale) : collect(),
        ]);
    }

    public function suggestions(Request $request, string $locale, string $token): JsonResponse
    {
        abort_if($this->intake->resolveLink($token) === null, 404);
        $typed = is_string($request->query('q')) ? $request->query('q') : '';

        return response()->json([
            'items' => $this->intake->suggestions($token, $typed, $locale)
                ->map(fn (StaffMatchSuggestionDTO $suggestion): array => [
                    'url' => route('public.staff-intake.form', ['locale' => $locale, 'token' => $token, 'ref' => $suggestion->ref]),
                    'nameAr' => $suggestion->nameAr,
                    'nameEn' => $suggestion->nameEn,
                    'title' => $suggestion->title,
                    'department' => $suggestion->department,
                    'faculty' => $suggestion->inLinkFaculty ? null : $suggestion->faculty,
                    'photoUrl' => $suggestion->photoUrl,
                ])
                ->values(),
        ]);
    }

    public function form(Request $request, string $locale, string $token): View
    {
        $ref = is_string($request->query('ref')) ? $request->query('ref') : null;
        $name = is_string($request->query('name')) ? $request->query('name') : null;
        $form = $this->intake->form($token, $ref, $name, $locale, $request->ip(), $request->userAgent());
        abort_if($form === null, 404);

        return view('public.staff-intake.form', [
            'locale' => $locale,
            'token' => $token,
            'form' => $form,
            'duplicates' => $request->session()->get('staff_intake_duplicates', []),
        ]);
    }

    public function submit(StaffIntakeSubmitRequest $request, string $locale, string $token): RedirectResponse
    {
        $result = $this->intake->submit($request->toDto($token, $locale));

        if ($result->reference === null) {
            return back()
                ->withInput($request->except(['photo', 'cv', 'confirm_not_duplicate']))
                ->with('staff_intake_duplicates', array_map(fn (StaffMatchSuggestionDTO $candidate): array => [
                    'url' => route('public.staff-intake.form', ['locale' => $locale, 'token' => $token, 'ref' => $candidate->ref]),
                    'name' => $locale === 'ar' ? ($candidate->nameAr ?: $candidate->nameEn) : ($candidate->nameEn ?: $candidate->nameAr),
                    'title' => $candidate->title,
                    'department' => $candidate->department,
                    'faculty' => $candidate->faculty,
                ], $result->duplicateCandidates));
        }

        return redirect()
            ->route('public.staff-intake.sent', ['locale' => $locale, 'token' => $token])
            ->with('staff_intake_reference', $result->reference);
    }

    public function sent(Request $request, string $locale, string $token): View
    {
        return view('public.staff-intake.sent', [
            'locale' => $locale,
            'token' => $token,
            'reference' => $request->session()->get('staff_intake_reference'),
        ]);
    }
}
