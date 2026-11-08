<?php
namespace App\Models;

use App\Core\Database;

/**
 * Agrège les statistiques d'une année pour une famille, en vue de l'export PowerPoint du récap
 * annuel (voir AnnualRecapController + public/js/annual_recap.js, qui génère le .pptx côté
 * client avec PptxGenJS vendorisé localement — aucune table dédiée : purement des lectures sur les
 * modules existants, exactement comme AiAssistant::buildContextSummary() pour le digest IA.
 */
class AnnualRecap
{
    /** Première année disponible pour le sélecteur — année de création de la famille, ou année en
     *  cours si inconnue (ne jamais proposer une plage vide). */
    public static function firstAvailableYear(int $familyId): int
    {
        $row = Database::fetch('SELECT created_at FROM families WHERE id=?', [$familyId]);
        return $row && $row['created_at'] ? (int)substr($row['created_at'], 0, 4) : (int)date('Y');
    }

    public static function getStats(int $familyId, int $year): array
    {
        $start = $year . '-01-01 00:00:00';
        $end   = $year . '-12-31 23:59:59';

        return [
            'year'        => $year,
            'family_name' => Database::fetch('SELECT name FROM families WHERE id=?', [$familyId])['name'] ?? 'Famille',
            'events'      => self::eventStats($familyId, $start, $end),
            'tasks'       => self::taskStats($familyId, $start, $end),
            'budget'      => self::budgetStats($familyId, $year),
            'posts'       => self::postStats($familyId, $start, $end),
            'birthdays'   => self::birthdaysStats($familyId, $year),
            'photos'      => self::highlightPhotos($familyId, $start, $end),
        ];
    }

    private static function eventStats(int $familyId, string $start, string $end): array
    {
        $total = (int)(Database::fetch(
            'SELECT COUNT(*) as c FROM events WHERE family_id=? AND start_datetime BETWEEN ? AND ?',
            [$familyId, $start, $end]
        )['c'] ?? 0);

        $byMonth = Database::fetchAll(
            "SELECT MONTH(start_datetime) as m, COUNT(*) as c FROM events
              WHERE family_id=? AND start_datetime BETWEEN ? AND ? GROUP BY m",
            [$familyId, $start, $end]
        );
        $busiestMonth = null;
        $max = 0;
        foreach ($byMonth as $row) {
            if ((int)$row['c'] > $max) { $max = (int)$row['c']; $busiestMonth = (int)$row['m']; }
        }

        return ['total' => $total, 'busiest_month' => $busiestMonth, 'busiest_month_count' => $max];
    }

    private static function taskStats(int $familyId, string $start, string $end): array
    {
        $completed = (int)(Database::fetch(
            "SELECT COUNT(*) as c FROM tasks t JOIN task_lists tl ON tl.id=t.list_id
              WHERE tl.family_id=? AND tl.type != 'shopping' AND t.is_completed=1
                AND t.completed_at BETWEEN ? AND ?",
            [$familyId, $start, $end]
        )['c'] ?? 0);

        return ['completed' => $completed];
    }

    private static function budgetStats(int $familyId, int $year): array
    {
        try {
            $row = Database::fetch(
                "SELECT
                    COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END), 0) as income,
                    COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END), 0) as expense
                 FROM budget_transactions WHERE family_id=? AND YEAR(date)=?",
                [$familyId, $year]
            );
            $income = (float)($row['income'] ?? 0);
            $expense = (float)($row['expense'] ?? 0);
            return ['income' => $income, 'expense' => $expense, 'balance' => $income - $expense];
        } catch (\Throwable) {
            return ['income' => 0, 'expense' => 0, 'balance' => 0];
        }
    }

    private static function postStats(int $familyId, string $start, string $end): array
    {
        $total = (int)(Database::fetch(
            'SELECT COUNT(*) as c FROM posts WHERE family_id=? AND created_at BETWEEN ? AND ?',
            [$familyId, $start, $end]
        )['c'] ?? 0);
        $withPhoto = (int)(Database::fetch(
            "SELECT COUNT(*) as c FROM posts WHERE family_id=? AND created_at BETWEEN ? AND ? AND image_path IS NOT NULL",
            [$familyId, $start, $end]
        )['c'] ?? 0);
        return ['total' => $total, 'with_photo' => $withPhoto];
    }

    private static function birthdaysStats(int $familyId, int $year): array
    {
        try {
            $rows = Database::fetchAll(
                "SELECT first_name, last_name, birthday FROM contacts
                  WHERE family_id=? AND birthday IS NOT NULL AND birthday != ''",
                [$familyId]
            );
            $names = [];
            foreach ($rows as $r) {
                $names[] = trim($r['first_name'] . ' ' . $r['last_name']);
            }
            return ['count' => count($names), 'names' => array_slice($names, 0, 10)];
        } catch (\Throwable) {
            return ['count' => 0, 'names' => []];
        }
    }

    /** Jusqu'à 6 photos marquantes (les plus commentées/aimées, sinon les plus récentes),
     *  encodées en base64 pour être intégrées directement dans le .pptx côté client — un post
     *  n'est jamais accessible publiquement, donc l'URL seule ne suffirait pas à PptxGenJS. */
    private static function highlightPhotos(int $familyId, string $start, string $end): array
    {
        $posts = Database::fetchAll(
            "SELECT p.image_path, p.content,
                    (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id=p.id) as comment_count
             FROM posts p
             WHERE p.family_id=? AND p.created_at BETWEEN ? AND ? AND p.image_path IS NOT NULL
             ORDER BY comment_count DESC, p.created_at DESC
             LIMIT 6",
            [$familyId, $start, $end]
        );

        $photos = [];
        foreach ($posts as $p) {
            $path = BASE_PATH . $p['image_path'];
            if (!is_file($path) || filesize($path) > 4 * 1024 * 1024) continue;
            $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
                'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
                default => 'image/jpeg',
            };
            $data = @file_get_contents($path);
            if ($data === false) continue;
            $photos[] = [
                'data_url' => 'data:' . $mime . ';base64,' . base64_encode($data),
                'caption'  => mb_substr(trim((string)$p['content']), 0, 100),
            ];
        }
        return $photos;
    }
}
