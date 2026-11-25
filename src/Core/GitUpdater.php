<?php
namespace App\Core;

/**
 * Mise à jour de l'application depuis le dépôt git (bouton "Mettre à jour" de
 * /admin?tab=health) — exécute uniquement des commandes fixes, jamais une valeur venant de la
 * requête HTTP dans la commande elle-même. Protégé en amont par
 * AdminController::requireSuperAdmin().
 */
class GitUpdater
{
    /** @return array{code:int, output:string} */
    private static function run(string $command): array
    {
        if (!function_exists('proc_open')) {
            return ['code' => 1, 'output' => "proc_open() est désactivé sur ce serveur (disable_functions) — impossible d'exécuter git."];
        }
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes, BASE_PATH);
        if (!is_resource($process)) {
            return ['code' => 1, 'output' => 'Impossible de lancer la commande.'];
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        return ['code' => $code, 'output' => trim($stdout . $stderr)];
    }

    private static function commandExists(string $cmd): bool
    {
        return trim(self::run('command -v ' . escapeshellarg($cmd))['output']) !== '';
    }

    /**
     * État actuel (branche, dernier commit, modifications locales non commitées) et retard sur
     * origin après un `git fetch` — pour afficher "une mise à jour est disponible" avant de
     * l'appliquer, sans rien modifier sur le disque.
     */
    public static function status(): array
    {
        $branch = trim(self::run('git rev-parse --abbrev-ref HEAD')['output']);
        $commit = trim(self::run("git log -1 --format='%h %s (%cr)'")['output']);
        // --untracked-files=no : un fichier non suivi (cache runtime, binaire local...) ne sera
        // jamais écrasé par un `git pull --ff-only` — seules les modifications sur des fichiers
        // DÉJÀ suivis par git représentent un risque de perte et doivent bloquer la mise à jour.
        $dirty = trim(self::run('git status --porcelain --untracked-files=no')['output']) !== '';

        $behind = null;
        $fetchError = null;
        if ($branch !== '') {
            $fetch = self::run('git fetch origin ' . escapeshellarg($branch) . ' 2>&1');
            if ($fetch['code'] !== 0) {
                $fetchError = $fetch['output'];
            } else {
                $behind = (int)trim(self::run('git rev-list --count HEAD..origin/' . escapeshellarg($branch))['output']);
            }
        }

        return [
            'branch'      => $branch ?: null,
            'commit'      => $commit ?: null,
            'dirty'       => $dirty,
            'behind'      => $behind,
            'fetch_error' => $fetchError,
        ];
    }

    /**
     * Applique la mise à jour : `git pull --ff-only` (jamais de merge ni de force — échoue
     * proprement plutôt que de créer un commit de fusion ou d'écraser quoi que ce soit), puis
     * `composer install` si composer.lock a changé.
     * @return array{success:bool, output:string}
     */
    public static function pull(): array
    {
        $status = self::status();
        if ($status['dirty']) {
            return ['success' => false, 'output' => "Des modifications non commitées existent sur le serveur (git status --porcelain) — mise à jour refusée pour ne rien perdre. Résolvez-les manuellement avant de réessayer."];
        }

        $lockBefore = @md5_file(BASE_PATH . '/composer.lock');
        $pull = self::run('git pull --ff-only 2>&1');
        $output = "\$ git pull --ff-only\n" . $pull['output'] . "\n";
        if ($pull['code'] !== 0) {
            return ['success' => false, 'output' => $output];
        }

        $lockAfter = @md5_file(BASE_PATH . '/composer.lock');
        if ($lockBefore !== false && $lockAfter !== false && $lockAfter !== $lockBefore) {
            if (self::commandExists('composer')) {
                $composer = self::run('composer install --no-dev --no-interaction --optimize-autoloader 2>&1');
                $output .= "\n\$ composer install\n" . $composer['output'] . "\n";
                if ($composer['code'] !== 0) {
                    return ['success' => false, 'output' => $output];
                }
            } else {
                $output .= "\ncomposer.lock a changé mais la commande `composer` est introuvable sur ce serveur — exécutez `composer install` manuellement.\n";
            }
        }

        return ['success' => true, 'output' => $output];
    }
}
