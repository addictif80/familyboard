<?php
$pageTitle = 'Bébé 👶';
ob_start();
?>
<div class="baby-module">

    <!-- Header: baby selector + add -->
    <div class="baby-header">
        <div class="baby-selector-wrap">
            <select id="baby-select" class="baby-select" onchange="BabyApp.selectBaby(this.value)">
                <option value="">— Choisir un bébé —</option>
                <?php foreach ($babies as $b): ?>
                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary btn-sm" onclick="BabyApp.openAddBaby()">+ Nouveau bébé</button>
        </div>
    </div>

    <!-- No baby selected placeholder -->
    <div id="baby-empty" class="baby-empty" <?= !empty($babies) ? 'style="display:none"' : '' ?>>
        <div class="empty-icon">👶</div>
        <p>Ajoutez votre premier bébé pour commencer le suivi.</p>
        <button class="btn btn-primary" onclick="BabyApp.openAddBaby()">Ajouter un bébé</button>
    </div>
    <div id="baby-select-hint" class="baby-empty" style="display:none">
        <p>Sélectionnez un bébé dans la liste ci-dessus.</p>
    </div>

    <!-- Baby dashboard (shown when a baby is selected) -->
    <div id="baby-dashboard" style="display:none">

        <!-- Baby info bar -->
        <div class="baby-info-bar">
            <div class="baby-avatar-wrap" onclick="BabyApp.uploadAvatar()">
                <img id="baby-avatar-img" src="" alt="" class="baby-avatar" style="display:none">
                <span id="baby-avatar-placeholder" class="baby-avatar-placeholder">👶</span>
                <span class="baby-avatar-edit">✏️</span>
                <input type="file" id="avatar-file-input" accept="image/*" style="display:none" onchange="BabyApp.doUploadAvatar(this)">
            </div>
            <div class="baby-info-details">
                <h2 id="baby-name-display" class="baby-name"></h2>
                <p id="baby-birth-summary" class="baby-birth-summary text-muted"></p>
            </div>
            <div class="baby-info-actions">
                <button class="btn btn-secondary btn-sm" onclick="BabyApp.openEditBaby()">Modifier</button>
                <button class="btn btn-danger btn-sm" onclick="BabyApp.deleteBaby()">Supprimer</button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="baby-tabs">
            <button class="baby-tab active" data-tab="tracking" onclick="BabyApp.switchTab('tracking', this)">📊 Suivi quotidien</button>
            <button class="baby-tab" data-tab="pregnancy" onclick="BabyApp.switchTab('pregnancy', this)">🤰 Grossesse</button>
            <button class="baby-tab" data-tab="birth" onclick="BabyApp.switchTab('birth', this)">🎉 Naissance</button>
            <button class="baby-tab" data-tab="consultations" onclick="BabyApp.switchTab('consultations', this)">🩺 Consultations</button>
        </div>

        <!-- TAB: Tracking -->
        <div id="tab-tracking" class="baby-tab-content">
            <div class="tracking-controls">
                <button class="btn btn-secondary btn-sm" onclick="BabyApp.prevDay()">‹ Jour préc.</button>
                <input type="date" id="tracking-date" class="date-picker" onchange="BabyApp.loadTracking()">
                <button class="btn btn-secondary btn-sm" onclick="BabyApp.nextDay()">Jour suiv. ›</button>
            </div>

            <!-- Daily stats -->
            <div class="daily-stats" id="daily-stats"></div>

            <!-- Quick add buttons -->
            <div class="quick-add-bar">
                <button class="quick-btn feeding" onclick="BabyApp.quickAdd('feeding')">🍼 Repas</button>
                <button class="quick-btn stool"   onclick="BabyApp.quickAdd('stool')">💩 Selles</button>
                <button class="quick-btn urine"   onclick="BabyApp.quickAdd('urine')">💧 Pipi</button>
                <button class="quick-btn diaper"  onclick="BabyApp.quickAdd('diaper')">🧷 Couche</button>
                <button class="quick-btn bath"    onclick="BabyApp.quickAdd('bath')">🛁 Lavage</button>
                <button class="quick-btn sleep"   onclick="BabyApp.quickAddSleep()" id="sleep-btn">😴 Sommeil</button>
            </div>

            <!-- Timeline -->
            <div id="tracking-timeline" class="tracking-timeline"></div>
        </div>

        <!-- TAB: Pregnancy -->
        <div id="tab-pregnancy" class="baby-tab-content" style="display:none">
            <div class="section-header">
                <h3>Grossesse</h3>
                <button class="btn btn-primary btn-sm" onclick="BabyApp.openPregnancyForm()">✏️ Modifier</button>
            </div>

            <div id="pregnancy-info" class="pregnancy-info-card">
                <p class="text-muted">Aucune information de grossesse enregistrée.</p>
            </div>

            <div class="section-header" style="margin-top:1.5rem">
                <h3>Consultations prénatales</h3>
                <button class="btn btn-primary btn-sm" onclick="BabyApp.openPregnancyConsultation()">+ Ajouter</button>
            </div>
            <div id="pregnancy-consultations-list" class="consultations-list"></div>
        </div>

        <!-- TAB: Birth -->
        <div id="tab-birth" class="baby-tab-content" style="display:none">
            <div class="section-header">
                <h3>Informations de naissance</h3>
                <button class="btn btn-primary btn-sm" onclick="BabyApp.openBirthForm()">✏️ Modifier</button>
            </div>
            <div id="birth-info" class="birth-info-card">
                <p class="text-muted">Aucune information de naissance enregistrée.</p>
            </div>
        </div>

        <!-- TAB: Consultations -->
        <div id="tab-consultations" class="baby-tab-content" style="display:none">
            <div class="section-header">
                <h3>Consultations médicales</h3>
                <button class="btn btn-primary btn-sm" onclick="BabyApp.openConsultationForm()">+ Ajouter</button>
            </div>
            <div id="consultations-list" class="consultations-list"></div>
        </div>

    </div><!-- #baby-dashboard -->
