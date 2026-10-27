<?php

use App\Support\ModuleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_news')) {
            ModuleCatalog::sync();

            return;
        }

        // Actualités du site vitrine : un système à part, sans lien avec les publications du fil T.Link.
        Schema::create('site_news', function (Blueprint $table): void {
            $table->id();
            $table->json('title');             // {fr, en, ar}
            $table->json('excerpt')->nullable();
            $table->json('body')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('scope', 50)->nullable()->index();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft'); // draft | published
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });

        $this->importFromFeed();

        // Ajoute le module « Actualités du site » au catalogue (sans toucher aux modules existants).
        ModuleCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('site_news');
    }

    /**
     * Reprend une seule fois les publications déjà autorisées pour la vitrine, pour que le site ne se vide pas.
     * Le contenu est copié : après l'import, plus rien ne relie les actualités au fil.
     */
    private function importFromFeed(): void
    {
        if (! Schema::hasTable('posts') || ! Schema::hasColumn('posts', 'is_site_news')) {
            return;
        }

        $posts = DB::table('posts')
            ->where('is_site_news', true)
            ->where('visibility', 'public')
            ->whereNotNull('published_at')
            ->orderBy('id')
            ->get();

        foreach ($posts as $post) {
            $content = trim((string) $post->content);

            if ($content === '') {
                continue;
            }

            $firstLine = trim((string) (preg_split('/\R/u', $content)[0] ?? ''));
            $title = mb_substr(strip_tags($firstLine) !== '' ? strip_tags($firstLine) : $content, 0, 160);

            DB::table('site_news')->insert([
                'title' => json_encode(['fr' => $title], JSON_UNESCAPED_UNICODE),
                'excerpt' => null,
                'body' => json_encode(['fr' => $content], JSON_UNESCAPED_UNICODE),
                'cover_path' => $this->copyCover((int) $post->id),
                'scope' => $post->scope,
                'author_id' => $post->user_id,
                'status' => 'published',
                'published_at' => $post->published_at,
                'created_at' => $post->created_at ?? now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function copyCover(int $postId): ?string
    {
        if (! Schema::hasTable('post_attachments')) {
            return null;
        }

        $attachments = DB::table('post_attachments')->where('post_id', $postId)->where('mime_type', 'like', 'image/%')->orderBy('id')->get();

        foreach ($attachments as $attachment) {
            $disk = Storage::disk($attachment->disk ?: 'public');

            if (! $disk->exists($attachment->path)) {
                continue;
            }

            $extension = strtolower(pathinfo($attachment->path, PATHINFO_EXTENSION)) ?: 'jpg';
            $target = 'news/covers/'.bin2hex(random_bytes(12)).'.'.$extension;

            try {
                Storage::disk('public')->put($target, $disk->get($attachment->path));
            } catch (\Throwable) {
                continue;
            }

            return $target;
        }

        return null;
    }
};
