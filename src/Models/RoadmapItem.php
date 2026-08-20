<?php
namespace App\Models;

use App\Core\Database;

class RoadmapItem
{
    public static function getAll(): array
    {
        return Database::fetchAll(
            "SELECT * FROM roadmap_items
             ORDER BY FIELD(status,'in_progress','idea','done'), sort_order, id"
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM roadmap_items WHERE id=?', [$id]);
    }

    public static function create(string $title, ?string $description, string $status): int
    {
        return Database::insert(
            'INSERT INTO roadmap_items (title, description, status) VALUES (?,?,?)',
            [$title, $description, $status]
        );
    }

    public static function update(int $id, string $title, ?string $description, string $status): void
    {
        Database::execute(
            'UPDATE roadmap_items SET title=?, description=?, status=? WHERE id=?',
            [$title, $description, $status, $id]
        );
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM roadmap_items WHERE id=?', [$id]);
    }
}
