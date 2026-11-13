<?php
namespace App\Core;

/**
 * Registre unique du contenu "Comment ça marche" : une entrée par module, réutilisée à la fois
 * par la landing page (section "Le détail, module par module") et par les info-bulles d'aide
 * affichées dans l'application (voir templates/partials/tour_widget.php). Une seule source de
 * vérité pour ne pas dupliquer 40 descriptions à deux endroits différents.
 *
 * 'route' est le segment d'URL réel du module (sans le slash initial), utilisé pour retrouver
 * automatiquement le bon guide depuis la page courante — null si le module n'a pas de page dédiée
 * (ex. "birthdays", détecté automatiquement, visible seulement en widget).
 */
class ModuleGuide
{
    public static function all(): array
    {
        return [
            'global' => ['route' => null, 'icon' => '🏠', 'title' => 'FamilyBoard en un coup d\'œil', 'tagline' => 'Comment l\'application est organisée.', 'points' => [
                'La barre de gauche regroupe les modules par thème : Quotidien, Souvenirs & famille, Garde partagée, Finances, Santé & sécurité, Juridique & administratif, Biens & déplacements',
                'Le tableau de bord (icône 🏠) est votre page d\'accueil : widgets personnalisables, aperçu de la journée et des alertes',
                'L\'Assistant IA (icône 🤖) comprend une phrase écrite ou parlée pour créer une tâche, un événement ou un article de courses',
                'Chaque module a son propre bouton d\'aide (icône ❓) pour revoir son fonctionnement à tout moment',
                'Le réglage du compte (icône profil) donne accès aux membres, à la facturation et aux options de confidentialité',
            ]],
            'dashboard' => ['route' => '', 'icon' => '🏠', 'title' => 'Tableau de bord', 'tagline' => 'Une vue d\'ensemble sur mesure, dès la connexion.', 'points' => [
                'Widgets personnalisables : tâches du jour, prochains événements, derniers messages…',
                'Réorganisation des widgets en glisser-déposer',
                'Accès rapide à tous les modules activés pour votre famille',
            ]],
            'calendar' => ['route' => 'calendar', 'icon' => '📅', 'title' => 'Calendrier partagé', 'tagline' => 'Un agenda commun, sans doublons ni confusion.', 'points' => [
                'Synchronisation CalDAV avec vos calendriers existants (Google, Apple…)',
                'Vue par membre, avec une couleur dédiée à chacun',
                'Événements récurrents, rappels et vacances scolaires intégrées',
            ]],
            'custody' => ['route' => 'custody', 'icon' => '👶', 'title' => 'Garde alternée', 'tagline' => 'Un planning de garde qui s\'adapte à toutes les organisations.', 'points' => [
                'Récurrences automatiques : semaine sur deux, un weekend sur deux…',
                'Un weekend sur deux + un jour fixe en semaine (ex. le mercredi)',
                'Périodes de vacances avec répartition dédiée (1 semaine sur 2, semaines paires/impaires…)',
                'Propositions de garde peintes jour par jour pour les cas les plus atypiques',
                'Journal d\'activité horodaté (fuseau horaire de la famille) et IP, activé automatiquement dès la première connexion du co-parent invité',
            ]],
            'coparent' => ['route' => 'coparent', 'icon' => '🔒', 'title' => 'Garde partagée', 'tagline' => 'Un accès restreint et sécurisé pour l\'autre parent.', 'points' => [
                'Le co-parent ne voit que le planning, le journal, les documents et évènements liés à l\'enfant',
                'Envoi de messages vocaux dans le journal parental',
                'Peut proposer des jours de garde directement depuis son accès',
                'Peut créer son propre espace FamilyBoard complet tout en gardant cet accès',
            ]],
            'chat' => ['route' => 'chat', 'icon' => '💬', 'title' => 'Chat familial', 'tagline' => 'Une messagerie privée, rien que pour votre foyer.', 'points' => [
                'Messages instantanés entre tous les membres de la famille',
                'Messages vocaux, enregistrés au micro depuis le navigateur',
                'Historique complet, aucun accès par un tiers',
            ]],
            'messages' => ['route' => 'messages', 'icon' => '✉️', 'title' => 'Messages privés', 'tagline' => 'Une messagerie 1-à-1 entre membres qui se suivent.', 'points' => [
                'Réservée aux membres qui se suivent mutuellement sur le mur familial',
                'Conversation privée distincte du chat familial (visible de toute la famille)',
                'Compteur de messages non lus affiché sur le tableau de bord',
            ]],
            'commlog' => ['route' => 'comm-log', 'icon' => '📝', 'title' => 'Journal parental', 'tagline' => 'Une trace de communication fiable entre parents.', 'points' => [
                'Messages horodatés, jamais modifiables ni supprimables',
                'Messages vocaux, pour un ton plus clair qu\'à l\'écrit',
                'Accusés de lecture pour savoir qui a vu quoi',
            ]],
            'wall' => ['route' => 'wall', 'icon' => '📸', 'title' => 'Mur familial', 'tagline' => 'Votre propre réseau social, rien que pour la famille.', 'points' => [
                'Publications personnelles visibles par vos abonnés, ou au nom de la famille (validées par un admin)',
                'Abonnements mutuels validés par la personne suivie, messages privés une fois l\'abonnement accepté',
                'Partage possible avec une famille amie, uniquement si la relation est acceptée des deux côtés',
                'Partagez vos photos d\'album directement sur le mur',
            ]],
            'albums' => ['route' => 'albums', 'icon' => '🖼️', 'title' => 'Albums photo', 'tagline' => 'Vos souvenirs de famille, organisés et jamais perdus.', 'points' => [
                'Albums thématiques partagés par toute la famille',
                'Lien public en lecture seule ou en dépôt (pour recevoir les photos d\'un événement)',
                'Publication directe d\'une photo d\'album sur le mur familial',
            ]],
            'wishlist' => ['route' => 'wishlist', 'icon' => '🎁', 'title' => 'Liste de cadeaux', 'tagline' => 'Fini les cadeaux en double — la surprise reste intacte.', 'points' => [
                'Chacun note ce qui lui ferait plaisir',
                'Réservation secrète : invisible pour la personne concernée',
                'Suivi des cadeaux déjà achetés par la famille',
            ]],
            'polls' => ['route' => 'polls', 'icon' => '🗳️', 'title' => 'Sondages familiaux', 'tagline' => 'Décidez ensemble, en quelques secondes.', 'points' => [
                'Question à choix multiple, résultats en temps réel',
                'Idéal pour les petites décisions du quotidien',
                'Clôturable par son auteur ou un administrateur',
            ]],
            'birthdays' => ['route' => null, 'icon' => '🎂', 'title' => 'Anniversaires', 'tagline' => 'Plus aucun anniversaire oublié.', 'points' => [
                'Détection automatique depuis les membres, enfants et contacts',
                'Compte à rebours sur le tableau de bord',
                'Rappel par e-mail 7 jours avant',
            ]],
            'familywall' => ['route' => 'family-wall', 'icon' => '📺', 'title' => 'Écran mural', 'tagline' => 'Affichez l\'essentiel de votre organisation sur un écran fixe.', 'points' => [
                'Vue calendrier, tâches et météo en un coup d\'œil',
                'Idéal sur une tablette ou un écran dans la cuisine',
                'Se met à jour automatiquement',
            ]],
            'tasks' => ['route' => 'tasks', 'icon' => '✅', 'title' => 'Tâches & Courses', 'tagline' => 'Listes de tâches et de courses, assignables à chacun.', 'points' => [
                'Corvées, courses et listes partagées entre membres',
                'Assignation par personne, avec priorités et échéances',
                'Synchronisées en temps réel pour toute la famille',
            ]],
            'stock' => ['route' => 'stock', 'icon' => '🛒', 'title' => 'Stock frigo/congélo', 'tagline' => 'Ce qu\'il reste à la maison, et ce qui va périmer.', 'points' => [
                'Quantités et dates de péremption par article, au frigo comme au congélateur',
                'Alerte sur les articles bientôt périmés',
                'Lié aux listes de courses pour ne pas racheter en double',
            ]],
            'budget' => ['route' => 'budget', 'icon' => '💰', 'title' => 'Budget familial', 'tagline' => 'Vos finances de famille, en toute transparence.', 'points' => [
                'Suivi des dépenses et revenus par catégorie',
                'Objectifs d\'épargne et prélèvements récurrents',
                'Graphiques mensuels pour visualiser vos tendances',
            ]],
            'projects' => ['route' => 'projects', 'icon' => '📋', 'title' => 'Projets', 'tagline' => 'Pilotez vos projets familiaux de A à Z.', 'points' => [
                'Tâches, budget et matériel liés à un même projet (travaux, voyages…)',
                'Suivi des dépenses et des achats',
                'Statuts d\'avancement clairs',
            ]],
            'contacts' => ['route' => 'contacts', 'icon' => '📒', 'title' => 'Répertoire', 'tagline' => 'Tous vos contacts importants au même endroit.', 'points' => [
                'Médecins, école, nounou, famille élargie…',
                'Accessible à tous les membres autorisés',
                'Recherche rapide',
            ]],
            'warranties' => ['route' => 'warranties', 'icon' => '🛡️', 'title' => 'Garanties', 'tagline' => 'Ne perdez plus jamais une preuve d\'achat.', 'points' => [
                'Date d\'achat, durée de garantie et rappel d\'expiration',
                'Photo du ticket ou de la facture jointe',
                'Alerte avant l\'expiration',
            ]],
            'documents' => ['route' => 'documents', 'icon' => '🗂️', 'title' => 'Documents', 'tagline' => 'Un coffre-fort numérique pour vos papiers importants.', 'points' => [
                'OCR automatique et classement par type',
                'Recherche plein texte instantanée',
                'Partage ciblé pour un enfant en garde alternée',
            ]],
            'links' => ['route' => 'links', 'icon' => '🔗', 'title' => 'Portail de liens', 'tagline' => 'Tous vos liens utiles, réunis dans de belles cartes.', 'points' => [
                'Aperçu automatique du site, titre et nombre de clics',
                'Propositions des membres soumises à validation',
                'Liens dédiés visibles pour un accès co-parent',
            ]],
            'baby' => ['route' => 'baby', 'icon' => '🍼', 'title' => 'Bébé & grossesse', 'tagline' => 'Le quotidien de bébé, suivi par les deux parents.', 'points' => [
                'Biberons, sommeil et changes en un geste',
                'Suivi de grossesse partagé',
                'Historique consultable par toute la famille',
            ]],
            'location' => ['route' => 'location', 'icon' => '📍', 'title' => 'Position', 'tagline' => 'Savoir que tout le monde est bien arrivé, sans intrusion.', 'points' => [
                'Partage de position volontaire entre membres',
                'Utile pour les trajets école/activités',
                'Désactivable à tout moment',
            ]],
            'emergency' => ['route' => 'emergency', 'icon' => '🆘', 'title' => 'Fiches d\'urgence', 'tagline' => 'Les informations vitales, accessibles en un scan.', 'points' => [
                'Allergies, traitements, contacts d\'urgence',
                'QR code à imprimer pour la nounou ou l\'école',
                'Toujours à jour, jamais périmé dans un tiroir',
            ]],
            'meals' => ['route' => 'meals', 'icon' => '🍽️', 'title' => 'Repas & recettes', 'tagline' => 'Planifiez la semaine sans y penser.', 'points' => [
                'Planning des repas en glisser-déposer',
                'Toute une semaine de recettes ajoutée à la liste de courses en un clic, sans doublon',
                'Idées de repas partagées par toute la famille',
            ]],
            'additions' => ['route' => 'additions', 'icon' => '🧾', 'title' => 'Additions', 'tagline' => 'Partagez une dépense sans sortir la calculette.', 'points' => [
                'Répartition entre membres de la famille, invités inclus via un espace dédié',
                'Chaque participant accepte, refuse ou règle sa part',
                'Suivi en temps réel de qui a payé quoi',
            ]],
            'letters' => ['route' => 'letters', 'icon' => '✉️', 'title' => 'Courriers', 'tagline' => 'Rédigez un courrier officiel en quelques minutes.', 'points' => [
                'Modèles réutilisables et variables (destinataire, civilité, société…)',
                'Aperçu impression / export PDF prêt à envoyer',
                'Bibliothèque de modèles propre à votre famille',
            ]],
            'disputes' => ['route' => 'disputes', 'icon' => '⚖️', 'title' => 'Dossiers de litige', 'tagline' => 'Gardez une trace solide de chaque litige, du premier au dernier échange.', 'points' => [
                'Historique chronologique des échanges avec l\'autre partie',
                'Documents justificatifs centralisés (preuves, courriers, factures…)',
                'Lien de partage sécurisé pour transmettre le dossier à un tiers (avocat, médiateur…), sans jamais exposer le journal interne',
            ]],
            'school' => ['route' => 'school', 'icon' => '🎓', 'title' => 'Suivi scolaire', 'tagline' => 'Emploi du temps, notes, absences et bulletins, pour chaque enfant.', 'points' => [
                'Emploi du temps, matières, professeurs et notes avec moyennes calculées automatiquement',
                'Absences justifiées ou non, activités extra-scolaires et bulletins numérisés',
                'Liez la fiche d\'un enfant au compte d\'un membre ou d\'un co-parent pour lui donner un accès en lecture seule à ses notes, absences et bulletins',
            ]],
            'employment' => ['route' => 'employment', 'icon' => '💼', 'title' => 'Suivi salarié', 'tagline' => 'Emploi, congés, heures et paie d\'un membre de la famille, en un coup d\'œil.', 'points' => [
                'Employeur recherché automatiquement par SIREN, congés payés et RTT calculés depuis le calendrier familial',
                'Planning de travail, heures supplémentaires majorées et estimation du salaire brut → net social → net à verser',
                'Arrêts de travail avec indemnités journalières, et documents (bulletins, contrat) liés au module Documents',
            ]],
            'nanny' => ['route' => 'nanny', 'icon' => '🕒', 'title' => 'Suivi nounou', 'tagline' => 'Heures de garde saisies au jour le jour, avec totaux et rapports PDF.', 'points' => [
                'Saisie manuelle par jour, liée en option à un enfant de la famille',
                'Totaux mensuel et annuel calculés automatiquement',
                'Rapport PDF téléchargeable, mois par mois ou sur l\'année entière',
            ]],
            'deals' => ['route' => 'deals', 'icon' => '🏷️', 'title' => 'Bons plans', 'tagline' => 'L\'annuaire privé de vos bonnes affaires, codes promo et adresses préférées.', 'points' => [
                'Titre, description, lien, code promo et réduction pour chaque bon plan',
                'Classement par catégorie (alimentation, loisirs, enfants…)',
                'Date de validité optionnelle, avec signalement des bons plans expirés',
            ]],
            'health' => ['route' => 'health', 'icon' => '🏥', 'title' => 'Santé', 'tagline' => 'Carnet de santé, courbes de croissance et carnet médical de toute la famille.', 'points' => [
                'Vaccins, allergies et traitements par membre ou par enfant',
                'Courbes de croissance (taille, poids) pour chaque enfant',
                'Médecins et spécialistes, avec rappels de renouvellement d\'ordonnance',
            ]],
            'medication' => ['route' => 'medication', 'icon' => '💊', 'title' => 'Traitement médical', 'tagline' => 'Vos prises de traitement, avec rappel automatique.', 'points' => [
                'Reste privé — aucun autre membre de la famille ne peut le voir',
                'Notification push à chaque heure de prise (pas par e-mail)',
                'Horaires fixes ou intervalle régulier, selon le traitement',
            ]],
            'vehicles' => ['route' => 'vehicles', 'icon' => '🚗', 'title' => 'Véhicules', 'tagline' => 'Entretien, contrôle technique, assurance et kilométrage de chaque véhicule.', 'points' => [
                'Historique d\'entretien daté, avec kilométrage et coût',
                'Échéances d\'assurance et de contrôle technique signalées automatiquement',
                'Un véhicule par membre ou partagé, sans limite',
            ]],
            'pets' => ['route' => 'pets', 'icon' => '🐾', 'title' => 'Animaux de compagnie', 'tagline' => 'Le carnet de santé de vos animaux, vétérinaire et rappels de vaccins.', 'points' => [
                'Fiche par animal avec coordonnées du vétérinaire',
                'Carnet de soins : vaccins, vermifuges, visites, avec rappel de la prochaine échéance',
                'Un animal par famille ou plusieurs, sans limite',
            ]],
            'meters' => ['route' => 'meters', 'icon' => '📊', 'title' => 'Compteurs & consommation', 'tagline' => 'Relevés eau, électricité, gaz — suivez votre consommation dans le temps.', 'points' => [
                'Un compteur par point de mesure, avec fournisseur et numéro de contrat',
                'Historique des relevés avec calcul automatique de la consommation entre deux dates',
                'Autant de compteurs que nécessaire (eau, électricité, gaz…)',
            ]],
            'admin_procedures' => ['route' => 'admin-procedures', 'icon' => '🪪', 'title' => 'Démarches administratives', 'tagline' => 'CNI, passeport, CAF, mutuelle… plus aucune échéance oubliée.', 'points' => [
                'Une échéance par membre ou enfant, avec type de démarche',
                'Rappels automatiques avant expiration (J-30 et J-7)',
                'Coché une fois fait, sans jamais supprimer l\'historique',
            ]],
            'leave' => ['route' => 'leave', 'icon' => '🏖️', 'title' => 'Congés familiaux', 'tagline' => 'Le planning des congés de toute la famille, pour ne plus se marcher dessus.', 'points' => [
                'Un congé par membre, avec type (payés, RTT, sans solde…) et dates',
                'Détection automatique des chevauchements entre membres',
                'Vue d\'ensemble pour organiser les départs sans conflit',
            ]],
            'travels' => ['route' => 'travels', 'icon' => '✈️', 'title' => 'Voyages & réservations', 'tagline' => 'Chaque voyage avec ses réservations : transport, hébergement, activités.', 'points' => [
                'Un voyage par séjour, avec dates, destination et budget',
                'Réservations détaillées avec numéro de confirmation et coût',
                'Tout centralisé pour ne plus fouiller les e-mails avant de partir',
            ]],
            'vault' => ['route' => 'vault', 'icon' => '🔐', 'title' => 'Coffre-fort numérique', 'tagline' => 'Documents et informations essentielles, accessibles à vos proches en cas de besoin.', 'points' => [
                'Documents, comptes, contacts utiles et souhaits, classés par catégorie',
                'Personnes de confiance désignées avec un lien d\'accès personnel',
                'Accès d\'urgence toujours validé manuellement par un administrateur, jamais automatique',
            ]],
            'vault_passwords' => ['route' => 'vault-passwords', 'icon' => '🔑', 'title' => 'Coffre-fort de mots de passe', 'tagline' => 'Vos mots de passe et codes 2FA, chiffrés et séparés de FamilyBoard.', 'points' => [
                'Hébergé séparément (comme Bitwarden) — votre mot de passe maître n\'est jamais connu de FamilyBoard',
                'Chiffrement de bout en bout : même un administrateur ne peut pas lire vos mots de passe',
                'Fonctionne comme une extension de navigateur ou une app classique de gestion de mots de passe',
            ]],
            'ai_assistant' => ['route' => 'ai-assistant', 'icon' => '🤖', 'title' => 'Assistant IA', 'tagline' => 'Un assistant auto-hébergé qui ajoute vos tâches, courses et événements pour vous.', 'points' => [
                'Ajoutez une tâche, un article de courses ou un événement en une phrase',
                'Fonctionne sur un serveur Ollama auto-hébergé : aucune donnée envoyée à un tiers',
                'Actions toujours tracées et visibles de toute la famille',
            ]],
        ];
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /** Retrouve la clé de guide correspondant à un segment de route réel (ex. 'family-wall'). */
    public static function keyForRoute(string $route): ?string
    {
        foreach (self::all() as $key => $guide) {
            if ($guide['route'] === $route) return $key;
        }
        return null;
    }
}
