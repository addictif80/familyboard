<?php
$pageTitle = 'Calendrier';
$extraJs = ['calendar.js'];
ob_start();
?>
<div class="calendar-container">
    <div id="share-invitations-panel"></div>
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
            <div id="event-share-status" style="display:none;margin-bottom:.75rem;font-size:.85rem"></div>
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
            <?php if (!empty($custodySchedules)): ?>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="event-custody-toggle" onchange="document.getElementById('event-custody-select-wrap').style.display = this.checked ? '' : 'none'">
                    Garde alternée
                </label>
                <div id="event-custody-select-wrap" style="display:none;margin-top:.4rem">
                    <?php foreach ($custodySchedules as $cs): ?>
                        <label class="radio-option">
                            <input type="checkbox" class="event-custody-child-cb" value="<?= (int)$cs['id'] ?>">
                            <span><?= htmlspecialchars($cs['child_name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                    <small class="form-hint">Cet évènement sera visible par le(s) co-parent(s) à accès restreint des enfants sélectionnés.</small>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($employmentProfiles)): ?>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="event-employment-toggle" onchange="document.getElementById('event-employment-select-wrap').style.display = this.checked ? '' : 'none'">
                    Congé / absence salarié
                </label>
                <div id="event-employment-select-wrap" style="display:none;margin-top:.4rem">
                    <select id="event-employment-profile">
                        <?php foreach ($employmentProfiles as $ep): ?>
                            <option value="<?= (int)$ep['id'] ?>"><?= htmlspecialchars($ep['user_name']) ?><?= $ep['employer_name'] ? ' — ' . htmlspecialchars($ep['employer_name']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="event-employment-leave-type" style="margin-top:.4rem">
                        <option value="paid_leave">Congé payé</option>
                        <option value="rtt">RTT</option>
                        <option value="unpaid">Absence non payée</option>
                    </select>
                    <small class="form-hint">Cet événement sera compté dans le suivi salarié du profil choisi (module « Suivi salarié »).</small>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($friendFamilies)): ?>
            <div class="form-group" id="event-share-wrap">
                <label>
                    <input type="checkbox" id="event-share-toggle" onchange="document.getElementById('event-share-select-wrap').style.display = this.checked ? '' : 'none'">
                    Inviter une famille amie
                </label>
                <div id="event-share-select-wrap" style="display:none;margin-top:.4rem">
                    <?php foreach ($friendFamilies as $ff): ?>
                        <label class="radio-option">
                            <input type="checkbox" class="event-share-family-cb" value="<?= (int)$ff['family_id'] ?>">
                            <span><?= htmlspecialchars($ff['family_name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                    <small class="form-hint">La famille invitée devra accepter avant de voir cet événement. Uniquement à la création — pour inviter une famille amie à un événement déjà créé, recréez-le.</small>
                </div>
            </div>
            <?php endif; ?>
            <details class="form-group" id="event-more-info">
                <summary>Ajouter plus d'infos</summary>
                <div class="form-group">
                    <label>Nom du professionnel</label>
                    <input type="text" id="event-professional" placeholder="Dr Martin, Garage Dupont…">
                </div>
                <div class="form-group">
                    <label>Adresse</label>
                    <div class="city-autocomplete" id="event-location-wrap">
                        <input type="text" id="event-location-input" autocomplete="off" placeholder="Tapez une adresse…">
                        <ul class="city-ac-dropdown" id="event-location-list" style="display:none"></ul>
                        <input type="hidden" id="event-location-lat">
                        <input type="hidden" id="event-location-lng">
                    </div>
                    <div id="event-location-preview" style="display:none">
                        <iframe id="event-location-map" class="event-location-map" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                        <div class="event-location-links">
                            <a id="event-location-gmaps" href="#" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">📍 Google Maps</a>
                            <a id="event-location-waze" href="#" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">🚗 Waze</a>
                        </div>
                    </div>
                </div>
            </details>
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
