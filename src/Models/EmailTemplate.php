<?php
namespace App\Models;

use App\Core\Database;

class EmailTemplate
{
    // Built-in default templates
    private static array $defaults = [
        'invitation' => [
            'subject' => '{{sender_name}} vous invite à rejoindre {{family_name}} sur FamilyBoard',
            'body'    => '<div style="font-family:Inter,sans-serif;max-width:560px;margin:auto">
<h2 style="color:#4A90D9">Vous avez été invité !</h2>
<p>Bonjour,</p>
<p><strong>{{sender_name}}</strong> vous invite à rejoindre la famille <strong>{{family_name}}</strong> sur FamilyBoard.</p>
<p style="margin:2rem 0">
  <a href="{{invite_url}}" style="background:#4A90D9;color:white;padding:.75rem 1.5rem;border-radius:8px;text-decoration:none;font-weight:600">
    Rejoindre la famille
  </a>
</p>
<p style="color:#666;font-size:.85rem">Ce lien est valide 7 jours. Si vous ne connaissez pas {{sender_name}}, ignorez ce message.</p>
</div>',
        ],
        'event_reminder' => [
            'subject' => '⏰ Rappel : {{event_title}} demain',
            'body'    => '<div style="font-family:Inter,sans-serif;max-width:560px;margin:auto">
<h2 style="color:#4A90D9">Rappel d\'événement</h2>
<p>Bonjour {{user_name}},</p>
<p>Un événement familial approche dans moins de 24 heures :</p>
<div style="background:#f0f7ff;border-left:4px solid #4A90D9;padding:1rem;border-radius:4px;margin:1rem 0">
  <strong>{{event_title}}</strong><br>
  📅 {{event_date}}<br>
  {{event_description}}
</div>
<p><a href="{{app_url}}/calendar">Voir le calendrier</a></p>
</div>',
        ],
        'task_reminder' => [
            'subject' => '✅ Tâche en attente depuis 7 jours : {{task_name}}',
            'body'    => '<div style="font-family:Inter,sans-serif;max-width:560px;margin:auto">
<h2 style="color:#E67E22">Tâche non complétée</h2>
<p>Bonjour {{user_name}},</p>
<p>La tâche suivante n\'a pas été complétée depuis plus d\'une semaine :</p>
<div style="background:#fff8f0;border-left:4px solid #E67E22;padding:1rem;border-radius:4px;margin:1rem 0">
  <strong>{{task_name}}</strong><br>
  Liste : {{list_name}}<br>
  Créée le : {{task_created}}
</div>
<p><a href="{{app_url}}/tasks">Voir les tâches</a></p>
</div>',
        ],
        'shopping_reminder' => [
            'subject' => '🛒 La liste de courses {{list_name}} attend !',
            'body'    => '<div style="font-family:Inter,sans-serif;max-width:560px;margin:auto">
<h2 style="color:#27AE60">Liste de courses</h2>
<p>Bonjour {{user_name}},</p>
<p>La liste de courses <strong>{{list_name}}</strong> contient des articles depuis plus d\'une semaine :</p>
<ul>{{items}}</ul>
<p><a href="{{app_url}}/tasks">Voir les tâches</a></p>
</div>',
        ],
    ];

    public static function getForFamily(int $familyId, string $type): array
    {
        $custom = Database::fetch(
            'SELECT * FROM email_templates WHERE family_id = ? AND type = ? AND is_active = 1',
            [$familyId, $type]
        );
        return $custom ?: (self::$defaults[$type] ?? ['subject' => '', 'body' => '']);
    }

    public static function getAll(int $familyId): array
    {
        $types = array_keys(self::$defaults);
        $result = [];
        foreach ($types as $type) {
            $custom = Database::fetch('SELECT * FROM email_templates WHERE family_id = ? AND type = ?', [$familyId, $type]);
            $result[$type] = $custom ?: array_merge(self::$defaults[$type], ['type' => $type, 'is_custom' => false]);
            $result[$type]['is_custom'] = (bool)$custom;
            $result[$type]['label'] = self::label($type);
        }
        return $result;
    }

    public static function save(int $familyId, string $type, string $subject, string $body): void
    {
        $exists = Database::fetch('SELECT id FROM email_templates WHERE family_id = ? AND type = ?', [$familyId, $type]);
        if ($exists) {
            Database::execute(
                'UPDATE email_templates SET subject=?, body=?, is_active=1, updated_at=NOW() WHERE family_id=? AND type=?',
                [$subject, $body, $familyId, $type]
            );
        } else {
            Database::insert(
                'INSERT INTO email_templates (family_id, type, subject, body) VALUES (?,?,?,?)',
                [$familyId, $type, $subject, $body]
            );
        }
    }

    public static function reset(int $familyId, string $type): void
    {
        Database::execute('DELETE FROM email_templates WHERE family_id = ? AND type = ?', [$familyId, $type]);
    }

    public static function render(int $familyId, string $type, array $vars): array
    {
        $tpl = self::getForFamily($familyId, $type);
        return [
            'subject' => self::replace($tpl['subject'], $vars),
            'body'    => self::replace($tpl['body'], $vars),
        ];
    }

    private static function replace(string $tpl, array $vars): string
    {
        foreach ($vars as $k => $v) {
            $tpl = str_replace('{{' . $k . '}}', $v, $tpl);
        }
        return $tpl;
    }

    private static function label(string $type): string
    {
        return match($type) {
            'invitation'        => 'Invitation famille',
            'event_reminder'    => 'Rappel événement (24h avant)',
            'task_reminder'     => 'Rappel tâche (7j sans action)',
            'shopping_reminder' => 'Rappel liste de courses',
            default             => $type,
        };
    }

    public static function variables(string $type): array
    {
        $common = ['family_name', 'user_name', 'app_url'];
        return match($type) {
            'invitation'        => array_merge($common, ['sender_name', 'invite_url']),
            'event_reminder'    => array_merge($common, ['event_title', 'event_date', 'event_description']),
            'task_reminder'     => array_merge($common, ['task_name', 'list_name', 'task_created']),
            'shopping_reminder' => array_merge($common, ['list_name', 'items']),
            default             => $common,
        };
    }
}
