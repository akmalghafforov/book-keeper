<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Client;

class TestPdfCommand extends Command
{
    protected $signature = 'test:pdf';
    protected $description = 'Test exact height DOMPDF via pages count with small height';

    public function handle()
    {
        $client = Client::first();
        if (!$client) { return; }
        
        $client->calculated_total_debt = 100;
        $client->recentLedgers = collect();
        for($i=0; $i<60; $i++) {
            $client->recentLedgers->push((object)[
                'created_at' => now(),
                'type' => 'charge',
                'amount' => 10,
                'distribution' => null,
                'notes' => 'Test notes ' . $i
            ]);
        }
        $client->previous_balance = 0;

        $view = view('admin.reports.pdf.single-client-debt', compact('client'));
        $html = $view->render();

        $pdf1 = Pdf::loadHTML($html);
        $pdf1->setPaper([0, 0, 841.89, 50]); 
        $pdf1->render();
        $dompdf = $pdf1->getDomPDF();
        $pages = $dompdf->getCanvas()->get_page_count();
        
        $calcHeight = $pages * 50;
        $this->info("Pages: $pages, Calc Height: $calcHeight");

        $pdf2 = Pdf::loadHTML($html);
        $height2 = max(595.28, $calcHeight); // at least a4 landscape height
        $pdf2->setPaper([0, 0, 841.89, $height2]);
        $outputFile = storage_path('app/public/test_report.pdf');
        $pdf2->save($outputFile);
        $this->info("Saved to $outputFile");
    }
}
