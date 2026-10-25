<?php
namespace App\Controllers;

use App\Core\LinkPreview;
use App\Core\Session;
use App\Models\FamilyChild;
use App\Models\FamilyFriend;
use App\Models\User;
use App\Models\WishlistChildLink;
use App\Models\WishlistItem;

class WishlistController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('wishlist');
        $user = Session::user();

        $members = User::getByFamily($user['family_id']);
        $items = array_map(
            fn($i) => WishlistItem::scrubForViewer($i, (int)$user['id']),
            WishlistItem::getByFamily($user['family_id'])
        );

        // Foyers séparés (familles amies) : leurs souhaits sont visibles en lecture seule pour
        // éviter les doublons de cadeaux — jamais modifiables/supprimables par une autre famille,
        // seule la réservation reste une action possible (voir reserve()/unreserve() ci-dessous).
        $friendItems = [];
        foreach (FamilyFriend::getAcceptedFamilyIds((int)$user['family_id']) as $friendFamilyId) {
            foreach (WishlistItem::getByFamily($friendFamilyId) as $i) {
                $friendItems[] = WishlistItem::scrubForViewer($i, (int)$user['id']);
            }
        }

        // Listes des enfants du registre familial (pas de compte) : une carte par enfant, lien
        // public créé à la demande pour que "Copier le lien" fonctionne dès le premier affichage.
        $familyChildren = FamilyChild::getByFamily((int)$user['family_id']);
        $childItems = [];
        $childTokens = [];
        foreach ($familyChildren as $fc) {
            $childId = (int)$fc['id'];
            $childItems[$childId] = array_map(
                fn($i) => WishlistItem::scrubForChildFamilyView($i),
                WishlistItem::getByChild($childId)
            );
            $childTokens[$childId] = WishlistChildLink::ensureForChild((int)$user['family_id'], $childId, (int)$user['id'])['token'];
        }

        require BASE_PATH . '/templates/wishlist/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            // $_POST (+ $_FILES) quand une image est jointe manuellement, JSON sinon — même
            // bascule que BirthListController::createItem().
            $data = $_POST ?: $this->jsonInput();

            $familyChildId = null;
            if (!empty($data['family_child_id'])) {
                $fc = FamilyChild::getById((int)$data['family_child_id']);
                if (!$fc || (int)$fc['family_id'] !== (int)$user['family_id']) {
                    return ['success' => false, 'error' => 'Enfant introuvable.'];
                }
                $familyChildId = (int)$fc['id'];
            }

            $data = $this->applyLinkPreview($data);
            if (empty($data['title'])) {
                return ['success' => false, 'error' => 'Titre requis.'];
            }
            // Une image jointe manuellement prend le pas sur celle récupérée automatiquement du
            // lien — utile quand le site bloque la récupération automatique (Cloudflare etc.).
            if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                [$data['image_path']] = \App\Core\OcrHelper::saveUploadedFile($_FILES['image'], 'wishlist', (int)$user['family_id']);
            }

            $userId = $familyChildId ? null : (int)$user['id'];
            $id = WishlistItem::create((int)$user['family_id'], $userId, $familyChildId, $data);
            $item = WishlistItem::getById($id);
            $item = $familyChildId
                ? WishlistItem::scrubForChildFamilyView($item)
                : WishlistItem::scrubForViewer($item, (int)$user['id']);
            return ['success' => true, 'item' => $item];
        });
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $item = WishlistItem::getById((int)$params['id']);
            if (!$item || !$this->canManage($item, $user)) {
                return ['success' => false, 'error' => 'Souhait introuvable.'];
            }
            $data = $_POST ?: $this->jsonInput();
            if (empty($data['title'])) {
                return ['success' => false, 'error' => 'Titre requis.'];
            }
            if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                [$data['image_path']] = \App\Core\OcrHelper::saveUploadedFile($_FILES['image'], 'wishlist', (int)$user['family_id']);
            }
            WishlistItem::update($item['id'], $data);
            $updated = WishlistItem::getById($item['id']);
            $updated = $item['family_child_id']
                ? WishlistItem::scrubForChildFamilyView($updated)
                : WishlistItem::scrubForViewer($updated, (int)$user['id']);
            return ['success' => true, 'item' => $updated];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $item = WishlistItem::getById((int)$params['id']);
            if (!$item || !$this->canManage($item, $user)) {
                return ['success' => false, 'error' => 'Souhait introuvable.'];
            }
            WishlistItem::delete($item['id']);
            return ['success' => true];
        });
    }

    public function reserve(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $item = WishlistItem::getById((int)$params['id']);
            // On ne peut jamais réserver son propre souhait — et la vérification "déjà réservé"
            // est refaite en SQL (reserved_by IS NULL) pour éviter une course entre deux membres
            // qui cliqueraient "réserver" au même instant sur le même item. Un membre d'une
            // famille amie (foyer séparé) peut réserver comme n'importe quel autre membre, pour
            // éviter que les deux foyers achètent le même cadeau. Les souhaits d'un enfant de la
            // famille se réservent uniquement via son lien public (voir WishlistChildAccessController) :
            // la famille ne doit jamais voir leur statut de réservation, surprise oblige.
            $sameOrFriendFamily = $item && ($item['family_id'] === $user['family_id'] || FamilyFriend::areFriends((int)$user['family_id'], (int)$item['family_id']));
            if (!$item || !$sameOrFriendFamily || $item['family_child_id'] || (int)$item['user_id'] === (int)$user['id']) {
                return ['success' => false, 'error' => 'Action impossible.'];
            }
            WishlistItem::reserve($item['id'], (int)$user['id']);
            return ['success' => true, 'item' => WishlistItem::scrubForViewer(WishlistItem::getById($item['id']), (int)$user['id'])];
        });
    }

    public function unreserve(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $item = WishlistItem::getById((int)$params['id']);
            // Seule la personne qui a réservé peut annuler — pas le propriétaire du souhait
            // (qui n'est pas censé savoir qu'une réservation existait), ni un tiers. Le seul
            // contrôle de famille nécessaire ici est "c'est bien elle qui a réservé" : peu
            // importe que ce soit sa propre famille ou une famille amie.
            if (!$item || (int)($item['reserved_by'] ?? 0) !== (int)$user['id']) {
                return ['success' => false, 'error' => 'Action impossible.'];
            }
            WishlistItem::unreserve($item['id']);
            return ['success' => true];
        });
    }

    public function markPurchased(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $item = WishlistItem::getById((int)$params['id']);
            if (!$item || (int)($item['reserved_by'] ?? 0) !== (int)$user['id']) {
                return ['success' => false, 'error' => 'Action impossible.'];
            }
            $purchased = !empty($this->jsonInput()['purchased']);
            WishlistItem::markPurchased($item['id'], $purchased);
            return ['success' => true];
        });
    }

    public function regenerateChildLink(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $child = FamilyChild::getById((int)$params['id']);
            if (!$child || (int)$child['family_id'] !== (int)$user['family_id']) {
                return ['success' => false, 'error' => 'Enfant introuvable.'];
            }
            WishlistChildLink::regenerateToken((int)$child['id']);
            return ['success' => true, 'token' => WishlistChildLink::getByChild((int)$child['id'])['token']];
        });
    }

    /** Un souhait "adulte" n'est géré que par son propriétaire ; un souhait d'enfant (pas de
     *  compte) est géré collectivement par toute la famille — n'importe quel parent peut y
     *  toucher, à condition que l'item appartienne bien à SA famille. */
    private function canManage(array $item, array $user): bool
    {
        if ((int)$item['family_id'] !== (int)$user['family_id']) return false;
        if ($item['family_child_id']) return true;
        return (int)$item['user_id'] === (int)$user['id'];
    }

    /** Lien produit -> titre/description/prix/image récupérés automatiquement ; toute valeur
     *  déjà saisie à la main par la famille reste prioritaire sur celle extraite de la page. */
    private function applyLinkPreview(array $data): array
    {
        if (empty($data['url'])) return $data;
        $preview = LinkPreview::fetch($data['url']);
        if (!$preview['ok']) return $data;
        if (empty($data['title'])) $data['title'] = $preview['title'];
        if (empty($data['description']) && $preview['description']) $data['description'] = $preview['description'];
        if ((!isset($data['price']) || $data['price'] === '') && $preview['price'] !== null) $data['price'] = $preview['price'];
        if ($preview['image_path']) $data['image_path'] = $preview['image_path'];
        return $data;
    }
}
