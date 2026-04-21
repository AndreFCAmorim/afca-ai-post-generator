<?php
/**
 * AIPG_Gemini
 *
 * Lightweight HTTP client for the Google Gemini REST API.
 * Uses wp_remote_post() so it respects WordPress HTTP proxy settings.
 */

defined( 'ABSPATH' ) || exit;

class AIPG_Gemini {

	const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

	private string $api_key;
	private string $model;

	public function __construct( string $api_key, string $model = 'gemini-2.0-flash' ) {
		$this->api_key = $api_key;
		$this->model   = $model;
	}

	/**
	 * Send a prompt and return the text response.
	 *
	 * @param  string $prompt
	 * @param  array  $generation_config  Optional overrides (temperature, maxOutputTokens, etc.)
	 * @return string|\WP_Error
	 */
	public function generate( string $prompt, array $generation_config = [] ) {
		if ( empty( $this->api_key ) ) {
			return new \WP_Error( 'afca_aipg_no_api_key', __( 'Gemini API key is not configured.', 'afca-ai-post-generator' ) );
		}

		$endpoint = self::API_BASE . $this->model . ':generateContent?key=' . $this->api_key;

		$config_defaults = [
			'temperature'     => 0.8,
			'maxOutputTokens' => 2048,
			'topP'            => 0.95,
		];
		$config          = array_merge( $config_defaults, $generation_config );

		$body = wp_json_encode(
			[
				'contents'         => [
					[
						'role'  => 'user',
						'parts' => [ [ 'text' => $prompt ] ],
					],
				],
				'generationConfig' => $config,
				'safetySettings'   => [
					[
						'category'  => 'HARM_CATEGORY_HARASSMENT',
						'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
					],
					[
						'category'  => 'HARM_CATEGORY_HATE_SPEECH',
						'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
					],
					[
						'category'  => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
						'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
					],
					[
						'category'  => 'HARM_CATEGORY_DANGEROUS_CONTENT',
						'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
					],
				],
			]
		);

		$response = wp_remote_post(
			$endpoint,
			[
				'method'  => 'POST',
				'timeout' => 60,
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => $body,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		// API-level error
		if ( $code !== 200 ) {
			$msg = $data['error']['message'] ?? "HTTP $code";
			return new \WP_Error( 'afca_aipg_api_error', $msg );
		}

		// Extract text from response
		$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
		if ( empty( $text ) ) {
			$finish = $data['candidates'][0]['finishReason'] ?? 'UNKNOWN';
			return new \WP_Error( 'afca_aipg_empty_response', "Empty response. Finish reason: $finish" );
		}

		return $text;
	}

	/**
	 * Test connectivity by asking a simple question.
	 *
	 * @return true|\WP_Error
	 */
	public function test_connection() {
		$result = $this->generate( 'Reply with exactly the text: CONNECTION_OK' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return true;
	}
}
