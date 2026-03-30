<?php
$pageTitle = 'Calendrier';
$extraJs = ['calendar.js'];
ob_start();
?>
<div class="calendar-container">
    <div class="calendar-toolbar">
        <div class="calendar-filters">
            <?php foreach ($members as $member): ?>
                <label class="member-filter" style="--color:<?= htmlspecialchars($member['color']) ?>">
                    <input type="checkbox" checked data-member="<?= $member['id'] ?>" onchange="filterCalendar()">
                    <?= htmlspecialchars($member['name']) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="calendar-actions">
            <?php if (!empty($custodySchedules)): ?>
            <label class="member-filter" style="--color:#E67E22">
                <input type="checkbox" id="custody-toggle" onchange="loadEvents()">
                👶 Garde alternée
            </label>
            <?php endif; ?>
            <?php if (!empty($hasProjects)): ?>
            <label class="member-filter" style="--color:#27AE60">
                <input type="checkbox" id="projects-toggle" onchange="loadEvents()">
                📋 Projets
            </label>
            <?php endif; ?>
            <label class="member-filter" style="--color:#0EA5E9">
                <input type="checkbox" id="vacations-toggle" checked onchange="loadEvents()">
                🏖 Congés
            </label>
            <?php if (!empty($schoolZone)): ?>
            <label class="member-filter" style="--color:#7C3AED">
                <input type="checkbox" id="school-toggle" checked onchange="loadEvents()">
                🎓 Vacances scolaires
            </label>
            <?php endif; ?>
            <button class="btn btn-secondary btn-sm" onclick="openVacationModal()">
                + Congé
            </button>
            <button class="btn btn-secondary btn-sm" onclick="openCalDAVModal()">
                🔗 CalDAV
            </button>
            <button class="btn btn-primary btn-sm" onclick="openEventModal()">
                + Événement
            </button>
        </div>
    </div>

    <div id="calendar"></div>
    <div id="cal-mobile-agenda" class="cal-agenda" style="display:none"></div>

    <?php if (!empty($caldavSources)): ?>
    <div class="caldav-list card" style="margin-top:1rem">
        <h4>Calendriers CalDAV</h4>
        <?php foreach ($caldavSources as $source): ?>
            <div class="caldav-item">
                <span class="caldav-dot" style="background:<?= htmlspecialchars($source['color']) ?>"></span>
                <span><?= htmlspecialchars($source['name']) ?></span>
                <small><?= htmlspecialchars($source['user_name']) ?></small>
                <?php if ($source['last_sync']): ?>
                    <small>Sync: <?= \App\Core\DateHelper::fromUtc($source['last_sync'], 'd/m H:i') ?></small>
                <?php endif; ?>
                <button class="btn-icon-sm" onclick="syncCalDAV(<?= $source['id'] ?>)">🔄</button>
                <button class="btn-icon-sm" onclick="deleteCalDAV(<?= $source['id'] ?>)">🗑</button>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Event Modal -->
<div class="modal-overlay" id="event-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="event-modal-title">Nouvel événement</h3>
            <button onclick="closeModal('event-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="event-id">
            <div class="form-group">
                <label>Titre *</label>
                <input type="text" id="event-title" required placeholder="Anniversaire, réunion…">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea id="event-desc" rows="2"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Début *</label>
                    <input type="datetime-local" id="event-start">
                </div>
                <div class="form-group">
                    <label>Fin *</label>
                    <input type="datetime-local" id="event-end">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="event-allday" onchange="toggleAllDay(this)"> Toute la journée
                    </label>
                </div>
                <div class="form-group">
                    <label>Couleur</label>
                    <input type="color" id="event-color" value="#4A90D9">
                </div>
            </div>
            <div class="form-group">
                <label>Récurrence</label>
                <select id="event-recurrence">
                    <option value="">Aucune</option>
                    <option value="daily">Quotidienne</option>
                    <option value="weekly">Hebdomadaire</option>
                    <option value="monthly">Mensuelle</option>
                    <option value="yearly">Annuelle</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('event-modal')">Annuler</button>
            <button class="btn btn-danger" id="event-delete-btn" style="display:none" onclick="deleteEvent()">Supprimer</button>
            <button class="btn btn-primary" onclick="saveEvent()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Vacation Modal -->
<div class="modal-overlay" id="vacation-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>🏖 Ajouter un congé</h3>
            <button onclick="closeModal('vacation-modal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Intitulé</label>
                <input type="text" id="vac-title" placeholder="Congé, vacances, RTT…" value="Congé">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Début *</label>
                    <input type="date" id="vac-start">
                </div>
                <div class="form-group">
                    <label>Fin *</label>
                    <input type="date" id="vac-end">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('vacation-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveVacation()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- CalDAV Modal -->
<div class="modal-overlay" id="caldav-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Ajouter un calendrier CalDAV</h3>
            <button onclick="closeModal('caldav-modal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Nom</label>
                <input type="text" id="caldav-name" placeholder="Mon calendrier Google">
            </div>
            <div class="form-group">
                <label>URL CalDAV / iCal</label>
                <input type="url" id="caldav-url" placeholder="https://calendar.google.com/calendar/ical/...">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Identifiant (optionnel)</label>
                    <input type="text" id="caldav-user">
                </div>
                <div class="form-group">
                    <label>Mot de passe (optionnel)</label>
                    <input type="password" id="caldav-pass">
                </div>
            </div>
            <div class="form-group">
                <label>Couleur</label>
                <input type="color" id="caldav-color" value="#4A90D9">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('caldav-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="addCalDAV()">Ajouter et synchroniser</button>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
