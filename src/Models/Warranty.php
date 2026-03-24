<?php
namespace App\Models;

use App\Core\Database;

class Warranty
{
    private static function storageDir(int $familyId): string
    {
        $dir = BASE_PATH . '/storage/warranties/' . $familyId;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        return $dir;
    }

    public static function getAll(int $familyId, string $search = ''): array
    {
        if ($search !== '') {
            // Try FULLTEXT first, fall back to LIKE
            try {
                $rows = Database::fetchAll(
                    'SELECT w.*, u.name as user_name, u.color as user_color
                     FROM warranties w JOIN users u ON u.id = w.user_id
                     WHERE w.family_id = ?
                       AND MATCH(w.title, w.brand, w.model, w.serial_number, w.store, w.ocr_text)
                           AGAINST(? IN BOOLEAN MODE)
                     ORDER BY w.purchase_date DESC',
                    [$familyId, $search . '*']
                );
            } catch (\Throwable) {
                $like = '%' . $search . '%';
                $rows = Database::fetchAll(
                    'SELECT w.*, u.name as user_name, u.color as user_color
                     FROM warranties w JOIN users u ON u.id = w.user_id
                     WHERE w.family_id = ?
                       AND (w.title LIKE ? OR w.brand LIKE ? OR w.model LIKE ?
                            OR w.serial_number LIKE ? OR w.store LIKE ?)
                     ORDER BY w.purchase_date DESC',
                    [$familyId, $like, $like, $like, $like, $like]
                );
            }
        } else {
            $rows = Database::fetchAll(
                'SELECT w.*, u.name as user_name, u.color as user_color
                 FROM warranties w JOIN users u ON u.id = w.user_id
                 WHERE w.family_id = ?
                 ORDER BY w.warranty_end_date IS NULL, w.warranty_end_date ASC, w.purchase_date DESC',
                [$familyId]
            );
        }
        return array_map([self::class, 'decorate'], $rows);
    }

    public static function findById(int $id, int $familyId): ?array
    {
        $row = Database::fetch(
            'SELECT w.*, u.name as user_name, u.color as user_color
             FROM warranties w JOIN users u ON u.id = w.user_id
             WHERE w.id = ? AND w.family_id = ?',
            [$id, $familyId]
        );
        return $row ? self::decorate($row) : null;
    }

    public static function getExpiringSoon(int $familyId, int $days = 30): array
    {
        $rows = Database::fetchAll(
            'SELECT w.*, u.name as user_name
             FROM warranties w JOIN users u ON u.id = w.user_id
             WHERE w.family_id = ?
               AND w.warranty_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY w.warranty_end_date ASC',
            [$familyId, $days]
        );
        return array_map([self::class, 'decorate'], $rows);
    }

    public static function create(int $familyId, int $userId, array $data, ?array $file = null): int
    {
        $filePath = null;
        $fileOrig = null;
        $fileMime = null;

        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            [$filePath, $fileOrig, $fileMime] = self::saveFile($familyId, $file);
        }

        $endDate = self::computeEndDate($data['purchase_date'] ?? null, (int)($data['warranty_months'] ?? 24));

