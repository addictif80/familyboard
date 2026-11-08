<?php
namespace App\Models;

use App\Core\Database;

class Contact
{
    private static array $COLORS = [
        '#4A90D9','#E74C3C','#27AE60','#F39C12','#9B59B6',
        '#1ABC9C','#E67E22','#2ECC71','#3498DB','#E91E63',
    ];

    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT * FROM contacts WHERE family_id=? ORDER BY is_system DESC, last_name ASC, first_name ASC',
            [$familyId]
        );
    }

    /** Seed emergency numbers for a family if not already present. */
    public static function seedEmergency(int $familyId, int $userId): void
    {
        $existing = Database::fetch(
            'SELECT id FROM contacts WHERE family_id=? AND is_system=1 LIMIT 1',
            [$familyId]
        );
        if ($existing) return;

        $numbers = [
            ['first_name' => 'Police secours', 'phone' => '17',  'color' => '#2563EB', 'notes' => 'Police nationale'],
            ['first_name' => 'Pompiers',        'phone' => '18',  'color' => '#DC2626', 'notes' => 'Sapeurs-pompiers'],
            ['first_name' => 'SAMU',            'phone' => '15',  'color' => '#16A34A', 'notes' => 'Aide médicale urgente'],
            ['first_name' => 'Urgences EU',     'phone' => '112', 'color' => '#EA580C', 'notes' => 'Numéro européen d\'urgence'],
        ];
        foreach ($numbers as $n) {
            Database::execute(
                'INSERT INTO contacts (family_id, user_id, first_name, last_name, phone, color, notes, is_system)
                 VALUES (?,?,?,\'\',?,?,?,1)',
                [$familyId, $userId, $n['first_name'], $n['phone'], $n['color'], $n['notes']]
            );
        }
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM contacts WHERE id=?', [$id]);
    }

    public static function create(int $familyId, int $userId, array $data): int
    {
        $color = $data['color'] ?? self::$COLORS[array_rand(self::$COLORS)];
        return Database::insert(
            'INSERT INTO contacts (family_id, user_id, first_name, last_name, email, phone, phone2, address, birthday, notes, color)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                $familyId, $userId,
                $data['first_name'],
                $data['last_name']  ?? '',
                $data['email']      ?? null,
                $data['phone']      ?? null,
                $data['phone2']     ?? null,
                $data['address']    ?? null,
                $data['birthday']   ?? null,
                $data['notes']      ?? null,
                $color,
            ]
        );
    }

    public static function update(int $id, array $data): void
    {
        Database::execute(
            'UPDATE contacts SET first_name=?, last_name=?, email=?, phone=?, phone2=?, address=?, birthday=?, notes=?, color=? WHERE id=?',
            [
                $data['first_name'],
                $data['last_name']  ?? '',
                $data['email']      ?? null,
                $data['phone']      ?? null,
                $data['phone2']     ?? null,
                $data['address']    ?? null,
                $data['birthday']   ?? null,
                $data['notes']      ?? null,
                $data['color']      ?? '#4A90D9',
                $id,
            ]
        );
    }

    public static function updateAvatar(int $id, string $path): void
    {
        Database::execute('UPDATE contacts SET avatar=? WHERE id=?', [$path, $id]);
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM contacts WHERE id=?', [$id]);
    }

    public static function toVcard(array $c): string
    {
        $fn  = trim($c['first_name'] . ' ' . $c['last_name']);
        $vcf = "BEGIN:VCARD\r\nVERSION:3.0\r\n";
        $vcf .= "FN:" . self::vcfEscape($fn) . "\r\n";
        $vcf .= "N:" . self::vcfEscape($c['last_name']) . ";" . self::vcfEscape($c['first_name']) . ";;;\r\n";
        if ($c['phone'])   $vcf .= "TEL;TYPE=CELL:" . self::vcfEscape($c['phone'])  . "\r\n";
        if ($c['phone2'])  $vcf .= "TEL;TYPE=WORK:" . self::vcfEscape($c['phone2']) . "\r\n";
        if ($c['email'])   $vcf .= "EMAIL:" . self::vcfEscape($c['email'])   . "\r\n";
        if ($c['address']) $vcf .= "ADR;TYPE=HOME:;;" . self::vcfEscape(str_replace(["\r\n","\n"], ' ', $c['address'])) . ";;;;\r\n";
        if ($c['birthday'])$vcf .= "BDAY:" . str_replace('-', '', $c['birthday']) . "\r\n";
        if ($c['notes'])   $vcf .= "NOTE:" . self::vcfEscape($c['notes']) . "\r\n";
        $vcf .= "END:VCARD\r\n";
        return $vcf;
    }

    private static function vcfEscape(string $s): string
    {
        return str_replace([',', ';', "\n", "\r"], ['\\,', '\\;', '\\n', ''], $s);
    }

    /** Importe un fichier .vcf (un ou plusieurs contacts — export standard de Google Contacts,
     *  iCloud, Outlook ou du répertoire du téléphone) : pour quitter une autre app sans avoir à
     *  ressaisir ses contacts à la main. Retourne le nombre de contacts créés. */
    public static function importVcf(int $familyId, int $userId, string $vcfContent): int
    {
        $count = 0;
        foreach (self::splitVcards($vcfContent) as $block) {
            $parsed = self::parseVcard($block);
            if (!$parsed || $parsed['first_name'] === '') continue;
            self::create($familyId, $userId, $parsed);
            $count++;
        }
        return $count;
    }

    private static function splitVcards(string $content): array
    {
        preg_match_all('/BEGIN:VCARD.*?END:VCARD/is', $content, $matches);
        return $matches[0];
    }

    private static function parseVcard(string $block): ?array
    {
        // RFC 6350 : une ligne qui commence par un espace/tab est la continuation de la précédente.
        $block = preg_replace('/\r?\n[ \t]/', '', $block);
        $lines = preg_split('/\r\n|\n|\r/', $block);

        $data = ['first_name' => '', 'last_name' => ''];
        foreach ($lines as $line) {
            if (!str_contains($line, ':')) continue;
            [$prop, $value] = explode(':', $line, 2);
            $value = self::vcfUnescape($value);
            $propName = strtoupper(explode(';', $prop)[0]);
            $propAttrs = strtoupper($prop);

            switch ($propName) {
                case 'N':
                    $parts = explode(';', $value);
                    $data['last_name']  = $parts[0] ?? '';
                    $data['first_name'] = $parts[1] ?? '';
                    break;
                case 'FN':
                    if ($data['first_name'] === '' && $data['last_name'] === '') {
                        $spacePos = strrpos($value, ' ');
                        $data['first_name'] = $spacePos !== false ? substr($value, 0, $spacePos) : $value;
                        $data['last_name']  = $spacePos !== false ? substr($value, $spacePos + 1) : '';
                    }
                    break;
                case 'TEL':
                    if (str_contains($propAttrs, 'WORK') && empty($data['phone2'])) $data['phone2'] = $value;
                    elseif (empty($data['phone'])) $data['phone'] = $value;
                    break;
                case 'EMAIL':
                    if (empty($data['email'])) $data['email'] = $value;
                    break;
                case 'ADR':
                    if (empty($data['address'])) {
                        $data['address'] = trim(str_replace(';', ' ', $value));
                    }
                    break;
                case 'BDAY':
                    $digits = preg_replace('/\D/', '', $value);
                    if (strlen($digits) === 8) {
                        $data['birthday'] = substr($digits, 0, 4) . '-' . substr($digits, 4, 2) . '-' . substr($digits, 6, 2);
                    }
                    break;
                case 'NOTE':
                    $data['notes'] = $value;
                    break;
            }
        }

        return $data['first_name'] !== '' || $data['last_name'] !== '' ? $data : null;
    }

    private static function vcfUnescape(string $s): string
    {
        return str_replace(['\\,', '\\;', '\\n', '\\N'], [',', ';', "\n", "\n"], trim($s));
    }
}
