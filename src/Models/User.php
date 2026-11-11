<?php
namespace App\Models;

use App\Core\Database;

class User
{
    public static function findById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function findByEmail(string $email): ?array
    {
        // Normalisé explicitement plutôt que de dépendre uniquement de la collation de la
        // colonne `email` (insensible à la casse aujourd'hui) : une future migration de
        // collation ou de moteur de base ne doit pas pouvoir réintroduire une confusion de
        // compte entre "User@x.com" et "user@x.com".
        return Database::fetch('SELECT * FROM users WHERE email = ?', [self::normalizeEmail($email)]);
    }

    public static function create(int $familyId, string $name, string $email, string $password, string $role = 'member', string $color = '#4A90D9'): int
    {
        $id = Database::insert(
            'INSERT INTO users (family_id, name, email, password, role, color) VALUES (?, ?, ?, ?, ?, ?)',
            [$familyId, $name, self::normalizeEmail($email), password_hash($password, PASSWORD_DEFAULT), $role, $color]
        );
        self::enqueueLdapProvisioning($id, $password);
        return $id;
    }

    /** Enfile le provisioning LDAP/Synology du compte (voir App\Core\StorageProvisioner) — sans
     *  effet tant qu'aucun backend n'est activé par l'admin (storage_provisioner_backend). Le
     *  mot de passe n'est connu en clair qu'ici et à updatePassword() : jamais stocké tel quel,
     *  toujours chiffré via App\Core\Crypto avant d'entrer dans la file. */
    private static function enqueueLdapProvisioning(int $userId, string $plainPassword): void
    {
        if (!\App\Core\StorageProvisionerFactory::ldapEnabled()) return;
        \App\Models\ProvisioningJob::enqueue($userId, 'provision_ldap', ['password' => \App\Core\Crypto::encrypt($plainPassword)]);
    }

    /**
     * Migration des comptes créés avant l'intégration LDAP : FamilyBoard n'a jamais eu accès au
     * mot de passe en clair de ces membres (seulement son hash bcrypt), donc la seule occasion
     * de créer leur compte LDAP avec le bon mot de passe est leur prochaine connexion réussie
     * (le mot de passe est brièvement en clair côté serveur à ce moment précis, voir
     * AuthController::login()). N'enfile qu'une seule fois (évite d'empiler un job par
     * connexion tant qu'il n'a pas encore tourné) et seulement si le compte n'a pas déjà un
     * ldap_username — un membre déjà migré n'a pas besoin de ce chemin, updatePassword() suffit.
     */
    public static function enqueueLdapProvisioningOnLoginIfMissing(array $user, string $plainPassword): void
    {
        if (!empty($user['ldap_username'])) return;
        if (!\App\Core\StorageProvisionerFactory::ldapEnabled()) return;
        if (\App\Models\ProvisioningJob::hasOutstanding((int)$user['id'], 'provision_ldap')) return;
        self::enqueueLdapProvisioning((int)$user['id'], $plainPassword);
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll('SELECT id, name, email, phone, role, avatar, color, created_at FROM users WHERE family_id = ? ORDER BY name', [$familyId]);
    }

    /**
     * Vérifie qu'un ID utilisateur reçu du client (ex. assigned_to, user_id) appartient bien à
     * la famille courante avant de s'en servir (assignation de tâche, notification...) — sans
     * ce contrôle, un utilisateur peut cibler n'importe quel compte d'une autre famille en
     * devinant un ID (les ID sont séquentiels).
     */
    public static function belongsToFamily(int $userId, int $familyId): bool
    {
        return (bool)Database::fetch('SELECT 1 FROM users WHERE id = ? AND family_id = ?', [$userId, $familyId]);
    }

    /** Plafond d'add-ons de stockage perso propre à un membre (NULL = plafond admin global) —
     *  réservé à l'admin système, distinct de User::update() pour ne pas laisser un membre se
     *  l'auto-attribuer via le formulaire de profil. */
    public static function setStorageAddonCap(int $id, ?int $capGb): void
    {
        Database::execute('UPDATE users SET storage_addon_cap_gb = ? WHERE id = ?', [$capGb, $id]);
    }

    /** Quota de stockage perso illimité pour ce membre — réservé à l'admin système. Repousse le
     *  quota vers les backends actifs dans la foulée (même pattern que
     *  MemberStorageSubscription::enqueueQuotaSync), sans attendre un futur changement
     *  d'abonnement. */
    public static function setStorageUnlimited(int $id, bool $unlimited): void
    {
        Database::execute('UPDATE users SET storage_quota_unlimited = ? WHERE id = ?', [$unlimited ? 1 : 0, $id]);
        if (empty(\App\Core\StorageProvisionerFactory::activeQuotaBackends())) return;
        \App\Models\ProvisioningJob::enqueue($id, 'sync_quota');
    }

    public static function setStripeCustomerId(int $id, string $stripeCustomerId): void
    {
        Database::execute('UPDATE users SET stripe_customer_id = ? WHERE id = ?', [$stripeCustomerId, $id]);
    }

    /** uid du compte LDAP provisionné pour le stockage perso (voir App\Core\LdapService) — vide
     *  aussi ldap_resolved_username : la décision de migration (adoption/uid alternatif) n'a
     *  plus lieu d'être conservée une fois le compte réellement provisionné. */
    public static function setLdapUsername(int $id, string $ldapUsername): void
    {
        Database::execute('UPDATE users SET ldap_username = ?, ldap_resolved_username = NULL WHERE id = ?', [$ldapUsername, $id]);
    }

    /** Décision admin pour un conflit de uid LDAP détecté lors de la migration (voir
     *  AdminController::resolveStorageLdapMigration) — appliquée à la prochaine connexion. */
    public static function setLdapResolvedUsername(int $id, string $ldapUsername): void
    {
        Database::execute('UPDATE users SET ldap_resolved_username = ? WHERE id = ?', [$ldapUsername, $id]);
    }

    /** Membres sans compte LDAP (migration en attente, voir AdminController::analyzeStorageLdapMigration). */
    public static function getWithoutLdapUsername(): array
    {
        return Database::fetchAll(
            'SELECT u.id, u.name, u.email, u.family_id, u.ldap_resolved_username, f.name AS family_name
             FROM users u JOIN families f ON f.id = u.family_id
             WHERE u.ldap_username IS NULL
             ORDER BY f.name, u.name'
        );
    }

    public static function update(int $id, array $data): void
    {
        $allowed = ['name', 'email', 'avatar', 'color', 'birthday', 'location_tracking_enabled', 'phone'];
        $sets = [];
        $params = [];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $sets[] = "$field = ?";
                $params[] = $field === 'email' ? self::normalizeEmail($data[$field]) : $data[$field];
            }
        }
        if (!$sets) return;
        $params[] = $id;
        Database::execute('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    }

    /** Barre de navigation rapide (mobile/PWA) : jusqu'à 3 slugs de module choisis par
     *  l'utilisateur lui-même — préférence personnelle, jamais recalculée automatiquement.
     *  $slugs === null réinitialise vers la sélection par défaut (Family::defaultQuickNav). */
    public static function updateQuickNav(int $id, ?array $slugs): void
    {
        $value = $slugs ? implode(',', array_slice($slugs, 0, 3)) : null;
        Database::execute('UPDATE users SET quick_nav = ? WHERE id = ?', [$value, $id]);
    }

    public static function updatePassword(int $id, string $password): void
    {
        Database::execute('UPDATE users SET password = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
        self::enqueueLdapProvisioning($id, $password);
    }

    public static function verify(string $email, string $password): ?array
    {
        $user = self::findByEmail($email);
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return null;
    }
}
