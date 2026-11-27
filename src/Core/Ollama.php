<?php
namespace App\Core;

use App\Models\OllamaSettings;

/**
 * Client minimal pour l'API /api/chat d'une instance Ollama auto-hébergée
 * (https://github.com/ollama/ollama), avec support de l'appel d'outils ("tools" — format
 * compatible OpenAI function-calling). N'envoie jamais rien à un service tiers : toute la
 * requête part vers l'URL configurée par l'administrateur système, qui héberge son propre
 * serveur Ollama — c'est tout l'intérêt (aucune donnée famille ne quitte l'infrastructure).
 */
class Ollama
{
    private const TIMEOUT = 45; // un modèle local peut être lent, surtout sans GPU dédié

    /**
     * @param array $messages [['role'=>'system'|'user'|'assistant', 'content'=>string], ...]
     * @param array $tools Définitions au format function-calling (voir AiAssistant::TOOLS)
     * @return array{ok:bool, content?:string, tool_calls?:array, error?:string}
     */
    public static function chat(array $messages, array $tools = []): array
    {
        $settings = OllamaSettings::get();
        if (!$settings) {
            return ['ok' => false, 'error' => "Assistant IA non configuré (URL/modèle manquants)."];
        }

        $payload = [
            'model' => $settings['model'],
            'messages' => $messages,
            'stream' => false,
        ];
        if ($tools) $payload['tools'] = $tools;

        $ch = curl_init($settings['url'] . '/api/chat');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_USERAGENT      => 'FamilyBoard/1.0',
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return ['ok' => false, 'error' => "Impossible de joindre le serveur Ollama : $err"];
        }
        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'error' => "Le serveur Ollama a répondu une erreur (HTTP $status)."];
        }

        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['message'])) {
            return ['ok' => false, 'error' => "Réponse inattendue du serveur Ollama."];
        }

        return [
            'ok' => true,
            'content' => $data['message']['content'] ?? '',
            'tool_calls' => $data['message']['tool_calls'] ?? [],
        ];
    }

    /** Vérifie que le serveur répond, sans forcément que le modèle configuré soit déjà tiré
     *  (/api/tags liste les modèles présents localement — un modèle absent doit être signalé
     *  distinctement d'un serveur injoignable). */
    public static function testConnection(): array
    {
        $settings = OllamaSettings::get();
        if (!$settings) {
            return ['ok' => false, 'error' => 'URL et modèle requis.'];
        }

        $ch = curl_init($settings['url'] . '/api/tags');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => 'FamilyBoard/1.0',
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $status < 200 || $status >= 300) {
            return ['ok' => false, 'error' => 'Connexion au serveur Ollama refusée — vérifiez l\'URL.'];
        }

        $data = json_decode($body, true);
        $models = array_column($data['models'] ?? [], 'name');
        // Ollama laisse ":latest" implicite dans le nom du modèle demandé mais pas toujours dans
        // celui listé par /api/tags (ou l'inverse) — comparaison tolérante au préfixe.
        $found = false;
        foreach ($models as $m) {
            if ($m === $settings['model'] || str_starts_with($m, $settings['model'] . ':')) { $found = true; break; }
        }
        if (!$found) {
            return ['ok' => false, 'error' => "Serveur joignable, mais le modèle « {$settings['model']} » n'est pas installé dessus (essayez : ollama pull {$settings['model']})."];
        }
        return ['ok' => true, 'error' => ''];
    }
}
