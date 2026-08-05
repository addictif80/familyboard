<?php
$pageTitle = 'Guide d\'utilisation';
ob_start();

$isCoparent = $user['role'] === 'coparent';

// slug => [icône+titre (facultatif, sinon Family::MODULES est utilisé), étapes/astuces en HTML]
$moduleHelp = [
    'wall' => [
        "Chaque publication est soit personnelle (author = vous, visible par les membres qui vous suivent), soit « au nom de la famille » (visible par tous, mais doit d'abord être validée par un administrateur).",
        "Pour publier : bouton <strong>+ Publier</strong> en haut du mur, choisissez le type de publication puis rédigez votre texte et joignez éventuellement une photo.",
        "Pour partager une photo de vos albums directement sur le mur, ouvrez la photo dans un album puis utilisez l'action <strong>Partager sur le mur</strong>.",
        "Suivre un membre : ouvrez son profil et cliquez sur <strong>Suivre</strong> — la demande doit être acceptée par la personne visée avant que vous voyiez son contenu personnel.",
        "Une fois deux membres abonnés l'un à l'autre, une conversation privée devient possible depuis leur profil (icône ✉️).",
    ],
    'albums' => $isCoparent ? [
        "Vous voyez uniquement les albums que la famille a choisi de partager avec votre accès co-parent.",
        "Vous pouvez ajouter des photos aux albums partagés, en plus de celles déjà présentes.",
    ] : [
        "Créez un album depuis <strong>+ Nouvel album</strong>, puis glissez-déposez vos photos ou utilisez le sélecteur de fichiers.",
        "Un album peut être partagé avec un accès restreint (co-parent) via l'option <strong>Partager avec…</strong> dans les réglages de l'album.",
        "Chaque photo peut être commentée ou partagée directement sur le mur familial depuis le bouton dédié dans la visionneuse.",
    ],
    'calendar' => $isCoparent ? [
        "Vous voyez uniquement les événements liés à l'enfant pour lequel vous avez un accès de garde partagée.",
        "Vous pouvez ajouter un événement concernant cet enfant (rendez-vous médical, activité…) ; il devient visible par l'autre parent ayant accès au même enfant.",
    ] : [
        "Cliquez sur un jour (ou glissez sur plusieurs jours) pour créer un événement ; la récurrence (hebdomadaire, mensuelle…) se règle dans le formulaire d'événement.",
        "Pour importer un calendrier externe (Google, Nextcloud, iCloud…), allez dans <strong>Paramètres → Calendriers externes</strong> et ajoutez une URL CalDAV/iCal : la synchronisation se fait ensuite automatiquement à l'intervalle choisi.",
        "Chaque événement peut être lié à un enfant suivi en garde alternée, ce qui le fait apparaître aussi côté co-parent si celui-ci a accès à cet enfant.",
        "Pour inviter une <strong>famille amie</strong> à un événement (anniversaire, sortie commune…), devenez d'abord amie avec elle via son code famille dans <strong>Réglages → Familles amies</strong>, puis cochez-la à la création de l'événement. Elle doit accepter l'invitation, et obtient alors sa propre copie de l'événement, modifiable de son côté.",
        "Si vous modifiez ou supprimez un événement déjà partagé, chaque famille participante reçoit une alerte lui demandant d'accepter ou de refuser le changement — rien n'est jamais répercuté automatiquement chez elle.",
    ],
    'custody' => $isCoparent ? [
        "Votre planning affiche uniquement les jours de garde de l'enfant pour lequel vous avez un accès — jamais les autres enfants ou membres de la famille.",
        "Pour proposer un échange de jour, ouvrez le jour concerné dans le planning et utilisez <strong>Proposer un échange</strong> : l'autre parent reçoit une notification et doit valider.",
        "Le <strong>journal parental</strong> (onglet dédié) permet d'échanger des messages ou notes courtes sur le quotidien de l'enfant, visibles par les deux parents ayant accès à cet enfant.",
        "L'<strong>historique d'activité</strong> trace les principales actions liées à cet accès (modifications de planning, échanges…), pour garder une trace horodatée en cas de désaccord.",
    ] : [
        "Créez un planning de garde par enfant depuis le module, en choisissant une périodicité automatique (ex. une semaine sur deux) — les jours se répètent ensuite sans ressaisie.",
        "Un parent peut proposer un échange de jour ; l'autre parent doit valider la proposition pour qu'elle s'applique au planning.",
        "Pour donner un accès de suivi à un parent séparé sans l'ajouter comme membre de la famille, utilisez <strong>Inviter un co-parent</strong> : il obtient un accès restreint (planning, journal, documents et agenda de l'enfant uniquement).",
        "Le journal parental permet de partager des notes du quotidien avec l'autre parent, même si celui-ci n'est pas membre de votre famille FamilyBoard.",
    ],
    'tasks' => [
        "Créez une liste (tâches ou courses) puis ajoutez des éléments ; chaque élément peut être assigné à un membre, avec priorité et échéance.",
        "Depuis le <strong>planning repas</strong>, un bouton permet d'ajouter en un clic les ingrédients des recettes de la semaine à la liste de courses, sans créer de doublons avec ce qui y figure déjà.",
        "Les tâches en attente depuis plusieurs jours déclenchent automatiquement un rappel par e-mail à la personne assignée.",
    ],
    'chat' => [
        "Une messagerie de groupe propre à toute la famille — distincte des messages privés du mur, qui restent entre deux membres qui se suivent.",
        "Les messages arrivent en temps quasi réel ; une notification s'affiche si l'onglet n'est pas actif.",
    ],
    'budget' => [
        "Enregistrez vos transactions avec une catégorie ; le tableau de bord du module affiche automatiquement la répartition par catégorie et l'évolution du solde.",
        "Les charges récurrentes (loyer, abonnements…) se configurent une fois et génèrent une alerte avant chaque échéance.",
        "Un objectif d'épargne peut être suivi en parallèle, avec sa propre progression.",
    ],
    'projects' => [
        "Chaque projet dispose d'un tableau façon kanban (à faire / en cours / terminé) : glissez-déposez les cartes entre colonnes.",
        "Une carte peut inclure un budget prévisionnel et une liste de matériaux nécessaires, pour garder tout le suivi d'un projet au même endroit.",
    ],
    'contacts' => [
        "Ajoutez vos contacts familiaux et professionnels (médecin, école, artisans…) ; ils sont ensuite visibles par tous les membres de la famille.",
        "Un contact peut être lié à une fiche urgence pour apparaître automatiquement dessus (ex. le médecin traitant d'un enfant).",
    ],
    'warranties' => [
        "Ajoutez une garantie en prenant en photo la facture : le texte est analysé automatiquement (OCR) pour préremplir la date d'achat et la durée de garantie.",
        "Une alerte est envoyée avant l'expiration d'une garantie, pour ne pas la laisser filer sans recours possible.",
    ],
    'documents' => [
        "Déposez vos documents (factures, contrats, carnets de santé…) par glisser-déposer ; les documents scannés sont analysés (OCR) pour permettre une recherche plein texte, y compris dans leur contenu.",
        "Utilisez la barre de recherche en haut du module pour retrouver un document par son contenu, pas seulement par son nom.",
    ],
    'family-wall' => [
        "Générez un lien d'affichage depuis les réglages du module : ouvrez ce lien sur une télévision ou une tablette murale pour un affichage plein écran (agenda du jour, tâches, budget…), sans connexion individuelle nécessaire.",
        "L'affichage se rafraîchit automatiquement ; aucune interaction n'est nécessaire une fois le lien ouvert.",
    ],
    'baby' => [
        "Enregistrez biberons, couches, siestes et mesures de croissance au fil de la journée ; plusieurs personnes peuvent contribuer au suivi du même enfant.",
        "Les courbes de croissance se construisent automatiquement à partir des mesures saisies, pour visualiser l'évolution dans le temps.",
    ],
    'location' => [
        "Partagez votre position pour une durée limitée (24h maximum) depuis le module — ce n'est jamais un suivi permanent en arrière-plan, seulement ce que vous déclenchez explicitement.",
        "Les lieux enregistrés (domicile, école…) peuvent être nommés pour repérer plus vite qui est où.",
    ],
    'emergency' => [
        "Créez une fiche par enfant ou membre avec allergies, traitements en cours et contact d'urgence.",
        "Chaque fiche génère un lien (et un QR code) consultable sans compte — utile pour une baby-sitter, l'école ou les secours. Le lien peut être régénéré à tout moment (ex. si un QR code imprimé est perdu), ce qui invalide l'ancien.",
    ],
    'comm_log' => [
        "Un journal d'échanges courts entre parents autour du quotidien d'un enfant (santé, humeur, événements notables…), visible par les deux parents ayant accès à cet enfant, y compris un accès co-parent.",
    ],
    'meals' => [
        "Planifiez les repas de la semaine, jour par jour ; associez une recette existante ou décrivez le repas librement.",
        "Depuis le module Tâches & Courses, les ingrédients des recettes planifiées peuvent être ajoutés en un clic à la liste de courses.",
    ],
    'sitter' => [
        "Depuis les réglages du module, créez un accès temporaire (durée limitée) donnant à une personne un lien d'accès à l'agenda, au répertoire et aux fiches urgence — sans qu'elle ait besoin de créer de compte.",
        "L'accès peut être révoqué à tout moment, ce qui invalide immédiatement le lien.",
    ],
    'kiosk' => [
        "Fonctionne comme l'écran mural : générez un lien (ou code PIN) d'affichage plein écran depuis les réglages, à ouvrir sur l'appareil dédié.",
    ],
    'wishlist' => [
        "Ajoutez ce qui vous ferait plaisir depuis <strong>+ Ajouter un souhait</strong> ; les autres membres voient votre liste mais pas l'état de réservation de leurs propres souhaits.",
        "Pour offrir un cadeau sans doublon, un autre membre peut <strong>réserver</strong> un souhait de votre liste en secret : vous ne verrez jamais qu'un de vos souhaits est réservé ou déjà acheté, pour préserver la surprise.",
    ],
    'polls' => [
        "Créez un sondage avec une question et plusieurs choix de réponse depuis <strong>+ Nouveau sondage</strong> ; les votes de la famille s'affichent en temps réel.",
        "Un sondage peut être clôturé manuellement à tout moment par son créateur ou un administrateur.",
    ],
    'additions' => $isCoparent ? [
        "En tant qu'accès co-parent, vous pouvez être invité(e) à une addition ou une cagnotte en tant que personne indépendante — pas au nom d'une famille. Répondez depuis l'onglet <strong>🧾 Additions</strong> de votre espace.",
    ] : [
        "Créez une addition (restaurant, cadeau commun, activité…) avec un montant total, puis invitez des participants : membres de votre famille, un co-parent, un membre d'une famille amie, ou une personne sans compte via son e-mail (elle reçoit un lien vers son propre espace additions).",
        "Choisissez la répartition en <strong>pourcentage</strong> ou en <strong>montant libre</strong> par participant ; chaque personne invitée doit accepter individuellement avant de compter dans le suivi.",
        "La <strong>cagnotte</strong> (bouton dédié) est une variante gratuite, sans paiement en ligne : indiquez un objectif et les moyens acceptés (espèces, virement, Wero), puis un administrateur de la famille enregistre chaque perception à la main au fur et à mesure.",
        "Si une personne invitée par e-mail crée ensuite un compte FamilyBoard avec la même adresse, elle retrouve automatiquement les mêmes additions dans son compte.",
    ],
    'links' => $isCoparent ? [
        "Vous ne voyez que les liens marqués comme visibles pour un accès co-parent par l'administrateur de la famille.",
        "Vous pouvez proposer un lien utile (école, mutuelle…) : il doit être validé par un administrateur de la famille avant d'apparaître.",
    ] : [
        "En tant qu'administrateur, ajoutez un lien directement (il est publié immédiatement) ou validez/refusez les propositions envoyées par les membres et les accès co-parent, depuis l'onglet <strong>En attente</strong>.",
        "En tant que membre, proposez un lien via <strong>+ Proposer un lien</strong> : il apparaît une fois validé par un administrateur.",
        "Chaque lien est vérifié automatiquement (adresse accessible et autorisée) et affiche un aperçu du site quand disponible, ainsi que son nombre de clics.",
        "Cochez <strong>Visible par un accès co-parent</strong> pour qu'un lien apparaisse aussi dans le portail que voit un co-parent.",
        "Les liens portant le badge <strong>Certifié</strong> sont ajoutés par l'administrateur du service à toutes les familles : ils ne peuvent pas être modifiés ni supprimés au niveau d'une famille.",
    ],
];

