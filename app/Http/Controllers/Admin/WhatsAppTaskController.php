<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\DebtLedger;
use App\Models\Distribution;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTask;
use App\Services\WhatsAppTaskDataExtractor;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WhatsAppTaskController extends Controller
{
    public function index(Request $request): View
    {
        $activeTab = in_array($request->query('tab'), ['without_task', 'with_task', 'deleted'], true)
            ? $request->query('tab')
            : 'without_task';

        $messagesQuery = WhatsAppMessage::query()
            ->where('is_system', false)
            ->withCount('tasks')
            ->orderByDesc('message_at')
            ->orderByDesc('id');

        if ($activeTab === 'without_task') {
            $messagesQuery->doesntHave('tasks');
        }

        if ($activeTab === 'with_task') {
            $messagesQuery->has('tasks');
        }

        if ($activeTab === 'deleted') {
            $messagesQuery->onlyTrashed();
        }

        if ($request->filled('sender')) {
            $messagesQuery->where('sender', 'like', '%' . $request->string('sender')->toString() . '%');
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $messagesQuery->where(function ($query) use ($search) {
                $query->where('body', 'like', '%' . $search . '%')
                    ->orWhere('attachment_filename', 'like', '%' . $search . '%');
            });
        }

        return view('admin.whatsapp-tasks.index', [
            'activeTab' => $activeTab,
            'clients' => Client::query()->orderBy('name')->get(),
            'messages' => $messagesQuery->paginate(20)->withQueryString(),
        ]);
    }

    public function created(Request $request): View
    {
        $statusOptions = ['pending', 'completed', 'cancelled'];

        $tasksQuery = WhatsAppTask::query()
            ->with(['client', 'creditClient', 'creator'])
            ->withCount('messages')
            ->latest();

        if ($request->filled('status') && in_array($request->string('status')->toString(), $statusOptions, true)) {
            $tasksQuery->where('status', $request->string('status')->toString());
        }

        if ($request->filled('task_type') && in_array($request->string('task_type')->toString(), WhatsAppTask::TYPES, true)) {
            $tasksQuery->where('task_type', $request->string('task_type')->toString());
        }

        if ($request->filled('client_id')) {
            $tasksQuery->where('client_id', $request->integer('client_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $tasksQuery->where(function ($query) use ($search) {
                $query->where('notes', 'like', '%' . $search . '%')
                    ->orWhereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('creditClient', function ($clientQuery) use ($search) {
                        $clientQuery->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('messages', function ($messageQuery) use ($search) {
                        $messageQuery->where('body', 'like', '%' . $search . '%')
                            ->orWhere('attachment_filename', 'like', '%' . $search . '%')
                            ->orWhere('sender', 'like', '%' . $search . '%');
                    });
            });
        }

        return view('admin.whatsapp-tasks.created', [
            'clients' => Client::query()->orderBy('name')->get(),
            'statusOptions' => $statusOptions,
            'taskTypeOptions' => WhatsAppTask::TYPES,
            'tasks' => $tasksQuery->paginate(20)->withQueryString(),
        ]);
    }

    public function review(WhatsAppTaskDataExtractor $extractor): View
    {
        $task = $this->nextReviewableTask();

        return $this->reviewView($extractor, $task);
    }

    public function previewReviewedRecord(Request $request, WhatsAppTaskDataExtractor $extractor, WhatsAppTask $whatsappTask): View
    {
        $this->ensureReviewableTask($whatsappTask);

        $validated = $this->validateReviewRecord($request, $whatsappTask);
        $preview = $this->buildReviewPreview($whatsappTask, $validated);

        return $this->reviewView($extractor, $whatsappTask, $preview, $validated);
    }

    public function confirmReviewedRecord(Request $request, WhatsAppTask $whatsappTask): RedirectResponse
    {
        $this->ensureReviewableTask($whatsappTask);

        $validated = $this->validateReviewRecord($request, $whatsappTask);
        $this->createReviewedRecord($whatsappTask, $validated);

        return redirect()
            ->route('admin.whatsapp-tasks.review')
            ->with('success', __('Record created from WhatsApp Task #:id.', ['id' => $whatsappTask->id]));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'task_type' => ['required', Rule::in(WhatsAppTask::TYPES)],
            'message_ids' => ['required', 'array', 'min:1'],
            'message_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('whatsapp_messages', 'id')->whereNull('deleted_at'),
            ],
            'client_id' => ['nullable', 'exists:clients,id'],
            'credit_client_id' => ['nullable', 'exists:clients,id', 'different:client_id'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'task_date' => ['nullable', 'date_format:d/m/Y'],
            'notes' => ['nullable', 'string'],
        ]);

        $task = DB::transaction(function () use ($validated, $request) {
            $task = WhatsAppTask::query()->create([
                'task_type' => $validated['task_type'],
                'status' => 'pending',
                'client_id' => $validated['client_id'] ?? null,
                'credit_client_id' => $validated['task_type'] === WhatsAppTask::TYPE_CLIENT_TRANSFER
                    ? ($validated['credit_client_id'] ?? null)
                    : null,
                'amount' => $validated['amount'] ?? null,
                'task_date' => isset($validated['task_date'])
                    ? Carbon::createFromFormat('d/m/Y', $validated['task_date'])->format('Y-m-d')
                    : null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            $task->messages()->sync($validated['message_ids']);

            return $task;
        });

        return redirect()
            ->route('admin.whatsapp-tasks.index')
            ->with('success', __('WhatsApp task #:id created successfully.', ['id' => $task->id]));
    }

    public function storeExtractedGoodsPieces(Request $request, WhatsAppTask $whatsappTask): RedirectResponse
    {
        abort_unless($whatsappTask->task_type === WhatsAppTask::TYPE_GOODS_PIECES, 404);

        $validated = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'client_id' => ['required', 'exists:clients,id'],
            'product_id' => [
                'required',
                Rule::exists('products', 'id')->where(function ($query) {
                    $query->whereNull('default_unit')
                        ->orWhere('default_unit', '!=', 'per_ton');
                }),
            ],
            'quantity' => ['required', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        $subtotal = $validated['quantity'] * $validated['price'];

        DB::transaction(function () use ($validated, $subtotal, $whatsappTask) {
            Distribution::query()->create([
                'supplier_id' => $validated['supplier_id'] ?? null,
                'client_id' => $validated['client_id'],
                'product_id' => $validated['product_id'],
                'quantity_unit' => 'per_piece',
                'quantity' => $validated['quantity'],
                'price' => $validated['price'],
                'subtotal' => $subtotal,
                'distribution_date' => ($whatsappTask->task_date ?? now())->format('Y-m-d'),
            ]);

            $whatsappTask->update([
                'status' => 'completed',
                'client_id' => $validated['client_id'],
                'amount' => $subtotal,
            ]);
        });

        return redirect()
            ->route('admin.whatsapp-tasks.created')
            ->with('success', __('Extracted WhatsApp task values saved successfully.'));
    }

    public function storeExtractedPayment(Request $request, WhatsAppTask $whatsappTask): RedirectResponse
    {
        abort_unless($whatsappTask->task_type === WhatsAppTask::TYPE_PAYMENT, 404);

        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        DB::transaction(function () use ($validated, $whatsappTask) {
            DebtLedger::query()->create([
                'client_id' => $validated['client_id'],
                'type' => 'payment',
                'amount' => $validated['amount'],
                'transaction_date' => ($whatsappTask->task_date ?? now())->format('Y-m-d'),
                'notes' => 'Auto-generated payment from WhatsApp Task #' . $whatsappTask->id,
            ]);

            $whatsappTask->update([
                'status' => 'completed',
                'client_id' => $validated['client_id'],
                'amount' => $validated['amount'],
            ]);
        });

        return redirect()
            ->route('admin.whatsapp-tasks.created')
            ->with('success', __('Extracted WhatsApp payment saved successfully.'));
    }

    public function storeExtractedClientTransfer(Request $request, WhatsAppTask $whatsappTask): RedirectResponse
    {
        abort_unless($whatsappTask->task_type === WhatsAppTask::TYPE_CLIENT_TRANSFER, 404);

        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'credit_client_id' => ['required', 'exists:clients,id', 'different:client_id'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        $amount = $validated['quantity'] * $validated['price'];
        $transactionDate = ($whatsappTask->task_date ?? now())->format('Y-m-d');

        DB::transaction(function () use ($validated, $amount, $transactionDate, $whatsappTask) {
            DebtLedger::query()->create([
                'client_id' => $validated['client_id'],
                'type' => 'charge',
                'amount' => $amount,
                'transaction_date' => $transactionDate,
                'notes' => 'Auto-generated client transfer charge from WhatsApp Task #' . $whatsappTask->id,
            ]);

            DebtLedger::query()->create([
                'client_id' => $validated['credit_client_id'],
                'type' => 'credit_note',
                'amount' => $amount,
                'transaction_date' => $transactionDate,
                'notes' => 'Auto-generated client transfer credit from WhatsApp Task #' . $whatsappTask->id,
            ]);

            $whatsappTask->update([
                'status' => 'completed',
                'client_id' => $validated['client_id'],
                'credit_client_id' => $validated['credit_client_id'],
                'amount' => $amount,
            ]);
        });

        return redirect()
            ->route('admin.whatsapp-tasks.created')
            ->with('success', __('Extracted WhatsApp client transfer saved successfully.'));
    }

    public function destroyMessages(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message_ids' => ['required', 'array', 'min:1'],
            'message_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('whatsapp_messages', 'id')->whereNull('deleted_at'),
            ],
        ]);

        $deletedCount = WhatsAppMessage::query()
            ->whereIn('id', $validated['message_ids'])
            ->delete();

        return redirect()
            ->route('admin.whatsapp-tasks.index', [
                'tab' => $request->input('tab', 'without_task'),
                'sender' => $request->input('sender'),
                'search' => $request->input('search'),
            ])
            ->with('success', __(':count WhatsApp message(s) removed.', ['count' => $deletedCount]));
    }

    private function reviewView(
        WhatsAppTaskDataExtractor $extractor,
        ?WhatsAppTask $task,
        ?array $preview = null,
        array $formValues = [],
    ): View {
        $reviewTaskType = $formValues['task_type'] ?? $task?->task_type;
        $paymentExtraction = null;

        if ($task) {
            $task->loadMissing(['client', 'creditClient', 'creator', 'messages' => function ($query) {
                $query->orderByDesc('message_at')->orderByDesc('whatsapp_messages.id');
            }]);

            $reviewTask = $task->replicate();
            $reviewTask->setRelation('messages', $task->messages);
            $reviewTask->task_type = $reviewTaskType;
            $extraction = $extractor->extract($reviewTask);

            $paymentTask = $task->replicate();
            $paymentTask->setRelation('messages', $task->messages);
            $paymentTask->task_type = WhatsAppTask::TYPE_PAYMENT;
            $paymentExtraction = $extractor->extract($paymentTask);
        }

        return view('admin.whatsapp-tasks.review', [
            'clients' => Client::query()->orderBy('name')->get(),
            'products' => Product::query()
                ->where(function ($query) {
                    $query->whereNull('default_unit')
                        ->orWhere('default_unit', '!=', 'per_ton');
                })
                ->orderBy('name')
                ->get(),
            'suppliers' => Supplier::query()->orderBy('car_number')->get(),
            'task' => $task,
            'extraction' => $extraction ?? null,
            'preview' => $preview,
            'formValues' => $formValues,
            'reviewTaskType' => $reviewTaskType,
            'reviewableTaskTypes' => $this->reviewableTaskTypes(),
            'paymentAmountCandidates' => data_get($paymentExtraction ?? [], 'amounts', collect()),
            'pendingCount' => $this->reviewableTaskQuery()->count(),
        ]);
    }

    private function nextReviewableTask(): ?WhatsAppTask
    {
        return $this->reviewableTaskQuery()
            ->with(['messages' => function ($query) {
                $query->orderByDesc('message_at')->orderByDesc('whatsapp_messages.id');
            }])
            ->oldest()
            ->oldest('id')
            ->first();
    }

    private function reviewableTaskQuery()
    {
        return WhatsAppTask::query()
            ->where('status', 'pending')
            ->whereIn('task_type', $this->reviewableTaskTypes());
    }

    /**
     * @return array<int, string>
     */
    private function reviewableTaskTypes(): array
    {
        return [
            WhatsAppTask::TYPE_GOODS_PIECES,
            WhatsAppTask::TYPE_PAYMENT,
        ];
    }

    private function ensureReviewableTask(WhatsAppTask $task): void
    {
        abort_unless(
            $task->status === 'pending'
            && in_array($task->task_type, $this->reviewableTaskTypes(), true),
            404
        );
    }

    private function validateReviewRecord(Request $request, WhatsAppTask $task): array
    {
        $validated = $request->validate([
            'task_type' => ['required', Rule::in($this->reviewableTaskTypes())],
            'client_id' => ['required', 'exists:clients,id'],
            'record_date' => ['required', 'date_format:d/m/Y'],
        ]);

        if ($validated['task_type'] === WhatsAppTask::TYPE_GOODS_PIECES) {
            return $request->validate([
                'task_type' => ['required', Rule::in($this->reviewableTaskTypes())],
                'supplier_id' => ['nullable', 'exists:suppliers,id'],
                'client_id' => ['required', 'exists:clients,id'],
                'product_id' => [
                    'required',
                    Rule::exists('products', 'id')->where(function ($query) {
                        $query->whereNull('default_unit')
                            ->orWhere('default_unit', '!=', 'per_ton');
                    }),
                ],
                'quantity' => ['required', 'numeric', 'min:0'],
                'price' => ['required', 'numeric', 'min:0'],
                'record_date' => ['required', 'date_format:d/m/Y'],
            ]);
        }

        return $request->validate([
            'task_type' => ['required', Rule::in($this->reviewableTaskTypes())],
            'client_id' => ['required', 'exists:clients,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'record_date' => ['required', 'date_format:d/m/Y'],
        ]);
    }

    private function buildReviewPreview(WhatsAppTask $task, array $validated): array
    {
        $client = Client::query()->findOrFail($validated['client_id']);
        $recordDate = Carbon::createFromFormat('d/m/Y', $validated['record_date']);
        $sourceMessage = $this->reviewSourceMessage($task);

        if ($validated['task_type'] === WhatsAppTask::TYPE_GOODS_PIECES) {
            $product = Product::query()->findOrFail($validated['product_id']);
            $supplier = isset($validated['supplier_id'])
                ? Supplier::query()->find($validated['supplier_id'])
                : null;
            $subtotal = (float) $validated['quantity'] * (float) $validated['price'];

            return [
                'kind' => 'distribution',
                'title' => __('Distribution preview'),
                'date' => $recordDate->format('Y-m-d'),
                'client' => $client->name,
                'product' => $product->name,
                'supplier' => $supplier
                    ? trim($supplier->car_number . ' ' . ($supplier->car_color ? '(' . $supplier->car_color . ')' : ''))
                    : __('None'),
                'quantity' => $validated['quantity'],
                'price' => $validated['price'],
                'subtotal' => $subtotal,
                'source_message' => $sourceMessage,
                'ledger_effect' => __('A charge debt ledger entry will also be created for this client.'),
            ];
        }

        return [
            'kind' => 'payment',
            'title' => __('Debt payment preview'),
            'date' => $recordDate->format('Y-m-d'),
            'client' => $client->name,
            'amount' => (float) $validated['amount'],
            'source_message' => $sourceMessage,
            'notes' => 'Auto-generated payment from WhatsApp Task #' . $task->id,
        ];
    }

    private function reviewSourceMessage(WhatsAppTask $task): string
    {
        return $task->messages
            ->map(fn ($message) => trim((string) ($message->body ?: $message->attachment_filename)))
            ->filter()
            ->implode("\n");
    }

    private function createReviewedRecord(WhatsAppTask $task, array $validated): void
    {
        $recordDate = Carbon::createFromFormat('d/m/Y', $validated['record_date'])->format('Y-m-d');

        DB::transaction(function () use ($task, $validated, $recordDate) {
            if ($validated['task_type'] === WhatsAppTask::TYPE_GOODS_PIECES) {
                $subtotal = (float) $validated['quantity'] * (float) $validated['price'];

                Distribution::query()->create([
                    'supplier_id' => $validated['supplier_id'] ?? null,
                    'client_id' => $validated['client_id'],
                    'product_id' => $validated['product_id'],
                    'quantity_unit' => 'per_piece',
                    'quantity' => $validated['quantity'],
                    'price' => $validated['price'],
                    'subtotal' => $subtotal,
                    'distribution_date' => $recordDate,
                ]);

                $task->update([
                    'status' => 'completed',
                    'task_type' => $validated['task_type'],
                    'client_id' => $validated['client_id'],
                    'amount' => $subtotal,
                    'task_date' => $recordDate,
                ]);

                return;
            }

            DebtLedger::query()->create([
                'client_id' => $validated['client_id'],
                'type' => 'payment',
                'amount' => $validated['amount'],
                'transaction_date' => $recordDate,
                'notes' => 'Auto-generated payment from WhatsApp Task #' . $task->id,
            ]);

            $task->update([
                'status' => 'completed',
                'task_type' => $validated['task_type'],
                'client_id' => $validated['client_id'],
                'amount' => $validated['amount'],
                'task_date' => $recordDate,
            ]);
        });
    }
}
