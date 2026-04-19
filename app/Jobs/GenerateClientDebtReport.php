<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\GeneratedReport;
use App\Services\ClientDebtReportDataBuilder;
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

    private const MEASURE_PAGE_HEIGHT = 10;

    private const MAX_HEIGHT_ATTEMPTS = 6;

    private const HEIGHT_PRECISION = 1.0;

    public $timeout = 600; // Increase timeout to 10 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(public GeneratedReport $report) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        \Log::info('Starting report generation for report ID: '.$this->report->id);

        $reportWidth = 420;

        if (isset($this->report->parameters['locale'])) {
            app()->setLocale($this->report->parameters['locale']);
        }

        $clientId = $this->report->parameters['client_id'] ?? null;
        $lastIncludedLedgerId = null;

        if ($clientId) {
            $client = app(ClientDebtReportDataBuilder::class)->build($this->report);
            $lastIncludedLedgerId = $client->last_included_ledger_id;

            $html = view('admin.reports.pdf.single-client-debt', [
                'client' => $client,
                'report' => $this->report,
            ])->render();
            $pdf = $this->buildSinglePagePdf($html, $reportWidth);
            $fileNamePrefix = 'client-debt-report-'.str_replace(' ', '-', strtolower($client->name)).'-';
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
                ->filter(fn ($c) => $c->calculated_total_debt != 0)
                ->sortBy('name');

            $html = view('admin.reports.pdf.client-debt', [
                'clients' => $clients,
                'report' => $this->report,
            ])->render();
            $pdf = $this->buildSinglePagePdf($html, $reportWidth);
            $fileNamePrefix = 'all-clients-debt-';
        }

        $pdfContent = $pdf->output();
        $fileName = 'reports/'.$fileNamePrefix.now()->timestamp;

        if ($this->report->format === 'jpg' || $this->report->format === 'png') {
            $format = $this->report->format;
            $path = $fileName.'.'.$format;

            $imagick = new Imagick;
            $imagick->setResolution(150, 150); // Improved resolution
            $imagick->readImageBlob($pdfContent);

            foreach ($imagick as $page) {
                $page->setImageFormat($format);
                $page->setImageBackgroundColor('white');
                $page->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
            }

            $imagick->resetIterator();
            $combined = $imagick->appendImages(true);
            $combined->setImageFormat($format);

            if ($format === 'jpg') {
                $combined->setImageCompressionQuality(100);
            }

            $imageContent = $combined->getImageBlob();
            Storage::disk('public')->put($path, $imageContent);

            $imagick->clear();
            $combined->clear();

            $reportUpdates = [
                'status' => 'completed',
                'file_path' => $path,
            ];

            if ($lastIncludedLedgerId !== null) {
                $reportUpdates['last_included_ledger_id'] = $lastIncludedLedgerId;
            }

            $this->report->update($reportUpdates);
        }

        \Log::info('Report generation completed for report ID: '.$this->report->id);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        \Log::error('Report generation failed: '.$exception->getMessage());
        $this->report->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }

    private function buildSinglePagePdf(string $html, float $reportWidth)
    {
        $height = $this->estimateReportHeight($html, $reportWidth);
        $lastFailingHeight = 0.0;

        for ($attempt = 0; $attempt < self::MAX_HEIGHT_ATTEMPTS; $attempt++) {
            $pdf = Pdf::loadHTML($html)->setPaper([0, 0, $reportWidth, $height]);
            $pdf->render();

            if ($pdf->getDomPDF()->getCanvas()->get_page_count() <= 1) {
                $height = $this->refineSinglePageHeight($html, $reportWidth, $lastFailingHeight, $height);

                return Pdf::loadHTML($html)->setPaper([0, 0, $reportWidth, $height]);
            }

            $lastFailingHeight = $height;
            $height *= $pdf->getDomPDF()->getCanvas()->get_page_count();
        }

        return Pdf::loadHTML($html)->setPaper([0, 0, $reportWidth, $height]);
    }

    private function estimateReportHeight(string $html, float $reportWidth): float
    {
        $pages = $this->getPageCount($html, $reportWidth, self::MEASURE_PAGE_HEIGHT);

        return max(self::MEASURE_PAGE_HEIGHT, $pages * self::MEASURE_PAGE_HEIGHT);
    }

    private function refineSinglePageHeight(string $html, float $reportWidth, float $minHeight, float $maxHeight): float
    {
        if ($minHeight <= 0) {
            return $maxHeight;
        }

        while (($maxHeight - $minHeight) > self::HEIGHT_PRECISION) {
            $midpoint = ($minHeight + $maxHeight) / 2;

            if ($this->getPageCount($html, $reportWidth, $midpoint) <= 1) {
                $maxHeight = $midpoint;
            } else {
                $minHeight = $midpoint;
            }
        }

        return ceil($maxHeight);
    }

    private function getPageCount(string $html, float $reportWidth, float $height): int
    {
        $pdf = Pdf::loadHTML($html)->setPaper([0, 0, $reportWidth, $height]);
        $pdf->render();

        return max(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }
}
