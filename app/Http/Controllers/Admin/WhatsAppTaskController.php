<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTask;
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

    public function created(): View
    {
        return view('admin.whatsapp-tasks.created', [
            'tasks' => WhatsAppTask::query()
                ->with(['client', 'creditClient', 'creator', 'messages' => function ($query) {
                    $query->orderByDesc('message_at')->orderByDesc('whatsapp_messages.id');
                }])
                ->withCount('messages')
                ->latest()
                ->paginate(20),
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
