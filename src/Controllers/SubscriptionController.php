<?php
namespace App\Controllers;

use App\Core\Session;
use App\Core\StripeGateway;
use App\Models\AppSetting;
use App\Models\Family;
use App\Models\FamilySubscription;
use App\Models\Plan;

class SubscriptionController extends BaseController
{
    /** L'ancienne page dédiée /abonnement redirige désormais vers l'onglet "Abonnement" des
     *  réglages famille (voir templates/settings/index.php) — conservée comme point d'entrée
     *  stable puisque BaseController::requireModule() y redirige encore en cas de module
     *  premium bloqué, en faisant suivre le paramètre upsell. */
    public function index(array $params): void
    {
        $this->requireAuth();
        $upsell = trim($_GET['upsell'] ?? '');
        $target = BASE_URL . '/settings' . ($upsell !== '' ? '?upsell=' . urlencode($upsell) : '') . '#tab-abonnement';
        header('Location: ' . $target);
        exit;
    }

    public function checkout(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        if ($user['role'] !== 'admin') {
            Session::flash('error', "Seul l'administrateur de la famille peut gérer l'abonnement.");
            header('Location: ' . BASE_URL . '/abonnement');
            exit;
        }

        $stripe = StripeGateway::client();
        if (!$stripe) {
            Session::flash('error', 'La facturation n\'est pas encore configurée.');
            header('Location: ' . BASE_URL . '/abonnement');
            exit;
        }

        $planId   = (int)($_POST['plan_id'] ?? 0);
        $interval = ($_POST['interval'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';
        $plan = Plan::getById($planId);
        if (!$plan || !$plan['active']) {
            Session::flash('error', 'Palier invalide.');
            header('Location: ' . BASE_URL . '/abonnement');
            exit;
        }
        $priceId = $interval === 'yearly' ? $plan['stripe_price_id_yearly'] : $plan['stripe_price_id_monthly'];
        if (!$priceId) {
            Session::flash('error', "Ce palier n'est pas encore relié à Stripe (configuration admin incomplète).");
            header('Location: ' . BASE_URL . '/abonnement');
            exit;
        }

        $familyId = (int)$user['family_id'];
        $family = Family::findById($familyId);
        $sub = FamilySubscription::getByFamily($familyId);
        $customerId = $sub['stripe_customer_id'] ?? null;
        if (!$customerId) {
            $customer = $stripe->customers->create([
                'email' => $user['email'],
                'name'  => $family['name'] ?? $user['name'],
                'metadata' => ['family_id' => $familyId],
            ]);
            $customerId = $customer->id;
            FamilySubscription::setStripeCustomer($familyId, $customerId);
        }

        $subscriptionData = [];
        if (!FamilySubscription::hasUsedTrial($familyId)) {
            $trialDays = (int)(AppSetting::get('sub_trial_days') ?? '14');
            if ($trialDays > 0) $subscriptionData['trial_period_days'] = $trialDays;
        }

        try {
            $session = $stripe->checkout->sessions->create([
                'mode' => 'subscription',
                'customer' => $customerId,
                'line_items' => [['price' => $priceId, 'quantity' => 1]],
                'subscription_data' => $subscriptionData,
                'success_url' => BASE_URL . '/abonnement?success=1',
                'cancel_url' => BASE_URL . '/abonnement?canceled=1',
                'metadata' => ['family_id' => $familyId, 'plan_id' => $planId],
            ]);
        } catch (\Throwable $e) {
            Session::flash('error', "Impossible de contacter Stripe pour le moment.");
            header('Location: ' . BASE_URL . '/abonnement');
            exit;
        }

        header('Location: ' . $session->url);
        exit;
    }

    public function portal(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        if ($user['role'] !== 'admin') {
            Session::flash('error', "Seul l'administrateur de la famille peut gérer l'abonnement.");
            header('Location: ' . BASE_URL . '/abonnement');
            exit;
        }

        $stripe = StripeGateway::client();
        $sub = FamilySubscription::getByFamily((int)$user['family_id']);
        if (!$stripe || !$sub || !$sub['stripe_customer_id']) {
            Session::flash('error', 'Aucun abonnement Stripe actif.');
            header('Location: ' . BASE_URL . '/abonnement');
            exit;
        }

        try {
            $portalSession = $stripe->billingPortal->sessions->create([
                'customer' => $sub['stripe_customer_id'],
                'return_url' => BASE_URL . '/abonnement',
            ]);
        } catch (\Throwable $e) {
            Session::flash('error', "Impossible de contacter Stripe pour le moment.");
            header('Location: ' . BASE_URL . '/abonnement');
            exit;
        }

        header('Location: ' . $portalSession->url);
        exit;
    }

    // ── Webhook Stripe (public, signature vérifiée) ─────────────────

    public function webhook(array $params): void
    {
        $payload = @file_get_contents('php://input') ?: '';
        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $secret = StripeGateway::webhookSecret();

        if (!$secret) { http_response_code(503); exit; }

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\Throwable $e) {
            http_response_code(400);
            exit;
        }

        try {
            $obj = $event->data->object;
            switch ($event->type) {
                case 'checkout.session.completed':
                    if (!empty($obj->subscription)) {
                        $stripe = StripeGateway::client();
                        if ($stripe) $this->syncSubscriptionObject($stripe->subscriptions->retrieve($obj->subscription));
                    }
                    break;
                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                case 'customer.subscription.deleted':
                    $this->syncSubscriptionObject($obj);
                    break;
                default:
                    break;
            }
        } catch (\Throwable $e) {
            error_log('Stripe webhook handling error: ' . $e->getMessage());
        }

        http_response_code(200);
        echo 'ok';
    }

    private function syncSubscriptionObject($stripeSub): void
    {
        $statusMap = [
            'trialing' => 'trialing',
            'active'   => 'active',
            'past_due' => 'past_due',
            'unpaid'   => 'past_due',
            'canceled' => 'canceled',
            'incomplete_expired' => 'canceled',
            'incomplete' => 'none',
        ];
        $status = $statusMap[$stripeSub->status] ?? 'none';

        $priceId = $stripeSub->items->data[0]->price->id ?? null;
        $plan = $priceId ? Plan::getByStripePriceId($priceId) : null;
        $interval = null;
        if ($plan) {
            $interval = $priceId === $plan['stripe_price_id_yearly'] ? 'yearly' : 'monthly';
        }

        $trialEnd = $stripeSub->trial_end ? gmdate('Y-m-d H:i:s', $stripeSub->trial_end) : null;
        $periodEnd = $stripeSub->current_period_end ? gmdate('Y-m-d H:i:s', $stripeSub->current_period_end) : null;

        FamilySubscription::syncFromStripe(
            $stripeSub->customer,
            $stripeSub->id,
            $status,
            $plan['id'] ?? null,
            $interval,
            $trialEnd,
            $periodEnd
        );
    }
}
