<?php
namespace App\Controllers;

use App\Core\Session;
use App\Core\SirenLookup;
use App\Models\Document;
use App\Models\EmploymentProfile;
use App\Models\User;

class EmploymentController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('employment');
        $user = Session::user();
        $familyId = (int)$user['family_id'];

        $profiles = EmploymentProfile::getByFamily($familyId);
        $selectedId = (int)($_GET['id'] ?? 0);
        $selected = null;
        if ($selectedId) {
            $selected = EmploymentProfile::getById($selectedId);
            if (!$selected || (int)$selected['family_id'] !== $familyId) $selected = null;
        } elseif ($profiles) {
            $selected = $profiles[0];
            $selectedId = (int)$selected['id'];
        }

        $schedule = $exceptions = [];
        $paidLeaveBalance = $rttBalance = null;
        $paidLeaveEvents = $rttEvents = $unpaidAbsences = [];
        $primes = $payslip = null;
        $payslips = $sickLeaves = [];
        $linkedDocuments = [];
        $familyDocuments = [];
        $periodYear = (int)($_GET['year'] ?? date('Y'));
        $periodMonth = (int)($_GET['month'] ?? date('n'));

        if ($selected) {
            $schedule = EmploymentProfile::getSchedule($selectedId);
            $exceptions = EmploymentProfile::getExceptions($selectedId);
            $paidLeaveBalance = EmploymentProfile::getLeaveBalance($selected, 'paid_leave');
            $rttBalance = EmploymentProfile::getLeaveBalance($selected, 'rtt');
            $paidLeaveEvents = EmploymentProfile::getLeaveEvents($selectedId, 'paid_leave');
            $rttEvents = EmploymentProfile::getLeaveEvents($selectedId, 'rtt');
            $unpaidAbsences = EmploymentProfile::getUnpaidAbsences($selectedId);
            $primes = EmploymentProfile::getPrimes($selectedId, $periodYear, $periodMonth);
            $payslip = EmploymentProfile::getPayslip($selectedId, $periodYear, $periodMonth);
            $payslips = EmploymentProfile::getPayslips($selectedId);
            $sickLeaves = EmploymentProfile::getSickLeaves($selectedId);
            $linkedDocuments = EmploymentProfile::getLinkedDocuments($selectedId);
            $familyDocuments = Document::getAll($familyId);
        }
        $familyMembers = User::getByFamily($familyId);

        require BASE_PATH . '/templates/employment/index.php';
    }

    // ── Profils ──────────────────────────────────────────────────

    public function createProfile(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $d = $this->validatedProfile($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => 'Membre et taux horaire requis.'];
            $id = EmploymentProfile::create((int)$user['family_id'], (int)$user['id'], $d);
            return ['success' => true, 'id' => $id];
        });
    }

    public function updateProfile(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $profile = $this->ownedProfile((int)$params['id']);
            if (!$profile) return ['success' => false, 'error' => 'Profil introuvable.'];
            $d = $this->validatedProfile($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => 'Membre et taux horaire requis.'];
            EmploymentProfile::update((int)$params['id'], (int)$user['family_id'], $d);
            return ['success' => true];
        });
    }

    public function deleteProfile(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $profile = $this->ownedProfile((int)$params['id']);
            if (!$profile) return ['success' => false];
            EmploymentProfile::delete((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    public function sirenLookup(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $siren = trim((string)($_GET['siren'] ?? ''));
            $result = SirenLookup::find($siren);
            if (!$result) return ['success' => false, 'error' => "Entreprise introuvable — vérifiez le SIREN ou saisissez les informations manuellement."];
            return ['success' => true] + $result;
        });
    }

    // ── Planning ──────────────────────────────────────────────────

    public function setSchedule(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $profile = $this->ownedProfile((int)$params['id']);
            if (!$profile) return ['success' => false];
            $days = (array)($this->jsonInput()['days'] ?? []);
            EmploymentProfile::setSchedule((int)$profile['id'], $days);
            return ['success' => true];
        });
    }

    public function addException(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $profile = $this->ownedProfile((int)$params['id']);
            if (!$profile) return ['success' => false];
            $d = $this->jsonInput();
            $date = trim($d['date'] ?? '');
            if (!$date) return ['success' => false, 'error' => 'Date requise.'];
            EmploymentProfile::setException((int)$profile['id'], $date, (float)($d['hours'] ?? 0), trim($d['note'] ?? '') ?: null);
            return ['success' => true];
        });
    }

    public function deleteException(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $profile = $this->ownedProfile((int)$params['id']);
            if (!$profile) return ['success' => false];
            EmploymentProfile::deleteException((int)$params['exceptionId'], (int)$profile['id']);
            return ['success' => true];
        });
    }

    // ── Congés / RTT ──────────────────────────────────────────────

    public function addLeaveAdjustment(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $profile = $this->ownedProfile((int)$params['id']);
            if (!$profile) return ['success' => false];
            $d = $this->jsonInput();
            $type = ($d['leave_type'] ?? '') === 'rtt' ? 'rtt' : 'paid_leave';
            $date = trim($d['date'] ?? '') ?: date('Y-m-d');
            $days = (float)($d['days'] ?? 0);
            if ($days == 0) return ['success' => false, 'error' => 'Nombre de jours requis.'];
            EmploymentProfile::addLeaveAdjustment((int)$profile['id'], $type, $date, $days, trim($d['note'] ?? '') ?: null);
            return ['success' => true];
        });
    }

    public function deleteLeaveAdjustment(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $profile = $this->ownedProfile((int)$params['id']);
            if (!$profile) return ['success' => false];
            EmploymentProfile::deleteLeaveAdjustment((int)$params['adjustmentId'], (int)$profile['id']);
            return ['success' => true];
        });
    }

    // ── Primes & paie ──────────────────────────────────────────────

    public function addPrime(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $profile = $this->ownedProfile((int)$params['id']);
            if (!$profile) return ['success' => false];
            $d = $this->jsonInput();
            $label = trim($d['label'] ?? '');
            $amount = (int)round((float)str_replace(',', '.', $d['amount'] ?? '0') * 100);
            if (!$label || $amount <= 0) return ['success' => false, 'error' => 'Libellé et montant requis.'];
            EmploymentProfile::addPrime((int)$profile['id'], (int)($d['year'] ?? date('Y')), (int)($d['month'] ?? date('n')), $label, $amount);
            return ['success' => true];
        });
    }

    public function deletePrime(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $profile = $this->ownedProfile((int)$params['id']);
            if (!$profile) return ['success' => false];
            EmploymentProfile::deletePrime((int)$params['primeId'], (int)$profile['id']);
            return ['success' => true];
        });
    }

    public function computePayslip(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $profile = $this->ownedProfile((int)$params['id']);
            if (!$profile) return ['success' => false];
            $d = $this->jsonInput();
            $year = (int)($d['year'] ?? date('Y'));
            $month = (int)($d['month'] ?? date('n'));
            if ($month < 1 || $month > 12) return ['success' => false, 'error' => 'Mois invalide.'];
            $payslip = EmploymentProfile::computePayslip($profile, $year, $month);
            return ['success' => true, 'payslip' => $payslip];
        });
    }

    public function payslipPdf(array $params): void
    {
        $this->requireAuth();
        $profile = $this->ownedProfile((int)$params['id']);
        if (!$profile) { http_response_code(404); echo 'Introuvable.'; return; }
        $year = (int)($params['year'] ?? date('Y'));
        $month = (int)($params['month'] ?? date('n'));
        $payslip = EmploymentProfile::getPayslip((int)$profile['id'], $year, $month);
        if (!$payslip) { http_response_code(404); echo "Aucune estimation calculée pour ce mois."; return; }

        $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $euros = fn(int $cents) => number_format($cents / 100, 2, ',', ' ') . ' €';
        $monthLabel = ucfirst((new \DateTime("$year-$month-01"))->format('F Y'));

        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11pt; color: #000; }
    h1 { font-size: 16pt; margin-bottom: 2px; }
    .subtitle { color: #666; font-size: 9pt; margin-top: 0; margin-bottom: 4px; }
    .warning { background: #FFF3CD; border: 1px solid #E6C200; padding: 8px 12px; font-size: 8.5pt; margin: 12px 0 20px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    td, th { padding: 5px 8px; border-bottom: 1px solid #eee; text-align: left; }
    .amount { text-align: right; }
    .total-row td { font-weight: bold; border-top: 2px solid #333; }
</style></head><body>
<h1>Estimation de paie — {$h($profile['user_name'])}</h1>
<p class="subtitle">{$h($profile['employer_name'] ?? '')} — {$h($monthLabel)}</p>
<div class="warning">⚠️ Ceci est une ESTIMATION personnelle, pas un bulletin de paie officiel. Les cotisations et le prélèvement à la source sont calculés avec des taux saisis manuellement, pas un barème légal détaillé. Ne vaut pas justificatif.</div>
<table>
<tr><th>Élément</th><th class="amount">Détail</th><th class="amount">Montant</th></tr>
<tr><td>Heures travaillées</td><td class="amount">{$h($payslip['worked_hours'])} h</td><td></td></tr>
<tr><td>Salaire de base</td><td></td><td class="amount">{$euros((int)$payslip['base_gross_cents'])}</td></tr>
<tr><td>Heures supplémentaires</td><td class="amount">{$h($payslip['overtime_tier1_hours'])} h @ +{$h($profile['overtime_rate1_pct'])}% / {$h($payslip['overtime_tier2_hours'])} h @ +{$h($profile['overtime_rate2_pct'])}%</td><td class="amount">{$euros((int)$payslip['overtime_gross_cents'])}</td></tr>
<tr><td>Primes</td><td></td><td class="amount">{$euros((int)$payslip['primes_cents'])}</td></tr>
<tr class="total-row"><td>Total brut</td><td></td><td class="amount">{$euros((int)$payslip['gross_total_cents'])}</td></tr>
<tr><td>Cotisations salariales (estimation)</td><td class="amount">{$h($payslip['cotisation_rate_pct'])}%</td><td class="amount">-{$euros((int)$payslip['gross_total_cents'] - (int)$payslip['net_social_cents'])}</td></tr>
<tr class="total-row"><td>Net social (estimation)</td><td></td><td class="amount">{$euros((int)$payslip['net_social_cents'])}</td></tr>
<tr><td>Prélèvement à la source (estimation)</td><td class="amount">{$h($payslip['pas_rate_pct'])}%</td><td class="amount">-{$euros((int)$payslip['net_social_cents'] - (int)$payslip['net_a_verser_cents'])}</td></tr>
<tr class="total-row"><td>Net estimé à verser</td><td></td><td class="amount">{$euros((int)$payslip['net_a_verser_cents'])}</td></tr>
</table>
<p class="subtitle" style="margin-top:20px">Généré le {$h(date('d/m/Y à H:i'))} par FamilyBoard.</p>
</body></html>
HTML;

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'estimation-paie-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $profile['user_name']) . "-$year-$month.pdf";
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $dompdf->output();
    }

    // ── Arrêts de travail ──────────────────────────────────────────

    public function addSickLeave(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $profile = $this->ownedProfile((int)$params['id']);
            if (!$profile) return ['success' => false];
            $d = $this->validatedSickLeave($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => 'Dates de début et de fin requises.'];
            $id = EmploymentProfile::addSickLeave((int)$profile['id'], (int)$user['id'], $d);
            return ['success' => true, 'id' => $id];
        });
    }

    public function updateSickLeave(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $profile = $this->ownedProfile((int)$params['id']);
            if (!$profile) return ['success' => false];
            $d = $this->validatedSickLeave($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => 'Dates de début et de fin requises.'];
            EmploymentProfile::updateSickLeave((int)$params['sickLeaveId'], (int)$profile['id'], $d);
            return ['success' => true];
        });
    }

    public function deleteSickLeave(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $profile = $this->ownedProfile((int)$params['id']);
            if (!$profile) return ['success' => false];
            EmploymentProfile::deleteSickLeave((int)$params['sickLeaveId'], (int)$profile['id']);
            return ['success' => true];
        });
    }

    // ── Documents liés ──────────────────────────────────────────────

    public function updateLinkedDocuments(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $profile = $this->ownedProfile((int)$params['id']);
            if (!$profile) return ['success' => false];
            $ids = array_map('intval', (array)($this->jsonInput()['document_ids'] ?? []));
            $familyDocIds = array_column(Document::getAll((int)$user['family_id']), 'id');
            $ids = array_values(array_intersect($ids, $familyDocIds));
            EmploymentProfile::setLinkedDocuments((int)$profile['id'], $ids);
            return ['success' => true];
        });
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function ownedProfile(int $id): ?array
    {
        $user = Session::user();
        $profile = EmploymentProfile::getById($id);
        if (!$profile || (int)$profile['family_id'] !== (int)$user['family_id']) return null;
        return $profile;
    }

    private function validatedProfile(array $d): ?array
    {
        $userId = (int)($d['user_id'] ?? 0);
        $hourlyRate = $d['hourly_rate'] ?? null;
        if (!$userId || $hourlyRate === null || $hourlyRate === '') return null;

        $user = Session::user();
        $member = User::findById($userId);
        if (!$member || (int)$member['family_id'] !== (int)$user['family_id']) return null;

        $payMode = ($d['pay_mode'] ?? 'hourly') === 'monthly' ? 'monthly' : 'hourly';
        $monthlyGross = $d['monthly_gross'] ?? null;

        return [
            'user_id' => $userId,
            'employer_siren' => preg_replace('/\D/', '', (string)($d['employer_siren'] ?? '')) ?: null,
            'employer_name' => trim($d['employer_name'] ?? '') ?: null,
            'employer_address' => trim($d['employer_address'] ?? '') ?: null,
            'job_title' => trim($d['job_title'] ?? '') ?: null,
            'contract_type' => in_array($d['contract_type'] ?? '', ['cdi', 'cdd', 'temps_partiel', 'apprentissage', 'autre'], true) ? $d['contract_type'] : 'cdi',
            'hire_date' => trim($d['hire_date'] ?? '') ?: null,
            'trial_period_end' => trim($d['trial_period_end'] ?? '') ?: null,
            'color' => $this->safeColor($d['color'] ?? null),
            'pay_mode' => $payMode,
            'hourly_rate_cents' => (int)round((float)str_replace(',', '.', (string)$hourlyRate) * 100),
            'monthly_gross_cents' => ($monthlyGross !== null && $monthlyGross !== '') ? (int)round((float)str_replace(',', '.', (string)$monthlyGross) * 100) : null,
            'contractual_weekly_hours' => (float)($d['contractual_weekly_hours'] ?? 35),
            'overtime_threshold_hours' => (float)($d['overtime_threshold_hours'] ?? 8),
            'overtime_rate1_pct' => (float)($d['overtime_rate1_pct'] ?? 25),
            'overtime_rate2_pct' => (float)($d['overtime_rate2_pct'] ?? 50),
            'leave_reset_month' => max(1, min(12, (int)($d['leave_reset_month'] ?? 6))),
            'leave_reset_day' => max(1, min(31, (int)($d['leave_reset_day'] ?? 1))),
            'leave_accrual_days_per_month' => (float)($d['leave_accrual_days_per_month'] ?? 2.5),
            'rtt_days_per_year' => (float)($d['rtt_days_per_year'] ?? 0),
            'cotisation_rate_pct' => ($d['cotisation_rate_pct'] ?? '') !== '' ? (float)$d['cotisation_rate_pct'] : null,
            'pas_rate_pct' => ($d['pas_rate_pct'] ?? '') !== '' ? (float)$d['pas_rate_pct'] : null,
        ];
    }

    private function validatedSickLeave(array $d): ?array
    {
        $start = trim($d['start_date'] ?? '');
        $end = trim($d['end_date'] ?? '');
        if (!$start || !$end) return null;
        $toCents = fn($v) => ($v !== null && $v !== '') ? (int)round((float)str_replace(',', '.', (string)$v) * 100) : null;
        return [
            'start_date' => $start,
            'end_date' => $end,
            'reason' => trim($d['reason'] ?? '') ?: null,
            'ijss_total_cents' => $toCents($d['ijss_total'] ?? null),
            'employer_complement_cents' => $toCents($d['employer_complement'] ?? null),
            'notes' => trim($d['notes'] ?? '') ?: null,
        ];
    }
}
