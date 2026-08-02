<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Poll;

class PollController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('polls');
        $user = Session::user();
        $polls = Poll::getByFamily($user['family_id'], (int)$user['id']);
        require BASE_PATH . '/templates/polls/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $question = trim($data['question'] ?? '');
            $options = array_values(array_filter(array_map('trim', (array)($data['options'] ?? []))));
            if ($question === '' || count($options) < 2) {
                return ['success' => false, 'error' => 'Une question et au moins deux options sont requises.'];
            }
            if (count($options) > 10) {
                return ['success' => false, 'error' => '10 options maximum.'];
            }
            $id = Poll::create($user['family_id'], (int)$user['id'], $question, $options);
            return ['success' => true, 'poll_id' => $id];
        });
    }

    public function vote(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $poll = Poll::getById((int)$params['id']);
            if (!$poll || $poll['family_id'] !== $user['family_id']) {
                return ['success' => false, 'error' => 'Sondage introuvable.'];
            }
            if ($poll['is_closed']) {
                return ['success' => false, 'error' => 'Ce sondage est clos.'];
            }
            $optionId = (int)($this->jsonInput()['option_id'] ?? 0);
            if (!Poll::optionBelongsToPoll($optionId, $poll['id'])) {
                return ['success' => false, 'error' => 'Option invalide.'];
            }
            Poll::vote($poll['id'], $optionId, (int)$user['id']);
            return ['success' => true];
        });
    }

    public function close(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $poll = Poll::getById((int)$params['id']);
            // L'auteur du sondage (ou un admin de famille) peut le clore.
            if (!$poll || $poll['family_id'] !== $user['family_id']) {
                return ['success' => false, 'error' => 'Sondage introuvable.'];
            }
            if ((int)$poll['user_id'] !== (int)$user['id'] && $user['role'] !== 'admin') {
                return ['success' => false, 'error' => 'Action réservée à l\'auteur ou à un administrateur.'];
            }
            Poll::close($poll['id'], empty($poll['is_closed']));
            return ['success' => true];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $poll = Poll::getById((int)$params['id']);
            if (!$poll || $poll['family_id'] !== $user['family_id']) {
                return ['success' => false, 'error' => 'Sondage introuvable.'];
            }
            if ((int)$poll['user_id'] !== (int)$user['id'] && $user['role'] !== 'admin') {
                return ['success' => false, 'error' => 'Action réservée à l\'auteur ou à un administrateur.'];
            }
            Poll::delete($poll['id']);
            return ['success' => true];
        });
    }
}
