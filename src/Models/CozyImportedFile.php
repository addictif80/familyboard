<?php
namespace App\Models;

use App\Core\Database;

/** Déduplication des fichiers Cozy déjà importés dans Documents — voir App\Core\CozyDocumentSync. */
class CozyImportedFile
{
    public static function isImported(int $familyId, string $cozyFileId): bool
    {
        return (bool)Database::fetch(
            'SELECT 1 FROM cozy_imported_files WHERE family_id = ? AND cozy_file_id = ?',
            [$familyId, $cozyFileId]
        );
    }

    public static function markImported(int $familyId, string $cozyFileId, int $documentId): void
    {
        Database::insert(
            'INSERT INTO cozy_imported_files (family_id, cozy_file_id, document_id) VALUES (?,?,?)',
            [$familyId, $cozyFileId, $documentId]
        );
    }
}
