<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use App\Support\AccessScope;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PrivateMediaController extends Controller
{
    public function download(Request $request, MediaFile $mediaFile): Response
    {
        $user = $request->user();

        $canAccess = $mediaFile->uploaded_by === $user->id
            || AccessScope::can($user, 'entity.manage');

        if (! $canAccess) {
            abort(403, 'Acces refuse.');
        }

        $disk = $mediaFile->disk ?: 'local';
        $path = $mediaFile->path;

        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return Storage::disk($disk)->download($path, $mediaFile->original_name ?: basename($path));
    }
}
