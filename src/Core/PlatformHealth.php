<?php
namespace App\Core;

use App\Models\AppSetting;

/**
 * Indicateurs de santé technique de la plateforme (distinct du module "Santé" des familles,
 * qui concerne le suivi médical) — pour le tableau de bord admin système (/admin?tab=health).
 * Ne fait que LIRE des données déjà collectées ailleurs (support_tickets, email_logs,
 * information_schema) ; n'écrit rien, sauf recordCronHeartbeat() appelé depuis cron.php.
 */
class PlatformHealth
{
    private const CRON_STALE_AFTER_MINUTES = 10;

    /** Appelé une fois par exécution de cron.php (voir tout en haut du fichier), indépendamment
     *  du succès ou de l'échec des jobs individuels qui suivent — un heartbeat qui ne bouge plus
     *  indique que le cron lui-même ne tourne plus, ce qui est la panne la plus grave possible ici. */
    public static function recordCronHeartbeat(): void
    {
        AppSetting::set('cron_last_run', gmdate('Y-m-d H:i:s'));
    }

    public static function cronStatus(): array
    {
        $lastRun = AppSetting::get('cron_last_run');
        if (!$lastRun) {
            return ['last_run' => null, 'minutes_ago' => null, 'stale' => true];
        }
        $minutesAgo = (int)round((time() - (new \DateTime($lastRun, new \DateTimeZone('UTC')))->getTimestamp()) / 60);
        return [
            'last_run'    => $lastRun,
            'minutes_ago' => $minutesAgo,
            'stale'       => $minutesAgo > self::CRON_STALE_AFTER_MINUTES,
        ];
    }

    /** Erreurs techniques auto-détectées (voir ErrorReporter::report()), pas les signalements
     *  manuels d'utilisateurs ni les tickets de support classiques. */
    public static function errorStats(): array
    {
        $count = fn(string $interval) => (int)(Database::fetch(
            "SELECT COUNT(*) c FROM support_tickets WHERE source='auto' AND created_at >= NOW() - INTERVAL $interval"
        )['c'] ?? 0);
        $recent = Database::fetchAll(
            "SELECT st.id, st.subject, st.created_at, f.name as family_name
             FROM support_tickets st JOIN families f ON f.id = st.family_id
             WHERE st.source='auto' ORDER BY st.created_at DESC LIMIT 15"
        );
        return [
            'last_24h' => $count('1 DAY'),
            'last_7d'  => $count('7 DAY'),
            'recent'   => $recent,
        ];
    }

    public static function emailStats(): array
    {
        $row = fn(string $interval) => Database::fetch(
            "SELECT SUM(status='sent') ok, SUM(status='failed') fail FROM email_logs WHERE created_at >= NOW() - INTERVAL $interval"
        );
        $r24 = $row('1 DAY');
        $r7d = $row('7 DAY');
        return [
            'last_24h' => ['ok' => (int)($r24['ok'] ?? 0), 'fail' => (int)($r24['fail'] ?? 0)],
            'last_7d'  => ['ok' => (int)($r7d['ok'] ?? 0), 'fail' => (int)($r7d['fail'] ?? 0)],
        ];
    }

    public static function dbSizeBytes(): int
    {
        $row = Database::fetch(
            'SELECT SUM(data_length + index_length) bytes FROM information_schema.tables WHERE table_schema = DATABASE()'
        );
        return (int)($row['bytes'] ?? 0);
    }

    /** Taille des fichiers uploadés (storage/ + public/uploads/) — calculée via `du`, seule
     *  approche assez rapide pour ne pas bloquer la page sur un volume de fichiers important
     *  (contrairement à une somme récursive en PHP, en O(n) d'appels filesystem). Retourne null
     *  si `du` est indisponible (environnement restreint) plutôt que de faire planter la page. */
    public static function storageSizeBytes(): ?int
    {
        if (!function_exists('shell_exec')) return null;
        $total = 0;
        $found = false;
        foreach (['/storage', '/public/uploads'] as $rel) {
            $dir = BASE_PATH . $rel;
            if (!is_dir($dir)) continue;
            $out = @shell_exec('du -sb ' . escapeshellarg($dir) . ' 2>/dev/null');
            if ($out === null) continue;
            $found = true;
            $total += (int)strtok(trim($out), "\t ");
        }
        return $found ? $total : null;
    }

    public static function formatBytes(?int $bytes): string
    {
        if ($bytes === null) return 'inconnu';
        if ($bytes < 1024) return $bytes . ' o';
        $units = ['Ko', 'Mo', 'Go', 'To'];
        $i = -1;
        do { $bytes /= 1024; $i++; } while ($bytes >= 1024 && $i < count($units) - 1);
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
