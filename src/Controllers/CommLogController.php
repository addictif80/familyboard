<?php
namespace App\Controllers;

use App\Core\DateHelper;
use App\Core\OcrHelper;
use App\Core\Session;
use App\Models\CommLogMessage;
use App\Models\Family;
use App\Models\Notification;
use Dompdf\Dompdf;
use Dompdf\Options;

class CommLogController extends BaseController
{
    private const MAX_VOICE_SIZE = 15 * 1024 * 1024;

    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('comm_log');
        $user = Session::user();

        CommLogMessage::markAllRead($user['family_id'], $user['id']);

        $messages = CommLogMessage::getByFamily($user['family_id']);
        $reads    = CommLogMessage::getReadsByFamily($user['family_id']);
        $lastId   = CommLogMessage::getLastId($user['family_id']);
        require BASE_PATH . '/templates/comm_log/index.php';
    }

    public function send(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $audioFile = $_FILES['audio'] ?? null;

            if ($audioFile) {
                $content = trim($_POST['content'] ?? '');
                if (mb_strlen($content) > 4000) return ['success' => false, 'error' => 'Message trop long'];
                try {
                    [$audioPath, $audioOriginal, $audioMime] =
                        OcrHelper::saveUploadedFile($audioFile, 'voice', $user['family_id'], OcrHelper::VOICE_MIMES, self::MAX_VOICE_SIZE);
                } catch (\RuntimeException $e) {
                    return ['success' => false, 'error' => $e->getMessage()];
                }
                $duration = isset($_POST['duration']) ? (int)$_POST['duration'] : null;
                $id = CommLogMessage::create($user['family_id'], $user['id'], $content, null, $audioPath, $audioOriginal, $audioMime, $duration);
            } else {
                $content = trim($this->jsonInput()['content'] ?? '');
                if (!$content) return ['success' => false];
                if (mb_strlen($content) > 4000) return ['success' => false, 'error' => 'Message trop long'];
                $id = CommLogMessage::create($user['family_id'], $user['id'], $content);
                $audioPath = $audioMime = null;
                $duration = null;
            }

            Notification::notifyFamily($user['family_id'], $user['id'], 'comm_log',
                'Journal parental', $user['name'] . ' a ajouté ' . ($audioPath ? 'un message vocal' : 'un message') . '.', BASE_URL . '/comm-log');

            return [
                'success' => true,
                'message' => [
                    'id' => $id,
                    'content' => $content,
                    'audio_path' => $audioPath,
                    'audio_mime' => $audioMime,
                    'audio_duration' => $duration,
                    'user_name' => $user['name'],
                    'user_color' => $user['color'],
                    'user_avatar' => $user['avatar'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'user_id' => $user['id'],
                ],
            ];
        });
    }

    public function serveAudio(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $msg  = CommLogMessage::findById((int)$params['id']);

        if (!$msg || $msg['family_id'] !== $user['family_id'] || !$msg['audio_path']) {
            http_response_code(404); echo 'Introuvable.'; return;
        }

        $path = BASE_PATH . $msg['audio_path'];
        if (!file_exists($path)) { http_response_code(404); echo 'Introuvable.'; return; }

        header('Content-Type: ' . ($msg['audio_mime'] ?: 'audio/webm'));
        header('Content-Length: ' . filesize($path));
        header($this->contentDispositionHeader($msg['audio_original'] ?: basename($path)));
        header('Cache-Control: private, max-age=3600');
        header('Accept-Ranges: bytes');
        readfile($path);
        exit;
    }

    /**
     * Export judiciaire du journal parental : PDF horodaté, numéroté, en ordre chronologique
     * strict, avec les accusés de lecture — destiné à être produit devant un tiers (avocat,
     * juge). Les messages sont immuables par conception (CommLogMessage::create() est la seule
     * écriture possible sur cette table), ce qui rend cet export fidèle au contenu réellement
     * échangé. Une empreinte SHA-256 du contenu exporté est ajoutée en pied de document pour
     * permettre de vérifier après coup qu'aucune modification n'a été apportée au fichier.
     */
    public function exportJudicial(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $familyId = (int)$user['family_id'];
        $family = Family::findById($familyId);

        $from = trim($_GET['from'] ?? '');
        $to   = trim($_GET['to'] ?? '');
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : null;
        $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : null;

        $messages = CommLogMessage::getAllByFamily($familyId, $from, $to);
        $reads    = CommLogMessage::getReadsByFamily($familyId);

        $h = fn($s) => htmlspecialchars((string)$s);
        $rows = '';
        $fingerprintSource = '';
        $n = 0;
        foreach ($messages as $m) {
            $n++;
            $date = DateHelper::fromUtc($m['created_at'], 'd/m/Y à H:i:s');
            $author = $m['user_name'] ?: 'Compte supprimé';
            $content = $m['audio_path'] ? '[Message vocal — ' . ($m['audio_duration'] ? $m['audio_duration'] . 's' : 'durée inconnue') . ']' : $m['content'];
            $readBy = !empty($reads[$m['id']])
                ? 'Lu par ' . implode(', ', array_map(
                    fn($r) => $r['user_name'] . ' le ' . DateHelper::fromUtc($r['read_at'], 'd/m/Y à H:i'),
                    $reads[$m['id']]
                  ))
                : 'Non lu à ce jour';
            $fingerprintSource .= $n . '|' . $m['created_at'] . '|' . $author . '|' . $content . "\n";
            $rows .= '<div class="msg"><div class="msg-head"><span class="msg-n">#' . $n . '</span> '
                . '<strong>' . $h($author) . '</strong> — <span class="msg-date">' . $h($date) . '</span></div>'
                . '<div class="msg-content">' . nl2br($h($content)) . '</div>'
                . '<div class="msg-read">' . $h($readBy) . '</div></div>';
        }
        if ($rows === '') $rows = '<p>Aucun message dans la période sélectionnée.</p>';

        $fingerprint = hash('sha256', $fingerprintSource);
        $periodLabel = ($from || $to)
            ? 'Période : ' . ($from ? DateHelper::format($from, 'd/m/Y') : 'origine') . ' au ' . ($to ? DateHelper::format($to, 'd/m/Y') : 'aujourd\'hui')
            : 'Ensemble des échanges depuis la création du journal';

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #23201B; }
            h1 { font-size: 18px; margin-bottom: 4px; }
            .subtitle { color: #6b6558; margin-top: 0; margin-bottom: 4px; }
            .msg { border-bottom: 1px solid #ddd; padding: 8px 0; }
            .msg-head { margin-bottom: 3px; }
            .msg-n { color: #6b6558; }
            .msg-date { color: #6b6558; }
            .msg-content { margin: 3px 0; white-space: pre-wrap; }
            .msg-read { color: #6b6558; font-size: 10px; }
            .fingerprint { margin-top: 24px; padding-top: 8px; border-top: 1px solid #23201B; font-size: 9px; color: #6b6558; word-break: break-all; }
        </style></head><body>'
            . '<h1>Export judiciaire — Journal parental</h1>'
            . '<p class="subtitle">Famille ' . $h($family['name'] ?? '') . ' — ' . $h($periodLabel) . '</p>'
            . '<p class="subtitle">Document généré le ' . $h(date('d/m/Y à H:i')) . ' par ' . $h($user['name']) . ' — ' . $n . ' message(s), en ordre chronologique.</p>'
            . '<p class="subtitle">Ces messages sont horodatés à leur création et ne peuvent techniquement ni être modifiés ni supprimés une fois envoyés.</p>'
            . $rows
            . '<div class="fingerprint">Empreinte d\'intégrité (SHA-256 du contenu exporté ci-dessus, calculée à la génération) : ' . $fingerprint . '</div>'
            . '</body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'export-judiciaire-journal-parental-' . date('Y-m-d') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $dompdf->output();
    }

    public function poll(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user     = Session::user();
            $afterId  = (int)($_GET['after'] ?? 0);
            $messages = CommLogMessage::getNew($user['family_id'], $afterId);
            if ($messages) {
                CommLogMessage::markAllRead($user['family_id'], $user['id']);
            }
            return ['messages' => $messages];
        });
    }
}
