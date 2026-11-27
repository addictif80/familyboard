<?php
namespace App\Core;

/**
 * Rendu HTML d'un diff unifié entre deux versions d'un fichier (voir AiCodeFixer /
 * /admin/tickets/:id) — s'appuie sur l'utilitaire système `diff` (toujours présent sur un serveur
 * Linux standard) plutôt que de réimplémenter un algorithme de diff.
 */
class TextDiff
{
    public static function renderHtml(string $old, string $new): string
    {
        $diff = self::unified($old, $new);
        if ($diff === null) {
            // `diff` indisponible (environnement très restreint) : on retombe sur un affichage
            // des deux versions complètes plutôt que de ne rien montrer avant publication.
            return '<div class="ai-diff-fallback">'
                . '<h4>Avant</h4><pre class="ai-diff-pre">' . htmlspecialchars($old) . '</pre>'
                . '<h4>Après</h4><pre class="ai-diff-pre">' . htmlspecialchars($new) . '</pre>'
                . '</div>';
        }
        if (trim($diff) === '') {
            return '<p class="empty-state">Aucune différence.</p>';
        }

        $lines = explode("\n", $diff);
        $html = '<pre class="ai-diff-pre">';
        foreach ($lines as $line) {
            if ($line === '') continue;
            $cls = match (true) {
                str_starts_with($line, '+++') || str_starts_with($line, '---') => 'ai-diff-head',
                str_starts_with($line, '@@') => 'ai-diff-hunk',
                str_starts_with($line, '+') => 'ai-diff-add',
                str_starts_with($line, '-') => 'ai-diff-del',
                default => '',
            };
            $html .= '<span class="' . $cls . '">' . htmlspecialchars($line) . "</span>\n";
        }
        $html .= '</pre>';
        return $html;
    }

    private static function unified(string $old, string $new): ?string
    {
        if (!function_exists('shell_exec')) return null;
        $dir = sys_get_temp_dir();
        $oldFile = tempnam($dir, 'fbdiff_old_');
        $newFile = tempnam($dir, 'fbdiff_new_');
        if ($oldFile === false || $newFile === false) return null;

        try {
            file_put_contents($oldFile, $old);
            file_put_contents($newFile, $new);
            $cmd = 'diff -u ' . escapeshellarg($oldFile) . ' ' . escapeshellarg($newFile) . ' 2>/dev/null';
            $output = @shell_exec($cmd);
            // `diff` renvoie un code de sortie non nul (donc rien de fiable à tester ici) dès que
            // les fichiers diffèrent — seul `shell_exec`===null (fonction désactivée) indique une
            // vraie indisponibilité de l'outil.
            return $output ?? '';
        } finally {
            @unlink($oldFile);
            @unlink($newFile);
        }
    }
}
