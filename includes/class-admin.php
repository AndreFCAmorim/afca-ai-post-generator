<?php
/**
 * AIPG_Admin
 *
 * Registers the settings pages, handles form saves, and provides
 * AJAX endpoints for "Generate now" and "Test API" actions.
 */

defined( 'ABSPATH' ) || exit;

class AIPG_Admin {

	private static ?self $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_init', [ $this, 'handle_settings_save' ] );
		add_action( 'wp_ajax_aipg_generate_now', [ $this, 'ajax_generate_now' ] );
		add_action( 'wp_ajax_aipg_test_api', [ $this, 'ajax_test_api' ] );
		add_action( 'admin_notices', [ $this, 'pending_posts_notice' ] );
	}

	// ── Menu ──────────────────────────────────────────────────────────────────

	public function register_menu(): void {
		add_menu_page(
			__( 'AI Post Generator', 'ai-post-generator' ),
			__( 'AI Post Generator', 'ai-post-generator' ),
			'manage_options',
			'ai-post-generator',
			[ $this, 'render_settings_page' ],
			'dashicons-superhero',
			76
		);
		add_submenu_page(
			'ai-post-generator',
			__( 'Settings', 'ai-post-generator' ),
			__( 'Settings', 'ai-post-generator' ),
			'manage_options',
			'ai-post-generator',
			[ $this, 'render_settings_page' ]
		);
		add_submenu_page(
			'ai-post-generator',
			__( 'Generation Log', 'ai-post-generator' ),
			__( 'Generation Log', 'ai-post-generator' ),
			'manage_options',
			'ai-post-generator-log',
			[ $this, 'render_log_page' ]
		);
	}

	// ── Assets ────────────────────────────────────────────────────────────────

	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'ai-post-generator' ) === false ) {
			return;
		}
		wp_enqueue_style( 'aipg-admin', AFCA_AIPG_PLUGIN_URL . 'assets/admin.css', [], AIPG_VERSION );
		wp_enqueue_script( 'aipg-admin', AFCA_AIPG_PLUGIN_URL . 'assets/admin.js', [ 'jquery' ], AIPG_VERSION, true );

		// Pass provider data to JS for dynamic UI
		$providers_js = [];
		foreach ( AIPG_Settings::PROVIDERS as $slug => $cfg ) {
			$providers_js[ $slug ] = [
				'label'     => $cfg['label'],
				'free'      => $cfg['free'],
				'freeNote'  => $cfg['free_note'],
				'signupUrl' => $cfg['signup_url'],
				'models'    => $cfg['models'],
			];
		}

		wp_localize_script(
			'aipg-admin',
			'aipgData',
			[
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'aipg_ajax' ),
				'providers' => $providers_js,
				'strings'   => [
					'generating' => __( 'Generating… this may take up to 30 seconds.', 'ai-post-generator' ),
					'testing'    => __( 'Testing connection…', 'ai-post-generator' ),
					'error'      => __( 'An error occurred. Please try again.', 'ai-post-generator' ),
				],
			]
		);
	}

	// ── Settings save ─────────────────────────────────────────────────────────

	public function handle_settings_save(): void {
		if (
			! isset( $_POST['aipg_save_settings'] ) ||
			! check_admin_referer( 'aipg_save_settings', 'aipg_nonce' ) ||
			! current_user_can( 'manage_options' )
		) {
			return;
		}

		// Active provider
		$active = sanitize_text_field( wp_unslash( $_POST[ AIPG_Settings::OPT_ACTIVE_PROVIDER ] ?? 'groq' ) );
		if ( array_key_exists( $active, AIPG_Settings::PROVIDERS ) ) {
			AIPG_Settings::update( AIPG_Settings::OPT_ACTIVE_PROVIDER, $active );
		}

		// Per-provider API keys and models
		foreach ( array_keys( AIPG_Settings::PROVIDERS ) as $slug ) {
			$key_field   = "aipg_api_key_{$slug}";
			$model_field = "aipg_model_{$slug}";
			if ( isset( $_POST[ $key_field ] ) ) {
				AIPG_Settings::update( $key_field, sanitize_text_field( wp_unslash( $_POST[ $key_field ] ) ) );
			}
			if ( isset( $_POST[ $model_field ] ) ) {
				AIPG_Settings::update( $model_field, sanitize_text_field( wp_unslash( $_POST[ $model_field ] ) ) );
			}
		}

		// Simple text fields
		foreach ( [
			AIPG_Settings::OPT_SCHEDULE  => 'sanitize_text_field',
			AIPG_Settings::OPT_LANGUAGE  => 'sanitize_text_field',
			AIPG_Settings::OPT_POST_TAGS => 'sanitize_text_field',
		] as $key => $fn ) {
			AIPG_Settings::update( $key, isset( $_POST[ $key ] ) ? call_user_func( $fn, wp_unslash( $_POST[ $key ] ) ) : '' );
		}

		// Textarea fields
		foreach ( [ AIPG_Settings::OPT_TOPICS, AIPG_Settings::OPT_REQUIREMENTS ] as $key ) {
			AIPG_Settings::update( $key, isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : '' );
		}

		// Integer fields
		AIPG_Settings::update( AIPG_Settings::OPT_POSTS_PER_RUN, max( 1, min( 10, (int) ( $_POST[ AIPG_Settings::OPT_POSTS_PER_RUN ] ?? 1 ) ) ) );
		AIPG_Settings::update( AIPG_Settings::OPT_AUTHOR_ID, (int) ( $_POST[ AIPG_Settings::OPT_AUTHOR_ID ] ?? 1 ) );
		AIPG_Settings::update( AIPG_Settings::OPT_POST_CATEGORY, (int) ( $_POST[ AIPG_Settings::OPT_POST_CATEGORY ] ?? 0 ) );

		// Checkbox
		AIPG_Settings::update( AIPG_Settings::OPT_ENABLED, isset( $_POST[ AIPG_Settings::OPT_ENABLED ] ) ? '1' : '0' );

		AIPG_Cron::reschedule();

		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully.', 'ai-post-generator' ) . '</p></div>';
			}
		);
	}

	// ── AJAX handlers ─────────────────────────────────────────────────────────

	public function ajax_generate_now(): void {
		check_ajax_referer( 'aipg_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
		}

		$results = AIPG_Generator::run();

		$log = get_option( AFCA_AIPG_LOG_OPTION, [] );
		if ( ! is_array( $log ) ) {
			$log = [];
		}
		foreach ( $results as &$entry ) {
			$entry['manual'] = true;
			array_unshift( $log, $entry );
		}
		unset( $entry );
		update_option( AFCA_AIPG_LOG_OPTION, array_slice( $log, 0, 50 ) );

		$success = array_filter( $results, fn( $r ) => $r['success'] );
		$errors  = array_filter( $results, fn( $r ) => ! $r['success'] );

		wp_send_json_success(
			[
				'results' => $results,
				'summary' => sprintf( '%d post(s) created, %d error(s).', count( $success ), count( $errors ) ),
			]
		);
	}

	public function ajax_test_api(): void {
		check_ajax_referer( 'aipg_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
		}

		// Test the currently active provider
		$provider = AIPG_Provider_Factory::make();
		$result   = $provider->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		$slug  = AIPG_Settings::get_active_provider();
		$label = AIPG_Settings::PROVIDERS[ $slug ]['label'] ?? $slug;
		wp_send_json_success( [ 'message' => "$label API connection successful!" ] );
	}

	// ── Admin notices ─────────────────────────────────────────────────────────

	public function pending_posts_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, [ 'edit-post', 'toplevel_page_ai-post-generator' ], true ) ) {
			return;
		}
		$pending = get_posts(
			[
				'post_status'    => 'pending',
				'meta_key'       => '_aipg_generated',
				'meta_value'     => '1',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);
		if ( empty( $pending ) ) {
			return;
		}
		$count    = count( $pending );
		$edit_url = admin_url( 'edit.php?post_status=pending&post_type=post' );
		printf(
			'<div class="notice notice-info"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
			esc_html( 'AI Post Generator:' ),
			esc_html( sprintf( _n( '%d AI-generated post is waiting for your review.', '%d AI-generated posts are waiting for your review.', $count, 'ai-post-generator' ), $count ) ),
			esc_url( $edit_url ),
			esc_html__( 'Review now →', 'ai-post-generator' )
		);
	}

	// ── Page renderers ────────────────────────────────────────────────────────

	public function render_settings_page(): void {
		require_once AFCA_AIPG_PLUGIN_DIR . 'admin/views/settings.php';
	}

	public function render_log_page(): void {
		require_once AFCA_AIPG_PLUGIN_DIR . 'admin/views/log.php';
	}
}
