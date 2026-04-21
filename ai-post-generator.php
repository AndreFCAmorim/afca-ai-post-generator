<?php
/**
 * Plugin Name:       AI Post Generator
 * Plugin URI:        https://github.com/AndreFCAmorim/ai-post-generator
 * Description:       Automatically generates WordPress posts on a schedule using AI (Groq, OpenRouter, OpenAI, Gemini, Mistral, Claude). Posts are created as pending for editorial review.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            André Amorim
 * Author URI:        https://www.andreamorim.site
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-post-generator
 */

defined( 'ABSPATH' ) || exit;

// ─── Constants ────────────────────────────────────────────────────────────────
define( 'AFCA_AIPG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AFCA_AIPG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AFCA_AIPG_CRON_HOOK', 'aipg_generate_post_event' );
define( 'AFCA_AIPG_LOG_OPTION', 'aipg_generation_log' );

// ─── Core classes ─────────────────────────────────────────────────────────────
require_once AFCA_AIPG_PLUGIN_DIR . 'includes/class-settings.php';

// Provider system
require_once AFCA_AIPG_PLUGIN_DIR . 'includes/providers/class-provider-base.php';
require_once AFCA_AIPG_PLUGIN_DIR . 'includes/providers/class-provider-gemini.php';
require_once AFCA_AIPG_PLUGIN_DIR . 'includes/providers/class-provider-openai-compat.php';
require_once AFCA_AIPG_PLUGIN_DIR . 'includes/providers/class-provider-anthropic.php';
require_once AFCA_AIPG_PLUGIN_DIR . 'includes/providers/class-provider-factory.php';

// Generator, cron, admin
require_once AFCA_AIPG_PLUGIN_DIR . 'includes/class-generator.php';
require_once AFCA_AIPG_PLUGIN_DIR . 'includes/class-cron.php';
require_once AFCA_AIPG_PLUGIN_DIR . 'includes/class-admin.php';

// ─── Boot ─────────────────────────────────────────────────────────────────────
function aipg_init(): void {
	AIPG_Settings::get_instance();
	AIPG_Cron::get_instance();
	AIPG_Admin::get_instance();
}
add_action( 'plugins_loaded', 'aipg_init' );

// ─── Activation / Deactivation ────────────────────────────────────────────────
register_activation_hook( __FILE__, 'aipg_activate' );
function aipg_activate(): void {
	AIPG_Cron::schedule();
	AIPG_Settings::set_defaults();
}

register_deactivation_hook( __FILE__, 'aipg_deactivate' );
function aipg_deactivate(): void {
	AIPG_Cron::unschedule();
}
