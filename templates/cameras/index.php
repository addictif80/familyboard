<?php
$pageTitle = 'Caméras';
$extraJs   = ['cameras.js'];
ob_start();
?>
<div class="content-header">
    <h2>🎥 Caméras IP</h2>
    <button class="btn btn-primary" onclick="openCameraModal()">+ Ajouter une caméra</button>
</div>

<?php if (empty($cameras)): ?>
<div class="empty-page">
    <div class="empty-icon">🎥</div>
    <h3>Aucune caméra configurée</h3>
    <p>Ajoutez vos caméras IP pour afficher leurs flux en direct.<br>
    Renseignez l'URL du flux (MJPEG, snapshot, HLS) directement dans le formulaire.</p>
    <button class="btn btn-primary" onclick="openCameraModal()">+ Ajouter une caméra</button>
</div>
<?php else: ?>
<div class="cameras-grid">
    <?php foreach ($cameras as $cam): ?>
    <div class="cam-card card" data-id="<?= $cam['id'] ?>">
        <!-- Preview -->
        <div class="cam-preview">
            <?php if ($cam['stream_url'] && in_array($cam['stream_type'], ['mjpeg', 'snapshot', 'hls'])): ?>
                <?php if ($cam['stream_type'] === 'mjpeg'): ?>
                    <img src="<?= htmlspecialchars($cam['stream_url']) ?>"
                         class="cam-img"
                         onerror="camImgError(this)"
                         alt="Flux <?= htmlspecialchars($cam['name']) ?>">
                <?php elseif ($cam['stream_type'] === 'snapshot'): ?>
                    <img src="<?= htmlspecialchars($cam['stream_url']) ?>"
                         class="cam-snapshot"
                         data-src="<?= htmlspecialchars($cam['stream_url']) ?>"
                         onerror="camImgError(this)"
                         alt="Snapshot <?= htmlspecialchars($cam['name']) ?>">
                <?php elseif ($cam['stream_type'] === 'hls'): ?>
                    <video class="cam-hls" data-src="<?= htmlspecialchars($cam['stream_url']) ?>"
                           muted autoplay playsinline></video>
                <?php endif; ?>
            <?php elseif ($cam['stream_url'] && $cam['stream_type'] === 'rtsp'): ?>
                <div class="cam-placeholder cam-rtsp-trigger"
                     id="cam-rtsp-<?= $cam['id'] ?>"
                     onclick="startRtspStream(this, <?= $cam['id'] ?>)">
                    <span class="cam-play-icon">▶</span>
                    <small>Voir en direct</small>
                </div>
            <?php else: ?>
                <div class="cam-placeholder">
                    <span>📷</span>
                    <?php if ($cam['stream_url']): ?>
                        <small>Flux configuré</small>
                    <?php else: ?>
                        <small>Flux non configuré</small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Type badge overlaid -->
            <span class="cam-type-pill cam-type-<?= $cam['stream_type'] ?>">
                <?= strtoupper($cam['stream_type']) ?>
            </span>
        </div>

        <!-- Info -->
        <div class="cam-body">
            <div class="cam-name"><?= htmlspecialchars($cam['name']) ?></div>
            <div class="cam-host">📡 <?= htmlspecialchars($cam['host']) ?></div>
            <?php if ($cam['model']): ?>
                <div class="cam-model">🔧 <?= htmlspecialchars($cam['model']) ?></div>
            <?php endif; ?>
            <?php if ($cam['stream_url']): ?>
                <div class="cam-stream-url" title="<?= htmlspecialchars($cam['stream_url']) ?>">
                    🔗 <span><?= htmlspecialchars($cam['stream_url']) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <div class="cam-footer">
            <button class="btn btn-secondary btn-sm" onclick="openEditCameraModal(<?= htmlspecialchars(json_encode($cam)) ?>)">✏️ Modifier</button>
            <button class="btn btn-danger btn-sm" onclick="deleteCamera(<?= $cam['id'] ?>)">🗑</button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ═══ CAMERA MODAL (add / edit) ═══════════════════════════════ -->
<div class="modal-overlay" id="camera-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="cam-modal-title">Nouvelle caméra</h3>
            <button onclick="closeModal('camera-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="cam-id">

            <div class="form-row">
                <div class="form-group flex-2">
                    <label>Nom *</label>
                    <input type="text" id="cam-name" placeholder="Entrée, Garage, Jardin…">
                </div>
                <div class="form-group flex-1">
                    <label>Ordre d'affichage</label>
                    <input type="number" id="cam-order" value="0" min="0">
                </div>
            </div>

            <div class="form-group">
                <label>Adresse IP / hôte *</label>
                <input type="text" id="cam-host" placeholder="192.168.1.100 ou camera.local">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Identifiant</label>
                    <input type="text" id="cam-user" placeholder="admin" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Mot de passe</label>
                    <input type="password" id="cam-pass" placeholder="••••••••" autocomplete="new-password">
                </div>
            </div>

            <div class="form-group">
                <label>Modèle (optionnel)</label>
                <input type="text" id="cam-model" placeholder="Hikvision DS-2CD…, Reolink…">
            </div>

            <div class="form-group">
                <label>URL du flux</label>
                <input type="url" id="cam-stream-url" placeholder="http://192.168.1.100/video.cgi ou rtsp://…">
            </div>

            <div class="form-group">
                <label>Type de flux</label>
                <select id="cam-stream-type">
                    <option value="other">— Inconnu / autre —</option>
                    <option value="mjpeg">MJPEG (lecture directe dans le navigateur)</option>
                    <option value="snapshot">Snapshot JPEG (rafraîchi toutes les 5 s)</option>
                    <option value="hls">HLS (lecture via player intégré)</option>
                    <option value="rtsp">RTSP (URL uniquement, lecture externe)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Notes</label>
                <textarea id="cam-notes" rows="2" placeholder="Position, remarques…"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('camera-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveCamera()">Enregistrer</button>
        </div>
    </div>
</div>

<style>
.cam-rtsp-trigger {
    cursor: pointer;
    transition: background .15s;
}
.cam-rtsp-trigger:hover {
    background: var(--bg-hover, rgba(0,0,0,.05));
}
.cam-rtsp-trigger .cam-play-icon {
    font-size: 2rem;
    display: block;
    margin-bottom: .25rem;
    color: var(--primary);
}
.cam-go2rtc {
    width: 100%;
    height: 100%;
    border: none;
    display: block;
    min-height: 200px;
}
.cam-rtsp-stop {
    position: absolute;
    top: .4rem;
    right: .4rem;
    background: rgba(0,0,0,.55);
    color: #fff;
    border: none;
    border-radius: 4px;
    padding: .15rem .45rem;
    cursor: pointer;
    font-size: .75rem;
    line-height: 1.4;
    z-index: 5;
}
.cam-rtsp-stop:hover {
    background: rgba(0,0,0,.8);
}
</style>

<script>
const BASE_URL = <?= json_encode(BASE_URL) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
