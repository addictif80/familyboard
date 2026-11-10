<?php
namespace App\Core;

/**
 * Détermination de la majorité d'un utilisateur à partir de users.birthday — utilisée
 * uniquement pour bloquer/autoriser les actions de paiement du stockage perso (achat pour
 * soi-même ou validation d'une demande d'un mineur). Avant cette fonctionnalité, birthday
 * ne servait qu'à l'affichage (fête, âge) et aucune logique d'accès n'en dépendait.
 *
 * Règle explicitement demandée : en l'absence de date de naissance, l'utilisateur est traité
 * comme mineur et bloqué de tout paiement (même pour lui-même) jusqu'à ce qu'un adulte renseigne
 * sa date de naissance.
 */
class Majority
{
    public const MAJORITY_AGE = 18;

    /** true = majeur confirmé, false = mineur ou date de naissance absente (bloqué par défaut). */
    public static function isAdult(?string $birthday): bool
    {
        if (!$birthday) return false;

        try {
            $birth = new \DateTimeImmutable($birthday);
        } catch (\Throwable $e) {
            return false;
        }

        $age = $birth->diff(new \DateTimeImmutable('now'))->y;
        return $age >= self::MAJORITY_AGE;
    }

    /** true seulement si une date de naissance est renseignée ET valide. */
    public static function hasKnownBirthday(?string $birthday): bool
    {
        if (!$birthday) return false;
        try {
            new \DateTimeImmutable($birthday);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
