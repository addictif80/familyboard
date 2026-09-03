<?php
$pageTitle = 'Suivi scolaire';
$extraJs = ['school.js'];
ob_start();

use App\Core\DateHelper;

$dayNames = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
$timetableByDay = [];
foreach ($timetable as $slot) {
    $timetableByDay[(int)$slot['day_of_week']][] = $slot;
}
?>
<div class="tasks-container">
    <div class="tasks-sidebar">
        <div class="tasks-sidebar-header">
            <h3>Élèves</h3>
            <button class="btn-icon" onclick="openNewStudentModal()" title="Nouvel élève">+</button>
        </div>
        <ul class="lists-menu">
            <?php foreach ($students as $s): ?>
                <li class="list-item <?= $selected && (int)$selected['id'] === (int)$s['id'] ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>/school?id=<?= $s['id'] ?>" class="list-link">
                        <span class="list-dot" style="background:<?= htmlspecialchars($s['color']) ?>"></span>
                        <span class="list-name"><?= htmlspecialchars($s['name']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php if (empty($students)): ?>
                <li class="empty-state">Aucun élève.</li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="tasks-main">
        <?php if ($selected): ?>
            <div class="tasks-header">
                <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
                    <span class="list-dot-lg" style="background:<?= htmlspecialchars($selected['color']) ?>"></span>
                    <h2><?= htmlspecialchars($selected['name']) ?></h2>
                    <?php if ($selected['class_name']): ?><span class="badge"><?= htmlspecialchars($selected['class_name']) ?></span><?php endif; ?>
                    <?php if ($selected['school_name']): ?><span class="badge"><?= htmlspecialchars($selected['school_name']) ?></span><?php endif; ?>
                </div>
                <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                    <button class="btn btn-secondary btn-sm" onclick="openEditStudentModal()">✏️ Modifier</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteStudent()">🗑 Supprimer</button>
                </div>
            </div>

            <div class="settings-tabs">
                <button type="button" class="settings-tab-btn active" data-tab="timetable" onclick="switchSchoolTab('timetable')">📅 Emploi du temps</button>
                <button type="button" class="settings-tab-btn" data-tab="grades" onclick="switchSchoolTab('grades')">📊 Notes</button>
                <button type="button" class="settings-tab-btn" data-tab="absences" onclick="switchSchoolTab('absences')">🚫 Absences</button>
                <button type="button" class="settings-tab-btn" data-tab="subjects" onclick="switchSchoolTab('subjects')">🧑‍🏫 Matières &amp; profs</button>
                <button type="button" class="settings-tab-btn" data-tab="activities" onclick="switchSchoolTab('activities')">🏅 Activités</button>
                <button type="button" class="settings-tab-btn" data-tab="documents" onclick="switchSchoolTab('documents')">📎 Documents</button>
            </div>

            <!-- Emploi du temps -->
            <div class="settings-tab-panel active" data-tab="timetable">
                <div class="card settings-section">
                    <?php if (empty($subjects)): ?>
                        <p style="color:var(--text-muted);font-size:.85rem">Ajoutez d'abord une matière (onglet « Matières &amp; profs ») avant de créer un créneau.</p>
                    <?php else: ?>
                    <?php foreach ($dayNames as $dayNum => $dayLabel): ?>
                        <?php if (empty($timetableByDay[$dayNum])) continue; ?>
                        <div style="margin-bottom:1rem">
                            <div style="font-weight:600;margin-bottom:.4rem"><?= $dayLabel ?></div>
                            <?php foreach ($timetableByDay[$dayNum] as $slot): ?>
                            <div class="member-item" data-slot-id="<?= $slot['id'] ?>">
                                <div class="member-info">
                                    <strong style="color:<?= htmlspecialchars($slot['subject_color']) ?>"><?= htmlspecialchars($slot['subject_name']) ?></strong>
                                    <small><?= substr($slot['start_time'], 0, 5) ?>–<?= substr($slot['end_time'], 0, 5) ?><?= $slot['room'] ? ' · Salle ' . htmlspecialchars($slot['room']) : '' ?><?= $slot['teacher_name'] ? ' · ' . htmlspecialchars($slot['teacher_name']) : '' ?></small>
                                </div>
                                <button class="btn btn-danger btn-sm" onclick="deleteTimetableSlot(<?= $slot['id'] ?>)">✕</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($timetable)): ?>
                        <p style="color:var(--text-muted);font-size:.85rem">Aucun créneau.</p>
                    <?php endif; ?>
                    <hr style="margin:1rem 0;border-color:var(--border)">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Jour</label>
                            <select id="slot-day">
                                <?php foreach ($dayNames as $n => $l): ?><option value="<?= $n ?>"><?= $l ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Matière</label>
                            <select id="slot-subject">
                                <?php foreach ($subjects as $sub): ?><option value="<?= $sub['id'] ?>"><?= htmlspecialchars($sub['name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Début</label>
                            <input type="time" id="slot-start">
                        </div>
                        <div class="form-group">
                            <label>Fin</label>
                            <input type="time" id="slot-end">
                        </div>
                        <div class="form-group">
                            <label>Salle</label>
                            <input type="text" id="slot-room" placeholder="B12">
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="addTimetableSlot()">+ Ajouter le créneau</button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Notes -->
            <div class="settings-tab-panel" data-tab="grades">
                <div class="card settings-section">
                    <?php if ($averages['overall'] !== null): ?>
                    <div style="margin-bottom:1rem">
                        <strong>Moyenne générale : <?= number_format($averages['overall'], 1) ?>/20</strong>
                        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.5rem">
                            <?php foreach ($averages['subjects'] as $avg): ?>
                                <?php if ($avg['average'] === null) continue; ?>
                                <span class="badge" style="border-color:<?= htmlspecialchars($avg['color']) ?>"><?= htmlspecialchars($avg['name']) ?> : <?= number_format($avg['average'], 1) ?>/20</span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <hr style="margin:1rem 0;border-color:var(--border)">
                    <?php endif; ?>
                    <?php if (empty($grades)): ?>
                        <p style="color:var(--text-muted);font-size:.85rem">Aucune note enregistrée.</p>
                    <?php else: ?>
                    <?php foreach ($grades as $g): ?>
                    <div class="member-item" data-grade-id="<?= $g['id'] ?>">
                        <div class="member-info">
                            <strong style="color:<?= htmlspecialchars($g['subject_color']) ?>"><?= htmlspecialchars($g['subject_name']) ?></strong> —
                            <?= rtrim(rtrim(number_format((float)$g['grade_value'], 2), '0'), '.') ?>/<?= rtrim(rtrim(number_format((float)$g['grade_max'], 2), '0'), '.') ?>
                            <?php if ($g['title']): ?> · <?= htmlspecialchars($g['title']) ?><?php endif; ?>
                            <br><small><?= DateHelper::format($g['grade_date'], 'd/m/Y') ?> · <?= htmlspecialchars($g['author_name']) ?><?= $g['comment'] ? ' — ' . htmlspecialchars($g['comment']) : '' ?></small>
                        </div>
                        <button class="btn btn-danger btn-sm" onclick="deleteGrade(<?= $g['id'] ?>)">✕</button>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (!empty($subjects)): ?>
                    <hr style="margin:1rem 0;border-color:var(--border)">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Matière</label>
                            <select id="grade-subject">
                                <?php foreach ($subjects as $sub): ?><option value="<?= $sub['id'] ?>"><?= htmlspecialchars($sub['name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Note</label>
                            <input type="number" id="grade-value" step="0.5" placeholder="15">
                        </div>
                        <div class="form-group">
                            <label>Sur</label>
                            <input type="number" id="grade-max" step="0.5" value="20">
                        </div>
                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" id="grade-date" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group flex-2">
                            <label>Intitulé</label>
                            <input type="text" id="grade-title" placeholder="Contrôle chapitre 3…">
                        </div>
                        <div class="form-group flex-2">
                            <label>Commentaire</label>
                            <input type="text" id="grade-comment">
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="addGrade()">+ Ajouter la note</button>
                    <?php else: ?>
                        <p style="color:var(--text-muted);font-size:.85rem">Ajoutez d'abord une matière pour pouvoir saisir une note.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Absences -->
            <div class="settings-tab-panel" data-tab="absences">
                <div class="card settings-section">
                    <?php if (empty($absences)): ?>
                        <p style="color:var(--text-muted);font-size:.85rem">Aucune absence enregistrée.</p>
                    <?php else: ?>
                    <?php foreach ($absences as $a): ?>
                    <div class="member-item" data-absence-id="<?= $a['id'] ?>">
                        <div class="member-info">
                            <strong><?= DateHelper::format($a['absence_date'], 'd/m/Y') ?></strong>
                            <?= $a['subject_name'] ? ' · ' . htmlspecialchars($a['subject_name']) : '' ?>
                            <?= $a['duration'] ? ' · ' . htmlspecialchars($a['duration']) : '' ?>
                            <span class="badge <?= $a['justified'] ? '' : 'badge-shopping' ?>"><?= $a['justified'] ? '✅ Justifiée' : '⛔ Non justifiée' ?></span>
                            <br><small><?= htmlspecialchars($a['reason'] ?: 'Motif non renseigné') ?> · <?= htmlspecialchars($a['author_name']) ?></small>
                        </div>
                        <button class="btn btn-secondary btn-sm" onclick="toggleAbsenceJustified(<?= $a['id'] ?>, <?= $a['justified'] ? 'false' : 'true' ?>)"><?= $a['justified'] ? 'Marquer non justifiée' : 'Marquer justifiée' ?></button>
                        <button class="btn btn-danger btn-sm" onclick="deleteAbsence(<?= $a['id'] ?>)">✕</button>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    <hr style="margin:1rem 0;border-color:var(--border)">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" id="absence-date" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label>Matière (facultatif)</label>
                            <select id="absence-subject">
                                <option value="">— Journée entière / non précisé —</option>
                                <?php foreach ($subjects as $sub): ?><option value="<?= $sub['id'] ?>"><?= htmlspecialchars($sub['name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Durée</label>
                            <input type="text" id="absence-duration" placeholder="Journée, 2h…">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group flex-2">
                            <label>Motif</label>
                            <input type="text" id="absence-reason" placeholder="Rendez-vous médical…">
                        </div>
                        <div class="form-group">
                            <label><input type="checkbox" id="absence-justified"> Justifiée d'emblée</label>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="addAbsence()">+ Ajouter l'absence</button>
                </div>
            </div>

            <!-- Matières & profs -->
            <div class="settings-tab-panel" data-tab="subjects">
                <div class="card settings-section">
                    <?php if (empty($subjects)): ?>
                        <p style="color:var(--text-muted);font-size:.85rem">Aucune matière. Le sport/EPS est une matière comme les autres.</p>
                    <?php else: ?>
                    <?php foreach ($subjects as $sub): ?>
                    <div class="member-item" data-subject-id="<?= $sub['id'] ?>">
                        <div class="member-info">
                            <strong style="color:<?= htmlspecialchars($sub['color']) ?>"><?= htmlspecialchars($sub['name']) ?></strong>
                            <small><?= $sub['teacher_name'] ? htmlspecialchars($sub['teacher_name']) : 'Professeur non renseigné' ?></small>
                        </div>
                        <button class="btn btn-danger btn-sm" onclick="deleteSubject(<?= $sub['id'] ?>)">Supprimer</button>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    <hr style="margin:1rem 0;border-color:var(--border)">
                    <div class="form-row">
                        <div class="form-group flex-2">
                            <label>Matière</label>
                            <input type="text" id="subject-name" placeholder="Mathématiques, EPS…">
                        </div>
                        <div class="form-group flex-2">
                            <label>Professeur</label>
                            <input type="text" id="subject-teacher" placeholder="Mme Martin">
                        </div>
                        <div class="form-group">
                            <label>Couleur</label>
                            <input type="color" id="subject-color" value="#8E44AD">
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="addSubject()">+ Ajouter la matière</button>
                </div>
            </div>

            <!-- Activités -->
            <div class="settings-tab-panel" data-tab="activities">
                <div class="card settings-section">
                    <?php if (empty($activities)): ?>
                        <p style="color:var(--text-muted);font-size:.85rem">Aucune activité extra-scolaire.</p>
                    <?php else: ?>
                    <?php foreach ($activities as $act): ?>
                    <div class="member-item" data-activity-id="<?= $act['id'] ?>">
                        <div class="member-info">
                            <strong><?= htmlspecialchars($act['name']) ?></strong>
                            <small>
                                <?= $act['schedule_info'] ? htmlspecialchars($act['schedule_info']) : '' ?>
                                <?= $act['location'] ? ' · ' . htmlspecialchars($act['location']) : '' ?>
                                <?= $act['contact_info'] ? ' · ' . htmlspecialchars($act['contact_info']) : '' ?>
                                <?= $act['notes'] ? '<br>' . nl2br(htmlspecialchars($act['notes'])) : '' ?>
                            </small>
                        </div>
                        <button class="btn btn-danger btn-sm" onclick="deleteActivity(<?= $act['id'] ?>)">Supprimer</button>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    <hr style="margin:1rem 0;border-color:var(--border)">
                    <div class="form-row">
                        <div class="form-group flex-2">
                            <label>Nom de l'activité</label>
                            <input type="text" id="activity-name" placeholder="Football, Piano…">
                        </div>
                        <div class="form-group flex-2">
                            <label>Horaires</label>
                            <input type="text" id="activity-schedule" placeholder="Mercredi 17h–18h">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group flex-2">
                            <label>Lieu</label>
                            <input type="text" id="activity-location">
                        </div>
                        <div class="form-group flex-2">
                            <label>Contact</label>
                            <input type="text" id="activity-contact" placeholder="Nom, téléphone…">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea id="activity-notes" rows="2"></textarea>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="addActivity()">+ Ajouter l'activité</button>
                </div>
            </div>

            <!-- Documents -->
            <div class="settings-tab-panel" data-tab="documents">
                <div class="card settings-section">
                    <?php if (empty($documents)): ?>
                        <p style="color:var(--text-muted);font-size:.85rem">Aucun document.</p>
                    <?php else: ?>
                    <?php foreach ($documents as $doc): ?>
                    <div class="member-item" data-doc-id="<?= $doc['id'] ?>">
                        <div class="member-info">
                            <strong><?= $doc['doc_type'] === 'bulletin' ? '📘 ' : '' ?><?= htmlspecialchars($doc['title']) ?></strong>
                            <small><?= htmlspecialchars($doc['file_original']) ?> · ajouté par <?= htmlspecialchars($doc['uploader_name']) ?> le <?= DateHelper::fromUtc($doc['uploaded_at'], 'd/m/Y à H:i') ?></small>
                        </div>
                        <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>/school/students/<?= $selected['id'] ?>/documents/<?= $doc['id'] ?>" target="_blank">Télécharger</a>
                        <button class="btn btn-danger btn-sm" onclick="deleteSchoolDocument(<?= $doc['id'] ?>)">Supprimer</button>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    <hr style="margin:1rem 0;border-color:var(--border)">
                    <div class="form-row">
                        <div class="form-group flex-2">
                            <label>Titre</label>
                            <input type="text" id="doc-title" placeholder="Bulletin 1er trimestre…">
                        </div>
                        <div class="form-group">
                            <label>Type</label>
                            <select id="doc-type">
                                <option value="bulletin">📘 Bulletin</option>
                                <option value="other">📄 Autre document</option>
                            </select>
                        </div>
                    </div>
                    <input type="file" id="doc-file" accept=".pdf,.doc,.docx,.xls,.xlsx,.eml,image/*">
                    <button type="button" class="btn btn-primary btn-sm" onclick="uploadSchoolDocument()">+ Ajouter</button>
                    <small style="display:block;color:var(--text-muted);margin-top:.3rem">PDF, Word, Excel, images ou e-mail (.eml) — 20 Mo max.</small>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state-card">
                <p>Ajoutez un élève pour commencer.</p>
                <button class="btn btn-primary" onclick="openNewStudentModal()">+ Nouvel élève</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- New/Edit Student Modal -->
<div class="modal-overlay" id="student-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="student-modal-title">Nouvel élève</h3>
            <button onclick="closeModal('student-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="student-id">
            <div class="form-group">
                <label>Nom <span style="color:var(--danger)">*</span></label>
                <input type="text" id="student-name">
            </div>
            <div class="form-row">
                <div class="form-group flex-2">
                    <label>École / établissement</label>
                    <input type="text" id="student-school">
                </div>
                <div class="form-group">
                    <label>Classe</label>
                    <input type="text" id="student-class" placeholder="CM2, 6ème…">
                </div>
                <div class="form-group">
                    <label>Couleur</label>
                    <input type="color" id="student-color" value="#4A90D9">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('student-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveStudent()">Enregistrer</button>
        </div>
    </div>
</div>

<script>
const STUDENT_ID = <?= json_encode($selected['id'] ?? null) ?>;
const SELECTED_STUDENT = <?= json_encode($selected) ?>;
const BASE_URL = <?= json_encode(BASE_URL) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
