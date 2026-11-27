<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Addition;
use App\Models\AdditionParticipant;
use App\Models\AdditionPayment;
use App\Models\AdditionGuest;
use App\Models\Custody;
use App\Models\FamilyFriend;
use App\Models\User;

class AdditionController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('additions');
        $user = Session::user();
        $familyId = (int)$user['family_id'];
        $additions = Addition::getByFamily($familyId);

        // Personnes invitables : membres de ma famille (hors moi), membres des familles amies
        // acceptées, et co-parents ayant accès à un planning de garde de cette famille (même
        // sans lien "famille amie" — voir Custody::userHasAccessToAnyScheduleOfFamily()). Un
        // compte co-parent à accès restreint appartient déjà à cette famille (donc listé par la
        // première requête) ; seul un co-parent membre à part entière d'une AUTRE famille a
        // besoin d'être ajouté explicitement ici.
        $invitableUsers = array_values(array_filter(User::getByFamily($familyId), fn($u) => (int)$u['id'] !== (int)$user['id']));
        $invitableIds = array_column($invitableUsers, 'id');
        foreach (FamilyFriend::getAcceptedFor($familyId) as $ff) {
            foreach (User::getByFamily((int)$ff['family_id']) as $u) {
                $u['friend_family_name'] = $ff['family_name'];
                $invitableUsers[] = $u;
                $invitableIds[] = $u['id'];
            }
        }
        foreach (Custody::getCoparentUsersForFamily($familyId) as $cp) {
            if (in_array($cp['id'], $invitableIds, true)) continue;
            $invitableUsers[] = ['id' => $cp['id'], 'name' => $cp['name'], 'role' => 'coparent', 'friend_family_name' => null];
            $invitableIds[] = $cp['id'];
        }

        $myParticipations = AdditionParticipant::getForUser((int)$user['id']);

        require BASE_PATH . '/templates/additions/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            if (trim($data['title'] ?? '') === '') {
                return ['success' => false, 'error' => 'Titre requis.'];
            }
            if (empty($data['is_cagnotte']) && (float)($data['total_amount'] ?? 0) <= 0) {
                return ['success' => false, 'error' => 'Montant total requis.'];
            }
            $id = Addition::create((int)$user['family_id'], (int)$user['id'], $data);
            return ['success' => true, 'id' => $id, 'addition' => Addition::getById($id)];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $addition = Addition::getById((int)$params['id']);
            if (!$addition || $addition['family_id'] !== $user['family_id']) {
                return ['success' => false, 'error' => 'Non autorisé'];
            }
            Addition::delete((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    /** Invite un participant : soit un utilisateur existant (user_id), soit une personne
     *  externe (email + name). */
    public function inviteParticipant(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $addition = Addition::getById((int)$params['id']);
            if (!$addition || $addition['family_id'] !== $user['family_id']) {
                return ['success' => false, 'error' => 'Non autorisé'];
            }
            $data = $this->jsonInput();
            // Une cagnotte n'a pas de répartition : la part n'a de sens que pour une addition
            // classique (pourcentage ou montant à charge du participant).
            $shareValue = (float)($data['share_value'] ?? 0);
            if (!$addition['is_cagnotte'] && $shareValue <= 0) {
                return ['success' => false, 'error' => 'Part invalide.'];
            }

            if (!empty($data['user_id'])) {
                $targetId = (int)$data['user_id'];
                $target = User::findById($targetId);
                if (!$target) return ['success' => false, 'error' => 'Membre introuvable.'];
                $sameFamily = (int)$target['family_id'] === (int)$user['family_id'];
                $friendFamily = FamilyFriend::areFriends((int)$user['family_id'], (int)$target['family_id']);
                // Un co-parent (accès à un planning de garde de cette famille via custody_access)
                // est un participant légitime même sans lien "famille amie" — c'est justement le
                // cas d'usage visé (partage de frais liés à la garde), voir Custody::.
                $coparentAccess = Custody::userHasAccessToAnyScheduleOfFamily($targetId, (int)$user['family_id']);
                if (!$sameFamily && !$friendFamily && !$coparentAccess) {
                    return ['success' => false, 'error' => 'Ce membre n\'est ni de votre famille, ni d\'une famille amie, ni un co-parent ayant accès à un de vos plannings de garde.'];
                }
                $pid = AdditionParticipant::inviteUser((int)$params['id'], $targetId, $shareValue, (int)$user['id']);
            } elseif (!empty($data['email'])) {
                $email = trim($data['email']);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return ['success' => false, 'error' => 'Adresse e-mail invalide.'];
                }
                $pid = AdditionParticipant::inviteGuest((int)$params['id'], $email, trim($data['name'] ?? ''), $shareValue, (int)$user['id']);
            } else {
                return ['success' => false, 'error' => 'Sélectionnez un membre ou renseignez un e-mail.'];
            }

            return ['success' => true, 'id' => $pid, 'addition' => Addition::getById((int)$params['id'])];
        });
    }

    /** Réponse d'un participant connecté (membre, co-parent) à une invitation. */
    public function respond(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () use ($params) {
            $user = Session::user();
            $data = $this->jsonInput();
            $decision = ($data['decision'] ?? '') === 'accept' ? 'accept' : 'decline';
            $ok = AdditionParticipant::respond((int)$params['id'], $decision, (int)$user['id']);
            return ['success' => $ok];
        });
    }

    /** Marque la part d'un participant comme payée/non payée (addition classique, hors
     *  cagnotte) — autorisé pour le participant lui-même (il règle son dû et le déclare) ou pour
     *  la personne qui a créé l'addition (elle a reçu le paiement, hors ligne). */
    public function markParticipantPaid(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () use ($params) {
            $user = Session::user();
            $participant = AdditionParticipant::findById((int)$params['id']);
            if (!$participant) return ['success' => false, 'error' => 'Participant introuvable.'];
            $addition = Addition::getById((int)$participant['addition_id']);
            if (!$addition || $addition['is_cagnotte']) return ['success' => false, 'error' => 'Non autorisé.'];
            $isSelf = (int)($participant['user_id'] ?? 0) === (int)$user['id'];
            $isCreator = (int)($addition['created_by'] ?? 0) === (int)$user['id'];
            if (!$isSelf && !$isCreator) return ['success' => false, 'error' => 'Non autorisé.'];
            $paid = !empty($this->jsonInput()['paid']);
            AdditionParticipant::setPaid((int)$params['id'], $paid);
            return ['success' => true];
        });
    }

    /** Enregistre une perception manuelle (cagnotte) — réservé à un admin de la famille. */
    public function recordPayment(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            if ($user['role'] !== 'admin') {
                return ['success' => false, 'error' => 'Réservé à l\'administrateur de la famille.'];
            }
            $addition = Addition::getById((int)$params['id']);
            if (!$addition || $addition['family_id'] !== $user['family_id'] || !$addition['is_cagnotte']) {
                return ['success' => false, 'error' => 'Non autorisé'];
            }
            $data = $this->jsonInput();
            $amount = (float)($data['amount'] ?? 0);
            $payerName = trim($data['payer_name'] ?? '');
            $method = in_array($data['method'] ?? '', ['cash', 'transfer', 'wero'], true) ? $data['method'] : '';
            if ($amount <= 0 || $payerName === '' || $method === '') {
                return ['success' => false, 'error' => 'Payeur, montant et moyen de paiement requis.'];
            }
            $paidAt = $data['paid_at'] ?? date('Y-m-d');
            $participantId = !empty($data['participant_id']) ? (int)$data['participant_id'] : null;
            AdditionPayment::record((int)$params['id'], $participantId, $payerName, $amount, $method, $data['note'] ?? null, (int)$user['id'], $paidAt);
            return ['success' => true, 'addition' => Addition::getById((int)$params['id'])];
        });
    }

    public function deletePayment(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            if ($user['role'] !== 'admin') {
                return ['success' => false, 'error' => 'Réservé à l\'administrateur de la famille.'];
            }
            $addition = Addition::getById((int)$params['addition_id']);
            if (!$addition || $addition['family_id'] !== $user['family_id']) {
                return ['success' => false, 'error' => 'Non autorisé'];
            }
            AdditionPayment::delete((int)$params['id'], (int)$params['addition_id']);
            return ['success' => true];
        });
    }

    // ── Espace additions (invité externe, sans compte) ──────────────

    public function guestSpace(array $params): void
    {
        $guest = AdditionGuest::findByToken($params['token']);
        if (!$guest) {
            http_response_code(404);
            echo 'Lien invalide ou expiré.';
            return;
        }
        $participations = AdditionParticipant::getForGuest((int)$guest['id']);
        require BASE_PATH . '/templates/additions/guest_space.php';
    }

    public function guestRespond(array $params): void
    {
        $this->json(function () use ($params) {
            $guest = AdditionGuest::findByToken($params['token']);
            if (!$guest) return ['success' => false, 'error' => 'Lien invalide.'];
            $data = $this->jsonInput();
            $decision = ($data['decision'] ?? '') === 'accept' ? 'accept' : 'decline';
            $ok = AdditionParticipant::respond((int)$params['id'], $decision, null, (int)$guest['id']);
            return ['success' => $ok];
        });
    }
}
