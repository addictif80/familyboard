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
        if ($userId > 0) {
            $where[]  = 'd.user_id = ?';
            $params[] = $userId;
        }

        $whereStr = implode(' AND ', $where);

        if ($search !== '') {
            try {
                $rows = Database::fetchAll(
                    "SELECT d.*, u.name as user_name, u.color as user_color
                     FROM documents d JOIN users u ON u.id = d.user_id
                     WHERE $whereStr
                       AND MATCH(d.title, d.doc_type, d.issuer, d.tags, d.ocr_text)
                           AGAINST(? IN BOOLEAN MODE)
                     ORDER BY d.created_at DESC",
                    [...$params, $search . '*']
                );
            } catch (\Throwable) {
                $like     = '%' . $search . '%';
                $rows = Database::fetchAll(
                    "SELECT d.*, u.name as user_name, u.color as user_color
                     FROM documents d JOIN users u ON u.id = d.user_id
                     WHERE $whereStr
                       AND (d.title LIKE ? OR d.issuer LIKE ? OR d.tags LIKE ?)
                     ORDER BY d.created_at DESC",
                    [...$params, $like, $like, $like]
                );
            }
        } else {
            $rows = Database::fetchAll(
                "SELECT d.*, u.name as user_name, u.color as user_color
                 FROM documents d JOIN users u ON u.id = d.user_id
                 WHERE $whereStr
                 ORDER BY d.created_at DESC",
                $params
            );
        }

        return array_map([self::class, 'decorate'], $rows);
    }

    public static function findById(int $id, int $familyId): ?array
    {
        $row = Database::fetch(
            'SELECT d.*, u.name as user_name, u.color as user_color
             FROM documents d JOIN users u ON u.id = d.user_id
             WHERE d.id = ? AND d.family_id = ?',
            [$id, $familyId]
        );
        return $row ? self::decorate($row) : null;
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
            'SELECT d.*, u.name as user_name FROM documents d JOIN users u ON u.id=d.user_id
             WHERE d.family_id=?
               AND d.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY d.expiry_date ASC',
            [$familyId, $days]
        );
        return array_map([self::class, 'decorate'], $rows);
    }

    public static function create(int $familyId, int $userId, array $data, ?array $file = null): int
    {
        [$filePath, $fileOrig, $fileMime, $ocrText] = self::processFile($file, $familyId, $data['ocr_text'] ?? '');

        // Auto-classify if no type given or type is 'auto'
        $type = $data['doc_type'] ?? 'other';
        if (($type === '' || $type === 'auto') && $ocrText !== '') {
            $type = OcrHelper::classify($ocrText)['type'];
        }

        Database::execute(
            'INSERT INTO documents
             (family_id, user_id, title, doc_type, issuer, issue_date, expiry_date,
              tags, file_path, file_original, file_mime, ocr_text, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $familyId, $userId,
                $data['title'],
                $type,
                $data['issuer']      ?? null,
                $data['issue_date']  ?: null,
                $data['expiry_date'] ?: null,
                $data['tags']        ?? null,
                $filePath, $fileOrig, $fileMime,
                $ocrText ?: null,
                $data['notes'] ?? null,
            ]
        );
        return (int)Database::lastInsertId();
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

        $type = $data['doc_type'] ?? $existing['doc_type'];
        if (($type === '' || $type === 'auto') && $ocrText !== '') {
            $type = OcrHelper::classify($ocrText)['type'];
        }

        Database::execute(
            'UPDATE documents SET title=?, doc_type=?, issuer=?, issue_date=?, expiry_date=?,
             tags=?, file_path=?, file_original=?, file_mime=?, ocr_text=?, notes=?
             WHERE id=? AND family_id=?',
            [
                $data['title'], $type,
                $data['issuer']      ?? null,
                $data['issue_date']  ?: null,
                $data['expiry_date'] ?: null,
                $data['tags']        ?? null,
                $filePath, $fileOrig, $fileMime,
                $ocrText ?: null,
                $data['notes'] ?? null,
                $id, $familyId,
            ]
        );
    }

    public static function delete(int $id, int $familyId): void
    {
        $row = Database::fetch('SELECT file_path FROM documents WHERE id=? AND family_id=?', [$id, $familyId]);
        if ($row && $row['file_path']) @unlink(BASE_PATH . $row['file_path']);
        Database::execute('DELETE FROM documents WHERE id=? AND family_id=?', [$id, $familyId]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

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
                $ocrText = OcrHelper::run($file['tmp_name'], $file['type']);
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
