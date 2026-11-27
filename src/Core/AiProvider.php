<?php
namespace App\Core;

use App\Models\AppSetting;
use App\Models\OllamaSettings;
use App\Models\OneMinAiSettings;

/**
 * Point d'entrée unique pour l'assistant IA, quel que soit le fournisseur choisi par
 * l'administrateur système (AppSetting 'ai_provider') : 'ollama' (serveur auto-hébergé, défaut —
 * aucune donnée ne quitte l'infrastructure) ou '1min' (service externe 1min.ai, compatible
 * OpenAI). Tout appelant (AiAssistantController, …) passe par ici plutôt que par Ollama/OneMinAi
 * directement, pour ne jamais avoir à connaître le fournisseur actif.
 */
class AiProvider
{
    public const PROVIDERS = ['ollama', '1min'];

    public static function get(): string
    {
        $provider = AppSetting::get('ai_provider', 'ollama');
        return in_array($provider, self::PROVIDERS, true) ? $provider : 'ollama';
    }

    public static function set(string $provider): void
    {
        if (in_array($provider, self::PROVIDERS, true)) {
            AppSetting::set('ai_provider', $provider);
        }
    }

    public static function isConfigured(): bool
    {
        return self::get() === '1min' ? OneMinAiSettings::get() !== null : OllamaSettings::get() !== null;
    }

    /** @see Ollama::chat() / OneMinAi::chat() pour la forme du retour — identique dans les deux cas. */
    public static function chat(array $messages, array $tools = []): array
    {
        return self::get() === '1min' ? OneMinAi::chat($messages, $tools) : Ollama::chat($messages, $tools);
    }

    public static function testConnection(): array
    {
        return self::get() === '1min' ? OneMinAi::testConnection() : Ollama::testConnection();
    }
}
