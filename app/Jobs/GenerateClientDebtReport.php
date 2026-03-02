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
        $pdfContent = $pdf->output();

        $fileName = 'reports/client-debt-' . now()->timestamp;

        if ($this->report->format === 'pdf') {
            $path = $fileName . '.pdf';
            Storage::disk('public')->put($path, $pdfContent);
            $this->report->update([
                'status' => 'completed',
                'file_path' => $path,
            ]);
        } elseif ($this->report->format === 'jpg') {
            $path = $fileName . '.jpg';

            $imagick = new Imagick();
            // Using 100 DPI for a balance of speed and quality
            $imagick->setResolution(100, 100);
            $imagick->readImageBlob($pdfContent);

            // Convert each page to JPG format and remove transparency efficiently
            foreach ($imagick as $page) {
                $page->setImageFormat('jpg');
                $page->setImageBackgroundColor('white');
                // Remove alpha channel to flatten onto the background color
                $page->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
            }

            $imagick->resetIterator();
            // Combine all pages into one vertically
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
