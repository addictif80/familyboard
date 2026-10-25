# FamilyBoard — Proposition de design global v3 « Maison »

> Document de travail. Objectif : faire évoluer l'interface actuelle vers un système
> cohérent, scalable (≈ 40 modules) et accessible, **sans réécrire l'application**.
> Maquette visuelle : [`styleguide.html`](styleguide.html) · Tokens : [`tokens.css`](tokens.css).

---

## 1. Analyse de l'existant

**Ce qui fonctionne et doit être conservé**

- Une identité mémorable : crème chaud + indigo + ambre, police *display* **Fraunces** —
  FamilyBoard ne ressemble pas à un outil générique.
- Un tableau de bord **personnalisable** (widgets activables / réordonnables) et une vue
  « écran mural » plein écran : de vrais atouts.
- Un socle technique propre côté rendu : `data-theme` clair/sombre appliqué avant le premier
  paint, tokens CSS déjà regroupés en tête de `app.css`, PWA aboutie (splash, service worker,
  barre mobile).

**Ce qui pose problème à l'échelle**

| # | Problème | Impact |
|---|----------|--------|
| 1 | **Navigation** : ~40 entrées à plat réparties en 4 sections repliables dans une seule sidebar | Découverte difficile ; ajouter un module dégrade tous les autres |
| 2 | **Emoji comme icônes** (nav, titres, boutons, JS) | Rendu variable selon l'OS, impossible à colorer/aligner, aspect inégal |
| 3 | **En-têtes de module hétérogènes** : `.content-header`, `.tasks-header`, `<h2>🏖️ …</h2>`… | Chaque page « bricole » sa mise en page |
| 4 | **`app.css` monolithique (~1 950 lignes)** + nombreux styles en ligne dans 83 templates | Difficile à faire évoluer sans effet de bord |
| 5 | **États vides / chargement / erreurs** non normalisés | Expérience irrégulière selon les modules |
| 6 | **Chrome visuel lourd** : ombres grises génériques, 3 niveaux de radius incohérents, densité excessive | Fatigue visuelle sur les longues sessions |
| 7 | **`data-theme`** géré mais quelques couleurs codées en dur échappent au thème | Friction en mode sombre |

---

## 2. Direction retenue : « Maison »

> **Idée directrice** — l'application doit se vivre comme une *maison bien rangée* :
> calme, chaleureuse, respirante, où chaque chose est à sa place. On garde la chaleur de
> l'identité actuelle et on gagne en hiérarchie et en cohérence.

Cinq principes :

1. **Le contenu d'abord.** On réduit le chrome (ombres, bordures), on augmente l'espacement
   et on laisse la donnée occuper l'écran.
2. **Une couleur d'action.** L'indigo porte l'action ; l'ambre ne sert qu'à signaler
   (accent, mise en avant, badge) — jamais les deux au même niveau.
3. **Un vrai jeu d'icônes.** SVG vectoriel unique, colorable, aligné (fin des emoji).
4. **Un vocabulaire, pas des variantes.** Un en-tête de page, un état vide, un tableau, une
   alerte : une seule implémentation pour tout le produit.
5. **Doux en clair comme en sombre.** Le mode sombre n'est pas une inversion : c'est un
   thème charbon chaud où l'élévation se traduit par la lumière, pas par l'ombre.

---

## 3. Fondations

### 3.1 Couleur

