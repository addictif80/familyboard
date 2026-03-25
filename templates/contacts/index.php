<?php
$pageTitle = 'Répertoire';
$extraJs   = ['contacts.js'];
ob_start();
?>
<div class="contacts-page">
    <div class="contacts-toolbar">
        <div class="contacts-search-wrap">
            <input type="text" id="contact-search" class="form-control" placeholder="🔍 Rechercher un contact…" oninput="filterContacts()">
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <a href="<?= BASE_URL ?>/contacts/export.vcf" class="btn btn-secondary btn-sm" title="Exporter tous les contacts pour import sur smartphone">
                📲 Exporter .vcf
            </a>
            <button class="btn btn-primary" onclick="openContactModal()">+ Ajouter un contact</button>
        </div>
    </div>

    <?php if (empty($contacts)): ?>
        <div class="empty-state-full">
            <div style="font-size:3rem">📒</div>
            <p>Aucun contact pour le moment.</p>
            <button class="btn btn-primary" onclick="openContactModal()">Ajouter le premier contact</button>
        </div>
    <?php else: ?>
    <div class="contacts-grid" id="contacts-grid">
        <?php foreach ($contacts as $c): ?>
        <?php
            $fullName = trim($c['first_name'] . ' ' . $c['last_name']);
            $initial  = mb_strtoupper(mb_substr($c['first_name'], 0, 1));
        ?>
        <div class="contact-card" data-search="<?= htmlspecialchars(strtolower($fullName . ' ' . $c['email'] . ' ' . $c['phone'])) ?>"
             onclick="openViewModal(<?= htmlspecialchars(json_encode($c)) ?>)">
            <div class="contact-avatar" style="background:<?= htmlspecialchars($c['color']) ?>">
                <?php if ($c['avatar']): ?>
                    <img src="<?= BASE_URL . htmlspecialchars($c['avatar']) ?>" alt="">
                <?php else: ?>
                    <?= $initial ?>
                <?php endif; ?>
            </div>
            <div class="contact-info">
                <div class="contact-name"><?= htmlspecialchars($fullName) ?></div>
                <?php if ($c['phone']): ?>
                    <div class="contact-line">📞 <a href="tel:<?= htmlspecialchars($c['phone']) ?>" onclick="event.stopPropagation()"><?= htmlspecialchars($c['phone']) ?></a></div>
                <?php endif; ?>
                <?php if ($c['email']): ?>
                    <div class="contact-line">✉️ <a href="mailto:<?= htmlspecialchars($c['email']) ?>" onclick="event.stopPropagation()"><?= htmlspecialchars($c['email']) ?></a></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <p id="contacts-empty-search" style="display:none;text-align:center;color:var(--text-muted);margin-top:2rem">Aucun résultat.</p>
    <?php endif; ?>
</div>

<!-- View modal -->
<div class="modal-overlay" id="contact-view-modal" style="display:none">
    <div class="modal modal-contact-view">
        <div class="modal-header">
            <h3>Détails du contact</h3>
            <button onclick="closeModal('contact-view-modal')">✕</button>
        </div>
        <div class="modal-body" id="contact-view-body"></div>
        <div class="modal-footer" id="contact-view-footer"></div>
    </div>
</div>

<!-- Edit/Create modal -->
<div class="modal-overlay" id="contact-edit-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="contact-edit-title">Nouveau contact</h3>
            <button onclick="closeModal('contact-edit-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="ce-id">

            <!-- Avatar upload -->
            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem">
                <div class="contact-avatar" id="ce-avatar-preview" style="background:#4A90D9;cursor:pointer" onclick="document.getElementById('ce-avatar-file').click()" title="Cliquer pour choisir une photo">
                    <span id="ce-avatar-initial">?</span>
                </div>
                <div>
                    <input type="file" id="ce-avatar-file" accept="image/*" style="display:none" onchange="previewAvatar(this)">
                    <button class="btn btn-secondary btn-sm" onclick="document.getElementById('ce-avatar-file').click()">📷 Choisir une photo</button>
                    <div style="font-size:.78rem;color:var(--text-muted);margin-top:.2rem">Cliquer sur l'avatar ou le bouton</div>
                </div>
                <div class="form-group" style="margin:0;flex:none">
                    <label style="font-size:.8rem">Couleur</label>
                    <input type="color" id="ce-color" value="#4A90D9" onchange="document.getElementById('ce-avatar-preview').style.background=this.value">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Prénom *</label>
                    <input type="text" id="ce-firstname" placeholder="Jean" oninput="updateAvatarInitial()">
                </div>
                <div class="form-group">
                    <label>Nom</label>
                    <input type="text" id="ce-lastname" placeholder="Dupont">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="tel" id="ce-phone" placeholder="+33 6 12 34 56 78">
                </div>
                <div class="form-group">
                    <label>Téléphone 2</label>
                    <input type="tel" id="ce-phone2" placeholder="Fixe, travail…">
                </div>
            </div>
            <div class="form-group">
                <label>E-mail</label>
                <input type="email" id="ce-email" placeholder="jean@exemple.fr">
            </div>
            <div class="form-group">
                <label>Adresse postale</label>
                <textarea id="ce-address" rows="2" placeholder="123 rue de Paris, 75001 Paris"></textarea>
            </div>
            <div class="form-group">
                <label>Date de naissance</label>
                <input type="date" id="ce-birthday">
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea id="ce-notes" rows="2" placeholder="Informations complémentaires…"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('contact-edit-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveContact()">Enregistrer</button>
        </div>
    </div>
</div>

<script>
const BASE_URL = <?= json_encode(BASE_URL) ?>;
// Pass server-side contacts for initial JS state
let contactsData = <?= json_encode(array_values($contacts)) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
