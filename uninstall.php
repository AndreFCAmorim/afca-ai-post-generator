<?php
/**
 * Uninstall – AFCA AI Post Generator
 *
 * Fired when the user clicks "Delete" in the Plugins screen.
 * Removes ALL data left by the plugin:
 *   • WP-Cron scheduled event
 *   • All wp_options entries (settings + per-provider keys/models + log)
 *   • All post-meta rows written by the generator
 *
 * @package AFCA_AI_Post_Generator
 */

// Only run when WordPress is performing the uninstall routine.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// ─── 1. Clear the cron event ──────────────────────────────────────────────────

$cron_hook = 'afca_aipg_generate_post_event';
wp_clear_scheduled_hook( $cron_hook );

// ─── 2. Delete plugin options ─────────────────────────────────────────────────

// Core settings
$options = [
	'afca_aipg_active_provider',
	'afca_aipg_topics',
	'afca_aipg_requirements',
	'afca_aipg_schedule',
	'afca_aipg_posts_per_run',
	'afca_aipg_author_id',
	'afca_aipg_post_category',
	'afca_aipg_post_tags',
	'afca_aipg_language',
	'afca_aipg_enabled',
	// Generation log
	'afca_aipg_generation_log',
];

// Per-provider API keys and model selections
$providers = [ 'groq', 'openrouter', 'gemini', 'openai', 'mistral', 'anthropic' ];
foreach ( $providers as $provider ) {
	$options[] = "afca_aipg_api_key_{$provider}";
	$options[] = "afca_aipg_model_{$provider}";
}

foreach ( $options as $option ) {
	delete_option( $option );
}

// ─── 3. Delete post meta written by the generator ─────────────────────────────

/**
 * The generator stores these meta keys on every post it creates:
 *   _afca_aipg_generated    → flag marking the post as AI-generated
 *   _afca_aipg_topic        → the topic used for generation
 *   _afca_aipg_provider     → the AI provider used (e.g. "groq")
 *   _afca_aipg_model        → the model used (e.g. "llama-3.3-70b-versatile")
 *   _afca_aipg_generated_at → MySQL datetime of generation
 *
 * We use a single DELETE query per meta key so we don't have to load
 * all post IDs into memory – safe even on very large databases.
 */
global $wpdb;

$meta_keys = [
	'_afca_aipg_generated',
	'_afca_aipg_topic',
	'_afca_aipg_provider',
	'_afca_aipg_model',
	'_afca_aipg_generated_at',
];

foreach ( $meta_keys as $meta_key ) {
	$wpdb->delete(
		$wpdb->postmeta,
		[ 'meta_key' => $meta_key ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		[ '%s' ]
	);
}
