<?php
namespace App\Core;

/**
 * Abstraction du backend de provisioning de stockage perso — un seul backend concret existe
 * aujourd'hui (SynologyProvisioner), mais cette interface évite de ré-écrire les points
 * d'accroche (User::create, AccountDeletion...) si un autre backend (Nextcloud...) est ajouté
 * plus tard. Le backend actif (choisi par l'admin, app_settings.storage_provisioner_backend,
 * "none" par défaut) est résolu par StorageProvisionerFactory::active().
 */
interface StorageProvisioner
{
    /** Crée/met à jour le compte du membre (annuaire) avec son mot de passe en clair — jamais
     *  persisté au-delà de cet appel. Retourne l'identifiant externe (ex. uid LDAP) à stocker
     *  dans users.ldap_username, ou null en cas d'échec (le job sera rejoué). */
    public function provisionUser(int $userId, string $email, string $displayName, string $plainPassword, ?string $existingExternalUsername): ?string;

    /** Pousse le quota (Go) actuel du membre vers le backend. Retourne false si l'opération a
     *  échoué (le job sera rejoué). */
    public function syncQuota(string $externalUsername, int $quotaGb): bool;

    /** Supprime le compte du membre (résiliation/suppression) — sans effet si déjà absent. */
    public function deprovisionUser(string $externalUsername): void;

    public function testConnection(): array;
}
