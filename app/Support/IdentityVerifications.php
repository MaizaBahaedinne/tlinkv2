<?php

namespace App\Support;

use App\Models\AppNotification;
use App\Models\IdentityVerification;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Vérification d'identité : les pièces sont chiffrées avant d'être écrites sur le disque privé (jamais le disque public),
 * ne sont lisibles que par un administrateur habilité, et sont supprimées dès que la décision est prise.
 */
class IdentityVerifications
{
    private const DISK = 'local';

    /** @param array{front: UploadedFile, back: UploadedFile, selfie: UploadedFile} $files */
    public static function submit(User $user, array $files): IdentityVerification
    {
        $paths = [];

        foreach (IdentityVerification::DOCUMENTS as $key => $column) {
            $paths[$column] = self::store($user, $files[$key]);
        }

        return IdentityVerification::query()->create(['user_id' => $user->id, 'status' => 'pending'] + $paths);
    }

    public static function latestFor(User $user): ?IdentityVerification
    {
        return IdentityVerification::query()->where('user_id', $user->id)->latest('id')->first();
    }

    public static function isVerified(User $user): bool
    {
        return $user->profile?->identity_verified_at !== null;
    }

    /** Contenu déchiffré d'une pièce : [octets, type MIME image] ou null. */
    public static function read(IdentityVerification $verification, string $key): ?array
    {
        $column = IdentityVerification::DOCUMENTS[$key] ?? null;
        $path = $column ? $verification->{$column} : null;

        if (! $path || ! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        try {
            $bytes = Crypt::decryptString((string) Storage::disk(self::DISK)->get($path));
        } catch (\Throwable) {
            return null;
        }

        $raw = base64_decode($bytes, true);

        if ($raw === false) {
            return null;
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($raw);

        return [$raw, in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) ? $mime : 'application/octet-stream'];
    }

    public static function approve(IdentityVerification $verification, User $reviewer): void
    {
        DB::transaction(function () use ($verification, $reviewer): void {
            $verification->update(['status' => 'approved', 'rejection_reason' => null, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now()]);

            Profile::query()->firstOrCreate(['user_id' => $verification->user_id])
                ->forceFill(['identity_verified_at' => now()])->save();
        });

        self::purge($verification);

        AppNotification::query()->create([
            'user_id' => $verification->user_id,
            'type' => 'identity.approved',
            'title' => 'Profil vérifié',
            'body' => 'Votre identité a été validée : le badge « Profil vérifié » s’affiche à côté de votre nom.',
            'data' => ['identity_verification_id' => $verification->id],
        ]);
    }

    public static function reject(IdentityVerification $verification, User $reviewer, string $reason): void
    {
        $verification->update(['status' => 'rejected', 'rejection_reason' => $reason, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now()]);

        self::purge($verification);

        AppNotification::query()->create([
            'user_id' => $verification->user_id,
            'type' => 'identity.rejected',
            'title' => 'Vérification du profil refusée',
            'body' => 'Motif : '.$reason.' Vous pouvez envoyer de nouvelles photos depuis votre profil.',
            'data' => ['identity_verification_id' => $verification->id],
        ]);
    }

    /** Supprime les pièces du disque et les références en base. */
    public static function purge(IdentityVerification $verification): void
    {
        foreach (IdentityVerification::DOCUMENTS as $column) {
            if ($verification->{$column}) {
                Storage::disk(self::DISK)->delete($verification->{$column});
            }
        }

        $verification->update(['id_front_path' => null, 'id_back_path' => null, 'selfie_path' => null]);
    }

    private static function store(User $user, UploadedFile $file): string
    {
        $path = 'identity/'.$user->id.'/'.Str::random(40).'.enc';
        Storage::disk(self::DISK)->put($path, Crypt::encryptString(base64_encode((string) file_get_contents($file->getRealPath()))));

        return $path;
    }
}
