<?php
namespace App\Models;

use App\Core\Database;
use App\Core\OcrHelper;

class Deal
{
    public const CATEGORIES = [
        'alimentation' => ['label' => 'Alimentation', 'icon' => '🛒'],
        'loisirs'      => ['label' => 'Loisirs',      'icon' => '🎡'],
        'enfants'      => ['label' => 'Enfants',      'icon' => '🧸'],
        'shopping'     => ['label' => 'Shopping',      'icon' => '🛍️'],
        'voyage'       => ['label' => 'Voyage',        'icon' => '✈️'],
        'maison'       => ['label' => 'Maison',        'icon' => '🏠'],
        'sante'        => ['label' => 'Santé',         'icon' => '💊'],
        'services'     => ['label' => 'Services',      'icon' => '🔧'],
        'autre'        => ['label' => 'Autre',         'icon' => '🏷️'],
    ];

    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT * FROM deals WHERE family_id=? ORDER BY (expires_at IS NOT NULL AND expires_at < CURDATE()) ASC, title',
            [$familyId]
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM deals WHERE id=?', [$id]);
    }

    public static function create(int $familyId, int $userId, array $d, ?array $file = null): int
    {
        $filePath = $fileOriginal = $fileMime = null;
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            [$filePath, $fileOriginal, $fileMime] = OcrHelper::saveUploadedFile($file, 'deals', $familyId);
        }
        return Database::insert(
            'INSERT INTO deals (family_id, title, description, url, promo_code, discount, category, expires_at, file_path, file_original, file_mime, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$familyId, $d['title'], $d['description'], $d['url'], $d['promo_code'], $d['discount'], $d['category'], $d['expires_at'],
             $filePath, $fileOriginal, $fileMime, $userId]
        );
    }

    public static function update(int $id, int $familyId, array $d, ?array $file = null): void
    {
        $existing = self::getById($id);
        $filePath = $existing['file_path'] ?? null;
        $fileOriginal = $existing['file_original'] ?? null;
        $fileMime = $existing['file_mime'] ?? null;

        if (!empty($d['remove_file'])) {
            if ($filePath) @unlink(BASE_PATH . $filePath);
            $filePath = $fileOriginal = $fileMime = null;
        }
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            if ($filePath) @unlink(BASE_PATH . $filePath);
            [$filePath, $fileOriginal, $fileMime] = OcrHelper::saveUploadedFile($file, 'deals', $familyId);
        }

        Database::execute(
            'UPDATE deals SET title=?, description=?, url=?, promo_code=?, discount=?, category=?, expires_at=?, file_path=?, file_original=?, file_mime=? WHERE id=? AND family_id=?',
            [$d['title'], $d['description'], $d['url'], $d['promo_code'], $d['discount'], $d['category'], $d['expires_at'],
             $filePath, $fileOriginal, $fileMime, $id, $familyId]
        );
    }

    public static function delete(int $id, int $familyId): void
    {
        $deal = self::getById($id);
        if ($deal && (int)$deal['family_id'] === $familyId && $deal['file_path']) {
            @unlink(BASE_PATH . $deal['file_path']);
        }
        Database::execute('DELETE FROM deals WHERE id=? AND family_id=?', [$id, $familyId]);
    }
}
