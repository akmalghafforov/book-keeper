<?php

namespace App\Services;

use App\Models\TransactionDraft;
use App\Models\TransactionDraftImage;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class TransactionDraftImages
{
    private const IMAGE_BYTES = 5 * 1024 * 1024;

    private const BATCH_BYTES = 10 * 1024 * 1024;

    private const MAX_PIXELS = 16_000_000;

    public function rules(bool $required = false): array
    {
        return [
            'images' => $required ? ['required', 'array', 'list', 'max:20'] : ['sometimes', 'array', 'list', 'max:20'],
            'images.*' => ['required', 'array:content_base64,locator_index'],
            'images.*.content_base64' => ['required', 'string', 'max:'.(4 * intdiv(self::IMAGE_BYTES + 2, 3))],
            'images.*.locator_index' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function validate(array $images, array $source): array
    {
        $total = 0;
        $lastLocator = count($source['additional_locators'] ?? []);
        $attachments = [];

        foreach ($images as $index => $image) {
            $locator = $image['locator_index'] ?? null;
            if ($locator !== null && $locator > $lastLocator) {
                throw ValidationException::withMessages(["images.$index.locator_index" => __('The locator index does not identify a message on this draft.')]);
            }

            $encoded = $image['content_base64'];
            $bytes = base64_decode($encoded, true);
            $field = "images.$index.content_base64";
            if ($bytes === false || base64_encode($bytes) !== $encoded || strlen($bytes) > self::IMAGE_BYTES) {
                throw ValidationException::withMessages([$field => __('Provide valid base64 for an image of at most 5 MiB.')]);
            }

            $total += strlen($bytes);
            if ($total > self::BATCH_BYTES) {
                throw ValidationException::withMessages(['images' => __('Images may total at most 10 MiB.')]);
            }

            $info = @getimagesizefromstring($bytes);
            if (! $info || ! in_array($info['mime'], TransactionDraftImage::MIME_TYPES, true)
                || $info[0] * $info[1] > self::MAX_PIXELS || ! @imagecreatefromstring($bytes)) {
                throw ValidationException::withMessages([$field => __('Provide a valid JPEG or PNG of at most 16 megapixels.')]);
            }

            $attachments[] = ['locator_index' => $locator, 'mime_type' => $info['mime'], 'content' => $encoded];
        }

        return $attachments;
    }

    public function download(TransactionDraft $draft, string $image): Response
    {
        $attachment = $draft->images()->findOrFail($image);
        abort_unless(in_array($attachment->mime_type, TransactionDraftImage::MIME_TYPES, true), 404);
        $content = base64_decode($attachment->content, true);
        abort_if($content === false || $content === '', 404);

        return response($content, 200, [
            'Content-Type' => $attachment->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }
}
