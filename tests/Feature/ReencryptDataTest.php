<?php

namespace Tests\Feature;

use App\Models\IdentityVerification;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReencryptDataTest extends TestCase
{
    use RefreshDatabase;

    private Encrypter $old;

    protected function setUp(): void
    {
        parent::setUp();

        $key = Encrypter::generateKey(config('app.cipher'));
        $this->old = new Encrypter($key, config('app.cipher'));
        putenv('OLD_APP_KEY=base64:'.base64_encode($key));
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        putenv('OLD_APP_KEY');

        parent::tearDown();
    }

    public function test_values_and_files_encrypted_with_the_old_key_become_readable(): void
    {
        $user = User::factory()->create();
        Profile::query()->create(['user_id' => $user->id]);
        $verification = IdentityVerification::query()->create(['user_id' => $user->id, 'status' => 'pending']);

        DB::table('profiles')->where('user_id', $user->id)->update(['cin' => $this->old->encryptString('01234567')]);
        DB::table('identity_verifications')->where('id', $verification->id)->update([
            'cin_number' => $this->old->encryptString('76543210'),
            'card_issued_on' => $this->old->encryptString('2020-01-01'),
            'card_issued_place' => $this->old->encryptString('Tunis'),
            'id_front_path' => 'identity/front.bin',
        ]);
        Storage::disk('local')->put('identity/front.bin', $this->old->encryptString(base64_encode('image-bytes')));

        // Avant : illisible avec la clé de cette instance.
        $this->assertThrows(fn () => Profile::query()->where('user_id', $user->id)->firstOrFail()->cin);

        $this->artisan('data:reencrypt', ['--dry-run' => true])->expectsOutputToContain('[simulation] Rechiffrées : 5')->assertSuccessful();
        $this->assertThrows(fn () => Profile::query()->where('user_id', $user->id)->firstOrFail()->cin);

        $this->artisan('data:reencrypt')->expectsOutputToContain('Rechiffrées : 5')->assertSuccessful();

        $this->assertSame('01234567', Profile::query()->where('user_id', $user->id)->firstOrFail()->cin);
        $fresh = IdentityVerification::query()->findOrFail($verification->id);
        $this->assertSame('76543210', $fresh->cin_number);
        $this->assertSame('Tunis', $fresh->card_issued_place);
        $this->assertSame('image-bytes', base64_decode(\Illuminate\Support\Facades\Crypt::decryptString(Storage::disk('local')->get('identity/front.bin'))));

        // Relancer ne casse rien : tout est déjà à jour.
        $this->artisan('data:reencrypt')->expectsOutputToContain('Rechiffrées : 0, déjà à jour : 5')->assertSuccessful();
        $this->assertSame('01234567', Profile::query()->where('user_id', $user->id)->firstOrFail()->cin);
    }

    public function test_unreadable_values_fail_the_command_and_a_missing_key_is_refused(): void
    {
        $user = User::factory()->create();
        Profile::query()->create(['user_id' => $user->id]);
        DB::table('profiles')->where('user_id', $user->id)->update(['cin' => 'pas-une-valeur-chiffree']);

        $this->artisan('data:reencrypt')->expectsOutputToContain('illisibles : 1')->assertFailed();

        putenv('OLD_APP_KEY');
        $this->artisan('data:reencrypt')->expectsOutputToContain('OLD_APP_KEY absente')->assertFailed();
    }
}
