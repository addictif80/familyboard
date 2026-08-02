<?php
$pageTitle = 'Repas';
$extraJs   = ['meals.js'];
ob_start();
?>
<div class="content-header">
    <h2>🍽️ Planification des repas</h2>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <button class="btn btn-secondary btn-sm" onclick="addWeekIngredientsToShoppingList('<?= $start ?>')">🛒 Ajouter les ingrédients à la liste de courses</button>
        <a href="<?= BASE_URL ?>/meals?start=<?= $prevWeek ?>" class="btn btn-secondary btn-sm">← Semaine préc.</a>
        <a href="<?= BASE_URL ?>/meals?start=<?= $nextWeek ?>" class="btn btn-secondary btn-sm">Semaine suiv. →</a>
    </div>
</div>

<div class="table-scroll">
<table class="admin-table" style="min-width:900px">
    <thead>
        <tr>
            <th>Repas</th>
            <?php foreach ($days as $d): ?>
                <th><?= ucfirst(\App\Core\DateHelper::format($d, 'l d/m')) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach (\App\Models\MealPlan::TYPES as $type => $label): ?>
        <tr>
            <td><strong><?= $label ?></strong></td>
            <?php foreach ($days as $d): ?>
                <?php $meal = $week[$d . '_' . $type] ?? null; ?>
                <td>
                    <div class="meal-cell" onclick='openMealModal(<?= json_encode($d) ?>, <?= json_encode($type) ?>, <?= json_encode($label) ?>, <?= json_encode($meal) ?>)'>
                        <?php if ($meal): ?>
                            <?= htmlspecialchars($meal['recipe_title'] ?? $meal['custom_title'] ?? '') ?>
                        <?php else: ?>
                            <span style="color:var(--text-muted)">+</span>
                        <?php endif; ?>
                    </div>
                </td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<div class="content-header" style="margin-top:2rem">
    <h2>📖 Recettes</h2>
    <button class="btn btn-primary" onclick="openRecipeModal()">+ Nouvelle recette</button>
</div>

<?php if (empty($recipes)): ?>
<div class="empty-page">
    <div class="empty-icon">📖</div>
    <h3>Aucune recette enregistrée</h3>
    <p>Ajoutez vos recettes pour les planifier facilement dans la semaine.</p>
</div>
<?php else: ?>
<div class="settings-container">
    <?php foreach ($recipes as $r): ?>
    <div class="card settings-section" data-recipe-id="<?= $r['id'] ?>">
        <h3><?= htmlspecialchars($r['title']) ?> <small style="color:var(--text-muted);font-weight:400">(<?= (int)$r['servings'] ?> portions)</small></h3>
        <pre style="white-space:pre-wrap;font-family:inherit;font-size:.85rem;color:var(--text-muted)"><?= htmlspecialchars($r['ingredients']) ?></pre>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <button class="btn btn-secondary btn-sm" onclick='openRecipeModal(<?= json_encode($r) ?>)'>✏️ Modifier</button>
            <button class="btn btn-secondary btn-sm" onclick="addIngredientsToShoppingList(<?= $r['id'] ?>)">🛒 Ajouter à la liste de courses</button>
            <button class="btn btn-danger btn-sm" onclick="deleteRecipe(<?= $r['id'] ?>)">Supprimer</button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Recipe modal -->
<div class="modal-overlay" id="recipe-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="recipe-modal-title">Nouvelle recette</h3>
            <button onclick="closeModal('recipe-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="recipe-id">
            <div class="form-row">
                <div class="form-group flex-2">
                    <label>Titre *</label>
                    <input type="text" id="recipe-title">
                </div>
                <div class="form-group">
                    <label>Portions</label>
                    <input type="number" id="recipe-servings" value="4" min="1">
                </div>
            </div>
            <div class="form-group">
                <label>Ingrédients * (un par ligne)</label>
                <textarea id="recipe-ingredients" rows="6" placeholder="200g de pâtes&#10;2 œufs&#10;100g de lardons"></textarea>
            </div>
            <div class="form-group">
                <label>Préparation (optionnel)</label>
                <textarea id="recipe-instructions" rows="4"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('recipe-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveRecipe()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Meal assignment modal -->
<div class="modal-overlay" id="meal-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="meal-modal-title">Repas</h3>
            <button onclick="closeModal('meal-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="meal-date">
            <input type="hidden" id="meal-type">
            <div class="form-group">
                <label>Recette</label>
                <select id="meal-recipe-select" onchange="document.getElementById('meal-custom-title').disabled = !!this.value">
                    <option value="">— Aucune (texte libre) —</option>
                    <?php foreach ($recipes as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Ou texte libre</label>
                <input type="text" id="meal-custom-title" placeholder="Ex : Restes d'hier">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-danger" onclick="clearMeal()">Effacer</button>
            <button class="btn btn-secondary" onclick="closeModal('meal-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveMeal()">Enregistrer</button>
        </div>
    </div>
</div>

<style>
.meal-cell { min-height: 2.2rem; padding: .3rem .5rem; cursor: pointer; border-radius: 6px; font-size: .82rem; }
.meal-cell:hover { background: var(--bg); }
.table-scroll { overflow-x: auto; }
</style>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