$coparentIntroModules = ['custody', 'comm_log', 'documents', 'calendar', 'albums', 'links', 'additions'];
?>
<div class="card" style="max-width:820px;margin:2rem auto;padding:2rem">
    <h2 style="margin-top:0">🧭 Guide d'utilisation</h2>
    <p style="color:var(--text-muted);margin-bottom:1.5rem">
        Le mode d'emploi pratique de chaque module. Pour une présentation plus générale, voir la
        <a href="<?= BASE_URL ?>/faq">FAQ</a>.
    </p>

    <?php if ($isCoparent): ?>
        <p style="background:var(--bg-alt, rgba(127,127,127,.08));border-radius:8px;padding:.75rem 1rem;font-size:.88rem;color:var(--text-muted)">
            Votre accès est un <strong>accès co-parent</strong> restreint : vous ne voyez ici que les modules
            réellement accessibles depuis votre espace (planning de garde, journal parental, documents, agenda,
            albums, portail de liens et additions liés à l'enfant concerné).
        </p>
    <?php else: ?>
        <p style="background:var(--bg-alt, rgba(127,127,127,.08));border-radius:8px;padding:.75rem 1rem;font-size:.88rem;color:var(--text-muted)">
            Plusieurs personnes peuvent administrer la même famille (par exemple les deux parents) : depuis
            <strong>Réglages → Membres de la famille</strong>, un administrateur peut promouvoir un membre au
            rôle d'administrateur, ou rétrograder un autre administrateur. Le tout premier administrateur de la
            famille (l'administrateur fondateur) ne peut être rétrogradé ni retiré que par lui-même.
        </p>
    <?php endif; ?>

    <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin:1.25rem 0 1.75rem">
        <?php foreach ($moduleHelp as $slug => $steps):
            if ($isCoparent && !in_array($slug, $coparentIntroModules, true)) continue;
            if (!$isCoparent && in_array($slug, $disabledModules, true)) continue;
            $meta = \App\Models\Family::MODULES[$slug] ?? ['label' => ucfirst($slug), 'icon' => '📦'];
        ?>
        <a href="#help-<?= htmlspecialchars($slug) ?>" class="btn btn-secondary btn-sm" style="text-decoration:none">
            <?= $meta['icon'] ?> <?= htmlspecialchars($meta['label']) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php foreach ($moduleHelp as $slug => $steps):
        if ($isCoparent && !in_array($slug, $coparentIntroModules, true)) continue;
        if (!$isCoparent && in_array($slug, $disabledModules, true)) continue;
        $meta = \App\Models\Family::MODULES[$slug] ?? ['label' => ucfirst($slug), 'icon' => '📦'];
    ?>
    <div id="help-<?= htmlspecialchars($slug) ?>" style="border-bottom:1px solid var(--border);padding:1rem 0">
        <h3 style="margin:0 0 .6rem"><?= $meta['icon'] ?> <?= htmlspecialchars($meta['label']) ?></h3>
        <ul style="color:var(--text-muted);margin:0;padding-left:1.2rem;display:flex;flex-direction:column;gap:.4rem">
            <?php foreach ($steps as $step): ?>
                <li><?= $step ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endforeach; ?>

    <p style="margin-top:2rem;font-size:.82rem">
        Une question qui n'est pas couverte ici ? <a href="<?= BASE_URL ?>/faq">Consultez la FAQ</a>
        ou <a href="<?= BASE_URL ?>/support">contactez le support</a>.
    </p>
    <p style="margin-top:.5rem;font-size:.82rem">
        <a href="<?= BASE_URL ?>/">&larr; Retour à l'accueil</a>
    </p>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
