<?php

namespace App\Support;

/**
 * Metne eklenen video bağlantısı (Faz F3, 2026-09-27 format kararı: "video önce YouTube /
 * Vimeo bağlantısı"). Yazarın girdiği adresten sadece sağlayıcı + video kimliği çıkarılıyor,
 * oynatıcı adresi burada sıfırdan kuruluyor — ham adres hiçbir zaman iframe'e girmiyor.
 * Tanınmayan adres null döner (okuma sayfasında video satırı hiç çıkmaz).
 */
class VideoEmbed
{
    /**
     * @return array{provider: string, id: string, watch: string, embed: string}|null
     */
    public static function parse(?string $url): ?array
    {
        $url = trim((string) $url);
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return null;
        }

        $parts = parse_url($url);
        $host = strtolower(preg_replace('/^(www\.|m\.)/', '', $parts['host'] ?? ''));
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);

        if (in_array($host, ['youtube.com', 'youtube-nocookie.com', 'youtu.be'], true)) {
            $id = match (true) {
                $host === 'youtu.be' => trim($path, '/'),
                $path === '/watch' => $query['v'] ?? '',
                (bool) preg_match('#^/(embed|shorts|live)/([^/]+)#', $path, $match) => $match[2],
                default => '',
            };

            if (! is_string($id) || ! preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
                return null;
            }

            $start = self::seconds($query['t'] ?? $query['start'] ?? null);

            return [
                'provider' => 'youtube',
                'id' => $id,
                'watch' => 'https://www.youtube.com/watch?v='.$id.($start ? '&t='.$start : ''),
                // nocookie: oynatılmadan önce izleme çerezi bırakmıyor.
                'embed' => 'https://www.youtube-nocookie.com/embed/'.$id.'?autoplay=1&rel=0'.($start ? '&start='.$start : ''),
            ];
        }

        if (in_array($host, ['vimeo.com', 'player.vimeo.com'], true)) {
            // vimeo.com/123456789, vimeo.com/123456789/abcdef (liste dışı), player.vimeo.com/video/123456789?h=abcdef
            if (! preg_match('#^/(?:video/)?(\d{6,12})(?:/([A-Za-z0-9]{6,20}))?/?$#', $path, $match)) {
                return null;
            }

            $hash = $match[2] ?? ($query['h'] ?? null);
            $hash = is_string($hash) && preg_match('/^[A-Za-z0-9]{6,20}$/', $hash) ? $hash : null;

            return [
                'provider' => 'vimeo',
                'id' => $match[1],
                'watch' => 'https://vimeo.com/'.$match[1].($hash ? '/'.$hash : ''),
                'embed' => 'https://player.vimeo.com/video/'.$match[1].'?autoplay=1&dnt=1'.($hash ? '&h='.$hash : ''),
            ];
        }

        return null;
    }

    /** YouTube "t" parametresi: 90, 90s, 1m30s, 1h2m3s → saniye. */
    private static function seconds(mixed $value): ?int
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return (int) $value ?: null;
        }

        if (! preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/', $value, $m)) {
            return null;
        }

        $total = ((int) ($m[1] ?? 0)) * 3600 + ((int) ($m[2] ?? 0)) * 60 + (int) ($m[3] ?? 0);

        return $total ?: null;
    }
}
