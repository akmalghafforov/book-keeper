<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\DebtLedger;
use App\Models\Distribution;
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

    public function created(WhatsAppTaskDataExtractor $extractor): View
    {
        $tasks = WhatsAppTask::query()
            ->with(['client', 'creditClient', 'creator', 'messages' => function ($query) {
                $query->orderByDesc('message_at')->orderByDesc('whatsapp_messages.id');
            }])
            ->withCount('messages')
            ->latest()
            ->paginate(20);

        $tasks->getCollection()->each(function (WhatsAppTask $task) use ($extractor) {
            $task->extractedData = $extractor->extract($task);
        });

        return view('admin.whatsapp-tasks.created', [
            'tasks' => $tasks,
        ]);
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
}
