<?php
namespace App\Models;

use App\Core\Database;
use ZipArchive;

/**
 * Export RGPD-like des données personnelles/familiales sous forme d'archive ZIP
 * (donnees.json + copies des fichiers uploadés). Les tables purement techniques
 * (jetons, sessions, journaux d'accès admin…) sont volontairement exclues.
 */
class DataExport
{
    /** Tables partagées de la famille : [clé du JSON, table SQL]. Filtrées par family_id. */
    private const FAMILY_TABLES = [
        'evenements_calendrier'      => 'events',
        'conges'                     => 'vacations',
        'listes_taches'              => 'task_lists',
        'categories_budget'          => 'budget_categories',
        'transactions_budget'        => 'budget_transactions',
        'objectifs_epargne'          => 'budget_goals',
        'depenses_recurrentes'       => 'budget_recurring',
        'plannings_garde'            => 'custody_schedules',
        'projets'                    => 'projects',
        'garanties'                  => 'warranties',
        'documents'                  => 'documents',
        'contacts'                   => 'contacts',
        'posts_mur_familial'         => 'posts',
        'messages_chat'              => 'messages',
        'journal_parental'           => 'comm_log_messages',
        'bebes'                      => 'babies',
        'grossesses'                 => 'pregnancies',
        'plans_repas'                => 'meal_plans',
        'recettes'                   => 'recipes',
        'fiches_urgence'             => 'emergency_cards',
        'lieux_enregistres'          => 'saved_places',
        'pointages_position'         => 'location_checkins',
        'tickets_support'            => 'support_tickets',
    ];

    /**
     * Tables où l'utilisateur est identifiable comme propriétaire/auteur : [clé JSON, table, colonne].
     * Filtrées uniquement par cette colonne (pas de family_id : certaines, comme `tasks`, n'en ont pas —
     * l'appartenance à l'utilisateur authentifié suffit, son id n'est jamais fourni par le client).
     */
    private const USER_TABLES = [
        'evenements_calendrier'  => ['events', 'user_id'],
        'conges'                 => ['vacations', 'user_id'],
        'listes_taches_creees'   => ['task_lists', 'user_id'],
        'taches_creees'          => ['tasks', 'user_id'],
        'transactions_budget'    => ['budget_transactions', 'user_id'],
        'depenses_recurrentes'   => ['budget_recurring', 'user_id'],
        'projets_crees'          => ['projects', 'user_id'],
        'taches_projet_creees'   => ['project_tasks', 'user_id'],
        'depenses_projet'        => ['project_expenses', 'user_id'],
        'garanties'              => ['warranties', 'user_id'],
        'documents'              => ['documents', 'user_id'],
        'contacts_crees'         => ['contacts', 'user_id'],
        'posts_mur_familial'     => ['posts', 'user_id'],
        'commentaires_mur'       => ['post_comments', 'user_id'],
        'messages_chat'          => ['messages', 'user_id'],
        'journal_parental'       => ['comm_log_messages', 'user_id'],
        'lieux_enregistres'      => ['saved_places', 'created_by'],
        'pointages_position'     => ['location_checkins', 'user_id'],
        'recettes_creees'        => ['recipes', 'created_by'],
        'plans_repas_crees'      => ['meal_plans', 'created_by'],
        'tickets_support'        => ['support_tickets', 'user_id'],
    ];

    /**
     * Tables sans family_id propre, rattachées à la famille via une table parente :
     * [clé JSON, table enfant, table parente, colonne de jointure].
     */
    private const FAMILY_JOIN_TABLES = [
        'taches'              => ['tasks', 'task_lists', 'list_id'],
        'taches_projet'       => ['project_tasks', 'projects', 'project_id'],
        'depenses_projet'     => ['project_expenses', 'projects', 'project_id'],
        'commentaires_mur'    => ['post_comments', 'posts', 'post_id'],
        'periodes_garde'      => ['custody_events', 'custody_schedules', 'schedule_id'],
    ];

