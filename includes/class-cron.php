<?php
/**
 * AIPG_Cron
 *
 * Manages WP-Cron scheduling and the actual generation callback.
 * Also registers the custom schedule intervals (e.g. 'weekly').
 */

defined( 'ABSPATH' ) || exit;

class AIPG_Cron {

	private static ?self $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Register custom intervals
		add_filter( 'cron_schedules', [ $this, 'add_custom_schedules' ] );

		// Hook the generation event
		add_action( AFCA_AIPG_CRON_HOOK, [ $this, 'run_generation' ] );
	}

	/**
	 * Add any missing schedules (WordPress includes hourly, twicedaily, daily
	 * natively; we add 'weekly' for completeness).
	 */
	public function add_custom_schedules( array $schedules ): array {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = [
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once a Week', 'afca-ai-post-generator' ),
			];
		}
		return $schedules;
	}

	// ── Schedule management ────────────────────────────────────────────────────

	/**
	 * Schedule the cron job using the configured recurrence.
	 * Safe to call multiple times – only schedules if not already set.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( AFCA_AIPG_CRON_HOOK ) ) {
			$recurrence = AIPG_Settings::get_schedule();
			wp_schedule_event( time(), $recurrence, AFCA_AIPG_CRON_HOOK );
		}
	}

	/**
	 * Remove the cron job entirely (called on plugin deactivation).
	 */
	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( AFCA_AIPG_CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, AFCA_AIPG_CRON_HOOK );
		}
		// Also clear all future hooks in case of multiple entries
		wp_clear_scheduled_hook( AFCA_AIPG_CRON_HOOK );
	}

	/**
	 * Reschedule with a new recurrence (call after saving settings).
	 */
	public static function reschedule(): void {
		self::unschedule();
		if ( AIPG_Settings::is_enabled() ) {
			self::schedule();
		}
	}

	/**
	 * Return the timestamp of the next scheduled run, or false.
	 *
	 * @return int|false
	 */
	public static function next_run() {
		return wp_next_scheduled( AFCA_AIPG_CRON_HOOK );
	}

	// ── Generation callback ────────────────────────────────────────────────────

	/**
	 * This method is called by WP-Cron.
	 */
	public function run_generation(): void {
		if ( ! AIPG_Settings::is_enabled() ) {
			return;
		}

		$results = AIPG_Generator::run();

		// Persist log (keep last 50 entries)
		$log = get_option( AFCA_AIPG_LOG_OPTION, [] );
		if ( ! is_array( $log ) ) {
			$log = [];
		}

		foreach ( $results as $entry ) {
			array_unshift( $log, $entry );
		}

		$log = array_slice( $log, 0, 50 );
		update_option( AFCA_AIPG_LOG_OPTION, $log );
	}
}
