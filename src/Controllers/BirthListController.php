<?php
namespace App\Controllers;

use App\Core\LinkPreview;
use App\Core\Session;
use App\Models\BirthList;

class BirthListController extends BaseController
{
    /** Vue famille : gestion des articles uniquement, jamais le statut de réservation. */
    public function get(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $list = BirthList::ensureForFamily((int)$user['family_id'], (int)$user['id']);
            $items = array_map([BirthList::class, 'scrubForOwner'], BirthList::getItems((int)$list['id']));
            return [
                'success' => true,
                'token' => $list['token'],
                'items' => $items,
            ];
        });
    }

    public function regenerateLink(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $list = BirthList::ensureForFamily((int)$user['family_id'], (int)$user['id']);
            BirthList::regenerateToken((int)$list['id'], (int)$user['family_id']);
            $list = BirthList::getByFamily((int)$user['family_id']);
            return ['success' => true, 'token' => $list['token']];
        });
    }

    public function createItem(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $list = BirthList::ensureForFamily((int)$user['family_id'], (int)$user['id']);
            $data = $_POST ?: $this->jsonInput();
            $d = $this->validated($data);
            $hadManualTitle = $d && $d['title'] !== '';

            // Une URL produit -> titre + image récupérés automatiquement (og:image en priorité,
            // donc la vraie photo du produit dans l'immense majorité des cas) ; un titre saisi à
            // la main par le parent reste prioritaire sur celui extrait de la page.
            if ($d && $d['url']) {
                $preview = LinkPreview::fetch($d['url']);
                if ($preview['ok']) {
                    if (!$hadManualTitle) $d['title'] = $preview['title'];
                    if ($preview['image_path']) $d['image_path'] = $preview['image_path'];
                }
            }
            if (!$d || $d['title'] === '') return ['success' => false, 'error' => 'Le titre est requis.'];

            $id = BirthList::createItem((int)$list['id'], (int)$user['family_id'], (int)$user['id'], $d, $_FILES['image'] ?? null);
            return ['success' => true, 'id' => $id];
        });
    }

    public function updateItem(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $item = $this->owned((int)$params['id']);
            if (!$item) return ['success' => false, 'error' => 'Article introuvable.'];
            $data = $_POST ?: $this->jsonInput();
            $d = $this->validated($data);
            if (!$d || !$d['title']) return ['success' => false, 'error' => 'Le titre est requis.'];
            $d['remove_image'] = !empty($data['remove_image']);
            BirthList::updateItem((int)$params['id'], (int)$item['birth_list_id'], (int)$user['family_id'], $d, $_FILES['image'] ?? null);
            return ['success' => true];
        });
    }

    public function deleteItem(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $item = $this->owned((int)$params['id']);
            if (!$item) return ['success' => false];
            BirthList::deleteItem((int)$params['id'], (int)$item['birth_list_id']);
            return ['success' => true];
        });
    }

    /** L'article doit appartenir à la liste de la famille de l'utilisateur connecté. */
    private function owned(int $itemId): ?array
    {
        $user = Session::user();
        $item = BirthList::getItemById($itemId);
        if (!$item) return null;
        $list = BirthList::getByFamily((int)$user['family_id']);
        if (!$list || (int)$item['birth_list_id'] !== (int)$list['id']) return null;
        return $item;
    }

    private function validated(array $data): ?array
    {
        $title = trim($data['title'] ?? '');
        $url = trim($data['url'] ?? '');
        if ($url !== '' && !preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
        $price = trim((string)($data['price'] ?? ''));
        return [
            'title' => $title,
            'url' => $url ?: null,
            'price' => $price !== '' && is_numeric(str_replace(',', '.', $price)) ? round((float)str_replace(',', '.', $price), 2) : null,
            'notes' => trim($data['notes'] ?? '') ?: null,
        ];
    }
}
