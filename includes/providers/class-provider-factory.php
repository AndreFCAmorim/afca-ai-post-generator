<?php
/**
 * AIPG_Provider_Factory
 *
 * Creates the correct provider instance based on the configured provider slug.
 * All providers extend AIPG_Provider_Base so callers only need to call generate().
 */

defined( 'ABSPATH' ) || exit;

class AIPG_Provider_Factory {

    /**
     * Build and return the active provider instance.
     *
     * @param  string|null $provider_slug  Override; uses saved setting if null
     * @return AIPG_Provider_Base
     */
    public static function make( ?string $provider_slug = null ): AIPG_Provider_Base {
        $slug  = $provider_slug ?? AIPG_Settings::get_active_provider();
        $model = AIPG_Settings::get_model_for( $slug );
        $key   = AIPG_Settings::get_api_key_for( $slug );

        switch ( $slug ) {

            case 'gemini':
                return new AIPG_Provider_Gemini( $key, $model );

            case 'openai':
                return new AIPG_Provider_OpenAI_Compat(
                    $key, $model,
                    'https://api.openai.com/v1',
                    'OpenAI'
                );

            case 'groq':
                return new AIPG_Provider_OpenAI_Compat(
                    $key, $model,
                    'https://api.groq.com/openai/v1',
                    'Groq'
                );

            case 'openrouter':
                return new AIPG_Provider_OpenAI_Compat(
                    $key, $model,
                    'https://openrouter.ai/api/v1',
                    'OpenRouter'
                );

            case 'mistral':
                return new AIPG_Provider_OpenAI_Compat(
                    $key, $model,
                    'https://api.mistral.ai/v1',
                    'Mistral'
                );

            case 'anthropic':
                return new AIPG_Provider_Anthropic( $key, $model );

            default:
                // Fallback to Groq (most likely to be free)
                return new AIPG_Provider_OpenAI_Compat(
                    AIPG_Settings::get_api_key_for( 'groq' ),
                    'llama-3.3-70b-versatile',
                    'https://api.groq.com/openai/v1',
                    'Groq'
                );
        }
    }
}
