# FamilyBoard — Refonte design v3 « Maison » (implémentée)

Passe de refonte **globale** du design et de la disposition, appliquée à **toutes les pages**
sans lancer de big-bang : les routes, les contrôleurs, les modèles et les scripts restent inchangés.

## Ce qui a été modifié

### 1. `public/css/app.css` — feuille globale (toutes les pages)
- **Design tokens rafraîchis** dans `:root` et `:root[data-theme="dark"]` : palette chaude
  conservée (crème + indigo + ambre), rayons et ombres plus généreux, nouveaux jetons
  (`--primary-soft`, `--accent-soft`, `--surface-2`, `--border-strong`, `--radius-xs`,
  `--sidebar-active-bg`, `--focus-ring`). **Aucun nom de jeton existant n'a été supprimé.**
- **Couche « v3 » ajoutée en fin de feuille** (≈ 210 lignes) : elle affine/complète la v2
  sans supprimer aucune règle (prioritaire par ordre). Elle couvre :
  - typographie et focus accessibles, barres de défilement discrètes ;
  - shell : sidebar (logo, items, section, état actif), topbar (fond translucide + flou),
    boutons d'action circulaires, barre de navigation basse ;
  - **en-têtes de page unifiés** : `.content-header`, `.tasks-header`, `.projects-header`,
    `.baby-header`, `.budget-nav`, `.calendar-toolbar`, `.custody-toolbar`, `.contacts-toolbar`,
    `.letters-toolbar`, `.warranty-toolbar`, `.docs-toolbar` partagent la même mise en forme,
    plus une classe canonique `.page-head` (+ `-main`, `-eyebrow`, `-title`, `-sub`, `-actions`) ;
  - cartes, boutons (`.btn-ghost`, `.btn-outline` ajoutés), formulaires, alertes, modales,
    badges/tags, tableaux, états vides, chiffres clés, grilles de cartes ;
  - modules « master-detail » (`.tasks-container`) — règles **scopées** pour ne pas transformer
    les conteneurs pleine largeur en cartes imbriquées ;
  - responsive consolidé (768px / 480px).

### 2. `templates/layout.php` — disposition du shell
- La section « Organisation » (22 entrées à plat) est scindée en **3 groupes lisibles** :
  - **Organisation** : Tâches, Projets, Budget, Documents, Garanties, Portail de liens ;
  - **Famille & suivi** : Courriers, Litiges, Scolaire, Salarié, Nounou, Santé, Véhicules, Animaux, Compteurs ;
  - **Démarches & services** : Démarches admin., Congés, Voyages, Coffre-fort, Assistant IA, Bons plans, Additions.
- Les intitulés de section deviennent **collants** pendant le défilement (CSS).
- Toutes les routes, conditions d'accès (`$_navEnabled(...)`, rôle admin, Ollama) et boutons
  (panneau Assistant IA) sont **préservés à l'identique**.

### 3. Habillage
- `public/manifest.json` et `<meta name="theme-color">` alignés sur le fond de sidebar (`#212838`)
  et le fond de page (`#F7F3EC`).
- `templates/landing.php` charge `app.css` puis `landing.css` → la page publique bénéficie
  automatiquement des nouveaux tokens.

## Aperçu
`design/preview.html` charge la **vraie** `public/css/app.css` et reproduit le shell + des
exemples de modules (liste, grille, hero, formulaire, état vide). Bascule clair/sombre incluse.

## Limites de vérification
Cet environnement **n'a ni PHP, ni MySQL, ni Node/Python** : impossible de lancer l'application
ou de prendre une capture des vraies pages. Validation faite de façon statique :
- équilibre des accolades CSS : 1106 / 1106 ;
- `layout.php` : 62 blocs `<?php if (...): ?>` pour 62 `<?php endif; ?>`, 6 sections de navigation.
- L'aperçu `design/preview.html` reflète le rendu réel de la feuille.

### À vérifier sur une instance réelle
- Rendu clair **et** sombre sur les principaux modules (tableau de bord, tâches, calendrier,
  budget, documents, landing, connexion).
- Repli/dépli des sections de la sidebar et persistance (`fb-nav-collapsed`).
- Empilement responsive de la topbar et de la barre basse sur mobile.

## Revert
Les changements sont localisés à `public/css/app.css`, `templates/layout.php`,
`public/manifest.json`. La couche v3 est isolée en fin de `app.css` sous le bandeau
`FamilyBoard v3 — « Maison »` (facile à retirer si besoin).
