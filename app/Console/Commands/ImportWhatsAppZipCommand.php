<?php

namespace App\Console\Commands;

use App\Services\WhatsAppZipMessageImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportWhatsAppZipCommand extends Command
{
    protected $signature = 'whatsapp:import-zip
        {path=/home/aghafforov/Downloads/Чат WhatsApp с контактом Таксимот.zip : Path to the WhatsApp ZIP export}
        {--dry-run : Parse the ZIP and report the message count without writing rows}';

    protected $description = 'Import messages from a WhatsApp ZIP export into whatsapp_messages.';

    public function handle(WhatsAppZipMessageImporter $importer): int
    {
        $path = (string) $this->argument('path');

        try {
            if ($this->option('dry-run')) {
                $parsed = $importer->parse($path);

                $this->info("Parsed {$parsed['messages']->count()} messages from {$parsed['text_file']}.");

                return self::SUCCESS;
            }

            $result = $importer->import($path);
        } catch (Throwable $exception) {
            if (app()->environment('testing')) {
                throw $exception;
            }

            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Imported {$result['total']} messages from {$result['text_file']}.");
        $this->line("Created: {$result['created']}");
        $this->line("Skipped: {$result['skipped']}");

        return self::SUCCESS;
    }
}