        Database::execute(
            'INSERT INTO warranties
             (family_id, user_id, title, brand, model, serial_number, store,
              purchase_date, warranty_months, warranty_end_date, price, notes,
              file_path, file_original, file_mime, ocr_text)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $familyId, $userId,
                $data['title'],
                $data['brand']         ?? null,
                $data['model']         ?? null,
                $data['serial_number'] ?? null,
                $data['store']         ?? null,
                $data['purchase_date'] ?? null,
                (int)($data['warranty_months'] ?? 24),
                $endDate,
                $data['price']   ? (float)$data['price'] : null,
                $data['notes']   ?? null,
                $filePath, $fileOrig, $fileMime,
                $data['ocr_text'] ?? null,
            ]
        );
        return (int)Database::lastInsertId();
    }

    public static function update(int $id, int $familyId, array $data, ?array $file = null): void
    {
        $existing = self::findById($id, $familyId);
        if (!$existing) return;

        $filePath = $existing['file_path'];
        $fileOrig = $existing['file_original'];
        $fileMime = $existing['file_mime'];

        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            // Delete old file
            if ($filePath) @unlink(BASE_PATH . $filePath);
            [$filePath, $fileOrig, $fileMime] = self::saveFile($familyId, $file);
        }

        $endDate = self::computeEndDate($data['purchase_date'] ?? null, (int)($data['warranty_months'] ?? 24));

        Database::execute(
            'UPDATE warranties SET
             title=?, brand=?, model=?, serial_number=?, store=?,
             purchase_date=?, warranty_months=?, warranty_end_date=?,
             price=?, notes=?, file_path=?, file_original=?, file_mime=?,
             ocr_text=COALESCE(?, ocr_text)
             WHERE id=? AND family_id=?',
            [
                $data['title'],
                $data['brand']         ?? null,
                $data['model']         ?? null,
                $data['serial_number'] ?? null,
                $data['store']         ?? null,
                $data['purchase_date'] ?? null,
                (int)($data['warranty_months'] ?? 24),
                $endDate,
                $data['price']   ? (float)$data['price'] : null,
                $data['notes']   ?? null,
                $filePath, $fileOrig, $fileMime,
                isset($data['ocr_text']) && $data['ocr_text'] !== '' ? $data['ocr_text'] : null,
                $id, $familyId,
            ]
        );
    }

    public static function delete(int $id, int $familyId): void
    {
        $row = Database::fetch('SELECT file_path FROM warranties WHERE id=? AND family_id=?', [$id, $familyId]);
        if ($row && $row['file_path']) {
            @unlink(BASE_PATH . $row['file_path']);
        }
        Database::execute('DELETE FROM warranties WHERE id=? AND family_id=?', [$id, $familyId]);
    }

    // ── OCR ──────────────────────────────────────────────────────────────────

    /** Common install paths — web server PATH is often minimal */
    private static array $binaryPaths = [
        '/usr/bin', '/usr/local/bin', '/opt/homebrew/bin', '/snap/bin',
        '/usr/local/tesseract/bin',
    ];

    private static function findBinary(string $name): string
    {
        foreach (self::$binaryPaths as $dir) {
            $path = $dir . '/' . $name;
            if (is_executable($path)) return $path;
        }
        // Last resort: ask the shell
        $found = trim((string)shell_exec('which ' . escapeshellarg($name) . ' 2>/dev/null'));
        return $found ?: '';
    }

    public static function runOcr(string $tmpPath, string $mime): string
    {
        // PDF: try pdftotext (poppler-utils)
        if ($mime === 'application/pdf') {
            $bin = self::findBinary('pdftotext');
            if ($bin) {
                $out  = [];
                $code = 0;
                exec($bin . ' ' . escapeshellarg($tmpPath) . ' - 2>/dev/null', $out, $code);
                $text = trim(implode("\n", $out));
                if ($text !== '') return $text;
            }
        }

        // Image (or PDF fallback): try tesseract
        $bin = self::findBinary('tesseract');
        if ($bin) {
            $outBase = sys_get_temp_dir() . '/ocr_' . uniqid();
            $cmd     = $bin . ' ' . escapeshellarg($tmpPath)
                     . ' ' . escapeshellarg($outBase)
                     . ' -l fra 2>/dev/null';
            exec($cmd);
            $text = @file_get_contents($outBase . '.txt') ?: '';
            @unlink($outBase . '.txt');
            return trim($text);
        }

        return '';
    }

    /** Returns debug info about binary availability (used by /api/warranties/ocr-check) */
    public static function ocrInfo(): array
    {
        return [
            'tesseract'  => self::findBinary('tesseract')  ?: null,
            'pdftotext'  => self::findBinary('pdftotext')  ?: null,
            'php_exec'   => function_exists('exec'),
            'shell_exec' => function_exists('shell_exec'),
            'tmp_dir'    => sys_get_temp_dir(),
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private static function saveFile(int $familyId, array $file): array
    {
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif',
            'application/pdf',
        ];
        if (!in_array($file['type'], $allowedMimes, true)) {
            throw new \RuntimeException('Type de fichier non autorisé.');
        }
        if ($file['size'] > 20 * 1024 * 1024) {
            throw new \RuntimeException('Fichier trop volumineux (max 20 Mo).');
        }

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $dir      = self::storageDir($familyId);
        $dest     = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('Erreur lors de l\'enregistrement du fichier.');
        }

        // Relative path from BASE_PATH for storage
        $rel = '/storage/warranties/' . $familyId . '/' . $filename;
        return [$rel, $file['name'], $file['type']];
    }

    private static function computeEndDate(?string $purchaseDate, int $months): ?string
    {
        if (!$purchaseDate || $months <= 0) return null;
        try {
            $dt = new \DateTime($purchaseDate);
            $dt->modify("+{$months} months");
            return $dt->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function decorate(array $row): array
    {
        $today = new \DateTime('today');
        $row['days_remaining'] = null;
        $row['status'] = 'none'; // no end date

        if (!empty($row['warranty_end_date'])) {
            $end = new \DateTime($row['warranty_end_date']);
            $diff = (int)$today->diff($end)->days * ($end >= $today ? 1 : -1);
            $row['days_remaining'] = $diff;
            if ($diff < 0)       $row['status'] = 'expired';
            elseif ($diff <= 30) $row['status'] = 'critical';
            elseif ($diff <= 90) $row['status'] = 'soon';
            else                 $row['status'] = 'ok';
        }
        return $row;
    }
}
