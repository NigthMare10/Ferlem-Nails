<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RewardImageStorage
{
    public function store(UploadedFile $file): string
    {
        $source = imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $source) {
            throw ValidationException::withMessages(['image' => 'No se pudo leer la imagen. Usa JPG, PNG o WebP.']);
        }
        $width = min(1200, imagesx($source));
        $scaled = imagescale($source, $width);
        ob_start();
        $ok = imagewebp($scaled, null, 85);
        $bytes = ob_get_clean();
        imagedestroy($source);
        imagedestroy($scaled);
        if (! $ok || ! $bytes) {
            throw ValidationException::withMessages(['image' => 'No se pudo procesar la imagen. Inténtalo de nuevo.']);
        }
        $path = 'reward-images/'.Str::uuid().'.webp';
        if (! Storage::disk('local')->put($path, $bytes)) {
            throw ValidationException::withMessages(['image' => 'No se pudo guardar la imagen. Inténtalo de nuevo.']);
        }

        return $path;
    }
}
