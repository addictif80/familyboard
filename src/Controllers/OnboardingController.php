<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Models\Family;
use App\Models\FamilyChild;
use App\Models\FamilySubscription;
use App\Models\User;

/** Configurateur affiché après l'inscription d'une nouvelle famille (fondateur uniquement,
 *  jamais pour un membre qui rejoint une famille existante via code/invitation — voir
 *  AuthController::register()) : enfants, invitation des membres, scolarité, activité pro,
 *  budget. N'orchestre aucune logique propre — chaque étape appelle les endpoints déjà
 *  existants de chaque module (App\Models\FamilyChild, Invitation, SchoolStudent,
 *  EmploymentProfile, Budget), pour rester cohérent avec ce qu'on peut faire depuis les
 *  modules eux-mêmes. Entièrement passable à tout moment (voir complete()), et peut être
 *  relancé depuis les réglages (onglet Famille).
 */
class OnboardingController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAdmin();
        $user = Session::user();
        $familyId = (int)$user['family_id'];

        $family = Family::findById($familyId);
        $familyChildren = FamilyChild::getByFamily($familyId);
        $members = User::getByFamily($familyId);

        $schoolEntitled = FamilySubscription::isEntitled($familyId, 'school');
        $employmentEntitled = FamilySubscription::isEntitled($familyId, 'employment');
        $budgetEntitled = FamilySubscription::isEntitled($familyId, 'budget');

        require BASE_PATH . '/templates/onboarding/index.php';
    }

    public function complete(array $params): void
    {
        $this->requireAdmin();
        $this->json(function () {
            $user = Session::user();
            Database::execute('UPDATE families SET onboarding_completed_at = NOW() WHERE id = ?', [(int)$user['family_id']]);
            return ['success' => true];
        });
    }
}
