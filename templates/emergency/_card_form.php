<?php
/** @var string $subjectType 'user'|'baby' */
/** @var int $subjectId */
/** @var array|null $card */
$formId = 'ec-' . $subjectType . '-' . $subjectId;
?>
<div class="form-row">
    <div class="form-group">
        <label>Groupe sanguin</label>
        <input type="text" id="<?= $formId ?>-blood" value="<?= htmlspecialchars($card['blood_type'] ?? '') ?>" placeholder="A+, O-…">
    </div>
    <div class="form-group flex-2">
        <label>Allergies</label>
        <input type="text" id="<?= $formId ?>-allergies" value="<?= htmlspecialchars($card['allergies'] ?? '') ?>" placeholder="Arachides, pénicilline…">
    </div>
</div>
<div class="form-row">
    <div class="form-group flex-2">
        <label>Traitements en cours</label>
        <input type="text" id="<?= $formId ?>-medications" value="<?= htmlspecialchars($card['medications'] ?? '') ?>">
    </div>
    <div class="form-group flex-2">
        <label>Conditions médicales</label>
        <input type="text" id="<?= $formId ?>-conditions" value="<?= htmlspecialchars($card['conditions'] ?? '') ?>" placeholder="Asthme, diabète…">
    </div>
</div>
<div class="form-row">
    <div class="form-group">
        <label>Médecin traitant</label>
        <input type="text" id="<?= $formId ?>-doctor-name" value="<?= htmlspecialchars($card['doctor_name'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label>Téléphone médecin</label>
        <input type="tel" id="<?= $formId ?>-doctor-phone" value="<?= htmlspecialchars($card['doctor_phone'] ?? '') ?>">
    </div>
</div>
<div class="form-row">
    <div class="form-group">
        <label>Contact d'urgence</label>
        <input type="text" id="<?= $formId ?>-contact-name" value="<?= htmlspecialchars($card['emergency_contact_name'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label>Téléphone contact</label>
        <input type="tel" id="<?= $formId ?>-contact-phone" value="<?= htmlspecialchars($card['emergency_contact_phone'] ?? '') ?>">
    </div>
</div>
<div class="form-group">
    <label>Notes</label>
    <textarea id="<?= $formId ?>-notes" rows="2"><?= htmlspecialchars($card['notes'] ?? '') ?></textarea>
</div>
<div style="display:flex;gap:.75rem;flex-wrap:wrap">
    <button type="button" class="btn btn-primary" onclick="saveEmergencyCard('<?= $subjectType ?>', <?= $subjectId ?>, '<?= $formId ?>')">Enregistrer</button>
    <?php if ($card): ?>
    <button type="button" class="btn btn-secondary" onclick="showEmergencyQr('<?= htmlspecialchars($card['public_token']) ?>')">📱 Voir le QR code</button>
    <button type="button" class="btn btn-secondary" onclick="viewEmergencyAccessLog(<?= (int)$card['id'] ?>)">🕓 Journal d'accès</button>
    <button type="button" class="btn btn-secondary" onclick="regenerateEmergencyLink(<?= (int)$card['id'] ?>)">🔄 Régénérer le lien</button>
    <?php endif; ?>
</div>
