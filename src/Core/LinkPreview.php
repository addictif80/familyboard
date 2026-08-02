<?php
namespace App\Core;

/**
 * Vérifie la fiabilité d'un lien soumis au portail familial et en extrait un aperçu
 * (titre + image) — même logique anti-SSRF que App\Models\CalDAVSource::isSafePublicUrl
 * (dupliquée plutôt que partagée : les deux évoluent indépendamment et une régression sur
 * l'une ne doit jamais silencieusement affaiblir l'autre).
 *
 * "Fiabilité" ici signifie concrètement : schéma http(s) uniquement, pas d'adresse réseau
 * interne/privée (SSRF), et une réponse HTTP effective au moment de l'ajout. Ce n'est PAS une
 * vérification anti-phishing/malware (aucune base de données de réputation consultée) — un
 * lien peut passer ce contrôle et être malveillant ; la seule garantie est qu'il ne peut pas
 * servir à faire sonder le réseau interne du serveur.
 */
class LinkPreview
{
    private const MAX_HTML_BYTES  = 500_000;
    private const MAX_IMAGE_BYTES = 3 * 1024 * 1024;

    /** @return array{ok:bool, error?:string, title?:?string, image_path?:?string} */
    public static function fetch(string $url): array
    {
        $ip = self::safePublicIp($url);
        if ($ip === null) {
            return ['ok' => false, 'error' => "Cette adresse n'est pas autorisée (schéma non http/https ou adresse réseau interne)."];
        }

        $html = self::curlGet($url, $ip, self::MAX_HTML_BYTES);
        if ($html === null) {
            return ['ok' => false, 'error' => "Le site n'a pas répondu (indisponible, trop lent, ou erreur HTTP)."];
        }

        $title = self::extractTitle($html) ?? parse_url($url, PHP_URL_HOST) ?? $url;
        $imageUrl = self::extractPreviewImageUrl($html, $url);

        // Chemin web relatif au format déjà utilisé par tous les autres uploads de l'app
        // (BaseController::uploadImage) : '/public/uploads/<fichier>', jamais un chemin absolu
        // disque — c'est cette même convention que templates/routes utilisent pour préfixer BASE_URL.
        $imagePath = $imageUrl ? self::downloadImage($imageUrl) : null;

        return ['ok' => true, 'title' => mb_substr(trim($title), 0, 255), 'image_path' => $imagePath];
    }

    private static function safePublicIp(string $url): ?string
    {
        $parts = parse_url($url);
        if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
            return null;
        }
        $host = $parts['host'];
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : (gethostbyname($host) !== $host ? gethostbyname($host) : null);
        if ($ip === null) return null;
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false ? $ip : null;
    }

    private static function curlGet(string $url, string $pinnedIp, int $maxBytes): ?string
    {
        if (!function_exists('curl_init')) return null;

        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? 'https';
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $host = $parts['host'];

        $buffer = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'FamilyBoard-LinkPreview/1.0',
            CURLOPT_RESOLVE        => ["$host:$port:$pinnedIp"],
            CURLOPT_HTTPHEADER     => ['Accept: text/html'],
            // Coupe le transfert dès qu'on a assez lu — évite de télécharger des pages/fichiers
            // de plusieurs dizaines de Mo juste pour en extraire le <title>.
            CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$buffer, $maxBytes) {
                $buffer .= $chunk;
                return strlen($buffer) >= $maxBytes ? 0 : strlen($chunk);
            },
        ]);
        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_errno($ch);
        curl_close($ch);

        if ($err !== 0 && $err !== CURLE_WRITE_ERROR) return null; // WRITE_ERROR = coupure volontaire ci-dessus
        if ($status < 200 || $status >= 400) return null;
        return $buffer;
    }

    private static function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            return html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        }
        return null;
    }

    private static function extractPreviewImageUrl(string $html, string $pageUrl): ?string
    {
        foreach (['og:image', 'twitter:image'] as $prop) {
            if (preg_match('/<meta[^>]+(?:property|name)=["\']' . preg_quote($prop, '/') . '["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)
                || preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\']' . preg_quote($prop, '/') . '["\']/i', $html, $m)) {
                $imgUrl = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
                // Résout les URLs relatives (rares pour og:image mais existent) par rapport à la page source.
                if (!parse_url($imgUrl, PHP_URL_SCHEME)) {
                    $base = parse_url($pageUrl);
                    $imgUrl = ($base['scheme'] ?? 'https') . '://' . ($base['host'] ?? '') . '/' . ltrim($imgUrl, '/');
                }
                return $imgUrl;
            }
        }
        return null;
    }

    private static function downloadImage(string $imageUrl): ?string
    {
        $ip = self::safePublicIp($imageUrl);
        if ($ip === null) return null;

        $data = self::curlGet($imageUrl, $ip, self::MAX_IMAGE_BYTES);
        if (!$data || strlen($data) > self::MAX_IMAGE_BYTES) return null;

        // Vérifie le contenu réel (pas seulement l'extension/le Content-Type déclaré par le
        // serveur distant) avant de l'écrire sur le disque — même exigence que pour un upload
        // utilisateur classique (voir BaseController::uploadImage).
        $info = @getimagesizefromstring($data);
        $extByMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!$info || !isset($extByMime[$info['mime']])) return null;

        if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);
        $filename = bin2hex(random_bytes(16)) . '.' . $extByMime[$info['mime']];
        if (file_put_contents(UPLOAD_DIR . $filename, $data) === false) return null;

        return '/public/uploads/' . $filename;
    }
}
