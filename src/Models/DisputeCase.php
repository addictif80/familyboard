<?php
namespace App\Models;

use App\Core\Database;
use App\Core\OcrHelper;

class DisputeCase
{
    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT dc.*, u.name as author_name,
             (SELECT COUNT(*) FROM dispute_documents WHERE dispute_id=dc.id) as document_count,
             (SELECT COUNT(*) FROM dispute_exchanges WHERE dispute_id=dc.id) as exchange_count
             FROM dispute_cases dc JOIN users u ON u.id=dc.created_by
             WHERE dc.family_id=? ORDER BY dc.status=\'open\' DESC, dc.start_date DESC',
            [$familyId]
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch(
            'SELECT dc.*, u.name as author_name FROM dispute_cases dc JOIN users u ON u.id=dc.created_by WHERE dc.id=?',
            [$id]
        );
    }

    public static function create(int $familyId, int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO dispute_cases (family_id, created_by, title, opposing_party, start_date, details) VALUES (?,?,?,?,?,?)',
            [$familyId, $userId, $d['title'], $d['opposing_party'], $d['start_date'], $d['details']]
        );
    }

    public static function update(int $id, int $familyId, array $d): void
    {
        Database::execute(
            'UPDATE dispute_cases SET title=?, opposing_party=?, start_date=?, details=? WHERE id=? AND family_id=?',
            [$d['title'], $d['opposing_party'], $d['start_date'], $d['details'], $id, $familyId]
        );
    }

    public static function setStatus(int $id, int $familyId, string $status): void
    {
        if (!in_array($status, ['open', 'closed'], true)) return;
        Database::execute('UPDATE dispute_cases SET status=? WHERE id=? AND family_id=?', [$status, $id, $familyId]);
    }

    public static function delete(int $id, int $familyId): void
    {
        $docs = self::getDocuments($id);
        Database::execute('DELETE FROM dispute_cases WHERE id=? AND family_id=?', [$id, $familyId]);
        foreach ($docs as $doc) {
            $abs = BASE_PATH . $doc['file_path'];
            if (is_file($abs)) @unlink($abs);
        }
    }

    // ── Pièces jointes ────────────────────────────────────────────

    public static function getDocuments(int $disputeId): array
    {
        return Database::fetchAll(
            'SELECT d.*, u.name as uploader_name FROM dispute_documents d JOIN users u ON u.id=d.uploaded_by
             WHERE d.dispute_id=? ORDER BY d.uploaded_at DESC',
            [$disputeId]
        );
    }

    public static function getDocumentById(int $id, int $disputeId): ?array
    {
        return Database::fetch('SELECT * FROM dispute_documents WHERE id=? AND dispute_id=?', [$id, $disputeId]);
    }

    public static function addDocument(int $disputeId, int $familyId, int $userId, array $file): int
    {
        [$path, $original, $mime] = OcrHelper::saveUploadedFile($file, 'disputes', $familyId, OcrHelper::DISPUTE_DOC_MIMES);
        return Database::insert(
            'INSERT INTO dispute_documents (dispute_id, uploaded_by, file_path, file_original, file_mime) VALUES (?,?,?,?,?)',
            [$disputeId, $userId, $path, $original, $mime]
        );
    }

    public static function deleteDocument(int $id, int $disputeId): void
    {
        $doc = self::getDocumentById($id, $disputeId);
        if (!$doc) return;
        Database::execute('DELETE FROM dispute_documents WHERE id=? AND dispute_id=?', [$id, $disputeId]);
        $abs = BASE_PATH . $doc['file_path'];
        if (is_file($abs)) @unlink($abs);
    }

    // ── Traçabilité des échanges ─────────────────────────────────

    public static function getExchanges(int $disputeId): array
    {
        return Database::fetchAll(
            'SELECT e.*, u.name as author_name FROM dispute_exchanges e JOIN users u ON u.id=e.created_by
             WHERE e.dispute_id=? ORDER BY e.exchange_date DESC, e.id DESC',
            [$disputeId]
        );
    }

    public static function addExchange(int $disputeId, int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO dispute_exchanges (dispute_id, created_by, type, contact_info, exchange_date, notes) VALUES (?,?,?,?,?,?)',
            [$disputeId, $userId, $d['type'], $d['contact_info'], $d['exchange_date'], $d['notes']]
        );
    }

    public static function deleteExchange(int $id, int $disputeId): void
    {
        Database::execute('DELETE FROM dispute_exchanges WHERE id=? AND dispute_id=?', [$id, $disputeId]);
    }
}
