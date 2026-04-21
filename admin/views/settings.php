<?php
/**
 * Admin view: Settings page (multi-provider)
 */

defined( 'ABSPATH' ) || exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

$next_run        = AIPG_Cron::next_run();
$active_provider = AIPG_Settings::get_active_provider();
$providers       = AIPG_Settings::PROVIDERS;
?>
<div class="wrap aipg-wrap">

    <div class="aipg-header">
        <div class="aipg-header__icon">🤖</div>
        <div class="aipg-header__text">
            <h1><?php esc_html_e( 'AI Post Generator', 'ai-post-generator' ); ?></h1>
            <p><?php esc_html_e( 'Automatically draft posts with AI — ready for your editorial review.', 'ai-post-generator' ); ?></p>
        </div>
        <div class="aipg-header__status">
            <?php if ( AIPG_Settings::is_enabled() ) : ?>
                <span class="aipg-badge aipg-badge--on">● <?php esc_html_e( 'Enabled', 'ai-post-generator' ); ?></span>
            <?php else : ?>
                <span class="aipg-badge aipg-badge--off">● <?php esc_html_e( 'Disabled', 'ai-post-generator' ); ?></span>
            <?php endif; ?>
            <?php if ( $next_run ) : ?>
                <span class="aipg-badge aipg-badge--info">
                    ⏱ <?php printf( esc_html__( 'Next run: %s', 'ai-post-generator' ), esc_html( human_time_diff( $next_run ) . ' from now' ) ); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <form method="post" action="" id="aipg-settings-form">
        <?php wp_nonce_field( 'aipg_save_settings', 'aipg_nonce' ); ?>
        <input type="hidden" name="aipg_save_settings" value="1">

        <!-- ══ PROVIDER SELECTOR ══════════════════════════════════════════ -->
        <div class="aipg-card aipg-card--provider-select">
            <div class="aipg-card__header">
                <span class="aipg-card__icon">⚙️</span>
                <h2><?php esc_html_e( 'AI Provider', 'ai-post-generator' ); ?></h2>
            </div>
            <div class="aipg-card__body">
                <div class="aipg-provider-grid">
                    <?php foreach ( $providers as $slug => $cfg ) :
                        $is_active = ( $slug === $active_provider );
                    ?>
                    <label class="aipg-provider-card <?php echo $is_active ? 'is-active' : ''; ?>" data-provider="<?php echo esc_attr( $slug ); ?>">
                        <input type="radio" name="<?php echo esc_attr( AIPG_Settings::OPT_ACTIVE_PROVIDER ); ?>"
                               value="<?php echo esc_attr( $slug ); ?>"
                               <?php checked( $is_active ); ?>>
                        <div class="aipg-provider-card__inner">
                            <div class="aipg-provider-card__name">
                                <?php echo esc_html( $cfg['label'] ); ?>
                                <?php if ( $cfg['free'] ) : ?>
                                    <span class="aipg-badge aipg-badge--on" style="font-size:10px;padding:2px 6px;">FREE</span>
                                <?php endif; ?>
                            </div>
                            <div class="aipg-provider-card__note"><?php echo esc_html( $cfg['free_note'] ); ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ══ PROVIDER API KEYS (show active, hide others) ══════════════ -->
        <?php foreach ( $providers as $slug => $cfg ) :
            $api_key = AIPG_Settings::get_api_key_for( $slug );
            $model   = AIPG_Settings::get_model_for( $slug );
        ?>
        <div class="aipg-provider-config" data-provider="<?php echo esc_attr( $slug ); ?>"
             style="<?php echo ( $slug !== $active_provider ) ? 'display:none;' : ''; ?>">
            <div class="aipg-card">
                <div class="aipg-card__header">
                    <span class="aipg-card__icon">🔑</span>
                    <h2><?php printf( esc_html__( '%s Configuration', 'ai-post-generator' ), esc_html( $cfg['label'] ) ); ?></h2>
                    <a href="<?php echo esc_url( $cfg['signup_url'] ); ?>" target="_blank" class="aipg-signup-link">
                        <?php esc_html_e( 'Get API Key →', 'ai-post-generator' ); ?>
                    </a>
                </div>
                <div class="aipg-card__body">
                    <div class="aipg-field-row">
                        <div class="aipg-field">
                            <label><?php printf( esc_html__( '%s API Key', 'ai-post-generator' ), esc_html( $cfg['label'] ) ); ?> <span class="aipg-required">*</span></label>
                            <div class="aipg-input-group">
                                <input type="password"
                                       name="aipg_api_key_<?php echo esc_attr( $slug ); ?>"
                                       value="<?php echo esc_attr( $api_key ); ?>"
                                       placeholder="<?php echo $slug === 'gemini' ? 'AIza…' : 'sk-…'; ?>"
                                       class="regular-text aipg-api-key-input"
                                >
                                <button type="button" class="button aipg-toggle-key">👁</button>
                            </div>
                        </div>
                        <div class="aipg-field">
                            <label><?php esc_html_e( 'Model', 'ai-post-generator' ); ?></label>
                            <select name="aipg_model_<?php echo esc_attr( $slug ); ?>">
                                <?php foreach ( $cfg['models'] as $model_id => $model_label ) : ?>
                                    <option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $model, $model_id ); ?>>
                                        <?php echo esc_html( $model_label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="aipg-test-api">
                        <button type="button" class="button button-secondary aipg-test-api-btn">
                            🧪 <?php esc_html_e( 'Test Connection', 'ai-post-generator' ); ?>
                        </button>
                        <span class="aipg-inline-result"></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- ══ MAIN GRID ════════════════════════════════════════════════ -->
        <div class="aipg-grid">

            <!-- Left column -->
            <div class="aipg-col">

                <!-- Schedule -->
                <div class="aipg-card">
                    <div class="aipg-card__header">
                        <span class="aipg-card__icon">📅</span>
                        <h2><?php esc_html_e( 'Schedule', 'ai-post-generator' ); ?></h2>
                    </div>
                    <div class="aipg-card__body">
                        <div class="aipg-field aipg-field--inline">
                            <label><?php esc_html_e( 'Enable automatic generation', 'ai-post-generator' ); ?></label>
                            <label class="aipg-toggle">
                                <input type="checkbox" name="<?php echo esc_attr( AIPG_Settings::OPT_ENABLED ); ?>" value="1" <?php checked( AIPG_Settings::is_enabled() ); ?>>
                                <span class="aipg-toggle__slider"></span>
                            </label>
                        </div>
                        <div class="aipg-field">
                            <label><?php esc_html_e( 'Frequency', 'ai-post-generator' ); ?></label>
                            <select name="<?php echo esc_attr( AIPG_Settings::OPT_SCHEDULE ); ?>">
                                <?php foreach ( AIPG_Settings::SCHEDULES as $value => $label ) : ?>
                                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( AIPG_Settings::get_schedule(), $value ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="aipg-field">
                            <label><?php esc_html_e( 'Posts per run', 'ai-post-generator' ); ?></label>
                            <input type="number" name="<?php echo esc_attr( AIPG_Settings::OPT_POSTS_PER_RUN ); ?>"
                                   value="<?php echo esc_attr( AIPG_Settings::get_posts_per_run() ); ?>"
                                   min="1" max="10" step="1" class="small-text">
                            <p class="description"><?php esc_html_e( 'How many posts to generate per run (1–10).', 'ai-post-generator' ); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Post Metadata -->
                <div class="aipg-card">
                    <div class="aipg-card__header">
                        <span class="aipg-card__icon">📝</span>
                        <h2><?php esc_html_e( 'Post Metadata', 'ai-post-generator' ); ?></h2>
                    </div>
                    <div class="aipg-card__body">
                        <div class="aipg-field">
                            <label><?php esc_html_e( 'Post Author', 'ai-post-generator' ); ?> <span class="aipg-required">*</span></label>
                            <?php wp_dropdown_users( [
                                'name'             => AIPG_Settings::OPT_AUTHOR_ID,
                                'id'               => AIPG_Settings::OPT_AUTHOR_ID,
                                'selected'         => AIPG_Settings::get_author_id(),
                                'show_option_none' => __( '— Select an author —', 'ai-post-generator' ),
                                'who'              => 'authors',
                            ] ); ?>
                        </div>
                        <div class="aipg-field">
                            <label><?php esc_html_e( 'Category', 'ai-post-generator' ); ?></label>
                            <select name="<?php echo esc_attr( AIPG_Settings::OPT_POST_CATEGORY ); ?>">
                                <option value="0"><?php esc_html_e( '— Uncategorized —', 'ai-post-generator' ); ?></option>
                                <?php foreach ( get_categories( [ 'hide_empty' => false ] ) as $cat ) : ?>
                                    <option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( AIPG_Settings::get_post_category(), $cat->term_id ); ?>><?php echo esc_html( $cat->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="aipg-field">
                            <label><?php esc_html_e( 'Fixed Tags', 'ai-post-generator' ); ?></label>
                            <input type="text" name="<?php echo esc_attr( AIPG_Settings::OPT_POST_TAGS ); ?>"
                                   value="<?php echo esc_attr( implode( ', ', AIPG_Settings::get_post_tags() ) ); ?>"
                                   placeholder="ai, technology, news" class="regular-text">
                            <p class="description"><?php esc_html_e( 'Comma-separated. AI-suggested tags are added automatically too.', 'ai-post-generator' ); ?></p>
                        </div>
                        <div class="aipg-field">
                            <label><?php esc_html_e( 'Output Language', 'ai-post-generator' ); ?></label>
                            <input type="text" name="<?php echo esc_attr( AIPG_Settings::OPT_LANGUAGE ); ?>"
                                   value="<?php echo esc_attr( AIPG_Settings::get_language() ); ?>"
                                   placeholder="English" class="regular-text">
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right column -->
            <div class="aipg-col">

                <!-- Topics -->
                <div class="aipg-card">
                    <div class="aipg-card__header">
                        <span class="aipg-card__icon">💡</span>
                        <h2><?php esc_html_e( 'Topics', 'ai-post-generator' ); ?></h2>
                    </div>
                    <div class="aipg-card__body">
                        <div class="aipg-field">
                            <label><?php esc_html_e( 'Topic List', 'ai-post-generator' ); ?> <span class="aipg-required">*</span></label>
                            <textarea name="<?php echo esc_attr( AIPG_Settings::OPT_TOPICS ); ?>" rows="10" class="large-text code"
                                      placeholder="The future of renewable energy&#10;How to build a morning routine&#10;Beginner's guide to investing"
                            ><?php echo esc_textarea( AIPG_Settings::get( AIPG_Settings::OPT_TOPICS ) ); ?></textarea>
                            <p class="description"><?php printf(
                                esc_html( _n( 'One topic per line. %d topic configured.', 'One topic per line. %d topics configured.', count( AIPG_Settings::get_topics() ), 'ai-post-generator' ) ),
                                count( AIPG_Settings::get_topics() )
                            ); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Requirements -->
                <div class="aipg-card">
                    <div class="aipg-card__header">
                        <span class="aipg-card__icon">📋</span>
                        <h2><?php esc_html_e( 'Writing Requirements', 'ai-post-generator' ); ?></h2>
                    </div>
                    <div class="aipg-card__body">
                        <div class="aipg-field">
                            <label><?php esc_html_e( 'Instructions for the AI', 'ai-post-generator' ); ?></label>
                            <textarea name="<?php echo esc_attr( AIPG_Settings::OPT_REQUIREMENTS ); ?>" rows="12" class="large-text code"
                                      placeholder="Write in an engaging, informative tone.&#10;Always include a sources section at the end.&#10;Use subheadings to organize the article."
                            ><?php echo esc_textarea( AIPG_Settings::get( AIPG_Settings::OPT_REQUIREMENTS ) ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'One instruction per line. Be specific about tone, format, length, and mandatory sections.', 'ai-post-generator' ); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Manual Generation -->
                <div class="aipg-card aipg-card--action">
                    <div class="aipg-card__header">
                        <span class="aipg-card__icon">⚡</span>
                        <h2><?php esc_html_e( 'Manual Generation', 'ai-post-generator' ); ?></h2>
                    </div>
                    <div class="aipg-card__body">
                        <p><?php esc_html_e( 'Trigger a generation run immediately, outside of the schedule.', 'ai-post-generator' ); ?></p>
                        <button type="button" class="button button-primary button-hero" id="aipg-generate-now">
                            ⚡ <?php esc_html_e( 'Generate Now', 'ai-post-generator' ); ?>
                        </button>
                        <div id="aipg-generate-result" class="aipg-result-box" style="display:none;"></div>
                    </div>
                </div>

            </div>
        </div>

        <div class="aipg-save-bar">
            <button type="submit" class="button button-primary button-large">💾 <?php esc_html_e( 'Save Settings', 'ai-post-generator' ); ?></button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-post-generator-log' ) ); ?>" class="button button-secondary button-large">
                📋 <?php esc_html_e( 'View Generation Log', 'ai-post-generator' ); ?>
            </a>
        </div>

    </form>
</div>
