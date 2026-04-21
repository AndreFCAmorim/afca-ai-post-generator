<?php
/**
 * AIPG_Provider_OpenAI_Compat
 *
 * Handles any provider that speaks the OpenAI Chat Completions API:
 *   - OpenAI       (https://api.openai.com/v1)
 *   - Groq         (https://api.groq.com/openai/v1)
 *   - OpenRouter   (https://openrouter.ai/api/v1)
 *   - Mistral      (https://api.mistral.ai/v1)
 *
 * All share the same /chat/completions endpoint shape.
 */

defined( 'ABSPATH' ) || exit;

class AIPG_Provider_OpenAI_Compat extends AIPG_Provider_Base {

    private string $base_url;
    private string $provider_name;

    /**
     * @param string $api_key
     * @param string $model
     * @param string $base_url       Full base URL, no trailing slash
     * @param string $provider_name  Human-readable name for error messages
     */
    public function __construct( string $api_key, string $model, string $base_url, string $provider_name = 'API' ) {
        parent::__construct( $api_key, $model );
        $this->base_url      = rtrim( $base_url, '/' );
        $this->provider_name = $provider_name;
    }

    public function generate( string $prompt ) {
        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
        ];

        // OpenRouter requires these extra headers
        if ( str_contains( $this->base_url, 'openrouter.ai' ) ) {
            $headers['HTTP-Referer'] = home_url();
            $headers['X-Title']      = get_bloginfo( 'name' );
        }

        $body = [
            'model'       => $this->model,
            'messages'    => [
                [ 'role' => 'user', 'content' => $prompt ],
            ],
            'temperature' => 0.8,
            'max_tokens'  => 2048,
        ];

        $data = $this->http_post(
            $this->base_url . '/chat/completions',
            $headers,
            $body
        );

        if ( is_wp_error( $data ) ) {
            return $data;
        }

        $text = $data['choices'][0]['message']['content'] ?? '';
        if ( empty( $text ) ) {
            $reason = $data['choices'][0]['finish_reason'] ?? 'unknown';
            return new \WP_Error(
                'afca_aipg_empty_response',
                sprintf( '%s returned an empty response. Finish reason: %s', $this->provider_name, $reason )
            );
        }

        return $text;
    }
}
