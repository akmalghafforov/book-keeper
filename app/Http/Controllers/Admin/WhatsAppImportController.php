<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppZipMessageImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class WhatsAppImportController extends Controller
{
    public function index(): View
    {
        return view('admin.whatsapp-imports.index', [
            'messageCount' => WhatsAppMessage::query()->count(),
            'latestMessageAt' => WhatsAppMessage::query()->max('message_at'),
        ]);
    }

    public function store(Request $request, WhatsAppZipMessageImporter $importer): RedirectResponse
    {
        $validated = $request->validate([
            'zip_file' => ['required', 'file', 'extensions:zip', 'max:51200'],
        ]);

        $file = $validated['zip_file'];

        try {
            $result = $importer->import($file->getRealPath(), $file->getClientOriginalName());
        } catch (Throwable $exception) {
            return redirect()
                ->route('admin.whatsapp-imports.index')
                ->withInput()
                ->withErrors(['zip_file' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.whatsapp-imports.index')
            ->with('import_result', $result)
            ->with('success', __('WhatsApp messages imported successfully.'));
    }
}
