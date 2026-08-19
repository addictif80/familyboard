<?php
namespace App\Models;

use App\Core\Database;

/**
 * System-wide (not per-family) customizable subject + message text for
 * each automatic email type. Only the text is editable here — the
 * graphical style is fixed in App\Core\EmailLayout and never exposed
 * through this model or its admin UI.
 */
class EmailContent
{
    private static array $defaults = [
        'invitation' => [
            'subject' => '{{sender_name}} vous invite à rejoindre {{family_name}} sur FamilyBoard',
            'message' => "Bonjour,\n\n{{sender_name}} vous invite à rejoindre la famille {{family_name}} sur FamilyBoard.\n\nCe lien est valide 7 jours. Si vous ne connaissez pas {{sender_name}}, ignorez ce message.",
        ],
        'custody_invitation' => [
            'subject' => '{{sender_name}} vous invite à un accès restreint sur FamilyBoard',
            'message' => "Bonjour,\n\n{{sender_name}} vous invite à accéder, sur FamilyBoard, au suivi de garde partagée de {{child_list}}.\n\nCet accès est volontairement limité au calendrier de garde, aux propositions de garde, au journal parental et aux documents/évènements liés à cet enfant — vous n'aurez pas accès au reste des données de la famille {{family_name}}.",
        ],
        'event_reminder' => [
            'subject' => '⏰ Rappel : {{event_title}} demain',
            'message' => "Bonjour {{user_name}},\n\nUn événement familial approche dans moins de 24 heures :",
        ],
        'task_reminder' => [
            'subject' => '✅ Tâche en attente depuis 7 jours : {{task_name}}',
            'message' => "Bonjour {{user_name}},\n\nLa tâche suivante n'a pas été complétée depuis plus d'une semaine :",
        ],
        'shopping_reminder' => [
            'subject' => '🛒 La liste de courses {{list_name}} attend !',
            'message' => "Bonjour {{user_name}},\n\nLa liste de courses {{list_name}} contient des articles depuis plus d'une semaine :",
        ],
        'budget_recurring' => [
            'subject' => 'Rappel : {{type_label}} « {{title}} » de {{amount}} le {{due_date}}',
            'message' => "Bonjour {{user_name}},\n\nRappel : votre {{type_label}} « {{title}} » de {{amount}} est prévu le {{due_date}}.",
        ],
        'event_tomorrow_digest' => [
            'subject' => '📅 {{event_count}} événement(s) demain',
            'message' => "Bonjour {{user_name}},\n\nVoici les événements prévus demain :",
        ],
        'wall_post' => [
            'subject' => 'Nouveau post de {{author_name}}',
            'message' => "{{author_name}} a publié sur le mur familial :\n\n{{content}}",
        ],
        'birthday_reminder' => [
            'subject' => "🎂 L'anniversaire de {{birthday_name}} approche",
            'message' => "Bonjour {{user_name}},\n\n{{birthday_name}} fête ses {{birthday_age}} ans le {{birthday_date}} !",
        ],
        'weekly_digest' => [
            'subject' => '🗓️ Votre semaine avec {{family_name}}',
            'message' => "Bonjour {{user_name}},\n\nVoici un résumé de la semaine à venir pour {{family_name}} :",
        ],
        'weekly_digest_coparent' => [
            'subject' => '🗓️ La semaine de {{child_names}}',
            'message' => "Bonjour {{user_name}},\n\nVoici le planning de garde et les rendez-vous de la semaine pour {{child_names}} :",
        ],
        'password_reset' => [
            'subject' => 'Réinitialisation de votre mot de passe FamilyBoard',
            'message' => "Bonjour {{user_name}},\n\nVous avez demandé la réinitialisation de votre mot de passe. Ce lien est valable 1 heure et à usage unique.\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail : votre mot de passe reste inchangé.",
        ],
        'account_deleted' => [
            'subject' => 'Votre compte FamilyBoard a été supprimé',
            'message' => "Bonjour {{user_name}},\n\nVotre compte FamilyBoard a été supprimé{{deleted_by}}, pour le motif suivant :\n\n{{reason}}\n\nSi vous pensez qu'il s'agit d'une erreur, contactez le support.",
        ],
    ];

