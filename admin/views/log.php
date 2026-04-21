<?php
/**
 * Admin view: Generation Log page
 */

defined( 'ABSPATH' ) || exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

// Handle log clear
if (
    isset( $_POST['afca_aipg_clear_log'] ) &&
    check_admin_referer( 'afca_aipg_clear_log', 'afca_aipg_log_nonce' )
) {
    delete_option( AFCA_AIPG_LOG_OPTION );
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Log cleared.', 'afca-ai-post-generator' ) . '</p></div>';
}

$log = get_option( AFCA_AIPG_LOG_OPTION, [] );
if ( ! is_array( $log ) ) {
    $log = [];
}
?>
<div class="wrap aipg-wrap">

    <div class="aipg-header">
        <div class="aipg-header__icon">📋</div>
        <div class="aipg-header__text">
            <h1><?php esc_html_e( 'Generation Log', 'afca-ai-post-generator' ); ?></h1>
            <p><?php printf( esc_html__( 'Last %d generation events.', 'afca-ai-post-generator' ), count( $log ) ); ?></p>
        </div>
        <div class="aipg-header__status">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-post-generator' ) ); ?>" class="button button-secondary">
                ← <?php esc_html_e( 'Back to Settings', 'afca-ai-post-generator' ); ?>
            </a>
        </div>
    </div>

    <?php if ( empty( $log ) ) : ?>
        <div class="aipg-card">
            <div class="aipg-card__body aipg-empty-state">
                <p class="aipg-empty-icon">📭</p>
                <p><?php esc_html_e( 'No generation events recorded yet. Posts will appear here after the first scheduled run or after clicking "Generate Now".', 'afca-ai-post-generator' ); ?></p>
            </div>
        </div>
    <?php else : ?>

        <div class="aipg-card">
            <div class="aipg-card__body" style="padding:0;">
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Status', 'afca-ai-post-generator' ); ?></th>
                            <th><?php esc_html_e( 'Time', 'afca-ai-post-generator' ); ?></th>
                            <th><?php esc_html_e( 'Topic', 'afca-ai-post-generator' ); ?></th>
                            <th><?php esc_html_e( 'Post Title', 'afca-ai-post-generator' ); ?></th>
                            <th><?php esc_html_e( 'Details', 'afca-ai-post-generator' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $log as $entry ) :
                            $is_manual = ! empty( $entry['manual'] );
                        ?>
                        <tr>
                            <td>
                                <?php if ( $entry['success'] ) : ?>
                                    <span class="aipg-badge aipg-badge--on">✓ <?php esc_html_e( 'Success', 'afca-ai-post-generator' ); ?></span>
                                <?php else : ?>
                                    <span class="aipg-badge aipg-badge--off">✗ <?php esc_html_e( 'Error', 'afca-ai-post-generator' ); ?></span>
                                <?php endif; ?>
                                <?php if ( $is_manual ) : ?>
                                    <span class="aipg-badge aipg-badge--info"><?php esc_html_e( 'Manual', 'afca-ai-post-generator' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="aipg-nowrap">
                                <?php echo esc_html( $entry['time'] ?? '–' ); ?>
                            </td>
                            <td>
                                <?php echo esc_html( $entry['topic'] ?? '–' ); ?>
                            </td>
                            <td>
                                <?php if ( ! empty( $entry['post_id'] ) && ! empty( $entry['title'] ) ) : ?>
                                    <a href="<?php echo esc_url( get_edit_post_link( $entry['post_id'] ) ); ?>">
                                        <?php echo esc_html( $entry['title'] ); ?>
                                    </a>
                                <?php elseif ( ! empty( $entry['title'] ) ) : ?>
                                    <?php echo esc_html( $entry['title'] ); ?>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html( $entry['message'] ?? '' ); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <form method="post" style="margin-top: 1rem;">
            <?php wp_nonce_field( 'afca_aipg_clear_log', 'afca_aipg_log_nonce' ); ?>
            <button type="submit" name="afca_aipg_clear_log" class="button button-secondary"
                onclick="return confirm('<?php esc_attr_e( 'Clear all log entries? This cannot be undone.', 'afca-ai-post-generator' ); ?>')">
                🗑 <?php esc_html_e( 'Clear Log', 'afca-ai-post-generator' ); ?>
            </button>
        </form>

    <?php endif; ?>

</div>
