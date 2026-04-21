<?php
/**
 * AIPG_Settings
 *
 * Central store for all plugin options.
 * Now supports multiple AI providers, each with their own API key and model.
 */

defined( 'ABSPATH' ) || exit;

class AIPG_Settings {

	// Core options
	const OPT_ACTIVE_PROVIDER = 'afca_aipg_active_provider';
	const OPT_TOPICS          = 'afca_aipg_topics';
	const OPT_REQUIREMENTS    = 'afca_aipg_requirements';
	const OPT_SCHEDULE        = 'afca_aipg_schedule';
	const OPT_POSTS_PER_RUN   = 'afca_aipg_posts_per_run';
	const OPT_AUTHOR_ID       = 'afca_aipg_author_id';
	const OPT_POST_CATEGORY   = 'afca_aipg_post_category';
	const OPT_POST_TAGS       = 'afca_aipg_post_tags';
	const OPT_LANGUAGE        = 'afca_aipg_language';
	const OPT_ENABLED         = 'afca_aipg_enabled';

	const PROVIDERS = [
		'groq'       => [
			'label'         => 'Groq',
			'free'          => true,
			'free_note'     => 'Free tier: ~14,400 req/day — no credit card needed',
			'signup_url'    => 'https://console.groq.com',
			'models'        => [
				'llama-3.3-70b-versatile' => 'Llama 3.3 70B (recommended)',
				'llama-3.1-8b-instant'    => 'Llama 3.1 8B (fastest)',
				'mixtral-8x7b-32768'      => 'Mixtral 8x7B',
				'gemma2-9b-it'            => 'Gemma 2 9B',
			],
			'default_model' => 'llama-3.3-70b-versatile',
		],
		'openrouter' => [
			'label'         => 'OpenRouter',
			'free'          => true,
			'free_note'     => 'Free tier available — use models ending in ":free"',
			'signup_url'    => 'https://openrouter.ai',
			'models'        => [
				'meta-llama/llama-3.3-70b-instruct:free'  => 'Llama 3.3 70B (free)',
				'mistralai/mistral-7b-instruct:free'      => 'Mistral 7B (free)',
				'google/gemma-2-9b-it:free'               => 'Gemma 2 9B (free)',
				'microsoft/phi-3-mini-128k-instruct:free' => 'Phi-3 Mini (free)',
				'meta-llama/llama-3.1-70b-instruct'       => 'Llama 3.1 70B (paid)',
				'openai/gpt-4o'                           => 'GPT-4o (paid)',
				'anthropic/claude-sonnet-4-6'             => 'Claude Sonnet 4.6 (paid)',
			],
			'default_model' => 'meta-llama/llama-3.3-70b-instruct:free',
		],
		'gemini'     => [
			'label'         => 'Google Gemini',
			'free'          => false,
			'free_note'     => 'Free tier may not be available in all accounts/regions',
			'signup_url'    => 'https://aistudio.google.com/app/apikey',
			'models'        => [
				'gemini-2.0-flash'      => 'Gemini 2.0 Flash (fast)',
				'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash Lite (cheapest)',
				'gemini-1.5-pro'        => 'Gemini 1.5 Pro (most capable)',
			],
			'default_model' => 'gemini-2.0-flash',
		],
		'openai'     => [
			'label'         => 'OpenAI',
			'free'          => false,
			'free_note'     => 'Paid only — requires billing',
			'signup_url'    => 'https://platform.openai.com/api-keys',
			'models'        => [
				'gpt-4o-mini'   => 'GPT-4o Mini (affordable)',
				'gpt-4o'        => 'GPT-4o (best quality)',
				'gpt-4-turbo'   => 'GPT-4 Turbo',
				'gpt-3.5-turbo' => 'GPT-3.5 Turbo (cheapest)',
			],
			'default_model' => 'gpt-4o-mini',
		],
		'mistral'    => [
			'label'         => 'Mistral AI',
			'free'          => false,
			'free_note'     => 'Trial credits on signup',
			'signup_url'    => 'https://console.mistral.ai',
			'models'        => [
				'mistral-small-latest' => 'Mistral Small (affordable)',
				'mistral-large-latest' => 'Mistral Large (best quality)',
				'open-mistral-7b'      => 'Mistral 7B (cheapest)',
				'open-mixtral-8x7b'    => 'Mixtral 8x7B',
			],
			'default_model' => 'mistral-small-latest',
		],
		'anthropic'  => [
			'label'         => 'Anthropic (Claude)',
			'free'          => false,
			'free_note'     => 'Paid only — requires billing',
			'signup_url'    => 'https://console.anthropic.com',
			'models'        => [
				'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (fast, cheapest)',
				'claude-sonnet-4-6'         => 'Claude Sonnet 4.6 (balanced)',
				'claude-opus-4-6'           => 'Claude Opus 4.6 (most capable)',
			],
			'default_model' => 'claude-haiku-4-5-20251001',
		],
	];

