<?php
/**
 * AIPG_Settings
 *
 * Central store for all plugin options. Each setting is persisted as its
 * own WordPress option (flat key/value), including the dynamic per-provider
 * API key / model fields (afca_aipg_api_key_{slug}, afca_aipg_model_{slug}).
 */

defined( 'ABSPATH' ) || exit;

class AIPG_Settings {

	private static ?self $instance = null;

	const OPT_ACTIVE_PROVIDER = 'afca_aipg_active_provider';
	const OPT_SCHEDULE        = 'afca_aipg_schedule';
	const OPT_POSTS_PER_RUN   = 'afca_aipg_posts_per_run';
	const OPT_AUTHOR_ID       = 'afca_aipg_author_id';
	const OPT_POST_CATEGORY   = 'afca_aipg_post_category';
	const OPT_POST_TAGS       = 'afca_aipg_post_tags';
	const OPT_LANGUAGE        = 'afca_aipg_language';
	const OPT_TOPICS          = 'afca_aipg_topics';
	const OPT_REQUIREMENTS    = 'afca_aipg_requirements';
	const OPT_ENABLED         = 'afca_aipg_enabled';

	const SCHEDULES = [
		'hourly'     => 'Hourly',
		'twicedaily' => 'Twice Daily',
		'daily'      => 'Daily',
		'weekly'     => 'Weekly',
	];

	const PROVIDERS = [
		'groq'       => [
			'label'      => 'Groq',
			'free'       => true,
			'free_note'  => 'Generous free tier, extremely fast inference.',
			'signup_url' => 'https://console.groq.com/keys',
			'models'     => [
				'llama-3.3-70b-versatile' => 'Llama 3.3 70B Versatile',
				'llama-3.1-8b-instant'    => 'Llama 3.1 8B Instant',
			],
		],
		'openrouter' => [
			'label'      => 'OpenRouter',
			'free'       => true,
			'free_note'  => 'Free-tier models available (rate-limited).',
			'signup_url' => 'https://openrouter.ai/keys',
			'models'     => [
				'meta-llama/llama-3.3-70b-instruct:free' => 'Llama 3.3 70B (free)',
			],
		],
		'gemini'     => [
			'label'      => 'Google Gemini',
			'free'       => true,
			'free_note'  => 'Free tier available via Google AI Studio.',
			'signup_url' => 'https://aistudio.google.com/apikey',
			'models'     => [
				'gemini-3.6-flash' => 'Gemini 3.6 Flash',
				'gemini-3.6-pro'   => 'Gemini 3.6 Pro',
			],
		],
		'openai'     => [
			'label'      => 'OpenAI',
			'free'       => false,
			'free_note'  => 'Paid API, pay-as-you-go pricing.',
			'signup_url' => 'https://platform.openai.com/api-keys',
			'models'     => [
				'gpt-4o-mini' => 'GPT-4o mini',
				'gpt-4o'      => 'GPT-4o',
			],
		],
		'mistral'    => [
			'label'      => 'Mistral',
			'free'       => true,
			'free_note'  => 'Free tier available on La Plateforme.',
			'signup_url' => 'https://console.mistral.ai/api-keys',
			'models'     => [
				'mistral-small-latest' => 'Mistral Small',
				'mistral-large-latest' => 'Mistral Large',
			],
		],
		'anthropic'  => [
			'label'      => 'Anthropic Claude',
			'free'       => false,
			'free_note'  => 'Paid API, pay-as-you-go pricing.',
			'signup_url' => 'https://console.anthropic.com/settings/keys',
			'models'     => [
				'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5',
			],
		],
	];

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Seed default option values on plugin activation.
	 * Only sets options that don't already exist, so re-activating
	 * never clobbers a user's saved configuration.
	 */
	public static function set_defaults(): void {
		$defaults = [
			self::OPT_ACTIVE_PROVIDER => 'groq',
			self::OPT_SCHEDULE        => 'daily',
			self::OPT_POSTS_PER_RUN   => 1,
			self::OPT_AUTHOR_ID       => 1,
			self::OPT_POST_CATEGORY   => 0,
			self::OPT_POST_TAGS       => 'ai, tech, automation',
			self::OPT_LANGUAGE        => 'English',
			self::OPT_TOPICS          => implode( "\n", [
				'The future of artificial intelligence in healthcare',
				'Sustainable living tips for urban apartments',
				"Beginner's guide to personal finance",
				'The impact of remote work on city planning',
				'How to start a vegetable garden at home',
			] ),
			self::OPT_REQUIREMENTS   => "Write in an engaging, informative tone suitable for a general audience.\nStructure the article with a compelling introduction, clear subheadings (H2/H3), and a conclusion.\nInclude practical tips or actionable advice where applicable.\nAt the end of the article, include a \"Sources & Further Reading\" section with 3-5 relevant, credible sources.\nThe article should be between 600 and 900 words.",
			self::OPT_ENABLED        => '0',
		];

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key, false ) ) {
				add_option( $key, $value );
			}
		}
	}

	// ── Generic getter/setter ───────────────────────────────────────────────────

	public static function get( string $key, $default = '' ) {
		return get_option( $key, $default );
	}

	public static function update( string $key, $value ): bool {
		return update_option( $key, $value );
	}

	// ── Typed accessors ──────────────────────────────────────────────────────────

	public static function get_active_provider(): string {
		return (string) self::get( self::OPT_ACTIVE_PROVIDER, 'groq' );
	}

	public static function get_api_key_for( string $slug ): string {
		return (string) self::get( "afca_aipg_api_key_{$slug}", '' );
	}

	public static function get_model_for( string $slug ): string {
		$models = self::PROVIDERS[ $slug ]['models'] ?? [];
		$default = ! empty( $models ) ? array_key_first( $models ) : '';
		return (string) self::get( "afca_aipg_model_{$slug}", $default );
	}

	public static function get_schedule(): string {
		return (string) self::get( self::OPT_SCHEDULE, 'daily' );
	}

	public static function is_enabled(): bool {
		return '1' === (string) self::get( self::OPT_ENABLED, '0' );
	}

	public static function get_posts_per_run(): int {
		return max( 1, min( 10, (int) self::get( self::OPT_POSTS_PER_RUN, 1 ) ) );
	}

	public static function get_topics(): array {
		$raw = (string) self::get( self::OPT_TOPICS, '' );
		return array_values( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) );
	}

	public static function get_requirements(): string {
		return (string) self::get( self::OPT_REQUIREMENTS, '' );
	}

	public static function get_language(): string {
		return (string) self::get( self::OPT_LANGUAGE, 'English' );
	}

	public static function get_author_id(): int {
		return (int) self::get( self::OPT_AUTHOR_ID, 1 );
	}

	public static function get_post_category(): int {
		return (int) self::get( self::OPT_POST_CATEGORY, 0 );
	}

	public static function get_post_tags(): array {
		$raw = (string) self::get( self::OPT_POST_TAGS, '' );
		return array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
	}
}