    public static function get(string $type): array
    {
        $custom = Database::fetch('SELECT * FROM email_content WHERE type = ?', [$type]);
        return $custom ?: (self::$defaults[$type] ?? ['subject' => '', 'message' => '']);
    }

    public static function getAll(): array
    {
        $result = [];
        foreach (array_keys(self::$defaults) as $type) {
            $custom = Database::fetch('SELECT * FROM email_content WHERE type = ?', [$type]);
            $result[$type] = $custom ?: array_merge(self::$defaults[$type], ['type' => $type]);
            $result[$type]['is_custom'] = (bool)$custom;
            $result[$type]['label'] = self::label($type);
        }
        return $result;
    }

    public static function save(string $type, string $subject, string $message): void
    {
        Database::execute(
            'INSERT INTO email_content (type, subject, message) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE subject=VALUES(subject), message=VALUES(message), updated_at=NOW()',
            [$type, $subject, $message]
        );
    }

    public static function reset(string $type): void
    {
        Database::execute('DELETE FROM email_content WHERE type = ?', [$type]);
    }

    /**
     * Returns ['subject' => raw text with vars substituted, 'message_html' => escaped + nl2br HTML fragment]
     */
    public static function render(string $type, array $vars): array
    {
        $tpl = self::get($type);

        $subject = $tpl['subject'];
        foreach ($vars as $k => $v) {
            $subject = str_replace('{{' . $k . '}}', (string)$v, $subject);
        }

        $messageHtml = htmlspecialchars($tpl['message'], ENT_QUOTES, 'UTF-8');
        foreach ($vars as $k => $v) {
            $messageHtml = str_replace('{{' . $k . '}}', htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'), $messageHtml);
        }

        return [
            'subject'      => $subject,
            'message_html' => nl2br($messageHtml),
        ];
    }

    public static function label(string $type): string
    {
        return match($type) {
            'invitation'           => 'Invitation famille',
            'custody_invitation'   => 'Invitation accès garde partagée',
            'event_reminder'       => 'Rappel événement (24h avant)',
            'task_reminder'        => 'Rappel tâche (7j sans action)',
            'shopping_reminder'    => 'Rappel liste de courses',
            'budget_recurring'     => 'Rappel prélèvement/virement récurrent',
            'event_tomorrow_digest'=> 'Récapitulatif des événements du lendemain',
            'wall_post'            => 'Nouveau post sur le mur',
            'birthday_reminder'    => 'Rappel anniversaire (7j avant)',
            'weekly_digest'         => 'Résumé hebdomadaire (dimanche soir)',
            'weekly_digest_coparent'=> 'Résumé hebdomadaire — accès co-parent',
            'password_reset'        => 'Réinitialisation de mot de passe',
            'account_deleted'       => 'Compte supprimé (motif et attribution renvoyés par l\'admin système ou en libre-service)',
            default                => $type,
        };
    }

    public static function variables(string $type): array
    {
        return match($type) {
            'invitation'            => ['sender_name', 'family_name', 'user_name'],
            'custody_invitation'    => ['sender_name', 'family_name', 'child_list'],
            'event_reminder'        => ['user_name', 'event_title', 'event_date', 'event_description'],
            'task_reminder'         => ['user_name', 'task_name', 'list_name', 'task_created'],
            'shopping_reminder'     => ['user_name', 'list_name'],
            'budget_recurring'      => ['user_name', 'type_label', 'title', 'amount', 'due_date'],
            'event_tomorrow_digest' => ['user_name', 'event_count'],
            'wall_post'             => ['author_name', 'content'],
            'birthday_reminder'     => ['user_name', 'birthday_name', 'birthday_age', 'birthday_date'],
            'weekly_digest'         => ['user_name', 'family_name'],
            'weekly_digest_coparent'=> ['user_name', 'child_names'],
            'password_reset'        => ['user_name'],
            'account_deleted'       => ['user_name', 'deleted_by', 'reason'],
            default                 => [],
        };
    }
}
