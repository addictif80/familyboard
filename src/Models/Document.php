<?php
namespace App\Models;

use App\Core\Database;
use App\Core\OcrHelper;

class Document
{
    public static function getAll(int $familyId, string $search = '', string $type = '', int $userId = 0): array
    {
        $where  = ['d.family_id = ?'];
        $params = [$familyId];

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
        $select   = 'SELECT d.*, u.name as user_name, u.color as user_color, u.avatar as user_avatar FROM documents d JOIN users u ON u.id = d.user_id';

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
            'SELECT d.*, u.name as user_name, u.color as user_color, u.avatar as user_avatar
             FROM documents d JOIN users u ON u.id = d.user_id
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
            'SELECT doc_type, COUNT(*) as cnt FROM documents WHERE family_id=? GROUP BY doc_type',
            [$familyId]
        );
        $map = [];
        foreach ($rows as $r) $map[$r['doc_type']] = (int)$r['cnt'];
        return $map;
    }

    public static function getExpiringsSoon(int $familyId, int $days = 60): array
    {
        $rows = Database::fetchAll(
            'SELECT d.*, u.name as user_name, u.color as user_color, u.avatar as user_avatar
             FROM documents d JOIN users u ON u.id=d.user_id
             WHERE d.family_id=?
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
              tags, file_path, file_original, file_mime, ocr_text, notes, custody_schedule_id)
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
                !empty($data['custody_schedule_id']) ? $data['custody_schedule_id'] : null,
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

        $memberIds     = self::extractMemberIds($data, $existing['user_id']);
        $primaryUserId = $memberIds[0];

        $type = $data['doc_type'] ?? $existing['doc_type'];
        if (($type === '' || $type === 'auto') && $ocrText !== '') {
            $type = OcrHelper::classify($ocrText)['type'];
        }

        Database::execute(
            'UPDATE documents SET user_id=?, title=?, doc_type=?, issuer=?, issue_date=?, expiry_date=?,
             tags=?, file_path=?, file_original=?, file_mime=?, ocr_text=?, notes=?, custody_schedule_id=?
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
                !empty($data['custody_schedule_id']) ? $data['custody_schedule_id'] : null,
                $id, $familyId,
            ]
        );

        self::syncMembers($id, $memberIds);
    }

    /** Documents tagués à l'un des plannings de garde donnés (vue co-parent restreint). */
    public static function getForSchedules(array $scheduleIds): array
    {
        $scheduleIds = array_values(array_unique(array_map('intval', $scheduleIds)));
        if (empty($scheduleIds)) return [];
        $ph = implode(',', array_fill(0, count($scheduleIds), '?'));
        $rows = Database::fetchAll(
            "SELECT d.*, u.name as user_name, u.color as user_color, u.avatar as user_avatar
             FROM documents d JOIN users u ON u.id = d.user_id
             WHERE d.custody_schedule_id IN ($ph)
             ORDER BY d.created_at DESC",
            $scheduleIds
        );
        self::attachMembers($rows);
        return array_map([self::class, 'decorate'], $rows);
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

    /** Parse member_ids from form data, fallback to $default */
    private static function extractMemberIds(array $data, int $default): array
    {
        $raw = $data['member_ids'] ?? $data['user_id'] ?? null;
        $ids = array_values(array_filter(
            array_map('intval', (array)$raw),
            fn($id) => $id > 0
        ));
        return empty($ids) ? [$default] : $ids;
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
