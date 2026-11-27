<?php

namespace App\Http\Controllers;

use App\Services\ImagePreparationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    public function show(string $type, string $filename, ImagePreparationService $images): BinaryFileResponse|RedirectResponse
    {
        if (! in_array($type, ['products', 'avatars'], true)) {
            abort(404);
        }

        if ($type === 'avatars' && (! auth()->check() || ! auth()->user()->isActiveAccount())) {
            return redirect()->route('login');
        }

        if (! preg_match('/^[a-zA-Z0-9_-]+\.(jpg|webp)$/', $filename)) {
            abort(404);
        }

        $disk = Storage::disk(config('pos.media_disk', 'local'));
        $diskPath = $images->diskPath("{$type}/{$filename}");

        if (! $disk->exists($diskPath)) {
            abort(404);
        }

        return response()->file($disk->path($diskPath), [
            'Content-Type' => str_ends_with($filename, '.webp') ? 'image/webp' : 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $type === 'products' ? 'public, max-age=86400' : 'private, max-age=86400',
        ]);
    }
}
