<?php
namespace App\Models;

use App\Core\Database;
use App\Core\DateHelper;
use App\Core\Mail;
use App\Core\EmailLayout;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Rapport PDF de l'activité d'un compte co-parent, envoyé par e-mail au moment où son accès
 * est supprimé (par l'administrateur de la famille ou par le co-parent lui-même) — voir
 * AccountDeletion::deleteCoparent(). Doit être généré et envoyé AVANT toute suppression : une
 * fois le compte détaché de son contenu (user_id mis à NULL) ou supprimé, ce rapport ne peut
 * plus être reconstitué à l'identique.
 */
class CoparentReport
{
    public static function sendFinalReport(array $user): void
    {
        if (empty($user['email'])) return;

        $data = self::gather((int)$user['id']);
        $pdf  = self::renderPdf($user, $data);

        $total = array_sum(array_map('count', $data));
        $messageHtml = '<p>Bonjour ' . htmlspecialchars($user['name']) . ',</p>'
            . '<p>Votre accès co-parent à FamilyBoard vient d\'être supprimé. Conformément à '
            . 'notre engagement de transparence, vous trouverez ci-joint un rapport récapitulant '
            . 'l\'ensemble des éléments que vous avez ajoutés pendant la durée de votre accès '
            . '(' . $total . ' élément(s) au total). Ce contenu reste conservé par la famille.</p>'
            . '<p>Si vous avez des questions, vous pouvez répondre à cet e-mail.</p>';

        $html = EmailLayout::render(
            'Votre rapport d\'activité FamilyBoard',
            $messageHtml
        );

        Mail::send(
            (int)$user['family_id'],
            $user['email'],
            $user['name'],
            'Votre rapport d\'activité FamilyBoard',
            $html,
            'manual',
            null,
            [[
                'filename' => 'rapport-activite-familyboard.pdf',
                'content'  => $pdf,
                'mime'     => 'application/pdf',
            ]]
        );
    }

    /** Tout le contenu créé par ce compte, regroupé par module. */
    private static function gather(int $userId): array
    {
        return [
            'documents' => Database::fetchAll(
                'SELECT title, doc_type, created_at FROM documents WHERE user_id=? ORDER BY created_at',
                [$userId]
            ),
            'events' => Database::fetchAll(
                'SELECT title, start_datetime, created_at FROM events WHERE user_id=? ORDER BY created_at',
                [$userId]
            ),
            'journal' => Database::fetchAll(
                'SELECT content, created_at FROM comm_log_messages WHERE user_id=? ORDER BY created_at',
                [$userId]
            ),
            'photos' => Database::fetchAll(
                'SELECT p.caption, p.created_at, a.title as album_title
                 FROM album_photos p JOIN albums a ON a.id = p.album_id
                 WHERE p.user_id=? ORDER BY p.created_at',
                [$userId]
            ),
            'links' => Database::fetchAll(
                'SELECT title, url, status, created_at FROM portal_links WHERE submitted_by=? ORDER BY created_at',
                [$userId]
            ),
            'activity' => Database::fetchAll(
                'SELECT action, details, child_name, created_at FROM custody_activity_log WHERE user_id=? ORDER BY created_at',
                [$userId]
            ),
        ];
    }

    private const ACTION_LABELS = [
        'proposal_sent'     => 'Proposition de garde envoyée',
        'proposal_applied'  => 'Proposition de garde appliquée',
        'event_created'     => 'Événement de garde ajouté',
        'event_updated'     => 'Événement de garde modifié',
        'event_deleted'     => 'Événement de garde supprimé',
    ];

    private static function renderPdf(array $user, array $data): string
    {
        $sections = [
            'documents' => ['📄 Documents ajoutés', fn($r) => htmlspecialchars($r['title']) . ' <span class="muted">(' . htmlspecialchars($r['doc_type']) . ')</span>'],
            'events'    => ['📅 Événements créés', fn($r) => htmlspecialchars($r['title']) . ' <span class="muted">— ' . DateHelper::format($r['start_datetime'], 'd/m/Y H:i') . '</span>'],
            'journal'   => ['📝 Messages du journal parental', fn($r) => nl2br(htmlspecialchars($r['content']))],
            'photos'    => ['🖼️ Photos ajoutées', fn($r) => 'Album « ' . htmlspecialchars($r['album_title']) . ' »' . ($r['caption'] ? ' — ' . htmlspecialchars($r['caption']) : '')],
            'links'     => ['🔗 Liens proposés', fn($r) => htmlspecialchars($r['title']) . ' <span class="muted">(' . htmlspecialchars($r['url']) . ')</span>'],
            'activity'  => ['👪 Activité planning de garde', fn($r) => (self::ACTION_LABELS[$r['action']] ?? htmlspecialchars($r['action'])) . ($r['child_name'] ? ' — ' . htmlspecialchars($r['child_name']) : '') . ($r['details'] ? ' (' . htmlspecialchars($r['details']) . ')' : '')],
        ];

        $body = '';
        foreach ($sections as $key => [$title, $render]) {
            $rows = $data[$key];
            if (empty($rows)) continue;
            $body .= '<h2>' . $title . ' <span class="count">(' . count($rows) . ')</span></h2><ul>';
            foreach ($rows as $r) {
                $date = DateHelper::format($r['created_at'], 'd/m/Y H:i');
                $body .= '<li><span class="date">' . $date . '</span> — ' . $render($r) . '</li>';
            }
            $body .= '</ul>';
        }
        if ($body === '') {
            $body = '<p>Aucun contenu n\'a été ajouté pendant la durée de cet accès.</p>';
        }

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #23201B; }
            h1 { font-size: 20px; margin-bottom: 4px; }
            .subtitle { color: #6b6558; margin-top: 0; margin-bottom: 24px; }
            h2 { font-size: 14px; margin-top: 22px; margin-bottom: 6px; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
            .count { color: #6b6558; font-weight: normal; font-size: 12px; }
            ul { margin: 0; padding-left: 18px; }
            li { margin-bottom: 6px; }
            .date { color: #6b6558; }
            .muted { color: #6b6558; }
        </style></head><body>'
            . '<h1>Rapport d\'activité — accès co-parent</h1>'
            . '<p class="subtitle">' . htmlspecialchars($user['name']) . ' — compte créé le ' . DateHelper::format($user['created_at'] ?? date('Y-m-d'), 'd/m/Y') . ', rapport généré le ' . DateHelper::format(date('Y-m-d H:i:s'), 'd/m/Y à H:i') . '</p>'
            . $body
            . '</body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }
}
