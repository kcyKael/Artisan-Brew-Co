<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImagePreparationService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    private const MAX_BYTES = 3145728;
    private const MIN_DIMENSION = 48;
    private const MAX_DIMENSION = 4500;

    public function prepareProductImage(UploadedFile $file, ?string $previousPath = null): string
    {
        return $this->processAndStore($file->getRealPath(), 'products', 640, 480, $previousPath, $file->getSize() ?: 0);
    }

    public function prepareAvatar(UploadedFile $file, ?string $previousPath = null): string
    {
        return $this->processAndStore($file->getRealPath(), 'avatars', 256, 256, $previousPath, $file->getSize() ?: 0);
    }

    public function prepareFromLocalFile(string $sourcePath, string $folder): string
    {
        if (! is_file($sourcePath)) {
            throw new \RuntimeException("Seed image not found: {$sourcePath}");
        }

        $width = $folder === 'avatars' ? 256 : 640;
        $height = $folder === 'avatars' ? 256 : 480;

        return $this->processAndStore($sourcePath, $folder, $width, $height, null, (int) filesize($sourcePath));
    }

    public function deletePreparedImage(?string $relativePath): void
    {
        if (! $relativePath) {
            return;
        }

        if (! preg_match('#^(products|avatars)/[a-zA-Z0-9_-]+\.(jpg|webp)$#', $relativePath)) {
            return;
        }

        $diskPath = $this->diskPath($relativePath);
        Storage::disk(config('pos.media_disk', 'local'))->delete($diskPath);
    }

    public function diskPath(string $relativePath): string
    {
        $root = trim((string) config('pos.media_root', 'media'), '/');

        return $root.'/'.ltrim($relativePath, '/');
    }

    private function processAndStore(
        string|false $realPath,
        string $folder,
        int $targetWidth,
        int $targetHeight,
        ?string $previousPath,
        int $byteSize
    ): string {
        if (! $realPath || ! is_file($realPath)) {
            $this->fail('Uploaded image could not be read.');
        }

        if ($byteSize <= 0 || $byteSize > self::MAX_BYTES) {
            $this->fail('Image file must be under 3 MB.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($realPath);
        if (! is_string($detectedMime) || ! in_array($detectedMime, self::ALLOWED_MIME_TYPES, true)) {
            $this->fail('Only valid JPEG, PNG, or WebP images are permitted.');
        }

        $imageInfo = @getimagesize($realPath);
        if (! is_array($imageInfo) || $imageInfo[0] < self::MIN_DIMENSION || $imageInfo[1] < self::MIN_DIMENSION) {
            $this->fail('Image dimensions must be at least 48×48 pixels.');
        }

        if ($imageInfo[0] > self::MAX_DIMENSION || $imageInfo[1] > self::MAX_DIMENSION) {
            $this->fail('Image dimensions cannot exceed 4500×4500 pixels.');
        }

        $sourceImage = match ($detectedMime) {
            'image/jpeg' => @imagecreatefromjpeg($realPath),
            'image/png' => @imagecreatefrompng($realPath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($realPath) : false,
            default => false,
        };

        if (! $sourceImage instanceof GdImage) {
            $this->fail('The uploaded file is not a decodable image.');
        }

        try {
            if ($detectedMime === 'image/jpeg' && function_exists('exif_read_data')) {
                $sourceImage = $this->normalizeOrientation($sourceImage, $realPath);
            }

            $srcWidth = imagesx($sourceImage);
            $srcHeight = imagesy($sourceImage);
            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

            if (! $canvas instanceof GdImage) {
                $this->fail('Failed to prepare image canvas.');
            }

            try {
                $bg = imagecolorallocate($canvas, 27, 26, 24);
                imagefill($canvas, 0, 0, $bg);

                $targetRatio = $targetWidth / $targetHeight;
                $srcRatio = $srcWidth / $srcHeight;

                if ($srcRatio > $targetRatio) {
                    $cropHeight = $srcHeight;
                    $cropWidth = (int) round($srcHeight * $targetRatio);
                    $srcX = (int) floor(($srcWidth - $cropWidth) / 2);
                    $srcY = 0;
                } else {
                    $cropWidth = $srcWidth;
                    $cropHeight = (int) round($srcWidth / $targetRatio);
                    $srcX = 0;
                    $srcY = (int) floor(($srcHeight - $cropHeight) / 2);
                }

                imagecopyresampled(
                    $canvas,
                    $sourceImage,
                    0,
                    0,
                    $srcX,
                    $srcY,
                    $targetWidth,
                    $targetHeight,
                    max(1, $cropWidth),
                    max(1, $cropHeight)
                );

                ob_start();
                $encoded = imagejpeg($canvas, null, 88);
                $binary = ob_get_clean();

                if (! $encoded || ! is_string($binary) || $binary === '') {
                    $this->fail('Failed to encode prepared image.');
                }

                $filename = Str::uuid()->toString().'.jpg';
                $relativePath = "{$folder}/{$filename}";

                $stored = Storage::disk(config('pos.media_disk', 'local'))
                    ->put($this->diskPath($relativePath), $binary);

                if (! $stored) {
                    $this->fail('Could not save prepared image to storage.');
                }

                if ($previousPath) {
                    $this->deletePreparedImage($previousPath);
                }

                return $relativePath;
            } finally {
                imagedestroy($canvas);
            }
        } finally {
            imagedestroy($sourceImage);
        }
    }

    private function normalizeOrientation(GdImage $image, string $path): GdImage
    {
        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        if ($rotated instanceof GdImage) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'image' => [$message],
            'avatar' => [$message],
        ]);
    }
}
