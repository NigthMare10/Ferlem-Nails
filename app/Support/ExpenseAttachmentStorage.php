<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class ExpenseAttachmentStorage
{
    public const DISK = 'expense_attachments';

    public function store(UploadedFile $file, User $user): array
    {
        $mime = $file->getMimeType();
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => throw new RuntimeException('El formato del comprobante no es válido.'),
        };
        $path = now(ReportPeriod::TIMEZONE)->format('Y/m').'/'.bin2hex(random_bytes(24)).'.'.$extension;
        $stream = fopen($file->getRealPath(), 'rb');

        if ($stream === false) {
            throw new RuntimeException('No se pudo leer el comprobante seleccionado.');
        }

        try {
            Storage::disk(self::DISK)->writeStream($path, $stream);
        } finally {
            fclose($stream);
        }

        $originalName = preg_replace('/[\x00-\x1F\x7F]+/u', '', basename($file->getClientOriginalName())) ?: 'comprobante';

        return [
            'attachment_path' => $path,
            'attachment_original_name' => mb_substr($originalName, 0, 200),
            'attachment_mime' => $mime,
            'attachment_size' => $file->getSize(),
            'attachment_uploaded_by' => $user->getKey(),
            'attachment_uploaded_at' => now('UTC'),
        ];
    }

    public function delete(?array $attachment): void
    {
        if ($attachment && isset($attachment['attachment_path'])) {
            Storage::disk(self::DISK)->delete($attachment['attachment_path']);
        }
    }
}
