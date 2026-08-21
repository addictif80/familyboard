<?php
namespace App\Controllers;

use App\Core\Session;
use App\Core\Mail;
use App\Core\DateHelper;
use App\Models\Family;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;

class LetterController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('letters');
        $user     = Session::user();
        $familyId = (int)$user['family_id'];

        $family       = Family::findById($familyId);
        $letters      = Letter::getByFamily($familyId);
        $templates    = LetterTemplate::getByFamily($familyId);
        $sendHistory  = Letter::getSendHistory($familyId);

        require BASE_PATH . '/templates/letters/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $d = $this->validated($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => 'Champs obligatoires manquants.'];
            $id = Letter::create((int)$user['family_id'], (int)$user['id'], $d);
            return ['success' => true, 'id' => $id];
        });
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $d = $this->validated($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => 'Champs obligatoires manquants.'];
            Letter::update((int)$params['id'], (int)$user['family_id'], $d);
            return ['success' => true];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            Letter::delete((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    public function sendEmail(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $letter = Letter::getById((int)$params['id']);
            if (!$letter || (int)$letter['family_id'] !== (int)$user['family_id']) {
                return ['success' => false, 'error' => 'Courrier introuvable.'];
            }
            if (empty($letter['recipient_email'])) {
                return ['success' => false, 'error' => "Aucun e-mail renseigné pour ce destinataire."];
            }

            $family = Family::findById((int)$letter['family_id']);
            $pdf = $this->generatePdf($letter, $family);

            $filename = 'courrier_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $letter['recipient_display_name']) . '.pdf';
            $html = '<p>Bonjour,</p><p>Vous trouverez ci-joint un courrier de la part de '
                . htmlspecialchars($letter['author_name']) . '.</p><p>Cordialement.</p>';

            $ok = Mail::send(
                (int)$letter['family_id'],
                $letter['recipient_email'],
                $letter['recipient_display_name'],
                '[' . htmlspecialchars($family['name'] ?? 'FamilyBoard') . '] ' . $letter['subject'],
                $html,
                'manual',
                (int)$user['id'],
                [['filename' => $filename, 'content' => $pdf, 'mime' => 'application/pdf']]
            );

            if (!$ok) return ['success' => false, 'error' => "Échec de l'envoi — vérifiez la configuration SMTP."];

            Letter::logSend((int)$letter['id'], (int)$user['id']);
            return ['success' => true];
        });
    }

    public function createTemplate(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $name = trim($data['name'] ?? '');
            if ($name === '') return ['success' => false, 'error' => 'Nom du modèle requis.'];
            $variables = is_array($data['variables'] ?? null) ? array_map('strval', $data['variables']) : [];
            $id = LetterTemplate::create(
                (int)$user['family_id'],
                (int)$user['id'],
                $name,
                trim($data['subject'] ?? ''),
                (string)($data['body'] ?? ''),
                $variables
            );
            return ['success' => true, 'id' => $id];
        });
    }

    public function deleteTemplate(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            LetterTemplate::delete((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    /** Valide et normalise les champs d'un courrier, calcule le nom d'affichage du destinataire. */
    private function validated(array $data): ?array
    {
        $lastName   = trim($data['recipient_last_name'] ?? '');
        $firstName  = trim($data['recipient_first_name'] ?? '');
        $address    = trim($data['recipient_address'] ?? '');
        $postalCity = trim($data['recipient_postal_city'] ?? '');
        $subject    = trim($data['subject'] ?? '');
        $body       = (string)($data['body'] ?? '');
        if ($lastName === '' || $address === '' || $postalCity === '' || $subject === '' || trim(strip_tags($body)) === '') {
            return null;
        }
        $civility = in_array($data['civility'] ?? '', ['Madame', 'Monsieur'], true) ? $data['civility'] : '';
        $displayName = trim($civility . ' ' . $firstName . ' ' . $lastName);

        return [
            'civility'                     => $civility,
            'recipient_last_name'          => $lastName,
            'recipient_first_name'         => $firstName,
            'recipient_display_name'       => $displayName,
            'recipient_complement'         => trim($data['recipient_complement'] ?? ''),
            'recipient_address'            => $address,
            'recipient_address_complement' => trim($data['recipient_address_complement'] ?? ''),
            'recipient_postal_city'        => $postalCity,
            'recipient_email'              => trim($data['recipient_email'] ?? ''),
            'place'                        => trim($data['place'] ?? ''),
            'subject'                      => $subject,
            'body'                         => $body,
        ];
    }

    /** Génère le PDF A4 du courrier (même mise en page que l'impression navigateur) pour l'envoi
     *  par e-mail — voir aussi public/js/letters.js:printLetter() pour la version imprimable. */
    private function generatePdf(array $letter, ?array $family): string
    {
        $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $senderName = $letter['author_name'];
        $familyName = $family['name'] ?? 'FamilyBoard';
        $senderAddress = $family['sender_address'] ?? '';
        $senderPostalCity = $family['sender_postal_city'] ?? '';

        $destLines = $h($letter['recipient_display_name']);
        if ($letter['recipient_complement']) $destLines .= '<br>' . $h($letter['recipient_complement']);
        $destLines .= '<br>' . $h($letter['recipient_address']);
        if ($letter['recipient_address_complement']) $destLines .= '<br>' . $h($letter['recipient_address_complement']);
        $destLines .= '<br>' . $h($letter['recipient_postal_city']);

        $dateLong = DateHelper::long($letter['letter_date']);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    @page { size: A4 portrait; margin: 20mm 20mm 20mm 20mm; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10pt; color: #000; line-height: 1.45; margin: 0; padding: 0; }
    table { border-collapse: collapse; width: 100%; }
    .brand { font-family: Georgia, serif; font-size: 16pt; font-weight: bold; color: #2C3E50; }
    .sender-info { font-size: 8.5pt; line-height: 1.35; color: #333; margin-top: 4mm; }
    .recipient-cell { border: 1px dashed #999; padding: 6px 10px; font-size: 10pt; line-height: 1.55; vertical-align: top; }
    .lieu-date { text-align: right; color: #444; margin-top: 14mm; margin-bottom: 10mm; }
    .objet { font-weight: bold; font-size: 10.5pt; margin-bottom: 6mm; }
    .corps { text-align: justify; line-height: 1.65; }
    .signature { text-align: right; margin-top: 22mm; }
</style>
</head>
<body>
<table>
<tr>
<td width="48%" style="vertical-align:top; padding-right:6mm;">
    <div class="brand">{$h($familyName)}</div>
    <div class="sender-info">
        {$h($senderName)}<br>
        {$h($senderAddress)}<br>
        {$h($senderPostalCity)}
    </div>
</td>
<td width="4%">&nbsp;</td>
<td width="48%" style="vertical-align:bottom;">
    <table style="width:100%;"><tr><td class="recipient-cell">{$destLines}</td></tr></table>
</td>
</tr>
</table>
<div class="lieu-date">{$h($letter['place'])}, le {$h($dateLong)}</div>
<div class="objet">Objet&nbsp;: {$h($letter['subject'])}</div>
<br>
<div class="corps">{$letter['body']}</div>
<div class="signature">{$h($senderName)}</div>
</body>
</html>
HTML;

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }
}
