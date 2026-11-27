// ---- Planification de repas ----

function openRecipeModal(recipe) {
    document.getElementById('recipe-modal-title').textContent = recipe ? 'Modifier la recette' : 'Nouvelle recette';
    document.getElementById('recipe-id').value = recipe ? recipe.id : '';
    document.getElementById('recipe-title').value = recipe ? recipe.title : '';
    document.getElementById('recipe-servings').value = recipe ? recipe.servings : 4;
    document.getElementById('recipe-ingredients').value = recipe ? recipe.ingredients : '';
    document.getElementById('recipe-instructions').value = recipe ? (recipe.instructions || '') : '';
    openModal('recipe-modal');
}

async function saveRecipe() {
    const id = document.getElementById('recipe-id').value;
    const payload = {
        title: document.getElementById('recipe-title').value.trim(),
        servings: parseInt(document.getElementById('recipe-servings').value, 10) || 4,
        ingredients: document.getElementById('recipe-ingredients').value.trim(),
        instructions: document.getElementById('recipe-instructions').value.trim(),
    };
    if (!payload.title || !payload.ingredients) {
        Dialog.toast('Titre et ingrédients requis.', 'error');
        return;
    }
    const url = id ? (BASE_URL + '/api/meals/recipes/' + id) : (BASE_URL + '/api/meals/recipes');
    const r = await apiFetch(url, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) {
        window.location.reload();
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

async function deleteRecipe(id) {
    const ok = await Dialog.confirm('Supprimer cette recette ?');
    if (!ok) return;
    const r = await apiFetch(BASE_URL + '/api/meals/recipes/' + id + '/delete', { method: 'POST' });
    if (r.success) window.location.reload();
}

async function addIngredientsToShoppingList(id) {
    const r = await apiFetch(BASE_URL + '/api/meals/recipes/' + id + '/add-to-shopping-list', { method: 'POST' });
    if (r.success) {
        Dialog.toast(r.added + ' ingrédient(s) ajouté(s) à la liste de courses !');
    } else {
        Dialog.toast('Erreur.', 'error');
    }
}

async function addWeekIngredientsToShoppingList(start) {
    const r = await apiFetch(BASE_URL + '/api/meals/add-week-to-shopping-list', { method: 'POST', body: JSON.stringify({ start }) });
    if (r.success) {
        Dialog.toast(r.added + ' ingrédient(s) ajouté(s) à la liste de courses !');
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

let currentMealDate = null;
let currentMealType = null;

function openMealModal(date, type, label, meal) {
    currentMealDate = date;
    currentMealType = type;
    document.getElementById('meal-modal-title').textContent = label + ' — ' + date;
    document.getElementById('meal-recipe-select').value = meal && meal.recipe_id ? meal.recipe_id : '';
    document.getElementById('meal-custom-title').value = meal ? (meal.custom_title || '') : '';
    document.getElementById('meal-custom-title').disabled = !!(meal && meal.recipe_id);
    openModal('meal-modal');
}

async function saveMeal() {
    const recipeId = document.getElementById('meal-recipe-select').value;
    const customTitle = document.getElementById('meal-custom-title').value.trim();
    const r = await apiFetch(BASE_URL + '/api/meals/plan', {
        method: 'POST',
        body: JSON.stringify({
            date: currentMealDate,
            meal_type: currentMealType,
            recipe_id: recipeId || null,
            custom_title: customTitle,
        }),
    });
    if (r.success) {
        window.location.reload();
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

async function clearMeal() {
    await apiFetch(BASE_URL + '/api/meals/plan/clear', {
        method: 'POST',
        body: JSON.stringify({ date: currentMealDate, meal_type: currentMealType }),
    });
    window.location.reload();
}