- **Indigo** `--brand-50 → 900`, pivot **`--brand-600 #3D5A8A`** (primaire, repris de l'existant).
- **Ambre** `--accent-100 → 700`, pivot **`--accent-500 #C99A44`** (accent).
- **Neutres chauds** : fond `#F7F4EE`, surfaces blanches, bordures `#E7E0D1`, texte `#211E18`.
- **Sémantique** : succès / erreur / alerte / info, chacun avec fond teinté + texte contrasté (AA).
- **Sombre** : fond `#15120D`, surfaces `#201C16 → #322B20`, primaire éclairci `#6E8AC0`.
- Le **chrome sombre de la sidebar** est conservé : c'est la seule zone « profonde », elle
  structure l'écran. En sombre, elle s'assombrit encore (`#12141C`).

### 3.2 Typographie

| Rôle | Police | Taille | Usage |
|------|--------|--------|-------|
| Titre héros / accueil | Fraunces 600 | `--text-2xl → 3xl` | Message d'accueil, landing |
| Titre de page | Fraunces 600 | `--text-xl` (22px) | En-tête de module |
| Titre de carte | Inter 600 | `--text-lg` (18px) | Cartes, modales |
| Élément | Inter 500/600 | `--text-md` (16px) | Listes, tableaux |
| Corps | Inter 400 | `--text-base` (14px / 1.6) | Texte courant |
| Label | Inter 600 | `--text-sm` (13px) | Formulaires |
| Méta | Inter 500 | `--text-xs` (12px) | Dates, mentions, badges |

> **Règle** : Fraunces réservé au « grand » (page, accueil, chiffres clés). En dessous de
> 18 px, tout passe en Inter — c'est ce qui rend l'app confortable en lecture dense.

### 3.3 Espace, forme, ombre, mouvement

- Espacement sur une base **4 px** (`--s-1` … `--s-16`).
- Rayons : `6 / 8 / 12 / 16 / 22 / pill` — **un rayon par usage**, plus de valeurs ad hoc.
- Ombres **teintées chaud** (jamais grises), 4 niveaux (`xs → lg`). En sombre : l'élévation
  passe par la surface claire.
- Mouvement : `120 / 200 / 320 ms`, easing `cubic-bezier(.22,.61,.36,1)`, respect de
  `prefers-reduced-motion`.

---

## 4. Navigation & architecture de l'information

Le point le plus structurant. Proposition **incrémentale** (aucune route ne change) :

1. **Favoris épinglables** en tête de sidebar (jusqu'à 6). L'utilisateur met en avant
   ses 3-4 modules du quotidien ; le reste reste accessible.
2. **5 rubriques** stables, repliables, à état mémorisé : *Vie de famille · Organisation ·
   Enfants & garde · Suivi & sécurité · Réglages*. (Reprend l'esprit des 4 sections actuelle.)
3. **Page « Tous les modules »** (nouvelle, `/modules`) : une grille de cartes avec icône,
   nom et phrase de description, avec recherche. C'est le plan du produit qui grandit sans
   casser la sidebar.
4. **Palette de commandes** (`Ctrl/⌘K`) promue au premier plan — s'appuie sur la recherche
   globale existante et le `sidebar-search`.
5. **Mobile** : la barre du bas est conservée ; on ajoute un bouton d'action central
   *Ajouter* (tâche / événement / publication) qui ouvre une feuille rapide.

> Alternative (plus radicale, non recommandée d'emblée) : passer à un **rail d'espaces**
> (6 icônes) + panneau contextuel. Plus « app desktop », mais impose une rééducation des
> utilisateurs et beaucoup plus de refonte.

---

## 5. Bibliothèque de composants

| Composant | Rôle | État actuel |
|-----------|------|-------------|
| `btn` (primary / secondary / ghost / accent / danger · sm/md/lg/pill · icon) | Actions | Partiel |
| `card`, `panel`, `stat` | Conteneurs | `card` seul |
| `page-head` | En-tête de module normalisé | Inexistant (N variantes) |
| `list-row`, `table`, `tag`, `badge`, `avatar` (+ groupe) | Données | Hétérogène |
| `alert`, `toast` | Retour d'action | `alert` seul |
| `empty-state` | Aucune donnée | Inexistant |
| `field`, `input`, `select`, `switch`, `segmented` | Formulaires | Partiel |
| `modal` / `bottom-sheet` (mobile) | Saisie | `modal` seul |
| `tabs`, `dropdown`, `command-palette` | Navigation interne | Partiel |
| `skeleton`, `progress`, `timeline`, `kanban`, `calendar-grid` | Spécifiques | Spécifique par module |

Chaque composant est livré en **clair + sombre**, avec états `hover / focus-visible / active /
disabled`, cible tactile ≥ 44 px.

---

## 6. Icônes

- Jeu unique style **Lucide** (MIT), trait 1,5–1,75 px, 24×24, `currentColor`.
- Exposé par un **sprite SVG** + helper `Icon::svg('calendar', ['size' => 20])`.
- Une **table de correspondance** `emoji → icône` pour migrer les 83 templates et les JS
  progressivement ; les emoji restent en repli tant qu'un écran n'est pas migré.
- Les emoji **restent** là où ils sont du contenu (ex. réactions, contenu du mur), pas de l'UI.

---

## 7. Plan de déploiement (sans big-bang)

| Phase | Contenu | Risque |
|-------|---------|--------|
| **0** | Poser `tokens.css` **avant** `app.css`, en gardant les alias existants (`--primary`, `--card-bg`, `--radius`…) | Faible — les valeurs changent, la structure non |
| **1** | Extraire les primitives (boutons, cartes, formulaires, alertes) dans une couche dédiée ; introduire `.page-head` et `.empty-state` | Faible |
| **2** | Normaliser les en-têtes de module (remplacement progressif de `.content-header` / `.tasks-header`) | Moyen |
| **3** | Sidebar v3 : favoris + rubriques + page « Tous les modules » + ⌘K | Moyen |
| **4** | Migration des icônes emoji → SVG, module par module | Faible (progressif) |
| **5** | Peau fine par module (calendrier, garde, budget, mur, kiosque) | Faible |

**Compatibilité** : les tokens historiques sont conservés comme alias, donc `app.css` continue
de fonctionner pendant la transition ; on retire les alias une fois la migration terminée.

---

## 8. Gains attendus

- **Cohérence** : un seul langage visuel sur ~40 modules.
- **Scalabilité** : ajouter un module ne dégrade plus la navigation.
- **Lisibilité** : hiérarchie typographique claire, densité maîtrisée, contraste AA.
- **Accessibilité** : focus visibles, cibles tactiles, `reduced-motion`, sombre soigné.
- **Vélocité produit** : composants partagés ⇒ moins de CSS et de styles en ligne par module.
