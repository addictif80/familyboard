<?php
namespace App\Controllers;

use App\Core\Majority;
use App\Core\Session;
use App\Core\StripeGateway;
use App\Models\MemberStorageSubscription;
use App\Models\FamilyStorageSubscription;
use App\Models\StorageProduct;
use App\Models\StorageRequest;
use App\Models\User;

/**
 * Consultation + achat de stockage additionnel, côté membre ("Mon stockage", perso) et côté
 * admin de famille ("Stockage familial") — voir templates/settings/index.php, onglet Stockage.
 * Même pattern Stripe que SubscriptionController (abonnement familial de base) : le palier
 * n'est jamais activé avant confirmation du paiement par le webhook (voir
 * SubscriptionController::webhook, qui dispatche désormais aussi vers ce domaine).
 *
 * Règle de majorité (voir App\Core\Majority) : un membre majeur confirmé paie directement pour
 * lui-même ; un mineur (ou sans date de naissance connue — traité comme mineur par défaut) ne
 * peut que DEMANDER un palier, qu'un adulte de sa famille approuve en payant lui-même
 * (approveAndPay ci-dessous) — jamais de paiement déclenché par un mineur lui-même.
 */
class MemberStorageController extends BaseController
{
    private const RATE_LIMIT_PER_24H = 5;

    // ── Achat direct (membre majeur, pour lui-même) ─────────────────

    public function checkout(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        if (!Majority::isAdult($user['birthday'] ?? null)) {
            Session::flash('error', "Vous devez être majeur (date de naissance confirmée) pour acheter du stockage directement — demandez à un adulte de votre famille d'approuver un palier pour vous.");
            $this->redirect('/settings#tab-stockage');
        }

        $this->startStripeCheckout(
            (int)($_POST['product_id'] ?? 0),
            'storage_member',
            (int)$user['id'],
            (int)$user['id'],
            null
        );
    }

    /** Demande d'un palier par un mineur (ou sans date de naissance confirmée) — en attente
     *  d'approbation + paiement par un adulte de sa famille. */
    public function requestStorage(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();

        $productId = (int)($_POST['product_id'] ?? 0);
        $product = StorageProduct::findById($productId);
        if (!$product || $product['scope'] !== 'member' || !$product['active']) {
            Session::flash('error', 'Palier invalide.');
            $this->redirect('/settings#tab-stockage');
        }

        if (StorageRequest::countRecentForBeneficiary((int)$user['id'], 24) >= self::RATE_LIMIT_PER_24H) {
            Session::flash('error', 'Trop de demandes récentes — réessayez plus tard.');
            $this->redirect('/settings#tab-stockage');
        }

        StorageRequest::create((int)$user['id'], $productId);
        Session::flash('success', 'Demande envoyée — un adulte de votre famille doit l\'approuver et la régler.');
        $this->redirect('/settings#tab-stockage');
    }

    /** Un adulte approuve la demande d'un mineur de sa famille ET devient le payeur dans la
     *  même action (lancement direct du Checkout Stripe) — la demande n'est marquée "approuvée"
     *  qu'à la confirmation du paiement par le webhook, jamais avant. */
    public function approveAndPay(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        if (!Majority::isAdult($user['birthday'] ?? null)) {
            Session::flash('error', 'Seul un adulte peut approuver et régler une demande de stockage.');
            $this->redirect('/settings#tab-stockage');
        }

        $requestId = (int)$params['id'];
        $request = StorageRequest::findById($requestId);
        if (!$request || $request['status'] !== 'pending') {
            Session::flash('error', 'Demande introuvable ou déjà traitée.');
            $this->redirect('/settings#tab-stockage');
        }
        $beneficiary = User::findById((int)$request['beneficiary_id']);
        if (!$beneficiary || (int)$beneficiary['family_id'] !== (int)$user['family_id']) {
            Session::flash('error', 'Demande introuvable.');
            $this->redirect('/settings#tab-stockage');
        }

        $this->startStripeCheckout(
            (int)$request['storage_product_id'],
            'storage_member',
            (int)$beneficiary['id'],
            (int)$user['id'],
            $requestId
        );
    }

    public function rejectRequest(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        if (!Majority::isAdult($user['birthday'] ?? null)) {
            Session::flash('error', 'Seul un adulte peut refuser une demande de stockage.');
            $this->redirect('/settings#tab-stockage');
        }

        $requestId = (int)$params['id'];
        $request = StorageRequest::findById($requestId);
        if ($request && $request['status'] === 'pending') {
            $beneficiary = User::findById((int)$request['beneficiary_id']);
            if ($beneficiary && (int)$beneficiary['family_id'] === (int)$user['family_id']) {
                StorageRequest::markRejected($requestId, (int)$user['id']);
            }
        }
        $this->redirect('/settings#tab-stockage');
    }

