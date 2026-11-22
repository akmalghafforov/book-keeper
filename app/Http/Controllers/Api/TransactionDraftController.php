<?php

namespace App\Http\Controllers\Api;

use App\Models\TransactionDraft;
use App\Services\TransactionDraftPosting;
use App\Services\TransactionDraftWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TransactionDraftController extends ApiController
{
    public function __construct(
        private TransactionDraftPosting $posting,
        private TransactionDraftWorkflow $workflow,
    ) {}

    public function store(Request $request): JsonResponse
    {
        return $this->idempotent($request, function () use ($request): JsonResponse {
            Validator::make(['body' => $request->all()], ['body' => ['array:kind,source,fields']])->validate();
            $kind = $request->validate(['kind' => ['required', Rule::in(array_keys(TransactionDraft::KINDS))]])['kind'];
            $input = $request->validate([
                ...$this->posting->sourceRules(),
                ...$this->posting->formatRules($kind),
            ]);

            return $this->workflow->submit($kind, $input['source'], $input['fields'], $request->user()->id);
        });
    }

    public function index(Request $request): array
    {
        $filters = $request->validate([
            'kind' => ['sometimes', Rule::in(array_keys(TransactionDraft::KINDS))],
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'dismissed'])],
            'corrected' => ['sometimes', Rule::in(['true', 'false', '1', '0'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $query = TransactionDraft::query();
        foreach (['kind', 'status'] as $key) {
            if (isset($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }
        if (isset($filters['corrected'])) {
            $query->where('corrected', $request->boolean('corrected'));
        }

        $page = $query->orderByDesc(($filters['status'] ?? null) === 'approved' ? 'updated_at' : 'created_at')
            ->orderByDesc('id')->paginate($filters['per_page'] ?? 25);

        return $this->collection($page, 'transaction-draft', fn (TransactionDraft $draft) => $this->workflow->resource($draft, detail: false));
    }

    public function show(TransactionDraft $draft): JsonResponse
    {
        return $this->workflow->response($draft);
    }
}
