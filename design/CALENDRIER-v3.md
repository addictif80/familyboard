# Calendrier v3 — affichage & robustesse technique

Correctif ciblé faisant suite au signalement :

> Échec du chargement du calendrier : **Load failed** — page `/calendar`, fichier `calendar.js:199`.

## 1. Cause du « Load failed »

`Load failed` est le libellé **WebKit/Safari** d'un échec réseau de `fetch()` — pas une réponse
HTTP 500 (qui aurait produit « Réponse serveur 500… »). Deux défauts se cumulaient :

1. **Service worker (`sw.js`)** — le gestionnaire `fetch` interceptait **toutes** les requêtes et,
   en cas d'échec réseau sans version en cache, faisait `respondWith(caches.match(request))`.
   Or `caches.match` **résout avec `undefined`** en l'absence de correspondance, et un
   `respondWith` qui résout sur autre chose qu'une `Response` est traité par le navigateur comme
   une **erreur réseau opaque** : c'est exactement ce que Safari affiche en `Load failed`.
   Le SW interceptait de plus les requêtes **cross-origin** (Nominatim, polices Google, tuiles
   OSM), exposant ces appels au même défaut.
2. **`calendar.js`** — `loadEvents()` n'envoyait ni délai maximal, ni `Accept: application/json`,
   ni reprise ; et le signalement d'erreur contenait un **numéro de ligne codé en dur**
   (`line: 199`), ce qui rendait le rapport trompeur (la ligne 199 ne correspondait à rien).

## 2. Correctifs techniques

### `sw.js` (cause racine)
- **On ne renvoie plus jamais `undefined`** depuis `respondWith`. Chaque branche se termine par
  une vraie `Response` :
  - assets `/public/` : cache → réseau → `504` ;
  - `/api/` : réseau → dernière réponse en cache → **`503` JSON `{error:"offline"}`** ;
  - pages HTML : réseau → page en cache → `offline.html` → page minimale `503`.
- Les requêtes **cross-origin ne sont plus interceptées** (ni mises en cache).
- Les `POST`/non-GET ne sont pas interceptés (comportement inchangé).
- Chaque `caches.put` est protégé par `.catch(() => {})` (un quota plein ne casse plus la réponse).

### `public/js/calendar.js`
- `loadEvents()` réécrit :
  - `AbortController` + **délai maximal de 20 s** (`fetchEventsWithTimeout`) ;
  - en-tête **`Accept: application/json`** — ce qui a aussi pour effet de faire renvoyer du JSON
    (et non une page HTML) par le gestionnaire d'exceptions global côté serveur ;
  - **détection des réponses non-JSON** (page de connexion après expiration de session) ;
  - **un réessai automatique** sur erreur réseau/délai, puis message clair et bouton
    « Réessayer » (au lieu d'un rechargement de page) ;
  - **jeton de séquence** (`_eventsReqSeq`) : une réponse périmée (mois précédent chargé plus
    lentement) n'écrase plus l'affichage courant ;
  - erreurs **typées** (`CalendarLoadError` : `network` / `offline` / `server`) avec messages
    distincts ; suppression du `line: 199` fabriqué.
- **Erreur de navigation corrigée** : `prevMonth()`/`nextMonth()` utilisaient
  `currentDate.setMonth(...)` en conservant le jour courant ; au 29/30/31, le mois suivant
  débordait (31 janv. → 3 mars), **sautant un mois entier**. La navigation repart désormais
  systématiquement du **1er du mois**.
- **Filtre par membre réellement fonctionnel** : les cases à cocher de la barre d'outils
  n'étaient reliées à rien (l'API ignore tout filtre par membre). Le filtrage est désormais
  fait **côté client** sur `extendedProps.user_id` (aucun rechargement réseau) ; les événements
  sans personne rattachée (garde alternée, vacances scolaires, anniversaires) restent toujours
  visibles.
- **Bug d'affichage corrigé** : les puces d'événement portaient **deux attributs `style`** ; le
  second (qui devait rendre la puce cliquable) était ignoré par le parseur. Un seul `style`
  fusionné désormais.
- **`syncCalDAV(id)`** lisait la variable globale obsolète `window.event.currentTarget` : le
  bouton est maintenant passé explicitement (`syncCalDAV(id, this)`) et l'appel réseau est
  protégé par un `try/catch` (plus de rejet non géré).
- `loadShareInvitations()` : panneau optionnel, protégé pour ne jamais produire de rejet non géré.

### `src/Controllers/CalendarController.php` + `src/Models/Event.php`
- **Résilience serveur** (éviter les erreurs internes) : chaque source optionnelle de
  `apiEvents()` (garde alternée, congés, vacances scolaires, anniversaires, projets) est isolée
  dans un garde-fou `$safe(...)`. L'échec de l'une est **journalisé** mais ne fait plus échouer
  tout le calendrier.
- `Event::getByFamily()` : la requête principale (qui dépend des tables/colonnes du partage
  inter-familles — `event_shares`, `shared_from_family_id`, `former_user_name`) dispose d'un
  **repli** sur une requête minimale si ces objets manquent (migration non appliquée).
- Ajout de **`user_id`** dans `extendedProps` des événements : nécessaire au filtre par membre.

## 3. Améliorations d'affichage

Bloc ajouté en fin de `public/css/app.css` (prioritaire par ordre, aucune règle supprimée) :

- barre d'outils : filtres/actions en « puces » cohérentes, ombre douce, état actif coloré ;
- cadre : bordure + rayon `--radius-lg`, en-tête de navigation sur fond `--surface-2`, mois en
  police d'affichage (Fraunces) ;
- grille (desktop, `min-width:769px` uniquement pour ne pas écraser le rendu compact mobile) :
  lignes de séparation par `gap` sur fond `--border`, week-ends légèrement teintés, survol doux,
  anneau sur « aujourd'hui », numéros alignés ;
- puces d'événement : coins arrondis, liseré interne, gras léger ; variantes `cal-event-custody`
  et `cal-event-school` ;
- **état de chargement** (spinner) et **état d'erreur** centré avec bouton « Réessayer » ;
- ajustements mobile (barre d'agenda, sélection, teinte du jour).

## 4. Fichiers touchés

- `sw.js` — cause racine du « Load failed ».
- `public/js/calendar.js` — robustesse, navigation, filtre membre, rendu.
- `public/css/app.css` — bloc `Calendrier v3 — affinage de l'affichage`.
- `src/Controllers/CalendarController.php` — garde-fous + `user_id`.
- `src/Models/Event.php` — requête de repli.
- `templates/calendar/index.php` — `syncCalDAV(id, this)`.

## 5. Limites de vérification

Environnement **sans PHP/MySQL/Node/Python** : pas d'exécution ni de capture possible.
Validation statique : accolades équilibrées (`app.css` 1150/1150, `calendar.js` 231/231,
`CalendarController.php` 82/82, `Event.php` 15/15, `sw.js` 41/41) ; parenthèses/crochets/backticks
équilibrés dans `calendar.js`.

### À vérifier sur une instance réelle
- Chargement du calendrier **hors-ligne** : doit afficher l'état d'erreur clair, jamais
  `Load failed`.
- Navigation de mois en fin de mois (29/30/31) : aucun mois sauté.
- Filtre par membre : décocher une personne masque instantanément ses événements.
- Après déploiement : forcer la mise à jour du service worker (fermer/rouvrir la PWA ou
  recharger deux fois) pour bénéficier du correctif `sw.js`.
