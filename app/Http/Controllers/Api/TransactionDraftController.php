<?php

namespace App\Http\Controllers\Api;

use App\Models\TransactionDraft;
use App\Services\TransactionDraftImages;
use App\Services\TransactionDraftPosting;
use App\Services\TransactionDraftWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TransactionDraftController extends ApiController
{
    public function __construct(
        private TransactionDraftPosting $posting,
        private TransactionDraftWorkflow $workflow,
        private TransactionDraftImages $images,
    ) {}

    public function store(Request $request): JsonResponse
    {
        return $this->idempotent($request, function () use ($request): JsonResponse {
            Validator::make(['body' => $request->all()], ['body' => ['array:kind,source,fields,images']])->validate();
            $kind = $request->validate(['kind' => ['required', Rule::in(array_keys(TransactionDraft::KINDS))]])['kind'];
            $input = $request->validate([
                ...$this->posting->sourceRules(),
                ...$this->posting->formatRules($kind),
                ...$this->images->rules(),
            ]);

            $images = $this->images->validate($input['images'] ?? [], $input['source']);

            return $this->workflow->submit($kind, $input['source'], $input['fields'], $request->user()->id, $images);
        });
    }

    public function appendImages(Request $request, TransactionDraft $draft): JsonResponse
    {
        return $this->idempotent($request, function () use ($request, $draft): JsonResponse {
            return $this->workflow->appendImages($draft, $request->user()->id, function (array $source) use ($request): array {
                Validator::make(['body' => $request->all()], ['body' => ['array:images']])->validate();
                $input = $request->validate($this->images->rules(required: true));

                return $this->images->validate($input['images'], $source);
            });
        });
    }

    public function image(TransactionDraft $draft, string $image): Response
    {
        return $this->images->download($draft, $image);
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
