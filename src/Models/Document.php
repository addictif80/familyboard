<?php
namespace App\Models;

use App\Core\Database;
use App\Core\OcrHelper;

class Document
{
    public static function getAll(int $familyId, string $search = '', string $type = '', int $userId = 0, bool $includeArchived = false): array
    {
        $where  = ['d.family_id = ?'];
        $params = [$familyId];

        if (!$includeArchived) {
            $where[] = 'd.archived_at IS NULL';
        }

        if ($type !== '') {
            $where[]  = 'd.doc_type = ?';
            $params[] = $type;
        }
        // Filter by member via junction table
        if ($userId > 0) {
            $where[]  = 'd.id IN (SELECT document_id FROM document_members WHERE user_id = ?)';
            $params[] = $userId;
        }

        $whereStr = implode(' AND ', $where);
        $select   = 'SELECT d.*, COALESCE(u.name, d.former_user_name) as user_name, u.color as user_color, u.avatar as user_avatar FROM documents d LEFT JOIN users u ON u.id = d.user_id';

        if ($search !== '') {
            try {
                $rows = Database::fetchAll(
                    "$select WHERE $whereStr
                       AND MATCH(d.title, d.doc_type, d.issuer, d.tags, d.ocr_text)
                           AGAINST(? IN BOOLEAN MODE)
                     ORDER BY d.created_at DESC",
                    [...$params, $search . '*']
                );
            } catch (\Throwable) {
                $like = '%' . $search . '%';
                $rows = Database::fetchAll(
                    "$select WHERE $whereStr
                       AND (d.title LIKE ? OR d.issuer LIKE ? OR d.tags LIKE ?)
                     ORDER BY d.created_at DESC",
                    [...$params, $like, $like, $like]
                );
            }
        } else {
            $rows = Database::fetchAll(
                "$select WHERE $whereStr ORDER BY d.created_at DESC",
                $params
            );
        }

        self::attachMembers($rows);
        return array_map([self::class, 'decorate'], $rows);
    }

    public static function findById(int $id, int $familyId): ?array
    {
        $row = Database::fetch(
            'SELECT d.*, COALESCE(u.name, d.former_user_name) as user_name, u.color as user_color, u.avatar as user_avatar
             FROM documents d LEFT JOIN users u ON u.id = d.user_id
             WHERE d.id = ? AND d.family_id = ?',
            [$id, $familyId]
        );
        if (!$row) return null;
        $rows = [&$row];
        self::attachMembers($rows);
        return self::decorate($row);
    }

    public static function getTypeCounts(int $familyId): array
    {
        $rows = Database::fetchAll(
            'SELECT doc_type, COUNT(*) as cnt FROM documents WHERE family_id=? AND archived_at IS NULL GROUP BY doc_type',
            [$familyId]
        );
        $map = [];
        foreach ($rows as $r) $map[$r['doc_type']] = (int)$r['cnt'];
        return $map;
    }

    public static function getExpiringsSoon(int $familyId, int $days = 60): array
    {
        $rows = Database::fetchAll(
            'SELECT d.*, COALESCE(u.name, d.former_user_name) as user_name, u.color as user_color, u.avatar as user_avatar
             FROM documents d LEFT JOIN users u ON u.id=d.user_id
             WHERE d.family_id=? AND d.archived_at IS NULL
               AND d.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY d.expiry_date ASC',
            [$familyId, $days]
        );
        self::attachMembers($rows);
        return array_map([self::class, 'decorate'], $rows);
    }

    public static function create(int $familyId, int $creatorId, array $data, ?array $file = null): int
    {
        [$filePath, $fileOrig, $fileMime, $ocrText] = self::processFile($file, $familyId, $data['ocr_text'] ?? '');

        $memberIds     = self::extractMemberIds($data, $creatorId);
        $primaryUserId = $memberIds[0];

        $type = $data['doc_type'] ?? 'other';
        if (($type === '' || $type === 'auto') && $ocrText !== '') {
            $type = OcrHelper::classify($ocrText)['type'];
        }

        $docId = (int)Database::insert(
            'INSERT INTO documents
             (family_id, user_id, title, doc_type, issuer, issue_date, expiry_date,
              tags, file_path, file_original, file_mime, ocr_text, notes, custody_schedule_ids)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $familyId, $primaryUserId,
                $data['title'],
                $type,
                $data['issuer']      ?? null,
                $data['issue_date']  ?: null,
                $data['expiry_date'] ?: null,
                $data['tags']        ?? null,
                $filePath, $fileOrig, $fileMime,
                $ocrText ?: null,
                $data['notes'] ?? null,
                self::encodeScheduleIds($data),
            ]
        );

