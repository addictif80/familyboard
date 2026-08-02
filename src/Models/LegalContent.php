<?php
namespace App\Models;

/**
 * Politique de confidentialité et CGU — texte éditable par l'administrateur système
 * (AppSetting), avec un contenu initial par défaut tant qu'il n'a rien personnalisé.
 * Stocké en texte brut (une ligne vide = un nouveau paragraphe) : pas de HTML, donc
 * aucun risque XSS côté rendu, volontairement simple pour un texte juridique.
 */
class LegalContent
{
    public const TYPES = ['privacy' => 'legal_privacy_policy', 'terms' => 'legal_terms'];

    public static function get(string $type): string
    {
        $key = self::TYPES[$type] ?? null;
        if (!$key) return '';
        $custom = AppSetting::get($key);
        return $custom !== null && trim($custom) !== '' ? $custom : self::default($type);
    }

    public static function isCustom(string $type): bool
    {
        $key = self::TYPES[$type] ?? null;
        if (!$key) return false;
        $custom = AppSetting::get($key);
        return $custom !== null && trim($custom) !== '';
    }

    public static function set(string $type, string $content): void
    {
        $key = self::TYPES[$type] ?? null;
        if (!$key) return;
        AppSetting::set($key, $content);
    }

    private static function default(string $type): string
    {
        $contact = AppSetting::get('admin_email') ?: '(adresse à renseigner par l\'administrateur — Paramètres → Mon profil)';

        if ($type === 'terms') {
            return <<<TXT
Dernière mise à jour : à personnaliser par l'administrateur de cette instance.

1. Objet

FamilyBoard est un logiciel libre d'organisation familiale, auto-hébergé par l'administrateur de cette instance (ci-après « l'administrateur »). Les présentes conditions régissent l'utilisation de cette instance précise, pas du logiciel FamilyBoard en général.

2. Compte et famille

La création d'un compte suppose de fournir des informations exactes (nom, adresse e-mail). Chaque compte appartient à une famille au sens de l'application (un foyer, ou un groupe de personnes souhaitant partager un espace commun) ; les membres d'une même famille ont accès aux données que les autres membres y déposent (agenda, messages, photos, budget, documents…), selon les modules activés.

3. Contenu déposé

Chaque utilisateur reste responsable du contenu qu'il publie ou dépose (messages, photos, documents, notes). Les fiches d'urgence et les liens d'accès temporaires (baby-sitter, écran kiosque) créés par un utilisateur engagent sa responsabilité quant aux personnes avec qui il choisit de les partager.

4. Disponibilité

Le service est fourni « en l'état », sans garantie de disponibilité continue. L'administrateur s'efforce de maintenir le service accessible et à jour, sans obligation de résultat.

5. Suspension et suppression de compte

L'administrateur peut suspendre un compte en cas d'usage abusif de l'instance. Chaque utilisateur peut supprimer son propre compte à tout moment depuis les paramètres ; les administrateurs de famille peuvent également supprimer la famille entière et l'ensemble de ses données.

6. Contact

Pour toute question relative à ces conditions : {$contact}
TXT;
        }

        return <<<TXT
Dernière mise à jour : à personnaliser par l'administrateur de cette instance.

1. Responsable du traitement

Cette instance de FamilyBoard est administrée par l'administrateur système de ce serveur, joignable à : {$contact}. C'est cette personne (ou cette organisation), et non les auteurs du logiciel FamilyBoard, qui est responsable du traitement de vos données au sens du RGPD.

2. Données collectées et finalités

Selon les modules que votre famille active, l'application peut traiter : identité et contact (nom, e-mail, avatar), contenus familiaux (publications, messages, photos, documents, notes de journal parental), données de planification (calendrier, tâches, budget, garde partagée), données de localisation ponctuelles (partage de position à durée limitée, explicitement déclenché par vous), et données de santé (fiches d'urgence médicales) si vous choisissez de les renseigner. Ces données sont traitées dans le seul but de fournir les fonctionnalités que vous et votre famille utilisez.

3. Base légale

Le traitement repose sur l'exécution du service demandé (création de compte, utilisation des modules) et, pour les données de santé des fiches d'urgence, sur votre consentement explicite au moment où vous choisissez de les renseigner.

4. Durée de conservation

Vos données sont conservées tant que votre compte existe. Les positions partagées expirent automatiquement à la durée que vous choisissez (au maximum 24h) et sont ensuite supprimées. Un ticket de support clos est conservé au maximum 18 mois. La suppression de votre compte ou de votre famille entraîne la suppression réelle des données associées (voir section 6).

5. Destinataires et sous-traitants

Vos données ne sont partagées qu'avec les membres de votre famille (et, pour un accès de garde partagée, avec le compte co-parent explicitement autorisé), jamais avec des tiers à des fins commerciales. Certaines fonctionnalités font appel à des services externes techniques : l'envoi d'e-mails transite par le serveur SMTP configuré par l'administrateur ; la météo et la recherche de lieux utilisent les API publiques open-meteo.com et nominatim.openstreetmap.org ; les polices de caractères sont chargées depuis Google Fonts. Ces services techniques ne reçoivent que les informations strictement nécessaires à leur fonction (ex. nom de ville pour la météo).

6. Vos droits

Vous disposez d'un droit d'accès, de rectification, d'effacement et de portabilité de vos données. Un export complet de vos données est disponible depuis Paramètres → Exporter mes données. La suppression de votre compte (Paramètres → Supprimer mon compte) efface réellement vos données personnelles et les fichiers associés, pas seulement un statut. Pour exercer ces droits ou pour toute question, contactez : {$contact}. Vous disposez également du droit d'introduire une réclamation auprès de l'autorité de contrôle compétente (en France, la CNIL).

7. Sécurité

Les mots de passe sont chiffrés (bcrypt), les sessions sécurisées, et l'accès aux données est strictement cloisonné par famille. Les fiches d'urgence médicales, accessibles sans compte via un lien dédié, journalisent chaque consultation et peuvent voir leur lien régénéré à tout moment par la famille.

8. Cookies

Seuls des cookies strictement nécessaires au fonctionnement (session, maintien de connexion) sont utilisés — aucun cookie de mesure d'audience ou de publicité.
TXT;
    }
}
