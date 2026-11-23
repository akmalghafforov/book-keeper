<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransactionDraft;
use App\Models\TransactionDraftImage;
use App\Services\ManualProviderLedgerEntry;
use App\Services\TransactionDraftMissingFields;
use App\Services\TransactionDraftPosting;
use App\Services\TransactionDraftReviewPage;
use App\Services\TransactionDraftWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TransactionDraftController extends Controller
{
    public function __construct(
        private TransactionDraftPosting $posting,
        private TransactionDraftWorkflow $workflow,
    ) {}

    public function index(Request $request, TransactionDraftReviewPage $page): View
    {
        $tabs = ['pending' => __('Pending'), 'approved' => __('Approved'), 'dismissed' => __('Dismissed')];
        $kinds = [
            'distribution' => __('Distribution'),
            'provider_ledger' => __('Provider Ledger'),
            'debt_ledger' => __('Debt Ledger'),
        ];
        $tab = $request->query('tab');
        $kind = $request->query('kind');
        $tab = is_string($tab) && isset($tabs[$tab]) ? $tab : 'pending';
        $kind = is_string($kind) && isset($kinds[$kind]) ? $kind : null;

        return view('admin.transaction-drafts.index', [
            ...$page->forQueue($tab, $kind),
            'tabs' => $tabs,
            'kinds' => $kinds,
            'tab' => $tab,
            'kind' => $kind,
        ]);
    }

    public function show(TransactionDraft $draft): JsonResponse
    {
        return $this->workflow->response($draft);
    }

    public function image(TransactionDraft $draft, string $image): Response
    {
        $attachment = $draft->images()->findOrFail($image);
        abort_unless(in_array($attachment->mime_type, TransactionDraftImage::MIME_TYPES, true), 404);
        $content = base64_decode($attachment->content, true);
        abort_if($content === false || $content === '', 404);

        return response($content, 200, [
            'Content-Type' => $attachment->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }

    public function preview(Request $request, TransactionDraft $draft): JsonResponse
    {
        abort_unless($draft->status === 'pending', 409);

        return response()->json([
            ...$this->workflow->preview($draft, $this->reviewInput($request, $draft)['fields']),
            'revision' => $draft->revision,
        ])
            ->header('ETag', $draft->etag());
    }

    public function update(Request $request, TransactionDraft $draft): JsonResponse
    {
        return $this->workflow->edit($draft, $request->user()->id, $request->header('If-Match'),
            fn () => $this->reviewInput($request, $draft)['fields']);
    }

    public function missingFields(Request $request, TransactionDraft $draft, TransactionDraftMissingFields $completion): JsonResponse
    {
        return $this->workflow->edit($draft, $request->user()->id, $request->header('If-Match'),
            function (TransactionDraft $locked) use ($completion, $request) {
                if ($request->isJson() && ! is_object(json_decode($request->getContent())->fields ?? null)) {
                    throw ValidationException::withMessages(['fields' => __('Supply a fields object.')]);
                }

                return $completion->validate($locked, $request->all());
            });
    }

    public function approve(Request $request, TransactionDraft $draft): JsonResponse
    {
        $key = $request->header('Idempotency-Key');
        if ($key === null || trim($key) === '' || strlen($key) > 255) {
            throw ValidationException::withMessages(['Idempotency-Key' => __('Supply an Idempotency-Key of at most 255 bytes.')]);
        }

        return $this->workflow->approve($draft, $request->user()->id, $request->header('If-Match'), $key, $request->all(),
            fn () => $this->reviewInput($request, $draft));
    }

    public function dismiss(Request $request, TransactionDraft $draft): JsonResponse
    {
        return $this->workflow->dismiss($draft, $request->user()->id, $request->header('If-Match'), fn () => $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'posted_record_id' => ['required_if:reason,already_recorded', 'integer', 'min:1'],
        ]));
    }

    private function reviewInput(Request $request, TransactionDraft $draft): array
    {
        $input = $request->all() + ['fields' => []];
        if (is_array($input['fields']) && is_string($input['fields']['provider_received_at'] ?? null)) {
            $input['fields']['provider_received_at'] = ManualProviderLedgerEntry::normalizeDraftTimestamp($input['fields']['provider_received_at']);
        }
        $validated = Validator::make($input, [
            ...$this->posting->formatRules($draft->kind),
            'duplicate_confirmation' => ['sometimes', 'nullable', 'string', 'size:64'],
        ])->validate();

        return $validated;
    }
}
