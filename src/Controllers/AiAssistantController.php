<?php
namespace App\Controllers;

use App\Core\Ollama;
use App\Core\Session;
use App\Models\AiAssistant;
use App\Models\AiAssistantAction;
use App\Models\AiAssistantMessage;
use App\Models\OllamaSettings;

class AiAssistantController extends BaseController
{
    private const SYSTEM_PROMPT_HEADER = <<<'TXT'
Tu es l'assistant familial de FamilyBoard. Réponds en français, de façon brève et chaleureuse.
Tu peux consulter le contexte ci-dessous (calendrier, tâches, liste de courses) pour répondre aux
questions. Quand l'utilisateur te demande d'ajouter une tâche, un article de courses ou un
événement, utilise l'outil correspondant plutôt que de simplement le dire en texte — c'est la
SEULE façon dont une action est réellement effectuée. N'invente jamais qu'une action a été faite :
si tu n'appelles pas l'outil, rien n'est créé.
TXT;

    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('ai_assistant');
        if (!OllamaSettings::get()) {
            Session::flash('error', "L'assistant IA n'est pas encore configuré par l'administrateur système.");
            header('Location: ' . BASE_URL . '/');
            exit;
        }
        $user = Session::user();
        $familyId = (int)$user['family_id'];
        $messages = array_reverse(AiAssistantMessage::getByFamily($familyId, 50));
        $actions = AiAssistantAction::getByFamily($familyId, 20);
        require BASE_PATH . '/templates/ai_assistant/index.php';
    }

    /** Historique récent pour le chargement initial de la bulle (voir toggleAiAssistantPanel()
     *  dans ai_assistant.js) — la bulle étant globale à toutes les pages, son contenu ne peut pas
     *  être pré-rendu côté serveur sans refaire cette requête sur CHAQUE page de l'application. */
    public function getMessages(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('ai_assistant');
        $this->json(function () {
            $user = Session::user();
            $messages = array_reverse(AiAssistantMessage::getByFamily((int)$user['family_id'], 50));
            return ['success' => true, 'messages' => array_map(fn($m) => [
                'role' => $m['role'],
                'content' => $m['content'],
                'user_name' => $m['user_name'],
            ], $messages)];
        });
    }

    public function sendMessage(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('ai_assistant');
        $this->json(function () {
            $user = Session::user();
            $familyId = (int)$user['family_id'];
            $userId = (int)$user['id'];

            $text = trim($this->jsonInput()['message'] ?? '');
            if ($text === '' || mb_strlen($text) > 2000) {
                return ['success' => false, 'error' => 'Message vide ou trop long.'];
            }

            AiAssistantMessage::create($familyId, $userId, 'user', $text);

            $system = self::SYSTEM_PROMPT_HEADER . "\n\n" . AiAssistant::buildContextSummary($familyId);
            $history = AiAssistantMessage::getRecentForPrompt($familyId);
            $messages = array_merge(
                [['role' => 'system', 'content' => $system]],
                array_map(fn($m) => ['role' => $m['role'], 'content' => $m['content']], $history)
            );

            $result = Ollama::chat($messages, AiAssistant::TOOLS);
            if (!$result['ok']) {
                return ['success' => false, 'error' => $result['error']];
            }

            $summaries = [];
            foreach ($result['tool_calls'] as $call) {
                $fn = $call['function']['name'] ?? '';
                $rawArgs = $call['function']['arguments'] ?? [];
                // Ollama renvoie normalement les arguments déjà décodés en objet, mais certains
                // modèles/versions les renvoient sous forme de chaîne JSON — on gère les deux.
                $args = is_array($rawArgs) ? $rawArgs : (json_decode((string)$rawArgs, true) ?: []);
                $outcome = AiAssistant::executeTool($fn, $args, $familyId, $userId);
                $summaries[] = $outcome['summary'];
            }

            // La confirmation d'action est TOUJOURS le texte déterministe généré par
            // executeTool(), jamais le texte libre du modèle — voir AiAssistant::executeTool().
            // S'il n'y a pas eu d'appel d'outil, on affiche la réponse texte du modèle (réponse à
            // une question, pas une action).
            $reply = $summaries ? implode("\n", $summaries) : (trim($result['content']) ?: 'Message reçu.');

            AiAssistantMessage::create($familyId, null, 'assistant', $reply);

            return ['success' => true, 'reply' => $reply];
        });
    }
}
