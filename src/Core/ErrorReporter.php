<?php
namespace App\Core;

use App\Models\SupportTicket;

/**
 * Surveillance continue des erreurs techniques : dès qu'un problème survient
 * pendant la navigation d'un utilisateur identifié (exception PHP non gérée,
 * erreur fatale, erreur JS côté navigateur…), un ticket de support est ouvert
 * automatiquement en son nom, pour que l'équipe support en soit informée sans
 * action de sa part. Complété par un signalement manuel en un clic
 * (reportManual) pour les cas — silencieux ou visuels — qu'aucune détection
 * automatique ne peut voir (ex : page qui s'affiche vide sans erreur JS).
 *
 * Ne s'applique qu'aux utilisateurs connectés : impossible d'attribuer un
 * ticket à un visiteur anonyme (page de connexion, lien public, etc.).
 */
class ErrorReporter
{
    /** En dessous de ce délai, une même erreur détectée automatiquement pour le même utilisateur est ignorée (anti-spam). */
    private const DEDUP_WINDOW_MINUTES = 5;
    /** Au-delà, une nouvelle occurrence rouvre le ticket existant plutôt que d'en créer un nouveau. */
    private const REOPEN_WINDOW_DAYS = 7;

    /** Erreur détectée automatiquement (exception serveur, erreur JS…) — dédupliquée. */
    public static function report(string $source, string $message, array $context = []): void
    {
        $message = trim($message);
        if ($message === '') return;

        try {
            $user = Session::isLoggedIn() ? Session::user() : null;
            if (!$user) return;

            $hash = self::hash($source, $message, $context);
            $existing = SupportTicket::findByUserAndHash((int)$user['id'], $hash);

            if ($existing) {
                // support_tickets.updated_at/created_at are stored in UTC (see
                // Database::getInstance) — must be parsed as UTC explicitly,
                // regardless of the family's local date_default_timezone_set().
                $minutesSinceUpdate = (time() - self::utcTimestamp($existing['updated_at'])) / 60;
                if ($minutesSinceUpdate < self::DEDUP_WINDOW_MINUTES) return;

                $daysSinceCreated = (time() - self::utcTimestamp($existing['created_at'])) / 86400;
                if ($daysSinceCreated <= self::REOPEN_WINDOW_DAYS) {
                    SupportTicket::addMessage(
                        (int)$existing['id'],
                        (int)$user['id'],
                        false,
                        "🔁 Cette erreur s'est reproduite.\n\n" . self::formatDetails($message, $context)
                    );
                    if ($existing['status'] === 'closed') {
                        SupportTicket::setStatus((int)$existing['id'], 'open');
                    }
                    return;
                }
            }

            $label = $source === 'client' ? 'Erreur technique (navigateur)' : 'Erreur technique (serveur)';
            $ticketId = SupportTicket::create(
                (int)$user['family_id'],
                (int)$user['id'],
                $label . ' — ' . mb_strimwidth($message, 0, 80, '…'),
                self::formatDetails($message, $context)
            );
            SupportTicket::markSource($ticketId, 'auto', $hash);
        } catch (\Throwable) {
            // Le signalement d'erreur ne doit jamais lui-même faire échouer la requête.
        }
    }

    /**
     * Signalement volontaire en un clic ("Signaler un problème"), pour un
     * utilisateur qui ne sait pas décrire techniquement ce qui ne va pas.
     * Toujours créé (jamais dédupliqué) : c'est une action explicite.
     */
    public static function reportManual(string $description, array $diagnostics = []): void
    {
        try {
            $user = Session::isLoggedIn() ? Session::user() : null;
            if (!$user) return;

            $description = trim($description) ?: '(aucune description fournie)';
            $subject = 'Signalement utilisateur — ' . mb_strimwidth($description, 0, 80, '…');

            $lines = [
                "L'utilisateur a signalé un problème via le bouton \"Signaler un problème\".",
                '',
                'Description : ' . $description,
                '',
                '— Diagnostic technique joint automatiquement —',
            ];
            foreach ($diagnostics as $k => $v) {
                if ($v === null || $v === '') continue;
                $lines[] = $k . ' : ' . (is_array($v) ? implode(', ', $v) : $v);
            }
            $lines[] = 'Horodatage : ' . date('d/m/Y H:i:s');

            $ticketId = SupportTicket::create((int)$user['family_id'], (int)$user['id'], $subject, implode("\n", $lines));
            SupportTicket::markSource($ticketId, 'manual');
        } catch (\Throwable) {
            // Idem : ne jamais faire échouer la requête à cause du signalement.
        }
    }

    private static function utcTimestamp(string $datetime): int
    {
        return (new \DateTime($datetime, new \DateTimeZone('UTC')))->getTimestamp();
    }

    private static function hash(string $source, string $message, array $context): string
    {
        $key = $source . '|' . $message . '|' . ($context['file'] ?? '') . ':' . ($context['line'] ?? '');
        return substr(sha1($key), 0, 32);
    }

    private static function formatDetails(string $message, array $context): string
    {
        $lines = [
            'Un problème technique a été détecté automatiquement pendant votre navigation.',
            '',
            'Détails : ' . $message,
        ];
        if (!empty($context['url']))  $lines[] = 'Page : ' . $context['url'];
        if (!empty($context['file'])) $lines[] = 'Fichier : ' . $context['file'] . (isset($context['line']) ? ':' . $context['line'] : '');
        $lines[] = 'Horodatage : ' . date('d/m/Y H:i:s');
        return implode("\n", $lines);
    }
}
