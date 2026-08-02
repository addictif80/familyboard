<?php
$pageTitle = 'FAQ — ' . APP_NAME;
ob_start();

$sections = [
    'Généralités' => [
        [
            "Qu'est-ce que FamilyBoard ?",
            "FamilyBoard est un service d'organisation familiale — l'équivalent de FamilyWall. Il réunit calendrier partagé, mur familial, tâches, budget, garde alternée, documents et une dizaine d'autres modules dans un seul espace privé pour votre famille.",
        ],
        [
            "Qui gère mes données ?",
            "FamilyBoard est édité et opéré par son administrateur, qui est responsable du traitement de vos données au sens du RGPD. Les détails (identité, contact, finalités) sont dans la " . "<a href=\"" . BASE_URL . "/confidentialite\">politique de confidentialité</a>.",
        ],
        [
            "Puis-je installer FamilyBoard comme une application sur mon téléphone ?",
            "Oui, sans passer par un store : depuis Paramètres → 📲 Installer l'application, un bouton propose l'installation directe (Android/Chrome) ou un guide pas-à-pas (iPhone/iPad via Safari → Partager → Sur l'écran d'accueil). L'app fonctionne ensuite hors-ligne partiellement et peut recevoir des notifications.",
        ],
    ],
    'Compte & famille' => [
        [
            "Comment créer mon espace famille ?",
            "Depuis l'inscription, choisissez « Créer une nouvelle famille » : vous devenez automatiquement administrateur de cette famille. Vous pouvez ensuite inviter les autres membres.",
        ],
        [
            "Comment inviter les autres membres de ma famille ?",
            "Deux façons : partager le code d'invitation de la famille (visible dans Paramètres → Famille, à saisir lors de l'inscription), ou envoyer une invitation par e-mail depuis les paramètres — le lien envoyé est valable 7 jours et à usage unique.",
        ],
        [
            "Quelle est la différence entre administrateur et membre ?",
            "L'administrateur peut gérer les paramètres de la famille, inviter/retirer des membres, activer ou désactiver des modules, et accéder aux réglages avancés (SMTP, etc. selon les droits). Un membre a accès à toutes les données de la famille mais pas à ces réglages d'administration.",
        ],
        [
            "Qu'est-ce qu'un accès co-parent ?",
            "C'est un accès restreint pensé pour un parent séparé qui a besoin de suivre la garde d'un enfant sans faire partie de la famille au sens large. Un compte co-parent ne voit que le planning de garde, le journal parental, les documents et les événements liés à l'enfant concerné — jamais le mur familial, le chat, le budget ou les autres données de la famille. Un même compte peut d'ailleurs avoir sa propre famille FamilyBoard et recevoir en plus un accès de garde partagée vers une autre famille : les deux notions sont indépendantes.",
        ],
        [
            "Puis-je changer de mot de passe ou d'e-mail ?",
            "Le mot de passe se change depuis Paramètres → Profil (le mot de passe actuel est demandé, et toutes vos autres sessions/appareils sont automatiquement déconnectés par sécurité). L'adresse e-mail n'est pas modifiable directement — contactez le support si besoin.",
        ],
        [
            "Comment récupérer mes données ou supprimer mon compte ?",
            "Depuis Paramètres, un export complet de vos données (JSON + fichiers) est disponible à tout moment. La suppression de compte y est également possible et efface réellement vos données personnelles, pas seulement un statut — voir la " . "<a href=\"" . BASE_URL . "/confidentialite\">politique de confidentialité</a> pour le détail.",
        ],
    ],
    'Vie de famille' => [
        [
            "📸 Mur familial",
            "Un fil d'actualité privé pour publier des messages et des photos, réagir avec des emoji et commenter — comme un réseau social, mais réservé à votre famille.",
        ],
        [
            "🖼️ Albums photo",
            "Créez des albums partagés par tous les membres de la famille. Un album peut aussi être partagé avec un accès restreint (ex. un co-parent) via un lien dédié.",
        ],
        [
            "📅 Calendrier partagé",
            "Événements avec récurrence, et import de calendriers externes (Google, Nextcloud, iCloud…) via CalDAV/iCal, avec synchronisation automatique configurable.",
        ],
        [
            "👶 Garde alternée",
            "Planning de garde par enfant avec périodicité automatique (semaine sur deux, etc.), proposition d'échange de jours entre parents, journal parental partagé et historique horodaté de l'activité.",
        ],
        [
            "💬 Chat familial",
            "Une messagerie instantanée propre à votre famille, séparée du mur (fil d'actualité) — pour les échanges du quotidien.",
        ],
        [
            "🎂 Anniversaires",
            "FamilyBoard repère automatiquement les anniversaires connus (membres de la famille, enfants suivis, contacts du répertoire) et affiche un compte à rebours sur le tableau de bord, avec un rappel par e-mail 7 jours avant.",
        ],
        [
            "🎁 Liste de cadeaux",
            "Chacun note ce qui lui ferait plaisir. Les autres membres peuvent réserver un cadeau en secret pour éviter les doublons : la personne concernée ne voit jamais si l'un de ses souhaits est réservé ou déjà acheté, pour préserver la surprise.",
        ],
        [
            "🗳️ Sondages familiaux",
            "Posez une question à choix multiple à toute la famille (« on mange où ce soir ? », « destination des vacances ? ») et suivez les votes en temps réel.",
        ],
    ],
    'Organisation' => [
        [
            "✅ Tâches & Courses",
            "Listes partagées avec assignation, priorités, échéances et rappels automatiques par e-mail pour les tâches en attente depuis plusieurs jours. Depuis le planning repas, les ingrédients des recettes de la semaine peuvent être ajoutés en un clic à la liste de courses, sans doublon.",
        ],
        [
            "💰 Budget partagé",
            "Suivi des transactions par catégorie, objectifs d'épargne, et charges récurrentes avec alertes avant échéance.",
        ],
        [
            "📋 Projets",
            "Gestion de projet façon kanban, avec suivi de budget et liste de matériaux liés à chaque tâche.",
        ],
        [
            "🛡️ Garanties",
            "Suivi des garanties produits avec reconnaissance automatique du texte des factures (OCR) pour retrouver rapidement date d'achat et durée de garantie.",
        ],
        [
            "🔗 Portail de liens",
            "Une page de liens utiles pour la famille (portail de l'école, mutuelle, mairie…), présentés sous forme de cartes avec aperçu du site, titre et nombre de clics. L'administrateur de famille ajoute des liens directement ; les autres membres — et un accès co-parent — peuvent en proposer, soumis à validation avant publication. Chaque lien est vérifié automatiquement (accessibilité, adresse autorisée) avant d'être ajouté. Un lien peut être marqué visible pour un accès co-parent, qui reçoit alors uniquement ceux qui le concernent.",
        ],
        [
            "🗂️ Documents",
            "Un coffre-fort documentaire familial avec OCR et recherche plein texte dans les documents scannés.",
        ],
        [
            "📇 Répertoire",
            "Un carnet de contacts familiaux et professionnels partagé (médecin, école, artisans…).",
        ],
        [
            "🍽️ Repas",
            "Planification des repas de la semaine, pour ne plus se demander chaque soir « qu'est-ce qu'on mange ? ».",
        ],
    ],
    'Suivi & sécurité' => [
        [
            "🍼 Suivi bébé",
            "Biberons, couches, sommeil et courbes de croissance, pour suivre le quotidien d'un nourrisson à plusieurs.",
        ],
        [
            "📍 Position",
            "Partage ponctuel de votre position (durée limitée choisie par vous, 24h maximum) et lieux enregistrés — ce n'est pas un suivi permanent en arrière-plan, seulement un partage explicite que vous déclenchez.",
        ],
        [
            "🚑 Fiches urgence",
            "Fiches médicales/urgence (allergies, traitements, contact d'urgence…) consultables via un lien dédié sans avoir besoin d'un compte — utile pour une baby-sitter, l'école ou les secours. Chaque consultation du lien est journalisée, et le lien peut être régénéré à tout moment si besoin (perte d'un QR code imprimé, par exemple).",
        ],
        [
            "🧑‍🍼 Accès baby-sitter",
            "Un lien temporaire donnant un accès limité (agenda, contacts, fiches urgence) sans créer de compte pour la personne qui garde vos enfants.",
        ],
        [
            "🌩️ Veille informationnelle",
            "Alertes officielles (canicule, inondation, feu de forêt…) pour votre zone géographique, remontées automatiquement.",
        ],
        [
            "📺 Écran mural / kiosque",
            "Un affichage plein écran pensé pour une télévision ou une tablette murale : agenda, tâches et budget du jour, accessible via un lien kiosque dédié sans connexion individuelle.",
        ],
    ],
    'Notifications & sécurité du compte' => [
        [
            "Comment fonctionnent les notifications ?",
            "Trois canaux : une cloche dans l'application (historique consultable), des notifications push sur votre appareil (à activer depuis Paramètres → Notifications push), et des rappels par e-mail (événements, tâches en attente). Un compte co-parent ne reçoit que les notifications liées à son accès de garde, jamais les notifications génériques d'une famille à laquelle il n'appartient pas.",
        ],
        [
            "Le résumé hebdomadaire",
            "Chaque dimanche en fin de journée, un e-mail récapitule la semaine à venir : agenda, tâches en attente, solde du mois et anniversaires proches. Un compte disposant d'un accès de garde partagée reçoit à la place (ou en plus, s'il a aussi sa propre famille) un résumé distinct et strictement limité au planning de garde et aux rendez-vous liés à l'enfant concerné.",
        ],
        [
            "Qu'est-ce que la double authentification (2FA) ?",
            "Une couche de sécurité supplémentaire au moment de la connexion, par code d'application d'authentification (TOTP) ou par code envoyé par e-mail. Activable depuis Paramètres → Sécurité ; l'administrateur système peut aussi la rendre obligatoire pour tous les utilisateurs du service, avec un délai de grâce avant blocage.",
        ],
        [
            "Que se passe-t-il si je perds l'accès à mon appareil 2FA ?",
            "Contactez l'administrateur de votre famille ou le support : une désactivation manuelle de la 2FA peut être nécessaire pour retrouver l'accès.",
        ],
        [
            "Comment signaler un problème technique ?",
            "Depuis le menu du compte → 🎫 Support, un ticket peut être créé, avec un diagnostic technique joint automatiquement pour faciliter le dépannage.",
        ],
    ],
];
?>
<div class="card" style="max-width:820px;margin:2rem auto;padding:2rem">
    <h2 style="margin-top:0">❓ Questions fréquentes</h2>
    <p style="color:var(--text-muted);margin-bottom:1.5rem">
        Une présentation détaillée de chaque fonctionnalité de <?= APP_NAME ?>. Pour tout ce qui
        touche aux données personnelles, voir la <a href="<?= BASE_URL ?>/confidentialite">politique de confidentialité</a>.
    </p>
    <?php foreach ($sections as $section => $items): ?>
        <h3 style="margin:1.75rem 0 .5rem"><?= htmlspecialchars($section) ?></h3>
        <?php foreach ($items as [$q, $a]): ?>
        <details style="border-bottom:1px solid var(--border);padding:.6rem 0">
            <summary style="cursor:pointer;font-weight:600"><?= htmlspecialchars($q) ?></summary>
            <p style="color:var(--text-muted);margin:.5rem 0 0"><?= $a ?></p>
        </details>
        <?php endforeach; ?>
    <?php endforeach; ?>
    <p style="margin-top:2rem;font-size:.82rem">
        <a href="<?= BASE_URL ?>/">&larr; Retour à l'accueil</a>
    </p>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
