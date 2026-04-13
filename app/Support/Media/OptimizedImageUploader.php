<?php

namespace App\Support\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OptimizedImageUploader
{
    public function upload(UploadedFile $file, string $directory): string
    {
        $directory = trim($directory, '/');
        Storage::disk('public')->makeDirectory($directory);

        // Fallback aman bila GD tidak tersedia.
        if (!extension_loaded('gd')) {
            return $file->store($directory, 'public');
        }

        $realPath = $file->getRealPath();
        if (!$realPath) {
            return $file->store($directory, 'public');
        }

        $imageInfo = @getimagesize($realPath);
        if (!$imageInfo || !isset($imageInfo[2])) {
            return $file->store($directory, 'public');
        }

        $source = match ($imageInfo[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($realPath),
            IMAGETYPE_PNG => @imagecreatefrompng($realPath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($realPath) : false,
            default => false,
        };

        if (!$source) {
            return $file->store($directory, 'public');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            imagedestroy($source);
            return $file->store($directory, 'public');
        }

        $maxSide = 720;
        $scale = min(1, $maxSide / max($sourceWidth, $sourceHeight));
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, true);
        imagesavealpha($target, true);

        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        $relativePath = $directory.'/'.Str::uuid()->toString().'.webp';
        $absolutePath = Storage::disk('public')->path($relativePath);
        $saved = imagewebp($target, $absolutePath, 78);

        imagedestroy($source);
        imagedestroy($target);

        if (!$saved) {
            return $file->store($directory, 'public');
        }

        return $relativePath;
    }
}
