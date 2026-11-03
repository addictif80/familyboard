<?php
namespace App\Core;

/**
 * Propose un correctif pour une erreur technique auto-détectée (voir ErrorReporter / SupportTicket)
 * en demandant au fournisseur IA actif (App\Core\AiProvider) une nouvelle version du SEUL fichier
 * en cause — jamais plusieurs fichiers à la fois, pour garder un correctif facile à relire
 * entièrement avant publication (voir GitHubPublisher). Le résultat n'est JAMAIS appliqué
 * automatiquement : seulement proposé, pour validation humaine dans /admin/tickets/:id.
 */
class AiCodeFixer
{
    private const MAX_FILE_BYTES = 200_000; // au-delà, trop gros pour un correctif fiable en un appel
    /** Si le fichier corrigé renvoyé est anormalement plus court que l'original, c'est probablement
     *  une réponse tronquée ou partielle du modèle, pas un vrai correctif complet — on la rejette
     *  plutôt que de risquer d'écraser le reste du fichier. */
    private const MIN_LENGTH_RATIO = 0.5;

    /**
     * @return array{ok:bool, diagnosis?:string, relative_path?:string, original_content?:string,
     *               proposed_content?:string, error?:string}
     */
    public static function proposeFix(string $errorMessage, string $rawFile, ?int $errorLine): array
    {
        $resolved = self::resolveSafePath($rawFile);
        if (!$resolved['ok']) {
            return ['ok' => false, 'error' => $resolved['error']];
        }
        $relativePath = $resolved['relative_path'];
        $absolutePath = $resolved['absolute_path'];

        $content = file_get_contents($absolutePath);
        if ($content === false) {
            return ['ok' => false, 'error' => "Impossible de lire le fichier $relativePath."];
        }
        if (strlen($content) > self::MAX_FILE_BYTES) {
            return ['ok' => false, 'error' => "Le fichier $relativePath est trop volumineux pour une correction automatique."];
        }

        $numbered = self::numberLines($content);
        $lineHint = $errorLine ? "à (ou près de) la ligne $errorLine" : "à un endroit non précisé";

        $system = <<<'TXT'
Tu es un assistant de débogage pour une application PHP/JavaScript en production (FamilyBoard).
On te donne le message d'une erreur technique réelle et le contenu intégral du fichier en cause.

Règles absolues :
1. Corrige UNIQUEMENT le problème précis décrit par l'erreur. Ne refactore rien d'autre, ne change
   aucun style de code, ne supprime aucun commentaire existant non lié au bug.
2. Fais le changement le plus petit et le plus sûr possible.
3. Si tu n'es pas certain de la cause réelle, ou si corriger ce fichier seul ne peut pas
   raisonnablement résoudre le problème (ex. la vraie cause est ailleurs), réponds uniquement par
   "IMPOSSIBLE: " suivi d'une explication courte — ne propose jamais un changement au hasard.
4. Si tu proposes un correctif, ta réponse doit suivre EXACTEMENT ce format, sans rien avant ni
   après, sans balises de code (```), sans aucun commentaire supplémentaire :

=== DIAGNOSTIC ===
<une explication brève, en français, de la cause et de la correction — 2 à 4 phrases>
=== FICHIER_CORRIGE ===
<le contenu INTÉGRAL et COMPLET du fichier après correction, du tout premier au tout dernier
caractère — jamais un extrait, jamais des "...", jamais de numéros de ligne>
TXT;

        $user = "Message d'erreur : {$errorMessage}\n"
            . "Fichier concerné : {$relativePath} (erreur {$lineHint})\n\n"
            . "Contenu actuel du fichier (numéroté pour référence uniquement — ne pas reproduire les numéros) :\n"
            . $numbered;

        $result = AiProvider::chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ]);

        if (!$result['ok']) {
            return ['ok' => false, 'error' => $result['error']];
        }

        return self::parseResponse($result['content'] ?? '', $relativePath, $content);
    }

    private static function parseResponse(string $raw, string $relativePath, string $originalContent): array
    {
        $raw = trim($raw);
        if (str_starts_with($raw, 'IMPOSSIBLE:') || str_starts_with($raw, 'IMPOSSIBLE :')) {
            return ['ok' => false, 'error' => trim(substr($raw, strpos($raw, ':') + 1))];
        }

        if (!preg_match('/=== DIAGNOSTIC ===\s*(.*?)\s*=== FICHIER_CORRIGE ===\s*(.*)/s', $raw, $m)) {
            return ['ok' => false, 'error' => "Réponse de l'IA dans un format inattendu — nouvelle tentative recommandée."];
        }
        $diagnosis = trim($m[1]);
        $proposed = self::stripCodeFence(trim($m[2]));

        if ($proposed === '') {
            return ['ok' => false, 'error' => "L'IA n'a renvoyé aucun contenu de fichier."];
        }
        if (strlen($proposed) < strlen($originalContent) * self::MIN_LENGTH_RATIO) {
            return ['ok' => false, 'error' => "Le fichier renvoyé par l'IA semble incomplet (bien plus court que l'original) — proposition écartée par sécurité."];
        }
        if ($proposed === $originalContent) {
            return ['ok' => false, 'error' => "L'IA n'a proposé aucun changement."];
        }

        return [
            'ok' => true,
            'diagnosis' => $diagnosis,
            'relative_path' => $relativePath,
            'original_content' => $originalContent,
            'proposed_content' => $proposed,
        ];
    }

    private static function stripCodeFence(string $text): string
    {
        return preg_replace('/^```[a-zA-Z0-9]*\n|\n```$/', '', $text);
    }

    private static function numberLines(string $content): string
    {
        $lines = explode("\n", $content);
        $out = '';
        foreach ($lines as $i => $line) {
            $out .= ($i + 1) . "\t" . $line . "\n";
        }
        return $out;
    }

    /**
     * Convertit le chemin brut d'une erreur (chemin serveur absolu pour une erreur PHP, ou URL
     * complète pour une erreur JS navigateur) en chemin relatif au dépôt, en vérifiant qu'il
     * désigne bien un fichier texte DANS le code de l'application — jamais hors de BASE_PATH
     * (traversée de chemin), jamais dans vendor/ (code tiers, pas le nôtre à corriger), jamais un
     * fichier binaire ou un dossier.
     */
    private static function resolveSafePath(string $rawFile): array
    {
        $path = $rawFile;
        $isUrl = (bool)preg_match('#^https?://#i', $path);
        if ($isUrl) {
            $path = parse_url($path, PHP_URL_PATH) ?: '';
            if (BASE_URL !== '' && str_starts_with($path, BASE_URL)) {
                $path = substr($path, strlen(BASE_URL));
            }
        }

        // Une erreur serveur (PHP) donne déjà un chemin filesystem ABSOLU ($e->getFile()) — ne
        // jamais lui préfixer BASE_PATH une deuxième fois. Seul le chemin extrait d'une URL
        // d'erreur navigateur (ci-dessus) est relatif et doit être résolu depuis BASE_PATH.
        $candidate = $isUrl
            ? (str_starts_with($path, '/') ? BASE_PATH . $path : BASE_PATH . '/' . $path)
            : $path;
        $real = realpath($candidate);
        $baseReal = realpath(BASE_PATH);

        if (!$real || !$baseReal || !str_starts_with($real, $baseReal . DIRECTORY_SEPARATOR)) {
            return ['ok' => false, 'error' => "Fichier non identifiable dans le code de l'application."];
        }
        if (!is_file($real)) {
            return ['ok' => false, 'error' => "Le chemin signalé ne correspond pas à un fichier."];
        }
        $relative = ltrim(substr($real, strlen($baseReal)), '/\\');
        if (str_starts_with($relative, 'vendor/') || str_starts_with($relative, 'storage/') || str_starts_with($relative, 'public/uploads/')) {
            return ['ok' => false, 'error' => "Ce fichier (code tiers ou donnée utilisateur) n'est pas corrigeable automatiquement."];
        }

        return ['ok' => true, 'relative_path' => str_replace('\\', '/', $relative), 'absolute_path' => $real];
    }
}