    // ── Achat familial (admin de famille) ────────────────────────────

    public function familyCheckout(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        if ($user['role'] !== 'admin') {
            Session::flash('error', "Seul l'administrateur de la famille peut gérer le stockage familial.");
            $this->redirect('/settings#tab-stockage');
        }

        $productId = (int)($_POST['product_id'] ?? 0);
        $product = StorageProduct::findById($productId);
        if (!$product || $product['scope'] !== 'family' || !$product['active']) {
            Session::flash('error', 'Palier invalide.');
            $this->redirect('/settings#tab-stockage');
        }

        $this->startStripeCheckout($productId, 'storage_family', (int)$user['family_id'], (int)$user['id'], null);
    }

    // ── Portail Stripe (gérer/annuler un abonnement de stockage déjà actif) ──

    public function portal(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $fresh = User::findById((int)$user['id']);
        $stripe = StripeGateway::client();
        if (!$stripe || !$fresh['stripe_customer_id']) {
            Session::flash('error', 'Aucun abonnement de stockage Stripe actif.');
            $this->redirect('/settings#tab-stockage');
        }

        try {
            $portalSession = $stripe->billingPortal->sessions->create([
                'customer' => $fresh['stripe_customer_id'],
                'return_url' => BASE_URL . '/settings#tab-stockage',
            ]);
        } catch (\Throwable $e) {
            Session::flash('error', "Impossible de contacter Stripe pour le moment.");
            $this->redirect('/settings#tab-stockage');
        }

        header('Location: ' . $portalSession->url);
        exit;
    }

    // ── Commun ────────────────────────────────────────────────────

    /**
     * $kind : 'storage_member' (bénéficiaire = $beneficiaryOrFamilyId, un users.id) ou
     * 'storage_family' (bénéficiaire = $beneficiaryOrFamilyId, un families.id). $requestId
     * (storage_member uniquement) : identifiant de la demande du mineur à marquer approuvée
     * dès la confirmation du paiement par le webhook — voir SubscriptionController::webhook().
     */
    private function startStripeCheckout(int $productId, string $kind, int $beneficiaryOrFamilyId, int $payerId, ?int $requestId): void
    {
        $product = StorageProduct::findById($productId);
        if (!$product || !$product['active'] || !$product['stripe_price_id_monthly']) {
            Session::flash('error', "Ce palier n'est pas encore relié à Stripe (configuration admin incomplète).");
            $this->redirect('/settings#tab-stockage');
        }

        $stripe = StripeGateway::client();
        if (!$stripe) {
            Session::flash('error', 'La facturation n\'est pas encore configurée.');
            $this->redirect('/settings#tab-stockage');
        }

        $payer = User::findById($payerId);
        $customerId = $payer['stripe_customer_id'] ?? null;
        if (!$customerId) {
            $customer = $stripe->customers->create([
                'email' => $payer['email'],
                'name'  => $payer['name'],
                'metadata' => ['user_id' => $payerId],
            ]);
            $customerId = $customer->id;
            User::setStripeCustomerId($payerId, $customerId);
        }

        $metadata = [
            'kind'               => $kind,
            'storage_product_id' => (string)$productId,
        ];
        if ($kind === 'storage_member') {
            $metadata['beneficiary_id'] = (string)$beneficiaryOrFamilyId;
            if ($requestId) $metadata['request_id'] = (string)$requestId;
        } else {
            $metadata['family_id'] = (string)$beneficiaryOrFamilyId;
        }

        try {
            $session = $stripe->checkout->sessions->create([
                'mode' => 'subscription',
                'customer' => $customerId,
                'line_items' => [['price' => $product['stripe_price_id_monthly'], 'quantity' => 1]],
                'subscription_data' => ['metadata' => $metadata],
                'success_url' => BASE_URL . '/settings?storage_success=1#tab-stockage',
                'cancel_url' => BASE_URL . '/settings?storage_canceled=1#tab-stockage',
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            Session::flash('error', "Impossible de contacter Stripe pour le moment.");
            $this->redirect('/settings#tab-stockage');
        }

        header('Location: ' . $session->url);
        exit;
    }

    private function redirect(string $path): void
    {
        header('Location: ' . BASE_URL . $path);
        exit;
    }
}
