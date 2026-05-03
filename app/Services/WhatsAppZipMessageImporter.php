<?php

namespace App\Services;

use App\Models\WhatsAppMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class WhatsAppZipMessageImporter
{
    /**
     * @return array{created:int, skipped:int, total:int, text_file:string}
     */
    public function import(string $zipPath, ?string $sourceArchive = null): array
    {
        $parsed = $this->parse($zipPath, $sourceArchive);
        $created = 0;
        $skipped = 0;
        $now = now();

        DB::transaction(function () use ($parsed, $now, &$created, &$skipped): void {
            foreach ($parsed['messages'] as $message) {
                $legacyImportHash = $message['legacy_import_hash'];
                unset($message['legacy_import_hash']);

                if (
                    WhatsAppMessage::query()
                        ->whereIn('import_hash', [$message['import_hash'], $legacyImportHash])
                        ->exists()
                ) {
                    $skipped++;

                    continue;
                }

                WhatsAppMessage::query()->create([
                    ...$message,
                    'imported_at' => $now,
                ]);

                $created++;
            }
        });

        return [
            'created' => $created,
            'skipped' => $skipped,
            'total' => $parsed['messages']->count(),
            'text_file' => $parsed['text_file'],
        ];
    }

    /**
     * @return array{messages:Collection<int, array<string, mixed>>, text_file:string}
     */
    public function parse(string $zipPath, ?string $sourceArchive = null): array
    {
        if (! is_file($zipPath)) {
            throw new RuntimeException("WhatsApp ZIP file does not exist: {$zipPath}");
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Unable to open WhatsApp ZIP file: {$zipPath}");
        }

        try {
            $sourceArchiveName = $sourceArchive ?? basename($zipPath);
            $textFileIndex = $this->findTextFileIndex($zip);
            $textFileName = (string) $zip->getNameIndex($textFileIndex);
            $contents = $zip->getFromIndex($textFileIndex);

            if ($contents === false) {
                throw new RuntimeException("Unable to read WhatsApp text export from ZIP: {$zipPath}");
            }

            $messages = $this->parseText($contents, $sourceArchiveName, $textFileName);
            $this->extractAttachments($zip, $messages, $sourceArchiveName);
        } finally {
            $zip->close();
        }

        return [
            'messages' => $messages,
            'text_file' => $textFileName,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function parseText(string $contents, string $sourceArchive, string $sourceTextFile): Collection
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $lines = preg_split('/\R/u', $contents) ?: [];
        $messages = collect();
        $current = null;

        foreach ($lines as $index => $line) {
            $sourceLine = $index + 1;

            if (preg_match('/^(\d{2}\.\d{2}\.\d{4}), (\d{2}:\d{2}) - (.*)$/u', $line, $matches) === 1) {
                if ($current !== null) {
                    $messages->push($this->normalizeMessage($current, $sourceArchive, $sourceTextFile, $messages->count() + 1));
                }

                $current = [
                    'message_at' => CarbonImmutable::createFromFormat('d.m.Y, H:i', "{$matches[1]}, {$matches[2]}", config('app.timezone')),
                    'content' => $matches[3],
                    'source_line' => $sourceLine,
                ];

                continue;
            }

            if ($current !== null) {
                $current['content'] .= "\n".$line;
            }
        }

        if ($current !== null) {
            $messages->push($this->normalizeMessage($current, $sourceArchive, $sourceTextFile, $messages->count() + 1));
        }

        return $messages;
    }

    private function findTextFileIndex(ZipArchive $zip): int
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if (str_ends_with(mb_strtolower($name), '.txt')) {
                return $index;
            }
        }

        throw new RuntimeException('WhatsApp ZIP file does not contain a text export.');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $messages
     */
    private function extractAttachments(ZipArchive $zip, Collection $messages, string $sourceArchive): void
    {
        $attachmentFilenames = $messages
            ->pluck('attachment_filename')
            ->filter(fn ($filename) => is_string($filename) && $filename !== '')
            ->unique()
            ->values();

        if ($attachmentFilenames->isEmpty()) {
            return;
        }

        $zipFileIndexesByBasename = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            $basename = basename(str_replace('\\', '/', $name));

            if ($basename !== '') {
                $zipFileIndexesByBasename[$basename] = $index;
            }
        }

        foreach ($attachmentFilenames as $attachmentFilename) {
            $zipFileIndex = $zipFileIndexesByBasename[$attachmentFilename] ?? null;

            if ($zipFileIndex === null) {
                continue;
            }

            $contents = $zip->getFromIndex($zipFileIndex);

            if ($contents === false) {
                continue;
            }

            Storage::disk('public')->put(
                WhatsAppMessage::attachmentStoragePathFor($sourceArchive, $attachmentFilename),
                $contents,
            );
        }
    }

    /**
     * @param  array{message_at:CarbonImmutable, content:string, source_line:int}  $message
     * @return array<string, mixed>
     */
    private function normalizeMessage(array $message, string $sourceArchive, string $sourceTextFile, int $messageIndex): array
    {
        $content = trim($message['content']);
        $sender = null;
        $body = $content;
        $isSystem = true;

        if (preg_match('/^([^:\n]+): ?(.*)$/us', $content, $matches) === 1) {
            $sender = $this->cleanInvisibleCharacters($matches[1]);
            $body = $matches[2];
            $isSystem = false;
        }

        $body = $this->cleanInvisibleCharacters($body);
        $attachmentFilename = $this->attachmentFilename($body);
        $hashParts = $this->hashParts($message, $messageIndex, $sender, $body);
        $legacyHashParts = [
            $sourceArchive,
            $sourceTextFile,
            ...$hashParts,
        ];

        return [
            'message_at' => $message['message_at'],
            'sender' => $sender,
            'body' => $body,
            'attachment_filename' => $attachmentFilename,
            'is_system' => $isSystem,
            'source_archive' => $sourceArchive,
            'source_text_file' => $sourceTextFile,
            'source_line' => $message['source_line'],
            'import_hash' => hash('sha256', json_encode($hashParts, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'legacy_import_hash' => hash('sha256', json_encode($legacyHashParts, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
        ];
    }

    /**
     * @param  array{message_at:CarbonImmutable, content:string, source_line:int}  $message
     * @return array<int, mixed>
     */
    private function hashParts(array $message, int $messageIndex, ?string $sender, string $body): array
    {
        return [
            $messageIndex,
            $message['source_line'],
            $message['message_at']->toDateTimeString(),
            $sender,
            $body,
        ];
    }

    private function cleanInvisibleCharacters(string $value): string
    {
        return trim(str_replace(["\u{200E}", "\u{200F}"], '', $value));
    }

    private function attachmentFilename(string $body): ?string
    {
        if (preg_match('/^([^\r\n]+?) \((?:файл добавлен|file attached)\)/ui', $body, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