        self::syncMembers($docId, $memberIds);
        return $docId;
    }

    public static function update(int $id, int $familyId, array $data, ?array $file = null): void
    {
        $existing = self::findById($id, $familyId);
        if (!$existing) return;

        [$filePath, $fileOrig, $fileMime, $newOcr] = self::processFile(
            $file, $familyId, $data['ocr_text'] ?? '',
            $existing['file_path']
        );

        $ocrText = $newOcr !== '' ? $newOcr : ($existing['ocr_text'] ?? '');

        $memberIds     = self::extractMemberIds($data, $existing['user_id'] !== null ? (int)$existing['user_id'] : null);
        $primaryUserId = $memberIds[0] ?? null;

        $type = $data['doc_type'] ?? $existing['doc_type'];
        if (($type === '' || $type === 'auto') && $ocrText !== '') {
            $type = OcrHelper::classify($ocrText)['type'];
        }

        Database::execute(
            'UPDATE documents SET user_id=?, title=?, doc_type=?, issuer=?, issue_date=?, expiry_date=?,
             tags=?, file_path=?, file_original=?, file_mime=?, ocr_text=?, notes=?, custody_schedule_ids=?
             WHERE id=? AND family_id=?',
            [
                $primaryUserId,
                $data['title'], $type,
                $data['issuer']      ?? null,
                $data['issue_date']  ?: null,
                $data['expiry_date'] ?: null,
                $data['tags']        ?? null,
                $filePath, $fileOrig, $fileMime,
                $ocrText ?: null,
                $data['notes'] ?? null,
                self::encodeScheduleIds($data),
                $id, $familyId,
            ]
        );

        self::syncMembers($id, $memberIds);
    }

    /** CSV de schedule_id (comme Invitation::create()), ou NULL si aucun enfant sélectionné. */
    private static function encodeScheduleIds(array $data): ?string
    {
        $ids = array_values(array_unique(array_map('intval', (array)($data['custody_schedule_ids'] ?? []))));
        return $ids ? implode(',', $ids) : null;
    }

    /** Documents tagués à l'un des plannings de garde donnés (vue co-parent restreint). */
    public static function getForSchedules(array $scheduleIds): array
    {
        $scheduleIds = array_values(array_unique(array_map('intval', $scheduleIds)));
        if (empty($scheduleIds)) return [];
        $ors = implode(' OR ', array_fill(0, count($scheduleIds), 'FIND_IN_SET(?, d.custody_schedule_ids)'));
        $rows = Database::fetchAll(
            "SELECT d.*, COALESCE(u.name, d.former_user_name) as user_name, u.color as user_color, u.avatar as user_avatar
             FROM documents d LEFT JOIN users u ON u.id = d.user_id
             WHERE $ors
             ORDER BY d.created_at DESC",
            $scheduleIds
        );
        self::attachMembers($rows);
        return array_map([self::class, 'decorate'], $rows);
    }

    /** Cherche un document existant (non archivé) au titre proche — même famille, même type si
     *  précisé. Utilisé à l'ajout pour proposer de remplacer une version précédente plutôt que
     *  de créer un doublon sans lien entre les deux. */
    public static function findSimilar(int $familyId, string $title, string $type = ''): ?array
    {
        $norm = self::normalizeTitle($title);
        if ($norm === '') return null;

        $where  = ['family_id = ?', 'archived_at IS NULL'];
        $params = [$familyId];
        if ($type !== '' && $type !== 'auto') {
            $where[]  = 'doc_type = ?';
            $params[] = $type;
        }

        $rows = Database::fetchAll(
            'SELECT id, title, doc_type, created_at FROM documents WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC',
            $params
        );
        foreach ($rows as $row) {
            $rowNorm = self::normalizeTitle($row['title']);
            if ($rowNorm === $norm) return $row;
            if (mb_strlen($norm) >= 4 && mb_strlen($rowNorm) >= 4
                && (str_contains($rowNorm, $norm) || str_contains($norm, $rowNorm))) {
                return $row;
            }
        }
        return null;
    }

    private static function normalizeTitle(string $title): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($title)));
    }

    /** Archive l'ancien document (retiré de la liste par défaut, gardé pour historique) et le
     *  lie à celui qui le remplace. */
    public static function archiveAndReplace(int $oldId, int $newId, int $familyId): void
    {
        Database::execute(
            'UPDATE documents SET archived_at=?, replaced_by_id=? WHERE id=? AND family_id=?',
            [date('Y-m-d H:i:s'), $newId, $oldId, $familyId]
        );
    }

    /** Chaîne des versions précédentes d'un document (la plus récente en premier). */
    public static function getPredecessors(int $id, int $familyId): array
    {
        $chain = [];
        $current = Database::fetch('SELECT id FROM documents WHERE replaced_by_id=? AND family_id=?', [$id, $familyId]);
        $guard = 0;
        while ($current && $guard < 20) {
            $doc = self::findById((int)$current['id'], $familyId);
            if (!$doc) break;
            $chain[] = $doc;
            $current = Database::fetch('SELECT id FROM documents WHERE replaced_by_id=? AND family_id=?', [$doc['id'], $familyId]);
            $guard++;
        }
        return $chain;
    }

    public static function delete(int $id, int $familyId): void
    {
        $row = Database::fetch('SELECT file_path FROM documents WHERE id=? AND family_id=?', [$id, $familyId]);
        if ($row && $row['file_path']) @unlink(BASE_PATH . $row['file_path']);
        // document_members deleted via ON DELETE CASCADE
        Database::execute('DELETE FROM documents WHERE id=? AND family_id=?', [$id, $familyId]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /** Attach a 'members' array to each row (1 extra query regardless of row count) */
    private static function attachMembers(array &$rows): void
    {
        if (empty($rows)) return;

        $docIds = array_column($rows, 'id');
        $ph     = implode(',', array_fill(0, count($docIds), '?'));

        $memberRows = Database::fetchAll(
            "SELECT dm.document_id, u.id, u.name, u.color, u.avatar
             FROM document_members dm JOIN users u ON u.id = dm.user_id
             WHERE dm.document_id IN ($ph)
             ORDER BY u.name",
            $docIds
        );

        $byDoc = [];
        foreach ($memberRows as $mr) {
            $byDoc[$mr['document_id']][] = [
                'id'     => (int)$mr['id'],
                'name'   => $mr['name'],
                'color'  => $mr['color'],
                'avatar' => $mr['avatar'],
            ];
        }

        foreach ($rows as &$row) {
            $row['members'] = $byDoc[$row['id']] ?? [];
            // Fallback: if junction table empty (pre-migration row), use user_id
            if (empty($row['members']) && !empty($row['user_name'])) {
                $row['members'] = [[
                    'id'     => (int)$row['user_id'],
                    'name'   => $row['user_name'],
                    'color'  => $row['user_color'],
                    'avatar' => $row['user_avatar'] ?? null,
                ]];
            }
        }
        unset($row);
    }

    /** Parse member_ids from form data, fallback to $default — $default peut être null si
     *  l'auteur d'origine du document a depuis été retiré de la famille (user_id devenu NULL,
     *  voir AccountDeletion::deleteUser()) : dans ce cas, sans sélection explicite, le document
     *  reste simplement sans membre associé plutôt que planter. */
    private static function extractMemberIds(array $data, ?int $default): array
    {
        $raw = $data['member_ids'] ?? $data['user_id'] ?? null;
        $ids = array_values(array_filter(
            array_map('intval', (array)$raw),
            fn($id) => $id > 0
        ));
        if (!empty($ids)) return $ids;
        return $default !== null ? [$default] : [];
    }

    /** Replace all members in junction table for a document */
    private static function syncMembers(int $docId, array $memberIds): void
    {
        Database::execute('DELETE FROM document_members WHERE document_id = ?', [$docId]);
        foreach (array_unique($memberIds) as $uid) {
            if ($uid > 0) {
                Database::execute(
                    'INSERT IGNORE INTO document_members (document_id, user_id) VALUES (?, ?)',
                    [$docId, $uid]
                );
            }
        }
    }

    private static function processFile(?array $file, int $familyId, string $providedOcr, ?string $existingPath = null): array
    {
        $filePath = $existingPath;
        $fileOrig = null;
        $fileMime = null;
        $ocrText  = $providedOcr;

        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            if ($existingPath) @unlink(BASE_PATH . $existingPath);
            [$filePath, $fileOrig, $fileMime] = OcrHelper::saveUploadedFile($file, 'documents', $familyId);
            if ($ocrText === '') {
                $ocrText = OcrHelper::run(BASE_PATH . $filePath, $fileMime);
            }
        }

        return [$filePath, $fileOrig, $fileMime, $ocrText];
    }

    private static function decorate(array $row): array
    {
        $row['is_archived'] = !empty($row['archived_at']);
        $row['type_label'] = OcrHelper::typeLabel($row['doc_type']);
        $row['type_icon']  = OcrHelper::typeIcon($row['doc_type']);
        $row['type_color'] = OcrHelper::typeColor($row['doc_type']);

        $row['days_until_expiry'] = null;
        $row['expiry_status']     = 'none';
        if (!empty($row['expiry_date'])) {
            $today = new \DateTime('today');
            $end   = new \DateTime($row['expiry_date']);
            $diff  = (int)$today->diff($end)->days * ($end >= $today ? 1 : -1);
            $row['days_until_expiry'] = $diff;
            if ($diff < 0)        $row['expiry_status'] = 'expired';
            elseif ($diff <= 30)  $row['expiry_status'] = 'critical';
            elseif ($diff <= 90)  $row['expiry_status'] = 'soon';
            else                  $row['expiry_status'] = 'ok';
        }

        return $row;
    }
}