</div><!-- .baby-module -->

<!-- ── Modals ────────────────────────────────────────────────── -->

<!-- Add / Edit Baby -->
<div id="modal-baby" class="modal-overlay" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modal-baby-title">Nouveau bébé</h3>
            <button class="modal-close" onclick="BabyApp.closeModal('modal-baby')">✕</button>
        </div>
        <div class="modal-body">
            <form id="form-baby" onsubmit="BabyApp.saveBaby(event)">
                <div class="form-group">
                    <label>Prénom *</label>
                    <input type="text" id="baby-form-name" class="form-control" required>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="BabyApp.closeModal('modal-baby')">Annuler</button>
            <button class="btn btn-primary" onclick="document.getElementById('form-baby').requestSubmit()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Birth form -->
<div id="modal-birth" class="modal-overlay" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Informations de naissance</h3>
            <button class="modal-close" onclick="BabyApp.closeModal('modal-birth')">✕</button>
        </div>
        <div class="modal-body">
            <form id="form-birth" onsubmit="BabyApp.saveBirth(event)">
                <div class="form-row">
                    <div class="form-group">
                        <label>Date de naissance</label>
                        <input type="date" id="birth-date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Heure</label>
                        <input type="time" id="birth-time" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Poids (kg)</label>
                        <input type="number" id="birth-weight" class="form-control" step="0.01" min="0" max="10" placeholder="3.50">
                    </div>
                    <div class="form-group">
                        <label>Taille (cm)</label>
                        <input type="number" id="birth-height" class="form-control" step="0.1" min="0" max="70" placeholder="50.0">
                    </div>
                </div>
                <div class="form-group">
                    <label>Détails</label>
                    <textarea id="birth-details" class="form-control" rows="3"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="BabyApp.closeModal('modal-birth')">Annuler</button>
            <button class="btn btn-primary" onclick="document.getElementById('form-birth').requestSubmit()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Pregnancy form -->
<div id="modal-pregnancy" class="modal-overlay" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Informations de grossesse</h3>
            <button class="modal-close" onclick="BabyApp.closeModal('modal-pregnancy')">✕</button>
        </div>
        <div class="modal-body">
            <form id="form-pregnancy" onsubmit="BabyApp.savePregnancy(event)">
                <div class="form-group">
                    <label>Date des dernières règles (DDR)</label>
                    <input type="date" id="preg-lmp" class="form-control" onchange="BabyApp.calcDueDate()">
                </div>
                <div class="form-group">
                    <label>Date prévisionnelle d'accouchement (DPA)</label>
                    <input type="date" id="preg-due" class="form-control">
                    <small class="text-muted">Calculée automatiquement (DDR + 280 jours) si laissée vide.</small>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea id="preg-notes" class="form-control" rows="3"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="BabyApp.closeModal('modal-pregnancy')">Annuler</button>
            <button class="btn btn-primary" onclick="document.getElementById('form-pregnancy').requestSubmit()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Pregnancy consultation form -->
