<?php
namespace App\Core;

use App\Models\FamilyFriend;

/**
 * Isole la visibilité entre comptes Nextcloud de familles différentes (recherche de personnes
 * dans Fichiers/partage, Talk, Carnet d'adresses, partage de calendrier) en s'appuyant sur le
 * réglage serveur `shareapi_only_share_with_group_members` (à activer une fois, manuellement,
 * côté Nextcloud — FamilyBoard ne peut pas modifier la config système à distance) : ce réglage
 * limite la recherche de comptes aux utilisateurs partageant au moins un groupe Nextcloud commun
 * — vérifié contre le code source réel de nextcloud/server (UserPlugin) ET de nextcloud/spreed
 * (Talk réutilise le même point d'entrée core `core/autocomplete/get`, donc le même filtre, à la
 * fois pour créer une conversation et pour ajouter un participant à une conversation existante).
 *
 * Chaque famille a déjà son groupe natif `family_N` (voir NextcloudGroupFolderService), mais ce
 * groupe sert aussi à l'accès au Group Folder : on ne peut pas y ajouter un membre d'une autre
 * famille sans lui donner accès à son espace de stockage partagé. On crée donc, par paire de
 * familles amies (App\Models\FamilyFriend, acceptée), un groupe DÉDIÉ qui ne sert qu'à cette
 * visibilité croisée — jamais utilisé dans un manageACL de Group Folder.
 */
class NextcloudVisibilityService
{
    /** Nom déterministe et symétrique — toujours les mêmes deux familles donnent le même nom,
     *  quel que soit l'ordre d'appel. */
    private static function linkGroupName(int $familyIdA, int $familyIdB): string
    {
        $pair = [$familyIdA, $familyIdB];
        sort($pair);
        return 'nb_link_' . $pair[0] . '_' . $pair[1];
    }

    /** Identifiants Nextcloud (avec prise en compte de users.nextcloud_username, voir
     *  NextcloudGroupFolderService) de tous les membres actuellement provisionnés d'une famille. */
    private static function familyNextcloudUsernames(int $familyId): array
    {
        $rows = Database::fetchAll(
            'SELECT ldap_username, nextcloud_username FROM users WHERE family_id = ? AND ldap_username IS NOT NULL',
            [$familyId]
        );
        return array_map(fn($row) => $row['nextcloud_username'] ?: $row['ldap_username'], $rows);
    }

    /** Crée/peuple ou vide/supprime le groupe de visibilité d'une paire de familles. Réconcilie
     *  toujours la liste COMPLÈTE des membres actuels (ajout des manquants, retrait de ceux qui
     *  ne font plus partie d'aucune des deux familles) plutôt que de supposer l'état précédent du
     *  groupe — couvre aussi bien un changement de lien d'amitié qu'un membre qui rejoint/quitte
     *  une famille déjà liée (voir syncFamilyMembership()). */
    public static function syncLink(int $familyIdA, int $familyIdB, bool $linked): void
    {
        $group = self::linkGroupName($familyIdA, $familyIdB);

        if (!$linked) {
            foreach (NextcloudService::getGroupMembers($group) as $username) {
                try {
                    NextcloudService::removeUserFromGroup($username, $group);
                } catch (\Throwable $e) {
                    error_log('NextcloudVisibilityService: échec retrait ' . $username . ' du groupe ' . $group . ' : ' . $e->getMessage());
                }
            }
            NextcloudService::deleteGroup($group);
            return;
        }

        NextcloudService::ensureGroup($group);
        $desired = array_unique(array_merge(
            self::familyNextcloudUsernames($familyIdA),
            self::familyNextcloudUsernames($familyIdB)
        ));
        $current = NextcloudService::getGroupMembers($group);

        foreach (array_diff($desired, $current) as $username) {
            try {
                NextcloudService::addUserToGroup($username, $group);
            } catch (\Throwable $e) {
                error_log('NextcloudVisibilityService: échec ajout ' . $username . ' au groupe ' . $group . ' : ' . $e->getMessage());
            }
        }
        foreach (array_diff($current, $desired) as $username) {
            try {
                NextcloudService::removeUserFromGroup($username, $group);
            } catch (\Throwable $e) {
                error_log('NextcloudVisibilityService: échec retrait ' . $username . ' du groupe ' . $group . ' : ' . $e->getMessage());
            }
        }
    }

    /** À appeler chaque fois que la composition d'une famille change (nouveau membre, départ,
     *  suppression de compte) — reconstruit, pour chaque famille déjà amie de celle-ci, la liste
     *  des membres du groupe de visibilité correspondant. Sans effet si la famille n'a aucune
     *  famille amie acceptée. */
    public static function syncFamilyMembership(int $familyId): void
    {
        foreach (FamilyFriend::getAcceptedFamilyIds($familyId) as $friendFamilyId) {
            try {
                self::syncLink($familyId, $friendFamilyId, true);
            } catch (\Throwable $e) {
                error_log('NextcloudVisibilityService: échec resync visibilité famille ' . $familyId . ' / ' . $friendFamilyId . ' : ' . $e->getMessage());
            }
        }
    }
}
