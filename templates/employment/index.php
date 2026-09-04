<?php
$pageTitle = 'Suivi salarié';
$extraJs = ['employment.js'];
ob_start();

use App\Core\DateHelper;

$dayNames = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
$scheduleByDay = [];
foreach ($schedule as $slot) $scheduleByDay[(int)$slot['day_of_week']] = $slot;
$contractLabels = ['cdi' => 'CDI', 'cdd' => 'CDD', 'temps_partiel' => 'Temps partiel', 'apprentissage' => 'Apprentissage', 'autre' => 'Autre'];
$monthNames = [1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'];
$euros = fn($cents) => number_format(((int)$cents) / 100, 2, ',', ' ') . ' €';
?>
<div class="tasks-container">
    <div class="tasks-sidebar">
        <div class="tasks-sidebar-header">
            <h3>Salariés</h3>
            <button class="btn-icon" onclick="openNewProfileModal()" title="Nouveau profil">+</button>
        </div>
        <ul class="lists-menu">
            <?php foreach ($profiles as $p): ?>
                <li class="list-item <?= $selected && (int)$selected['id'] === (int)$p['id'] ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>/employment?id=<?= $p['id'] ?>" class="list-link">
                        <span class="list-dot" style="background:<?= htmlspecialchars($p['color']) ?>"></span>
                        <span class="list-name"><?= htmlspecialchars($p['user_name']) ?><?= $p['employer_name'] ? ' · ' . htmlspecialchars($p['employer_name']) : '' ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php if (empty($profiles)): ?>
                <li class="empty-state">Aucun profil salarié.</li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="tasks-main">
        <?php if ($selected): ?>
            <div class="card" style="border-left:4px solid var(--warning, #E6C200);margin-bottom:1rem;padding:.75rem 1rem">
                <small>⚠️ Ce module fournit une <strong>estimation personnelle</strong> (congés, heures, paie), pas un bulletin de paie officiel ni un document légal. Vérifiez toujours avec votre vrai bulletin de paie.</small>
            </div>

            <div class="tasks-header">
                <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
                    <span class="list-dot-lg" style="background:<?= htmlspecialchars($selected['color']) ?>"></span>
                    <h2><?= htmlspecialchars($selected['user_name']) ?></h2>
                    <?php if ($selected['employer_name']): ?><span class="badge"><?= htmlspecialchars($selected['employer_name']) ?></span><?php endif; ?>
                    <span class="badge"><?= $contractLabels[$selected['contract_type']] ?? $selected['contract_type'] ?></span>
                </div>
                <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                    <button class="btn btn-secondary btn-sm" onclick="openEditProfileModal()">✏️ Modifier</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteProfile()">🗑 Supprimer</button>
                </div>
            </div>

            <div class="settings-tabs">
                <button type="button" class="settings-tab-btn active" data-tab="planning" onclick="switchEmploymentTab('planning')">📅 Planning</button>
                <button type="button" class="settings-tab-btn" data-tab="conges" onclick="switchEmploymentTab('conges')">🏖️ Congés &amp; RTT</button>
                <button type="button" class="settings-tab-btn" data-tab="absences" onclick="switchEmploymentTab('absences')">📋 Absences</button>
                <button type="button" class="settings-tab-btn" data-tab="arrets" onclick="switchEmploymentTab('arrets')">🏥 Arrêts de travail</button>
                <button type="button" class="settings-tab-btn" data-tab="paie" onclick="switchEmploymentTab('paie')">💶 Paie</button>
                <button type="button" class="settings-tab-btn" data-tab="documents" onclick="switchEmploymentTab('documents')">📎 Documents</button>
            </div>

            <!-- Planning -->
            <div class="settings-tab-panel active" data-tab="planning">
                <div class="card settings-section">
                    <h3 style="margin-top:0">Horaire hebdomadaire récurrent</h3>
                    <div class="form-row" style="flex-wrap:wrap;gap:.5rem">
                        <?php foreach ($dayNames as $dow => $label): ?>
                            <?php $slot = $scheduleByDay[$dow] ?? null; ?>
                            <div class="card" style="padding:.6rem;min-width:150px">
                                <label style="font-weight:600"><input type="checkbox" class="sched-day-enabled" data-day="<?= $dow ?>" <?= $slot ? 'checked' : '' ?> onchange="toggleSchedDay(<?= $dow ?>)"> <?= $label ?></label>
                                <div id="sched-day-<?= $dow ?>-fields" style="margin-top:.4rem;<?= $slot ? '' : 'display:none' ?>">
                                    <input type="time" class="sched-start" data-day="<?= $dow ?>" value="<?= $slot['start_time'] ?? '09:00' ?>" style="margin-bottom:.3rem">
                                    <input type="time" class="sched-end" data-day="<?= $dow ?>" value="<?= $slot['end_time'] ?? '17:00' ?>" style="margin-bottom:.3rem">
                                    <input type="number" class="sched-break" data-day="<?= $dow ?>" value="<?= $slot['break_minutes'] ?? 0 ?>" placeholder="Pause (min)">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" style="margin-top:1rem" onclick="saveSchedule()">Enregistrer le planning</button>
                </div>

                <div class="card settings-section">
                    <h3 style="margin-top:0">Correctifs jour par jour</h3>
                    <p style="color:var(--text-muted);font-size:.85rem">Remplace le gabarit récurrent pour une date précise (heure supplémentaire ponctuelle, jour normalement travaillé mais chômé…). 0h = jour non travaillé ce jour-là.</p>
                    <?php if (empty($exceptions)): ?>
                        <p style="color:var(--text-muted);font-size:.85rem">Aucun correctif.</p>
                    <?php else: ?>
                    <?php foreach ($exceptions as $ex): ?>
                    <div class="member-item">
                        <div class="member-info">
                            <strong><?= DateHelper::format($ex['exception_date'], 'd/m/Y') ?></strong> — <?= htmlspecialchars((string)$ex['hours_worked']) ?> h
                            <?php if ($ex['note']): ?><br><small><?= htmlspecialchars($ex['note']) ?></small><?php endif; ?>
                        </div>
                        <button class="btn btn-danger btn-sm" onclick="deleteException(<?= $ex['id'] ?>)">✕</button>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    <hr style="margin:1rem 0;border-color:var(--border)">
                    <div class="form-row">
                        <div class="form-group"><label>Date</label><input type="date" id="exception-date"></div>
                        <div class="form-group"><label>Heures travaillées</label><input type="number" id="exception-hours" step="0.25" value="0"></div>
                        <div class="form-group flex-2"><label>Note</label><input type="text" id="exception-note"></div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="addException()">+ Ajouter le correctif</button>
                </div>
            </div>

            <!-- Congés & RTT -->
            <div class="settings-tab-panel" data-tab="conges">
                <div class="card settings-section">
                    <h3 style="margin-top:0">🏖️ Congés payés</h3>
                    <p style="color:var(--text-muted);font-size:.85rem">Depuis le <?= DateHelper::format($paidLeaveBalance['anchor'], 'd/m/Y') ?> (remise à zéro annuelle).</p>
                    <div style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-bottom:1rem">
                        <div><strong style="font-size:1.4rem"><?= $paidLeaveBalance['acquired'] ?></strong><br><small>Acquis</small></div>
                        <div><strong style="font-size:1.4rem"><?= $paidLeaveBalance['taken'] ?></strong><br><small>Pris</small></div>
                        <div><strong style="font-size:1.4rem"><?= $paidLeaveBalance['adjustments'] >= 0 ? '+' : '' ?><?= $paidLeaveBalance['adjustments'] ?></strong><br><small>Ajustements</small></div>
                        <div><strong style="font-size:1.6rem;color:var(--primary)"><?= $paidLeaveBalance['balance'] ?></strong><br><small>Solde</small></div>
                    </div>
                    <p style="color:var(--text-muted);font-size:.85rem">Les congés posés se gèrent depuis le <a href="<?= BASE_URL ?>/calendar">Calendrier</a> (coche « Congé / absence salarié » → « Congé payé » sur un événement) — ils apparaissent ici automatiquement, en ne déduisant que les jours normalement travaillés.</p>
                    <?php if (!empty($paidLeaveEvents)): ?>
                    <details style="margin-top:.5rem"><summary style="cursor:pointer">Voir les congés posés (<?= count($paidLeaveEvents) ?>)</summary>
                        <?php foreach ($paidLeaveEvents as $ev): ?>
                        <div class="member-item"><div class="member-info"><?= htmlspecialchars($ev['title']) ?> — <?= DateHelper::format($ev['start_datetime'], 'd/m/Y') ?> → <?= DateHelper::format($ev['end_datetime'], 'd/m/Y') ?></div></div>
                        <?php endforeach; ?>
                    </details>
                    <?php endif; ?>
                    <hr style="margin:1rem 0;border-color:var(--border)">
                    <strong>Ajustements manuels (report, régularisation…)</strong>
                    <?php foreach (\App\Models\EmploymentProfile::getLeaveAdjustments((int)$selected['id'], 'paid_leave') as $adj): ?>
                    <div class="member-item">
                        <div class="member-info"><?= DateHelper::format($adj['adjustment_date'], 'd/m/Y') ?> — <?= $adj['days'] >= 0 ? '+' : '' ?><?= $adj['days'] ?> j<?= $adj['note'] ? ' — ' . htmlspecialchars($adj['note']) : '' ?></div>
                        <button class="btn btn-danger btn-sm" onclick="deleteLeaveAdjustment(<?= $adj['id'] ?>)">✕</button>
                    </div>
                    <?php endforeach; ?>
                    <div class="form-row" style="margin-top:.5rem">
                        <div class="form-group"><label>Date</label><input type="date" id="cp-adj-date" value="<?= date('Y-m-d') ?>"></div>
                        <div class="form-group"><label>Jours (+/-)</label><input type="number" id="cp-adj-days" step="0.5"></div>
                        <div class="form-group flex-2"><label>Note</label><input type="text" id="cp-adj-note"></div>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addLeaveAdjustment('paid_leave')">+ Ajouter l'ajustement</button>
                </div>

                <div class="card settings-section">
                    <h3 style="margin-top:0">⏱️ RTT</h3>
                    <div style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-bottom:1rem">
                        <div><strong style="font-size:1.4rem"><?= $rttBalance['acquired'] ?></strong><br><small>Acquis</small></div>
                        <div><strong style="font-size:1.4rem"><?= $rttBalance['taken'] ?></strong><br><small>Pris</small></div>
                        <div><strong style="font-size:1.4rem"><?= $rttBalance['adjustments'] >= 0 ? '+' : '' ?><?= $rttBalance['adjustments'] ?></strong><br><small>Ajustements</small></div>
                        <div><strong style="font-size:1.6rem;color:var(--primary)"><?= $rttBalance['balance'] ?></strong><br><small>Solde</small></div>
                    </div>
                    <?php if (!empty($rttEvents)): ?>
                    <details style="margin-top:.5rem"><summary style="cursor:pointer">Voir les RTT posées (<?= count($rttEvents) ?>)</summary>
                        <?php foreach ($rttEvents as $ev): ?>
                        <div class="member-item"><div class="member-info"><?= htmlspecialchars($ev['title']) ?> — <?= DateHelper::format($ev['start_datetime'], 'd/m/Y') ?> → <?= DateHelper::format($ev['end_datetime'], 'd/m/Y') ?></div></div>
                        <?php endforeach; ?>
                    </details>
                    <?php endif; ?>
                    <hr style="margin:1rem 0;border-color:var(--border)">
                    <strong>Ajustements manuels</strong>
                    <?php foreach (\App\Models\EmploymentProfile::getLeaveAdjustments((int)$selected['id'], 'rtt') as $adj): ?>
                    <div class="member-item">
                        <div class="member-info"><?= DateHelper::format($adj['adjustment_date'], 'd/m/Y') ?> — <?= $adj['days'] >= 0 ? '+' : '' ?><?= $adj['days'] ?> j<?= $adj['note'] ? ' — ' . htmlspecialchars($adj['note']) : '' ?></div>
                        <button class="btn btn-danger btn-sm" onclick="deleteLeaveAdjustment(<?= $adj['id'] ?>)">✕</button>
                    </div>
                    <?php endforeach; ?>
                    <div class="form-row" style="margin-top:.5rem">
                        <div class="form-group"><label>Date</label><input type="date" id="rtt-adj-date" value="<?= date('Y-m-d') ?>"></div>
                        <div class="form-group"><label>Jours (+/-)</label><input type="number" id="rtt-adj-days" step="0.5"></div>
                        <div class="form-group flex-2"><label>Note</label><input type="text" id="rtt-adj-note"></div>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addLeaveAdjustment('rtt')">+ Ajouter l'ajustement</button>
                </div>
            </div>

            <!-- Absences -->
            <div class="settings-tab-panel" data-tab="absences">
                <div class="card settings-section">
                    <p style="color:var(--text-muted);font-size:.85rem">Absences non payées, saisies depuis le <a href="<?= BASE_URL ?>/calendar">Calendrier</a> (« Congé / absence salarié » → « Absence non payée »).</p>
                    <?php if (empty($unpaidAbsences)): ?>
                        <p style="color:var(--text-muted);font-size:.85rem">Aucune absence enregistrée.</p>
                    <?php else: ?>
                    <?php foreach ($unpaidAbsences as $ab): ?>
                    <div class="member-item"><div class="member-info"><strong><?= htmlspecialchars($ab['title']) ?></strong><br><small><?= DateHelper::format($ab['start_datetime'], 'd/m/Y') ?> → <?= DateHelper::format($ab['end_datetime'], 'd/m/Y') ?></small></div></div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Arrêts de travail -->
            <div class="settings-tab-panel" data-tab="arrets">
                <div class="card settings-section">
                    <?php if (empty($sickLeaves)): ?>
                        <p style="color:var(--text-muted);font-size:.85rem">Aucun arrêt de travail enregistré.</p>
                    <?php else: ?>
                    <?php foreach ($sickLeaves as $sl): ?>
                    <div class="member-item" data-sick-id="<?= $sl['id'] ?>">
                        <div class="member-info">
                            <strong><?= DateHelper::format($sl['start_date'], 'd/m/Y') ?> → <?= DateHelper::format($sl['end_date'], 'd/m/Y') ?></strong>
                            <?= $sl['reason'] ? ' — ' . htmlspecialchars($sl['reason']) : '' ?>
                            <br><small>
                                <?php if ($sl['ijss_total_cents'] !== null): ?>IJSS : <?= $euros($sl['ijss_total_cents']) ?><?php endif; ?>
                                <?php if ($sl['employer_complement_cents'] !== null): ?> · Complément employeur : <?= $euros($sl['employer_complement_cents']) ?><?php endif; ?>
                            </small>
                        </div>
                        <button class="btn btn-danger btn-sm" onclick="deleteSickLeave(<?= $sl['id'] ?>)">✕</button>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    <hr style="margin:1rem 0;border-color:var(--border)">
                    <div class="form-row">
                        <div class="form-group"><label>Début</label><input type="date" id="sick-start"></div>
                        <div class="form-group"><label>Fin</label><input type="date" id="sick-end"></div>
                        <div class="form-group flex-2"><label>Motif</label><input type="text" id="sick-reason"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>IJSS reçues (€)</label><input type="text" id="sick-ijss" placeholder="Montant communiqué par l'Assurance Maladie"></div>
                        <div class="form-group"><label>Complément employeur (€)</label><input type="text" id="sick-complement"></div>
                    </div>
                    <div class="form-group"><label>Notes</label><textarea id="sick-notes" rows="2"></textarea></div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="addSickLeave()">+ Ajouter l'arrêt de travail</button>
                    <p style="color:var(--text-muted);font-size:.8rem;margin-top:.5rem">Pour joindre l'arrêt de travail lui-même, uploadez-le dans le module <a href="<?= BASE_URL ?>/documents">Documents</a> puis liez-le depuis l'onglet « Documents » ci-contre.</p>
                </div>
            </div>

            <!-- Paie -->
            <div class="settings-tab-panel" data-tab="paie">
                <div class="card settings-section">
                    <h3 style="margin-top:0">Estimation du mois</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Mois</label>
                            <select id="pay-month" onchange="reloadPayPeriod()">
                                <?php foreach ($monthNames as $n => $label): ?><option value="<?= $n ?>" <?= $n === $periodMonth ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Année</label>
                            <input type="number" id="pay-year" value="<?= $periodYear ?>" onchange="reloadPayPeriod()" style="max-width:100px">
                        </div>
                        <div class="form-group" style="align-self:flex-end">
                            <button type="button" class="btn btn-primary btn-sm" onclick="computePayslip()">⚙️ Calculer</button>
                        </div>
                    </div>

                    <strong>Primes de <?= $monthNames[$periodMonth] ?> <?= $periodYear ?></strong>
                    <?php foreach (($primes ?? []) as $pr): ?>
                    <div class="member-item">
                        <div class="member-info"><?= htmlspecialchars($pr['label']) ?> — <?= $euros($pr['amount_cents']) ?></div>
                        <button class="btn btn-danger btn-sm" onclick="deletePrime(<?= $pr['id'] ?>)">✕</button>
                    </div>
                    <?php endforeach; ?>
                    <div class="form-row" style="margin-top:.4rem">
                        <div class="form-group flex-2"><label>Libellé</label><input type="text" id="prime-label" placeholder="13e mois, prime exceptionnelle…"></div>
                        <div class="form-group"><label>Montant (€)</label><input type="text" id="prime-amount"></div>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addPrime()">+ Ajouter la prime</button>

                    <hr style="margin:1.25rem 0;border-color:var(--border)">

                    <?php if ($payslip): ?>
                    <table class="admin-table">
                        <tr><td>Heures travaillées</td><td style="text-align:right"><?= $payslip['worked_hours'] ?> h</td></tr>
                        <tr><td>Salaire de base</td><td style="text-align:right"><?= $euros($payslip['base_gross_cents']) ?></td></tr>
                        <tr><td>Heures supplémentaires (<?= $payslip['overtime_tier1_hours'] ?> h + <?= $payslip['overtime_tier2_hours'] ?> h)</td><td style="text-align:right"><?= $euros($payslip['overtime_gross_cents']) ?></td></tr>
                        <tr><td>Primes</td><td style="text-align:right"><?= $euros($payslip['primes_cents']) ?></td></tr>
                        <tr style="font-weight:700"><td>Total brut</td><td style="text-align:right"><?= $euros($payslip['gross_total_cents']) ?></td></tr>
                        <tr><td>Cotisations estimées (<?= $payslip['cotisation_rate_pct'] ?>%)</td><td style="text-align:right">-<?= $euros($payslip['gross_total_cents'] - $payslip['net_social_cents']) ?></td></tr>
                        <tr style="font-weight:700"><td>Net social estimé</td><td style="text-align:right"><?= $euros($payslip['net_social_cents']) ?></td></tr>
                        <tr><td>Prélèvement à la source estimé (<?= $payslip['pas_rate_pct'] ?>%)</td><td style="text-align:right">-<?= $euros($payslip['net_social_cents'] - $payslip['net_a_verser_cents']) ?></td></tr>
                        <tr style="font-weight:700;color:var(--primary)"><td>Net estimé à verser</td><td style="text-align:right"><?= $euros($payslip['net_a_verser_cents']) ?></td></tr>
                    </table>
                    <a class="btn btn-secondary btn-sm" style="margin-top:.75rem" href="<?= BASE_URL ?>/employment/profiles/<?= $selected['id'] ?>/payslip/<?= $periodYear ?>/<?= $periodMonth ?>/pdf" target="_blank">⬇️ Télécharger le PDF</a>
                    <?php else: ?>
                    <p style="color:var(--text-muted)">Aucune estimation calculée pour cette période — cliquez sur « Calculer ».</p>
                    <?php endif; ?>
                </div>

                <?php if (!empty($payslips)): ?>
                <div class="card settings-section">
                    <h3 style="margin-top:0">Historique</h3>
                    <?php foreach ($payslips as $ps): ?>
                    <div class="member-item">
                        <div class="member-info"><?= $monthNames[(int)$ps['period_month']] ?> <?= $ps['period_year'] ?> — Net à verser estimé : <?= $euros($ps['net_a_verser_cents']) ?></div>
                        <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>/employment/profiles/<?= $selected['id'] ?>/payslip/<?= $ps['period_year'] ?>/<?= $ps['period_month'] ?>/pdf" target="_blank">PDF</a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Documents -->
            <div class="settings-tab-panel" data-tab="documents">
                <div class="card settings-section">
                    <p style="color:var(--text-muted);font-size:.85rem">Bulletins de paie, contrat de travail, arrêts de travail scannés… uploadez-les dans le module <a href="<?= BASE_URL ?>/documents">Documents</a> puis liez-les ici.</p>
                    <div style="max-height:260px;overflow-y:auto;border:1px solid var(--border);border-radius:6px;padding:.5rem">
                        <?php $linkedIds = array_column($linkedDocuments, 'id'); ?>
                        <?php if (empty($familyDocuments)): ?>
                            <p style="color:var(--text-muted);font-size:.85rem;margin:0">Aucun document dans le module Documents.</p>
                        <?php endif; ?>
                        <?php foreach ($familyDocuments as $doc): ?>
                            <label style="display:block;font-weight:normal;margin-bottom:.3rem">
                                <input type="checkbox" class="link-doc-cb" value="<?= $doc['id'] ?>" <?= in_array($doc['id'], $linkedIds) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($doc['title']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" style="margin-top:.75rem" onclick="saveLinkedDocuments()">Enregistrer</button>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state-card">
                <p>Ajoutez un profil pour commencer le suivi salarié.</p>
                <button class="btn btn-primary" onclick="openNewProfileModal()">+ Nouveau profil</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Profil -->
<div class="modal-overlay" id="profile-modal" style="display:none">
    <div class="modal" style="max-width:640px">
        <div class="modal-header">
            <h3 id="profile-modal-title">Nouveau profil salarié</h3>
            <button onclick="closeModal('profile-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="profile-id">
            <div class="form-group">
                <label>Membre de la famille <span style="color:var(--danger)">*</span></label>
                <select id="profile-user-id">
                    <?php foreach ($familyMembers as $m): ?>
                        <?php if ($m['role'] === 'coparent') continue; ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <h4>Employeur</h4>
            <div class="form-row">
                <div class="form-group">
                    <label>SIREN</label>
                    <input type="text" id="profile-siren" maxlength="9" placeholder="9 chiffres">
                </div>
                <div class="form-group" style="align-self:flex-end">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="lookupSiren()">🔍 Rechercher</button>
                </div>
            </div>
            <div id="siren-lookup-result" style="margin-bottom:.5rem"></div>
            <div class="form-row">
                <div class="form-group flex-2"><label>Nom de l'entreprise</label><input type="text" id="profile-employer-name"></div>
                <div class="form-group flex-2"><label>Adresse</label><input type="text" id="profile-employer-address"></div>
            </div>

            <h4>Contrat</h4>
            <div class="form-row">
                <div class="form-group flex-2"><label>Intitulé du poste</label><input type="text" id="profile-job-title"></div>
                <div class="form-group">
                    <label>Type de contrat</label>
                    <select id="profile-contract-type">
                        <option value="cdi">CDI</option>
                        <option value="cdd">CDD</option>
                        <option value="temps_partiel">Temps partiel</option>
                        <option value="apprentissage">Apprentissage</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Date d'embauche</label><input type="date" id="profile-hire-date"></div>
                <div class="form-group"><label>Fin de période d'essai</label><input type="date" id="profile-trial-end"></div>
                <div class="form-group"><label>Couleur</label><input type="color" id="profile-color" value="#4A90D9"></div>
            </div>

            <h4>Rémunération</h4>
            <div class="form-row">
                <div class="form-group">
                    <label>Mode de rémunération</label>
                    <select id="profile-pay-mode" onchange="toggleMonthlyGrossField()">
                        <option value="hourly">Taux horaire</option>
                        <option value="monthly">Salaire mensuel fixe</option>
                    </select>
                </div>
                <div class="form-group"><label>Taux horaire brut (€) <span style="color:var(--danger)">*</span></label><input type="text" id="profile-hourly-rate" placeholder="11.65"></div>
                <div class="form-group" id="profile-monthly-gross-wrap" style="display:none"><label>Salaire mensuel brut fixe (€)</label><input type="text" id="profile-monthly-gross"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Heures contractuelles/semaine</label><input type="number" id="profile-weekly-hours" value="35" step="0.5"></div>
                <div class="form-group"><label>Seuil heures sup. tranche 2 (h/sem.)</label><input type="number" id="profile-overtime-threshold" value="8" step="0.5"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Majoration tranche 1 (%)</label><input type="number" id="profile-overtime-rate1" value="25"></div>
                <div class="form-group"><label>Majoration tranche 2 (%)</label><input type="number" id="profile-overtime-rate2" value="50"></div>
            </div>

            <h4>Congés &amp; RTT</h4>
            <div class="form-row">
                <div class="form-group"><label>Remise à zéro — jour</label><input type="number" id="profile-reset-day" value="1" min="1" max="31"></div>
                <div class="form-group"><label>Remise à zéro — mois</label>
                    <select id="profile-reset-month">
                        <?php foreach ($monthNames as $n => $label): ?><option value="<?= $n ?>" <?= $n === 6 ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Congés acquis / mois</label><input type="number" id="profile-leave-accrual" value="2.5" step="0.1"></div>
                <div class="form-group"><label>RTT / an</label><input type="number" id="profile-rtt-per-year" value="0" step="0.5"></div>
            </div>

            <h4>Estimation de paie</h4>
            <div class="form-row">
                <div class="form-group"><label>Taux de cotisations salariales (%)</label><input type="number" id="profile-cotisation-rate" step="0.1" placeholder="ex. 23"></div>
                <div class="form-group"><label>Taux de prélèvement à la source (%)</label><input type="number" id="profile-pas-rate" step="0.1" placeholder="ex. 5"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('profile-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveProfile()">Enregistrer</button>
        </div>
    </div>
</div>

<script>
const PROFILE_ID = <?= json_encode($selected['id'] ?? null) ?>;
const SELECTED_PROFILE = <?= json_encode($selected) ?>;
const BASE_URL = <?= json_encode(BASE_URL) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