	const SCHEDULES = [
		'hourly'     => 'Every hour',
		'twicedaily' => 'Twice a day',
		'daily'      => 'Once a day',
		'weekly'     => 'Once a week',
	];

	private static ?self $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	private function __construct() {}

	public static function set_defaults(): void {
		$defaults = [
			self::OPT_ACTIVE_PROVIDER => 'groq',
			self::OPT_TOPICS          => implode(
				"\n",
				[
					'The future of artificial intelligence in healthcare',
					'Sustainable living tips for urban apartments',
					"Beginner's guide to personal finance",
					'The impact of remote work on city planning',
					'How to start a vegetable garden at home',
				]
			),
			self::OPT_REQUIREMENTS    => implode(
				"\n",
				[
					'Write in an engaging, informative tone suitable for a general audience.',
					'Structure the article with a compelling introduction, clear subheadings (H2/H3), and a conclusion.',
					'Include practical tips or actionable advice where applicable.',
					'At the end of the article, include a "Sources & Further Reading" section with 3-5 relevant, credible sources (include URLs where possible).',
					'The article should be between 600 and 900 words.',
				]
			),
			self::OPT_SCHEDULE        => 'daily',
			self::OPT_POSTS_PER_RUN   => 1,
			self::OPT_AUTHOR_ID       => get_current_user_id() ?: 1,
			self::OPT_POST_CATEGORY   => 0,
			self::OPT_POST_TAGS       => '',
			self::OPT_LANGUAGE        => 'English',
			self::OPT_ENABLED         => '1',
		];
		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
		foreach ( self::PROVIDERS as $slug => $cfg ) {
			$key = "afca_aipg_model_{$slug}";
			if ( false === get_option( $key ) ) {
				add_option( $key, $cfg['default_model'] );
			}
		}
	}

	public static function get_active_provider(): string {
		$v = (string) get_option( self::OPT_ACTIVE_PROVIDER, 'groq' );
		return array_key_exists( $v, self::PROVIDERS ) ? $v : 'groq';
	}

	public static function get_api_key_for( string $provider ): string {
		return (string) get_option( "afca_aipg_api_key_{$provider}", '' );
	}

	public static function get_model_for( string $provider ): string {
		$saved   = (string) get_option( "afca_aipg_model_{$provider}", '' );
		$default = self::PROVIDERS[ $provider ]['default_model'] ?? '';
		return $saved ?: $default;
	}

	public static function get( string $key, $default = '' ) {
		return get_option( $key, $default );
	}

	public static function get_topics(): array {
		$raw = (string) get_option( self::OPT_TOPICS, '' );
		return array_values( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) );
	}

	public static function get_requirements(): string {
		return (string) get_option( self::OPT_REQUIREMENTS, '' );
	}

	public static function get_schedule(): string {
		$v = (string) get_option( self::OPT_SCHEDULE, 'daily' );
		return array_key_exists( $v, self::SCHEDULES ) ? $v : 'daily';
	}

	public static function get_posts_per_run(): int {
		return max( 1, min( 10, (int) get_option( self::OPT_POSTS_PER_RUN, 1 ) ) );
	}

	public static function get_author_id(): int {
		return (int) get_option( self::OPT_AUTHOR_ID, 1 );
	}

	public static function get_post_category(): int {
		return (int) get_option( self::OPT_POST_CATEGORY, 0 );
	}

	public static function get_post_tags(): array {
		$raw = (string) get_option( self::OPT_POST_TAGS, '' );
		return array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
	}

	public static function get_language(): string {
		return (string) get_option( self::OPT_LANGUAGE, 'English' );
	}

	public static function is_enabled(): bool {
		return '1' === get_option( self::OPT_ENABLED, '1' );
	}

	public static function update( string $key, $value ): void {
		update_option( $key, $value );
	}
}
