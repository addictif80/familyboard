<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\FamilyChild;
use App\Models\NannyHours;

class NannyController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth(true);
        $this->requireModule('nanny');
        $user = Session::user();
        $familyId = (int)$user['family_id'];
        $isCoparent = $user['role'] === 'coparent';

        $year = (int)($_GET['year'] ?? date('Y'));
        $month = (int)($_GET['month'] ?? date('n'));
        if ($month < 1 || $month > 12) $month = (int)date('n');
        $childId = (int)($_GET['child_id'] ?? 0) ?: null;

        $children = FamilyChild::getByFamily($familyId);
        if ($childId && !in_array($childId, array_column($children, 'id'), true)) $childId = null;

        $entries = NannyHours::getEntries($familyId, ['year' => $year, 'month' => $month, 'child_id' => $childId]);
        $monthlyTotal = NannyHours::monthlyTotal($familyId, $year, $month, $childId);
        $annualTotal = NannyHours::annualTotal($familyId, $year, $childId);
        $monthlyBreakdown = NannyHours::monthlyBreakdown($familyId, $year, $childId);
        $years = NannyHours::getYearsWithEntries($familyId);

        require BASE_PATH . '/templates/nanny/index.php';
    }

    // ── Entrées d'heures ─────────────────────────────────────────

    public function addEntry(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            if ($user['role'] === 'coparent') return ['success' => false, 'error' => 'Accès refusé.'];
            $d = $this->validatedEntry($this->jsonInput(), (int)$user['family_id'], (int)$user['id']);
            if (!$d) return ['success' => false, 'error' => 'Date et nombre d\'heures (entre 0 et 24) requis.'];
            $id = NannyHours::addEntry((int)$user['family_id'], (int)$user['id'], $d);
            return ['success' => true, 'id' => $id];
        });
    }

    public function updateEntry(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $entry = $this->ownedEntry((int)$params['id']);
            if (!$entry || $user['role'] === 'coparent') return ['success' => false, 'error' => 'Entrée introuvable.'];
            $d = $this->validatedEntry($this->jsonInput(), (int)$user['family_id'], (int)$user['id']);
            if (!$d) return ['success' => false, 'error' => 'Date et nombre d\'heures (entre 0 et 24) requis.'];
            NannyHours::updateEntry((int)$params['id'], (int)$user['family_id'], $d);
            return ['success' => true];
        });
    }

    public function deleteEntry(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $entry = $this->ownedEntry((int)$params['id']);
            if (!$entry || $user['role'] === 'coparent') return ['success' => false];
            NannyHours::deleteEntry((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    // ── Rapports PDF ─────────────────────────────────────────────

    public function monthlyPdf(array $params): void
    {
        $this->requireAuth(true);
        $this->requireModule('nanny');
        $user = Session::user();
        $familyId = (int)$user['family_id'];
        $year = (int)$params['year'];
        $month = (int)$params['month'];
        $childId = (int)($_GET['child_id'] ?? 0) ?: null;
        if ($month < 1 || $month > 12) { http_response_code(404); echo 'Mois invalide.'; return; }

        $entries = NannyHours::getEntries($familyId, ['year' => $year, 'month' => $month, 'child_id' => $childId]);
        $total = NannyHours::monthlyTotal($familyId, $year, $month, $childId);
        $childLabel = $childId ? (FamilyChild::getById($childId)['name'] ?? '') : 'Tous les enfants';
        $periodLabel = ucfirst((new \DateTime("$year-$month-01"))->format('F Y'));

        $this->renderReportPdf($entries, $total, $periodLabel, $childLabel, "rapport-nounou-$year-$month.pdf");
    }

    public function annualPdf(array $params): void
    {
        $this->requireAuth(true);
        $this->requireModule('nanny');
        $user = Session::user();
        $familyId = (int)$user['family_id'];
        $year = (int)$params['year'];
        $childId = (int)($_GET['child_id'] ?? 0) ?: null;

        $entries = NannyHours::getEntries($familyId, ['year' => $year, 'child_id' => $childId]);
        $total = NannyHours::annualTotal($familyId, $year, $childId);
        $breakdown = NannyHours::monthlyBreakdown($familyId, $year, $childId);
        $childLabel = $childId ? (FamilyChild::getById($childId)['name'] ?? '') : 'Tous les enfants';

        $this->renderReportPdf($entries, $total, "Année $year", $childLabel, "rapport-nounou-$year.pdf", $breakdown, $year);
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function ownedEntry(int $id): ?array
    {
        $user = Session::user();
        $entry = NannyHours::getEntryById($id);
        if (!$entry || (int)$entry['family_id'] !== (int)$user['family_id']) return null;
        return $entry;
    }

    private function validatedEntry(array $data, int $familyId, int $userId): ?array
    {
        $date = trim($data['entry_date'] ?? '');
        $hours = is_numeric($data['hours'] ?? null) ? (float)$data['hours'] : null;
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $hours === null || $hours <= 0 || $hours > 24) {
            return null;
        }
        $newChildName = trim($data['new_child_name'] ?? '');
        if ($newChildName !== '') {
            $childId = FamilyChild::findOrCreateByName($familyId, $userId, $newChildName);
        } else {
            $childId = (int)($data['child_id'] ?? 0) ?: null;
            if ($childId) {
                $child = FamilyChild::getById($childId);
                if (!$child || (int)$child['family_id'] !== $familyId) $childId = null;
            }
        }
        return [
            'child_id' => $childId,
            'entry_date' => $date,
            'hours' => round($hours, 2),
            'nanny_name' => trim($data['nanny_name'] ?? ''),
            'notes' => trim($data['notes'] ?? ''),
        ];
    }

    private function renderReportPdf(array $entries, float $total, string $periodLabel, string $childLabel, string $filename, ?array $breakdown = null, ?int $year = null): void
    {
        $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $monthNames = [1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
            7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'];

        $rows = '';
        foreach ($entries as $e) {
            $d = (new \DateTime($e['entry_date']))->format('d/m/Y');
            $rows .= '<tr><td>' . $h($d) . '</td><td>' . $h($e['child_name'] ?? '—') . '</td><td>' . $h($e['nanny_name'] ?: '—') . '</td><td class="amount">' . $h(number_format((float)$e['hours'], 2, ',', ' ')) . ' h</td><td>' . $h($e['notes']) . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="5" style="text-align:center;color:#888">Aucune entrée sur cette période.</td></tr>';
        }

        $breakdownHtml = '';
        if ($breakdown !== null) {
            $breakdownHtml = '<h2>Répartition mensuelle</h2><table><tr>';
            foreach ($monthNames as $num => $label) {
                $breakdownHtml .= '<th>' . substr($h($label), 0, 3) . '</th>';
            }
            $breakdownHtml .= '</tr><tr>';
            foreach ($monthNames as $num => $label) {
                $breakdownHtml .= '<td class="amount">' . $h(number_format($breakdown[$num], 1, ',', ' ')) . '</td>';
            }
            $breakdownHtml .= '</tr></table>';
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10.5pt; color: #000; }
    h1 { font-size: 16pt; margin-bottom: 2px; }
    h2 { font-size: 11pt; margin-top: 22px; margin-bottom: 4px; }
    .subtitle { color: #666; font-size: 9pt; margin-top: 0; margin-bottom: 4px; }
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    td, th { padding: 5px 8px; border-bottom: 1px solid #eee; text-align: left; font-size: 9.5pt; }
    .amount { text-align: right; }
    .total-row td { font-weight: bold; border-top: 2px solid #333; }
</style></head><body>
<h1>Rapport d'heures — Nounou</h1>
<p class="subtitle">{$h($childLabel)} — {$h($periodLabel)}</p>
<h2>Détail des journées</h2>
<table>
<tr><th>Date</th><th>Enfant</th><th>Nounou</th><th class="amount">Heures</th><th>Notes</th></tr>
{$rows}
<tr class="total-row"><td colspan="3">Total</td><td class="amount">{$h(number_format($total, 2, ',', ' '))} h</td><td></td></tr>
</table>
{$breakdownHtml}
<p class="subtitle" style="margin-top:20px">Généré le {$h(date('d/m/Y à H:i'))} par FamilyBoard.</p>
</body></html>
HTML;

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $breakdown !== null ? 'landscape' : 'portrait');
        $dompdf->render();

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $dompdf->output();
    }
}
