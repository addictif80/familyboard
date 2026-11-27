<?php
namespace App\Core;

use App\Models\OneMinAiSettings;

/**
 * Client minimal pour l'API compatible OpenAI de 1min.ai (https://docs.1min.ai/docs/api/intro),
 * alternative hébergée à App\Core\Ollama pour les familles sans serveur Ollama auto-hébergé.
 * Renvoie exactement la même forme que Ollama::chat() ({ok, content, tool_calls, error}) pour que
 * les deux fournisseurs soient interchangeables côté appelant — voir App\Core\AiProvider.
 *
 * Contrairement à Ollama (infrastructure de l'administrateur), ceci envoie les messages à un
 * service tiers : n'est utilisé que si l'administrateur système a explicitement choisi ce
 * fournisseur dans /admin (AppSetting 'ai_provider').
 */
class OneMinAi
{
    public const API_BASE = 'https://api.1min.ai';
    private const TIMEOUT = 60;

    /**
     * @param array $messages [['role'=>'system'|'user'|'assistant', 'content'=>string], ...]
     * @param array $tools Définitions au format function-calling (voir AiAssistant::TOOLS) — déjà
     *               au format {type:'function', function:{name, description, parameters}}
     *               compatible OpenAI tel quel (identique à ce qu'Ollama::chat() reçoit).
     * @return array{ok:bool, content?:string, tool_calls?:array, error?:string}
     */
    public static function chat(array $messages, array $tools = []): array
    {
        $settings = OneMinAiSettings::get();
        if (!$settings) {
            return ['ok' => false, 'error' => "Assistant IA non configuré (clé API 1min.ai manquante)."];
        }

        $payload = [
            'model' => $settings['model'],
            'messages' => $messages,
            'stream' => false,
        ];
        if ($tools) $payload['tools'] = $tools;

        [$body, $status, $err] = self::post('/openai/v1/chat/completions', $settings['api_key'], $payload);

        if ($body === false) {
            return ['ok' => false, 'error' => "Impossible de joindre 1min.ai : $err"];
        }
        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'error' => "1min.ai a répondu une erreur (HTTP $status)" . self::shortDetail($body)];
        }

        $data = json_decode($body, true);
        $message = $data['choices'][0]['message'] ?? null;
        if (!is_array($message)) {
            return ['ok' => false, 'error' => "Réponse inattendue de 1min.ai."];
        }

        return [
            'ok' => true,
            'content' => $message['content'] ?? '',
            // tool_calls[].function.arguments est une chaîne JSON côté OpenAI/1min.ai — déjà géré
            // par l'appelant (AiAssistantController::sendMessage()), qui gère les deux formats.
            'tool_calls' => $message['tool_calls'] ?? [],
        ];
    }

    /** Appel réel minimal (1 jeton) plutôt qu'un endpoint "whoami" qui n'existe pas sur 1min.ai —
     *  réussir ici garantit que la fonctionnalité marchera vraiment, pas juste que la clé est valide. */
    public static function testConnection(): array
    {
        $settings = OneMinAiSettings::get();
        if (!$settings) {
            return ['ok' => false, 'error' => 'Clé API requise.'];
        }

        [$body, $status, $err] = self::post('/openai/v1/chat/completions', $settings['api_key'], [
            'model' => $settings['model'],
            'stream' => false,
            'max_tokens' => 1,
            'messages' => [['role' => 'user', 'content' => 'ping']],
        ], 20);

        if ($body === false) {
            return ['ok' => false, 'error' => "Connexion impossible : $err"];
        }
        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'error' => "Le serveur a répondu une erreur (HTTP $status)" . self::shortDetail($body)];
        }
        return ['ok' => true, 'error' => ''];
    }

    /** @return array{0: string|false, 1: int, 2: string} [corps, code HTTP, erreur curl] */
    private static function post(string $path, string $apiKey, array $payload, int $timeout = self::TIMEOUT): array
    {
        $ch = curl_init(self::API_BASE . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
            CURLOPT_USERAGENT      => 'FamilyBoard/1.0',
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return [$body, $status, $err];
    }

    private static function shortDetail($body): string
    {
        $text = mb_strimwidth(trim(strip_tags((string)$body)), 0, 200, '…');
        return $text !== '' ? " : $text" : '.';
    }
}
