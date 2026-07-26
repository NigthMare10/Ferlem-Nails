<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class TransferProofStorage
{
    public const DISK = 'payment_proofs';

    public function store(UploadedFile $file, User $user): array
    {
        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException('El formato de la captura no es válido.'),
        };
        $path = now('America/Tegucigalpa')->format('Y/m').'/'.bin2hex(random_bytes(24)).'.'.$extension;
        $stream = fopen($file->getRealPath(), 'rb');

        if ($stream === false) {
            throw new RuntimeException('No se pudo leer la captura seleccionada.');
        }

        try {
            Storage::disk(self::DISK)->writeStream($path, $stream);
        } finally {
            fclose($stream);
        }

        return [
            'proof_path' => $path,
            'proof_original_name' => $file->getClientOriginalName(),
            'proof_mime' => $file->getMimeType(),
            'proof_size' => $file->getSize(),
            'proof_uploaded_by' => $user->getKey(),
            'proof_uploaded_at' => now('UTC'),
        ];
    }

    public function delete(?array $proof): void
    {
        if ($proof && isset($proof['proof_path'])) {
            Storage::disk(self::DISK)->delete($proof['proof_path']);
        }
    }
}
