<?php
namespace App\Core;

use App\Models\Family;

/** Rapport mensuel du chiffre d'affaires encaissé par la plateforme FamilyBoard elle-même
 *  via Stripe (abonnements payés par les familles) — à ne pas confondre avec le module Budget,
 *  qui suit les finances PERSONNELLES d'une famille. Sert la déclaration URSSAF mensuelle de
 *  l'auto-entrepreneur exploitant la plateforme (voir cron.php::sendUrssafReport() et
 *  AdminController pour la configuration : jour d'envoi, activation).
 *
 *  Un paiement Stripe payé un jour donné n'est pas forcément CRÉÉ ce jour-là (facture émise
 *  la veille par exemple) — l'API Stripe ne filtre les factures que sur leur date de création,
 *  jamais sur leur date de paiement. On interroge donc une fenêtre légèrement plus large, puis
 *  on filtre précisément en PHP sur status_transitions.paid_at. */
class UrssafReport
{
    /** @return \Stripe\Invoice[] triées par date de paiement croissante */
    public static function paidInvoicesForMonth(int $year, int $month): array
    {
        $client = StripeGateway::client();
        if (!$client) return [];

        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), new \DateTimeZone('UTC'));
        $end = $start->modify('+1 month');
        $queryStart = $start->modify('-5 days');
        $queryEnd = $end->modify('+5 days');

        $invoices = [];
        $params = [
            'status' => 'paid',
            'created' => ['gte' => $queryStart->getTimestamp(), 'lt' => $queryEnd->getTimestamp()],
            'limit' => 100,
        ];
        do {
            $page = $client->invoices->all($params);
            foreach ($page->data as $inv) {
                $paidAt = $inv->status_transitions->paid_at ?? null;
                if ($paidAt === null || $paidAt < $start->getTimestamp() || $paidAt >= $end->getTimestamp()) continue;
                $invoices[] = $inv;
            }
            $last = $page->data ? end($page->data) : null;
            $params['starting_after'] = $last ? $last->id : null;
        } while (!empty($page->has_more) && $params['starting_after']);

        usort($invoices, fn($a, $b) => $a->status_transitions->paid_at <=> $b->status_transitions->paid_at);
        return $invoices;
    }

    /** @return array{rows: array<int, array{date: \DateTimeImmutable, family_name: string, description: string, invoice_number: string, amount_cents: int}>, total_cents: int, currency: string} */
    public static function buildSummary(int $year, int $month): array
    {
        $invoices = self::paidInvoicesForMonth($year, $month);

        // Rattache chaque facture à la famille correspondante via son customer Stripe, pour un
        // rapport lisible (plutôt qu'une liste d'identifiants Stripe opaques).
        $customerToFamily = [];
        foreach (Database::fetchAll('SELECT family_id, stripe_customer_id FROM family_subscriptions WHERE stripe_customer_id IS NOT NULL') as $row) {
            $customerToFamily[$row['stripe_customer_id']] = (int)$row['family_id'];
        }
        $familyNames = [];

        $rows = [];
        $totalCents = 0;
        $currency = 'eur';
        foreach ($invoices as $inv) {
            $familyId = $customerToFamily[$inv->customer] ?? null;
            $familyName = null;
            if ($familyId) {
                if (!array_key_exists($familyId, $familyNames)) {
                    $familyNames[$familyId] = Family::findById($familyId)['name'] ?? null;
                }
                $familyName = $familyNames[$familyId];
            }
            $rows[] = [
                'date' => (new \DateTimeImmutable('@' . $inv->status_transitions->paid_at))->setTimezone(new \DateTimeZone('Europe/Paris')),
                'family_name' => $familyName ?? ('Client Stripe …' . substr((string)$inv->customer, -6)),
                'description' => $inv->lines->data[0]->description ?? ($inv->number ?? $inv->id),
                'invoice_number' => $inv->number ?? $inv->id,
                'amount_cents' => (int)$inv->amount_paid,
            ];
            $totalCents += (int)$inv->amount_paid;
            $currency = strtolower($inv->currency ?? 'eur');
        }

        return ['rows' => $rows, 'total_cents' => $totalCents, 'currency' => $currency];
    }

    private const MONTH_NAMES = [1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
        7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'];

    public static function monthLabel(int $year, int $month): string
    {
        return self::MONTH_NAMES[$month] . ' ' . $year;
    }

    public static function generatePdf(int $year, int $month): string
    {
        $summary = self::buildSummary($year, $month);
        $monthLabel = self::monthLabel($year, $month);
        $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $euros = fn(int $cents) => number_format($cents / 100, 2, ',', ' ') . ' €';

        $rows = '';
        foreach ($summary['rows'] as $r) {
            $rows .= '<tr><td>' . $h($r['date']->format('d/m/Y')) . '</td><td>' . $h($r['family_name']) . '</td>'
                . '<td>' . $h($r['description']) . '</td><td>' . $h($r['invoice_number']) . '</td>'
                . '<td class="amount">' . $h($euros($r['amount_cents'])) . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="5" style="text-align:center;color:#888">Aucune transaction encaissée ce mois-ci.</td></tr>';
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10.5pt; color: #000; }
    h1 { font-size: 16pt; margin-bottom: 2px; }
    .subtitle { color: #666; font-size: 9pt; margin-top: 0; margin-bottom: 4px; }
    .warning { background: #FFF3CD; border: 1px solid #E6C200; padding: 10px 14px; font-size: 9.5pt; margin: 16px 0 20px; }
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    td, th { padding: 5px 8px; border-bottom: 1px solid #eee; text-align: left; font-size: 9.5pt; }
    .amount { text-align: right; }
    .total-row td { font-weight: bold; border-top: 2px solid #333; font-size: 11pt; }
</style></head><body>
<h1>Chiffre d'affaires FamilyBoard — {$h($monthLabel)}</h1>
<p class="subtitle">Détail des paiements Stripe encaissés sur la période, pour la déclaration URSSAF (auto-entrepreneur).</p>
<div class="warning">⚠️ Ce document liste les paiements encaissés via Stripe sur ce mois civil. Vérifiez-le avant déclaration : un remboursement, un litige bancaire ou un paiement dans une devise étrangère peuvent nécessiter un ajustement manuel non reflété ici.</div>
<table>
<tr><th>Date</th><th>Famille</th><th>Description</th><th>N° facture</th><th class="amount">Montant encaissé</th></tr>
{$rows}
<tr class="total-row"><td colspan="4">Chiffre d'affaires à déclarer</td><td class="amount">{$h($euros($summary['total_cents']))}</td></tr>
</table>
<p class="subtitle" style="margin-top:24px">Généré automatiquement le {$h(date('d/m/Y à H:i'))} par FamilyBoard.</p>
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
        return $dompdf->output();
    }

    /** Génère le PDF du mois donné et l'envoie par e-mail à l'adresse admin — utilisé par le
     *  cron mensuel (cron.php::sendUrssafReport()) comme par le bouton d'envoi manuel du
     *  panneau admin. Retourne false sans lever si l'envoi SMTP échoue (voir Mail::send()). */
    public static function sendForMonth(int $year, int $month, string $adminEmail): bool
    {
        $pdf = self::generatePdf($year, $month);
        $summary = self::buildSummary($year, $month);
        $monthLabel = self::monthLabel($year, $month);
        $totalLabel = number_format($summary['total_cents'] / 100, 2, ',', ' ') . ' €';

        $html = '<p>Le rapport de chiffre d\'affaires FamilyBoard pour <strong>' . htmlspecialchars($monthLabel) . '</strong> est en pièce jointe.</p>'
              . '<p>Chiffre d\'affaires encaissé sur la période : <strong>' . htmlspecialchars($totalLabel) . '</strong>.</p>'
              . '<p>⚠️ Pensez à effectuer votre déclaration URSSAF pour cette période.</p>';

        $filename = 'urssaf-' . $year . '-' . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '.pdf';
        return Mail::send(0, $adminEmail, 'Administrateur', 'Rapport URSSAF — ' . $monthLabel, $html, 'urssaf_report', null, [
            ['filename' => $filename, 'content' => $pdf, 'mime' => 'application/pdf'],
        ]);
    }
}