    /** [table, colonne(s) contenant un chemin de fichier] pour la copie des pièces jointes. */
    private const FILE_COLUMNS = [
        'documents'  => 'file_path',
        'warranties' => 'file_path',
        'posts'      => 'image_path',
        'messages'   => 'audio_path',
        'comm_log_messages' => 'audio_path',
        'babies'     => 'avatar',
        'contacts'   => 'avatar',
    ];

    /**
     * @return string Chemin absolu vers le fichier ZIP temporaire généré (à supprimer après envoi).
     */
    public static function build(int $userId, int $familyId, bool $wholeFamily): string
    {
        $family = Family::findById($familyId);
        $data = [
            'genere_le' => date('Y-m-d H:i:s'),
            'perimetre' => $wholeFamily ? 'toute_la_famille' : 'mes_donnees',
            'famille'   => $family ? [
                'nom'          => $family['name'],
                'code_invitation' => $family['invite_code'],
                'fuseau_horaire'  => $family['timezone'],
            ] : null,
        ];

        if ($wholeFamily) {
            $data['membres'] = Database::fetchAll(
                'SELECT id, name, email, role, color, created_at FROM users WHERE family_id=? ORDER BY name',
                [$familyId]
            );
            foreach (self::FAMILY_TABLES as $key => $table) {
                $data[$key] = Database::fetchAll("SELECT * FROM `$table` WHERE family_id=?", [$familyId]);
            }
            foreach (self::FAMILY_JOIN_TABLES as $key => [$child, $parent, $joinCol]) {
                $data[$key] = Database::fetchAll(
                    "SELECT c.* FROM `$child` c JOIN `$parent` p ON c.`$joinCol`=p.id WHERE p.family_id=?",
                    [$familyId]
                );
            }
            $filePaths = self::collectFilePaths($familyId, null);
            foreach (Database::fetchAll('SELECT avatar FROM users WHERE family_id=?', [$familyId]) as $row) {
                if (!empty($row['avatar'])) $filePaths[] = $row['avatar'];
            }
        } else {
            $me = Database::fetch('SELECT id, name, email, role, color, avatar, created_at FROM users WHERE id=?', [$userId]);
            $data['profil'] = $me;
            foreach (self::USER_TABLES as $key => [$table, $col]) {
                $data[$key] = Database::fetchAll("SELECT * FROM `$table` WHERE `$col`=?", [$userId]);
            }
            $filePaths = self::collectFilePaths($familyId, $userId);
            if (!empty($me['avatar'])) $filePaths[] = $me['avatar'];
        }

        return self::zip($data, $filePaths);
    }

    private static function collectFilePaths(int $familyId, ?int $userId): array
    {
        $paths = [];
        foreach (self::FILE_COLUMNS as $table => $col) {
            $ownerCol = null;
            if ($userId !== null) {
                if (in_array($table, ['documents', 'warranties', 'posts', 'messages', 'comm_log_messages'], true)) {
                    $ownerCol = 'user_id';
                } elseif ($table === 'babies' || $table === 'contacts') {
                    continue; // ressources partagées, non attribuables à un seul membre
                }
            }
            $sql = "SELECT `$col` as p FROM `$table` WHERE family_id=?" . ($ownerCol ? " AND `$ownerCol`=?" : '');
            $params = $ownerCol ? [$familyId, $userId] : [$familyId];
            foreach (Database::fetchAll($sql, $params) as $row) {
                if (!empty($row['p'])) $paths[] = $row['p'];
            }
        }
        return array_unique($paths);
    }

    private static function zip(array $data, array $filePaths): string
    {
        $tmpZip = tempnam(sys_get_temp_dir(), 'familyboard_export_') . '.zip';
        $zip = new ZipArchive();
        $zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('donnees.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $used = [];
        foreach ($filePaths as $relPath) {
            $abs = BASE_PATH . $relPath;
            if (!is_file($abs)) continue;
            $name = basename($abs);
            // Évite les collisions de noms (fichiers uploadés avec des hash différents mais... par précaution)
            if (isset($used[$name])) $name = uniqid() . '_' . $name;
            $used[$name] = true;
            $zip->addFile($abs, 'fichiers/' . $name);
        }

        $zip->close();
        return $tmpZip;
    }
}
