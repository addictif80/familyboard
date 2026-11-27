<?php
namespace App\Models;

use App\Core\Database;

/**
 * Agrège les dates d'anniversaire connues pour une famille — membres, bébés suivis, contacts du
 * répertoire — et calcule la prochaine occurrence de chacune (indépendamment de l'année de
 * naissance stockée, qui ne sert qu'à calculer l'âge atteint).
 */
class Birthday
{
    /** @return array<int, array{type:string,id:int,name:string,date:string,days_until:int,age:int,color:string,avatar:?string}> triés par proximité */
    public static function getUpcoming(int $familyId, int $daysAhead = 60): array
    {
        $rows = [];

        foreach (Database::fetchAll('SELECT id, name, birthday, color, avatar FROM users WHERE family_id=? AND birthday IS NOT NULL', [$familyId]) as $u) {
            $rows[] = self::withNextOccurrence('user', (int)$u['id'], $u['name'], $u['birthday'], $u['color'], $u['avatar']);
        }
        foreach (Database::fetchAll('SELECT id, name, birth_date FROM babies WHERE family_id=? AND birth_date IS NOT NULL', [$familyId]) as $b) {
            $rows[] = self::withNextOccurrence('baby', (int)$b['id'], $b['name'], $b['birth_date'], '#F39C12', null);
        }
        foreach (Database::fetchAll("SELECT id, first_name, last_name, birthday, color FROM contacts WHERE family_id=? AND birthday IS NOT NULL", [$familyId]) as $c) {
            $name = trim($c['first_name'] . ' ' . $c['last_name']);
            $rows[] = self::withNextOccurrence('contact', (int)$c['id'], $name, $c['birthday'], $c['color'], null);
        }

        $rows = array_values(array_filter($rows, fn($r) => $r['days_until'] <= $daysAhead));
        usort($rows, fn($a, $b) => $a['days_until'] <=> $b['days_until']);
        return $rows;
    }

    private static function withNextOccurrence(string $type, int $id, string $name, string $birthDate, ?string $color, ?string $avatar): array
    {
        $today = new \DateTimeImmutable('today');
        $birth = new \DateTimeImmutable($birthDate);

        $next = $birth->setDate((int)$today->format('Y'), (int)$birth->format('m'), (int)$birth->format('d'));
        if ($next < $today) {
            $next = $next->modify('+1 year');
        }

        return [
            'type'       => $type,
            'id'         => $id,
            'name'       => $name,
            'date'       => $next->format('Y-m-d'),
            'days_until' => (int)$today->diff($next)->days,
            'age'        => (int)$next->format('Y') - (int)$birth->format('Y'),
            'color'      => $color ?: '#4A90D9',
            'avatar'     => $avatar,
        ];
    }
}