<div id="modal-preg-consult" class="modal-overlay" style="display:none">
    <div class="modal modal-wide">
        <div class="modal-header">
            <h3>Consultation prénatale</h3>
            <button class="modal-close" onclick="BabyApp.closeModal('modal-preg-consult')">✕</button>
        </div>
        <div class="modal-body">
            <form id="form-preg-consult" onsubmit="BabyApp.savePregnancyConsultation(event)" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label>Date et heure *</label>
                        <input type="datetime-local" id="pc-date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Praticien</label>
                        <input type="text" id="pc-practitioner" class="form-control" placeholder="Dr. Martin">
                    </div>
                </div>
                <div class="form-group">
                    <label>Détails</label>
                    <textarea id="pc-details" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Images (échographies)</label>
                    <div id="pc-images-preview" class="images-preview"></div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="BabyApp.addImageField()">+ Ajouter une image</button>
                    <div id="pc-image-inputs"></div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="BabyApp.closeModal('modal-preg-consult')">Annuler</button>
            <button class="btn btn-primary" id="btn-save-pc" onclick="BabyApp.savePregnancyConsultation()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Baby event form -->
<div id="modal-event" class="modal-overlay" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modal-event-title">Ajouter un événement</h3>
            <button class="modal-close" onclick="BabyApp.closeModal('modal-event')">✕</button>
        </div>
        <div class="modal-body">
            <form id="form-event" onsubmit="BabyApp.saveEvent(event)">
                <div class="form-group">
                    <label>Type *</label>
                    <select id="event-type" class="form-control" onchange="BabyApp.toggleQuantity()">
                        <option value="feeding">🍼 Repas</option>
                        <option value="stool">💩 Selles</option>
                        <option value="urine">💧 Pipi</option>
                        <option value="diaper">🧷 Couche</option>
                        <option value="bath">🛁 Lavage</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date et heure</label>
                    <input type="datetime-local" id="event-at" class="form-control">
                </div>
                <div class="form-group" id="event-quantity-group">
                    <label>Quantité (ml)</label>
                    <input type="number" id="event-quantity" class="form-control" step="1" min="0" max="500" placeholder="120">
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea id="event-notes" class="form-control" rows="2"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="BabyApp.closeModal('modal-event')">Annuler</button>
            <button class="btn btn-primary" onclick="document.getElementById('form-event').requestSubmit()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Sleep form -->
<div id="modal-sleep" class="modal-overlay" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modal-sleep-title">Sommeil</h3>
            <button class="modal-close" onclick="BabyApp.closeModal('modal-sleep')">✕</button>
        </div>
        <div class="modal-body">
            <form id="form-sleep" onsubmit="BabyApp.saveSleep(event)">
                <div class="form-row">
                    <div class="form-group">
                        <label>Début *</label>
                        <input type="datetime-local" id="sleep-start" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Fin</label>
                        <input type="datetime-local" id="sleep-end" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea id="sleep-notes" class="form-control" rows="2"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="BabyApp.closeModal('modal-sleep')">Annuler</button>
            <button class="btn btn-primary" onclick="document.getElementById('form-sleep').requestSubmit()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Post-birth consultation form -->
<div id="modal-consult" class="modal-overlay" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modal-consult-title">Consultation médicale</h3>
            <button class="modal-close" onclick="BabyApp.closeModal('modal-consult')">✕</button>
        </div>
        <div class="modal-body">
            <form id="form-consult" onsubmit="BabyApp.saveConsultation(event)">
                <div class="form-row">
                    <div class="form-group">
                        <label>Date et heure *</label>
                        <input type="datetime-local" id="consult-at" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Praticien</label>
                        <input type="text" id="consult-practitioner" class="form-control" placeholder="Dr. Martin">
                    </div>
                </div>
                <div class="form-group">
                    <label>Détails</label>
                    <textarea id="consult-details" class="form-control" rows="4"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="BabyApp.closeModal('modal-consult')">Annuler</button>
            <button class="btn btn-primary" onclick="document.getElementById('form-consult').requestSubmit()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Image lightbox -->
<div id="modal-lightbox" class="modal-overlay" style="display:none" onclick="BabyApp.closeModal('modal-lightbox')">
    <div class="lightbox-wrap">
        <img id="lightbox-img" src="" alt="" class="lightbox-img">
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/templates/layout.php';
