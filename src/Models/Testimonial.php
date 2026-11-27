<?php
namespace App\Models;

use App\Core\Database;

class Testimonial
{
    public static function getApproved(): array
    {
        return Database::fetchAll(
            "SELECT * FROM testimonials WHERE status='approved' ORDER BY sort_order, created_at DESC"
        );
    }

    /** Toutes les soumissions (tous statuts) — vue admin système, la plus récente d'abord. */
    public static function getAll(): array
    {
        return Database::fetchAll(
            'SELECT t.*, f.name as family_name FROM testimonials t LEFT JOIN families f ON f.id = t.family_id
             ORDER BY (t.status="pending") DESC, t.created_at DESC'
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM testimonials WHERE id=?', [$id]);
    }

    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll('SELECT * FROM testimonials WHERE family_id=? ORDER BY created_at DESC', [$familyId]);
    }

    /** Soumission par une famille — toujours en attente de modération, jamais publiée directement. */
    public static function submit(int $familyId, string $authorName, ?string $authorRole, string $content, ?int $rating): int
    {
        return Database::insert(
            "INSERT INTO testimonials (family_id, author_name, author_role, content, rating, status) VALUES (?,?,?,?,?,'pending')",
            [$familyId, $authorName, $authorRole, $content, $rating]
        );
    }

    /** Saisie directe par l'administrateur système — publiable immédiatement. */
    public static function createManual(string $authorName, ?string $authorRole, string $content, ?int $rating, bool $approveNow): int
    {
        return Database::insert(
            'INSERT INTO testimonials (author_name, author_role, content, rating, status) VALUES (?,?,?,?,?)',
            [$authorName, $authorRole, $content, $rating, $approveNow ? 'approved' : 'pending']
        );
    }

    public static function approve(int $id): void
    {
        Database::execute("UPDATE testimonials SET status='approved' WHERE id=?", [$id]);
    }

    public static function reject(int $id): void
    {
        Database::execute("UPDATE testimonials SET status='rejected' WHERE id=?", [$id]);
    }

    public static function updateSortOrder(int $id, int $sortOrder): void
    {
        Database::execute('UPDATE testimonials SET sort_order=? WHERE id=?', [$sortOrder, $id]);
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM testimonials WHERE id=?', [$id]);
    }
}
