<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\GeneratedReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Throwable;

class GenerateClientDebtReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // Increase timeout to 10 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(public GeneratedReport $report)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        \Log::info('Starting report generation for report ID: ' . $this->report->id);

        if (isset($this->report->parameters['locale'])) {
            app()->setLocale($this->report->parameters['locale']);
        }

        $clientId = $this->report->parameters['client_id'] ?? null;

        if ($clientId) {
            $client = Client::query()
                ->with(['debtLedgers' => function ($query) {
                    $query->with(['distribution.product', 'distribution.supplier', 'distribution.shop', 'distribution.client'])->orderBy('created_at', 'desc');
                }])
                ->withSum(['debtLedgers as total_charges' => function ($query) {
                    $query->where('type', 'charge');
                }], 'amount')
                ->withSum(['debtLedgers as total_payments' => function ($query) {
                    $query->where('type', 'payment');
                }], 'amount')
                ->withSum(['debtLedgers as total_credit_notes' => function ($query) {
                    $query->where('type', 'credit_note');
                }], 'amount')
                ->findOrFail($clientId);

            $client->calculated_total_debt = ($client->total_charges ?? 0) - ($client->total_payments ?? 0) - ($client->total_credit_notes ?? 0);

            $allLedgers = $client->debtLedgers;
            $client->recentLedgers = $allLedgers->take(10);
            $remainingLedgers = $allLedgers->slice(10);

            $client->previous_balance = $remainingLedgers->reduce(function ($carry, $item) {
                if ($item->type === 'charge') {
                    return $carry + (float) $item->amount;
                } else {
                    return $carry - (float) $item->amount;
                }
            }, 0);
            
            $pdf = Pdf::loadView('admin.reports.pdf.single-client-debt', compact('client'));
            $fileNamePrefix = 'client-debt-report-' . str_replace(' ', '-', strtolower($client->name)) . '-';
        } else {
            $clients = Client::query()
                ->withSum(['debtLedgers as total_charges' => function ($query) {
                    $query->where('type', 'charge');
                }], 'amount')
                ->withSum(['debtLedgers as total_payments' => function ($query) {
                    $query->where('type', 'payment');
                }], 'amount')
                ->withSum(['debtLedgers as total_credit_notes' => function ($query) {
                    $query->where('type', 'credit_note');
                }], 'amount')
                ->get()
                ->map(function ($client) {
                    $client->calculated_total_debt = ($client->total_charges ?? 0) - ($client->total_payments ?? 0) - ($client->total_credit_notes ?? 0);
                    return $client;
                })
                ->filter(fn($c) => $c->calculated_total_debt != 0)
                ->sortBy('name');

            $pdf = Pdf::loadView('admin.reports.pdf.client-debt', compact('clients'));
            $fileNamePrefix = 'all-clients-debt-';
        }

        $pdfContent = $pdf->output();
        $fileName = 'reports/' . $fileNamePrefix . now()->timestamp;

        if ($this->report->format === 'jpg') {
            $path = $fileName . '.jpg';

            $imagick = new Imagick();
            $imagick->setResolution(100, 100);
            $imagick->readImageBlob($pdfContent);

            foreach ($imagick as $page) {
                $page->setImageFormat('jpg');
                $page->setImageBackgroundColor('white');
                $page->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
            }

            $imagick->resetIterator();
            $combined = $imagick->appendImages(true);
            $combined->setImageFormat('jpg');
            $combined->setImageCompressionQuality(100);

            $jpgContent = $combined->getImageBlob();
            Storage::disk('public')->put($path, $jpgContent);

            $imagick->clear();
            $combined->clear();

            $this->report->update([
                'status' => 'completed',
                'file_path' => $path,
            ]);
        }

        \Log::info('Report generation completed for report ID: ' . $this->report->id);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        \Log::error('Report generation failed: ' . $exception->getMessage());
        $this->report->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}
