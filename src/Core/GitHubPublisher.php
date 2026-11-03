<?php
namespace App\Core;

use App\Models\AppSetting;

/**
 * Publie un correctif validé par un administrateur (voir AiCodeFixer) sur GitHub, toujours sous
 * forme de Pull Request sur une NOUVELLE branche — jamais un push direct sur la branche cible
 * (GITHUB_FIX_BASE_BRANCH, config/config.php) : la validation dans FamilyBoard est une première
 * relecture, pas une autorisation de déploiement. Fusionner la PR reste un acte humain volontaire
 * et distinct, sur GitHub.
 *
 * Utilise un jeton d'accès personnel (PAT) à portée "repo" configuré par l'administrateur système
 * dans /admin — jamais exposé aux familles.
 */
class GitHubPublisher
{
    private const API_BASE = 'https://api.github.com';
    private const TIMEOUT = 30;

    public static function isConfigured(): bool
    {
        return (bool)AppSetting::get('github_pat');
    }

    /**
     * @return array{ok:bool, pr_url?:string, error?:string}
     */
    public static function openFixPr(int $ticketId, string $relativePath, string $newContent, string $diagnosis, string $errorSubject): array
    {
        $token = AppSetting::get('github_pat');
        if (!$token) {
            return ['ok' => false, 'error' => 'Jeton GitHub non configuré (réglages système > GitHub).'];
        }
        $owner = GITHUB_FIX_REPO_OWNER;
        $repo = GITHUB_FIX_REPO_NAME;
        $base = GITHUB_FIX_BASE_BRANCH;
        $branch = 'ai-fix/ticket-' . $ticketId . '-' . time();

        $baseRef = self::request('GET', "/repos/$owner/$repo/git/ref/heads/$base", $token);
        if (!$baseRef['ok']) return ['ok' => false, 'error' => "Impossible de lire la branche de base « $base » : " . $baseRef['error']];
        $baseSha = $baseRef['body']['object']['sha'] ?? null;
        if (!$baseSha) return ['ok' => false, 'error' => "Branche de base « $base » introuvable sur $owner/$repo."];

        $createRef = self::request('POST', "/repos/$owner/$repo/git/refs", $token, [
            'ref' => "refs/heads/$branch",
            'sha' => $baseSha,
        ]);
        if (!$createRef['ok']) return ['ok' => false, 'error' => "Impossible de créer la branche : " . $createRef['error']];

        // Le SHA du blob existant est requis par l'API pour METTRE À JOUR un fichier (absent = création).
        $existing = self::request('GET', "/repos/$owner/$repo/contents/" . self::encodePath($relativePath) . "?ref=$branch", $token);
        $existingSha = $existing['ok'] ? ($existing['body']['sha'] ?? null) : null;

        $putBody = [
            'message' => "Correctif IA : $errorSubject\n\nTicket FamilyBoard #$ticketId.",
            'content' => base64_encode($newContent),
            'branch' => $branch,
        ];
        if ($existingSha) $putBody['sha'] = $existingSha;

        $put = self::request('PUT', "/repos/$owner/$repo/contents/" . self::encodePath($relativePath), $token, $putBody);
        if (!$put['ok']) return ['ok' => false, 'error' => "Impossible d'écrire le fichier sur la branche : " . $put['error']];

        $prBody = "Correctif proposé par l'assistant IA de FamilyBoard pour le ticket de support **#$ticketId**.\n\n"
            . "**Diagnostic de l'IA :**\n" . $diagnosis . "\n\n"
            . "Validé par un administrateur avant publication — relecture humaine recommandée avant fusion.";
        $pr = self::request('POST', "/repos/$owner/$repo/pulls", $token, [
            'title' => "Correctif IA : $errorSubject",
            'head' => $branch,
            'base' => $base,
            'body' => $prBody,
        ]);
        if (!$pr['ok']) return ['ok' => false, 'error' => "Branche et fichier créés, mais l'ouverture de la Pull Request a échoué : " . $pr['error']];

        return ['ok' => true, 'pr_url' => $pr['body']['html_url'] ?? ''];
    }

    /** Vérifie que le jeton permet de lire le dépôt cible ET qu'il a les droits d'écriture
     *  nécessaires (push) — un jeton en lecture seule semblerait "marcher" jusqu'à l'échec, bien
     *  plus tard, de la création de branche. */
    public static function testConnection(): array
    {
        $token = AppSetting::get('github_pat');
        if (!$token) return ['ok' => false, 'error' => 'Jeton GitHub requis.'];

        $res = self::request('GET', '/repos/' . GITHUB_FIX_REPO_OWNER . '/' . GITHUB_FIX_REPO_NAME, $token);
        if (!$res['ok']) return ['ok' => false, 'error' => $res['error']];
        if (empty($res['body']['permissions']['push'])) {
            return ['ok' => false, 'error' => "Le jeton n'a pas les droits d'écriture (push) sur " . GITHUB_FIX_REPO_OWNER . '/' . GITHUB_FIX_REPO_NAME . '.'];
        }
        return ['ok' => true, 'error' => ''];
    }

    private static function request(string $method, string $path, string $token, ?array $body = null): array
    {
        $ch = curl_init(self::API_BASE . $path);
        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: FamilyBoard/1.0',
        ];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'error' => "Connexion à GitHub impossible : $err"];
        }
        $decoded = json_decode($raw, true);
        if ($status < 200 || $status >= 300) {
            $msg = is_array($decoded) ? ($decoded['message'] ?? '') : '';
            return ['ok' => false, 'error' => "HTTP $status" . ($msg ? " : $msg" : '')];
        }
        return ['ok' => true, 'body' => is_array($decoded) ? $decoded : []];
    }

    /** Encode un chemin pour l'URL de l'API Contents sans encoder les "/" séparateurs de dossier. */
    private static function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
