<?php

namespace App\Support;

/**
 * Transforme un lien de vidéo (YouTube, Vimeo, Dailymotion, Facebook, Instagram, TikTok) en une intégration sûre.
 *
 * Seuls les liens https vers des domaines connus sont acceptés ; l'adresse d'intégration est toujours reconstruite
 * à partir de l'identifiant extrait, jamais recopiée telle quelle.
 */
class VideoEmbed
{
    public const MAX_VIDEOS = 10;

    /** @var array<string, string> */
    public const PLATFORMS = [
        'youtube' => 'YouTube',
        'vimeo' => 'Vimeo',
        'dailymotion' => 'Dailymotion',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
    ];

    /**
     * @return array{platform: string, label: string, url: string, embed: ?string, layout: string}|null
     *   layout : "wide" (16/9) ou "tall" (vertical). embed est null quand le lien ne peut qu'être ouvert sur la plateforme.
     */
    public static function parse(?string $input): ?array
    {
        $url = trim((string) $input);

        if ($url === '' || mb_strlen($url) > 500 || preg_match('/[\s<>"\'\\\\]/u', $url)) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^(www|m|mobile|web)\./', '', $host) ?? $host;
        $path = (string) ($parts['path'] ?? '');
        parse_str((string) ($parts['query'] ?? ''), $query);

        $result = match (true) {
            in_array($host, ['youtube.com', 'youtube-nocookie.com', 'music.youtube.com', 'youtu.be'], true) => self::youtube($host, $path, $query),
            $host === 'vimeo.com' || $host === 'player.vimeo.com' => self::vimeo($path),
            in_array($host, ['dailymotion.com', 'dai.ly'], true) => self::dailymotion($host, $path),
            in_array($host, ['facebook.com', 'fb.watch', 'fb.com'], true) => self::facebook($url, $host, $path),
            $host === 'instagram.com' => self::instagram($path),
            $host === 'tiktok.com' || in_array($host, ['vm.tiktok.com', 'vt.tiktok.com'], true) => self::tiktok($host, $path),
            default => null,
        };

        if ($result === null) {
            return null;
        }

        return [
            'platform' => $result['platform'],
            'label' => self::PLATFORMS[$result['platform']],
            'url' => $url,
            'embed' => $result['embed'],
            'layout' => $result['layout'],
        ];
    }

    /**
     * Nettoie la liste saisie dans l'administration : ignore les lignes vides, ne garde que les liens reconnus.
     *
     * @param  array<int|string, mixed>  $raw
     * @return array{videos: array<int, array{url: string, title: string}>, invalid: array<int, string>}
     */
    public static function normalize(array $raw): array
    {
        $videos = [];
        $invalid = [];

        foreach ($raw as $item) {
            $item = is_array($item) ? $item : [];
            $url = trim((string) ($item['url'] ?? ''));

            if ($url === '') {
                continue;
            }

            if (self::parse($url) === null) {
                $invalid[] = $url;

                continue;
            }

            $videos[] = ['url' => $url, 'title' => mb_substr(trim((string) ($item['title'] ?? '')), 0, 150)];
        }

        return ['videos' => array_slice($videos, 0, self::MAX_VIDEOS), 'invalid' => $invalid];
    }

    /** @param array<string, mixed> $query */
    private static function youtube(string $host, string $path, array $query): ?array
    {
        $id = null;

        if ($host === 'youtu.be') {
            $id = ltrim($path, '/');
        } elseif (preg_match('~^/(?:embed|shorts|live|v)/([^/?#]+)~', $path, $m)) {
            $id = $m[1];
        } elseif ($path === '/watch') {
            $id = is_string($query['v'] ?? null) ? $query['v'] : null;
        }

        if ($id === null || ! preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
            return null;
        }

        return [
            'platform' => 'youtube',
            'embed' => 'https://www.youtube-nocookie.com/embed/'.$id,
            'layout' => str_starts_with($path, '/shorts/') ? 'tall' : 'wide',
        ];
    }

    private static function vimeo(string $path): ?array
    {
        if (! preg_match('~^/(?:video/|channels/[^/]+/|groups/[^/]+/videos/)?(\d{6,12})(?:/|$)~', $path, $m)) {
            return null;
        }

        return ['platform' => 'vimeo', 'embed' => 'https://player.vimeo.com/video/'.$m[1], 'layout' => 'wide'];
    }

    private static function dailymotion(string $host, string $path): ?array
    {
        $pattern = $host === 'dai.ly' ? '~^/([A-Za-z0-9]{5,12})$~' : '~^/(?:embed/)?video/([A-Za-z0-9]{5,12})(?:_|$)~';

        if (! preg_match($pattern, $path, $m)) {
            return null;
        }

        return ['platform' => 'dailymotion', 'embed' => 'https://www.dailymotion.com/embed/video/'.$m[1], 'layout' => 'wide'];
    }

    private static function facebook(string $url, string $host, string $path): ?array
    {
        $isVideo = $host === 'fb.watch'
            ? preg_match('~^/[A-Za-z0-9_-]+/?$~', $path) === 1
            : preg_match('~(/videos?/|/reel/|/reels/|/watch/?$|/share/[vr]/)~', $path) === 1;

        if (! $isVideo) {
            return null;
        }

        return [
            'platform' => 'facebook',
            'embed' => 'https://www.facebook.com/plugins/video.php?'.http_build_query(['href' => $url, 'show_text' => 'false'], '', '&', PHP_QUERY_RFC3986),
            'layout' => str_contains($path, '/reel') ? 'tall' : 'wide',
        ];
    }

    private static function instagram(string $path): ?array
    {
        if (! preg_match('~^(?:/[A-Za-z0-9._]+)?/(p|reel|reels|tv)/([A-Za-z0-9_-]{5,20})/?$~', $path, $m)) {
            return null;
        }

        $type = $m[1] === 'reels' ? 'reel' : $m[1];

        return ['platform' => 'instagram', 'embed' => 'https://www.instagram.com/'.$type.'/'.$m[2].'/embed/', 'layout' => 'tall'];
    }

    private static function tiktok(string $host, string $path): ?array
    {
        if (preg_match('~/video/(\d{8,25})/?$~', $path, $m)) {
            return ['platform' => 'tiktok', 'embed' => 'https://www.tiktok.com/embed/v2/'.$m[1], 'layout' => 'tall'];
        }

        // Liens courts (vm.tiktok.com/XXXX, tiktok.com/t/XXXX) : l'identifiant n'est pas connu, on ouvre simplement la vidéo.
        $short = $host === 'tiktok.com' ? '~^/t/[A-Za-z0-9]{6,20}/?$~' : '~^/[A-Za-z0-9]{6,20}/?$~';

        if (preg_match($short, $path)) {
            return ['platform' => 'tiktok', 'embed' => null, 'layout' => 'tall'];
        }

        return null;
    }
}
