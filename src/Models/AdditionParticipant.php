<?php
namespace App\Models;

use App\Core\Database;

/**
 * Un participant à une addition — membre de la famille créatrice, co-parent, membre d'une
 * famille amie, ou personne externe invitée par e-mail (via AdditionGuest). Toujours soumis à
 * acceptation individuelle, quel que soit le type de participant.
 */
class AdditionParticipant
{
    public static function getByAddition(int $additionId): array
    {
        return Database::fetchAll(
            "SELECT ap.*,
                    COALESCE(u.name, g.name, g.email) as display_name,
                    u.color as user_color,
                    g.email as guest_email
             FROM addition_participants ap
             LEFT JOIN users u ON u.id = ap.user_id
             LEFT JOIN addition_guests g ON g.id = ap.guest_id
             WHERE ap.addition_id=?
             ORDER BY ap.created_at",
            [$additionId]
        );
    }

    /** Invite un membre existant (famille créatrice, co-parent, ou famille amie). */
    public static function inviteUser(int $additionId, int $userId, float $shareValue, int $invitedBy): int
    {
        $id = Database::insert(
            'INSERT INTO addition_participants (addition_id, user_id, share_value, invited_by) VALUES (?,?,?,?)',
            [$additionId, $userId, $shareValue, $invitedBy]
        );
        $addition = Addition::getById($additionId);
        Notification::create(
            $userId, 'addition',
            'Invitation à une addition',
            'Vous êtes invité(e) à participer à « ' . ($addition['title'] ?? '') . ' ».',
            BASE_URL . '/additions'
        );
        return $id;
    }

    /** Invite une personne sans compte par e-mail — génère (ou réutilise) son accès permanent
     *  à l'espace additions et lui envoie le lien par e-mail. */
    public static function inviteGuest(int $additionId, string $email, string $name, float $shareValue, int $invitedBy): int
    {
        $guest = AdditionGuest::findOrCreate($email, $name);
        $id = Database::insert(
            'INSERT INTO addition_participants (addition_id, guest_id, share_value, invited_by) VALUES (?,?,?,?)',
            [$additionId, $guest['id'], $shareValue, $invitedBy]
        );

        $addition = Addition::getById($additionId);
        $inviter = \App\Models\User::findById($invitedBy);
        $link = BASE_URL . '/additions/espace/' . $guest['access_token'];
        $html = \App\Core\EmailLayout::render(
            'Invitation à partager une addition',
            '<p>Bonjour,</p>'
            . '<p>' . htmlspecialchars($inviter['name'] ?? 'Quelqu\'un') . ' vous invite à participer à « ' . htmlspecialchars($addition['title'] ?? '') . ' » sur FamilyBoard.</p>'
            . '<p>Ce lien vous donne accès à votre espace additions personnel, où vous retrouverez toutes les additions partagées avec vous et le détail de la répartition.</p>'
            . '<p style="font-size:13px;color:#6b6558">Votre nom et votre adresse e-mail sont conservés pour cet usage tant que vous restez invité(e) à au moins une addition. '
            . 'Pour toute question ou pour demander la suppression de vos données, consultez notre '
            . '<a href="' . BASE_URL . '/confidentialite">politique de confidentialité</a>, qui indique comment nous contacter.</p>',
            ['url' => $link, 'label' => 'Accéder à mon espace additions']
        );
        try {
            \App\Core\Mail::send((int)$addition['family_id'], $guest['email'], $guest['name'] ?: $guest['email'], 'Invitation à partager une addition', $html);
        } catch (\Throwable $e) {
            error_log('Addition guest invite email error: ' . $e->getMessage());
        }

        return $id;
    }

    public static function respond(int $participantId, string $decision, ?int $userId = null, ?int $guestId = null): bool
    {
        $row = Database::fetch('SELECT * FROM addition_participants WHERE id=?', [$participantId]);
        if (!$row || $row['status'] !== 'pending') return false;
        if ($userId !== null && (int)$row['user_id'] !== $userId) return false;
        if ($guestId !== null && (int)$row['guest_id'] !== $guestId) return false;

        $status = $decision === 'accept' ? 'accepted' : 'declined';
        Database::execute('UPDATE addition_participants SET status=?, responded_at=NOW() WHERE id=?', [$status, $participantId]);
        return true;
    }

    /** Toutes les additions (acceptées ou en attente) où cette personne est participante. */
    public static function getForUser(int $userId): array
    {
        return Database::fetchAll(
            "SELECT ap.*, a.title, a.total_amount, a.split_mode, a.is_cagnotte, f.name as family_name
             FROM addition_participants ap
             JOIN additions a ON a.id = ap.addition_id
             JOIN families f ON f.id = a.family_id
             WHERE ap.user_id=?
             ORDER BY ap.created_at DESC",
            [$userId]
        );
    }

    /** Espace additions d'un invité externe (toutes familles confondues, via son guest_id). */
    public static function getForGuest(int $guestId): array
    {
        return Database::fetchAll(
            "SELECT ap.*, a.title, a.description, a.total_amount, a.split_mode, a.is_cagnotte,
                    a.cagnotte_goal, a.cagnotte_methods, a.cagnotte_iban, a.cagnotte_wero_phone,
                    f.name as family_name
             FROM addition_participants ap
             JOIN additions a ON a.id = ap.addition_id
             JOIN families f ON f.id = a.family_id
             WHERE ap.guest_id=?
             ORDER BY ap.created_at DESC",
            [$guestId]
        );
    }
}
