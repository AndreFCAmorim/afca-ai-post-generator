<?php
/**
 * Admin view: Generation Log page
 */

defined( 'ABSPATH' ) || exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

// Handle log clear
if (
    isset( $_POST['aipg_clear_log'] ) &&
    check_admin_referer( 'aipg_clear_log', 'aipg_log_nonce' )
) {
    delete_option( AIPG_LOG_OPTION );
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Log cleared.', 'ai-post-generator' ) . '</p></div>';
}

$log = get_option( AIPG_LOG_OPTION, [] );
if ( ! is_array( $log ) ) {
    $log = [];
}
?>
<div class="wrap aipg-wrap">

    <div class="aipg-header">
        <div class="aipg-header__icon">📋</div>
        <div class="aipg-header__text">
            <h1><?php esc_html_e( 'Generation Log', 'ai-post-generator' ); ?></h1>
            <p><?php printf( esc_html__( 'Last %d generation events.', 'ai-post-generator' ), count( $log ) ); ?></p>
        </div>
        <div class="aipg-header__status">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-post-generator' ) ); ?>" class="button button-secondary">
                ← <?php esc_html_e( 'Back to Settings', 'ai-post-generator' ); ?>
            </a>
        </div>
    </div>

    <?php if ( empty( $log ) ) : ?>
        <div class="aipg-card">
            <div class="aipg-card__body aipg-empty-state">
                <p class="aipg-empty-icon">📭</p>
                <p><?php esc_html_e( 'No generation events recorded yet. Posts will appear here after the first scheduled run or after clicking "Generate Now".', 'ai-post-generator' ); ?></p>
            </div>
        </div>
    <?php else : ?>

        <div class="aipg-card">
            <div class="aipg-card__body" style="padding:0;">
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Status', 'ai-post-generator' ); ?></th>
                            <th><?php esc_html_e( 'Time', 'ai-post-generator' ); ?></th>
                            <th><?php esc_html_e( 'Topic', 'ai-post-generator' ); ?></th>
                            <th><?php esc_html_e( 'Post Title', 'ai-post-generator' ); ?></th>
                            <th><?php esc_html_e( 'Details', 'ai-post-generator' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $log as $entry ) :
                            $is_manual = ! empty( $entry['manual'] );
                        ?>
                        <tr>
                            <td>
                                <?php if ( $entry['success'] ) : ?>
                                    <span class="aipg-badge aipg-badge--on">✓ <?php esc_html_e( 'Success', 'ai-post-generator' ); ?></span>
                                <?php else : ?>
                                    <span class="aipg-badge aipg-badge--off">✗ <?php esc_html_e( 'Error', 'ai-post-generator' ); ?></span>
                                <?php endif; ?>
                                <?php if ( $is_manual ) : ?>
                                    <span class="aipg-badge aipg-badge--info"><?php esc_html_e( 'Manual', 'ai-post-generator' ); ?></span>
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
            <?php wp_nonce_field( 'aipg_clear_log', 'aipg_log_nonce' ); ?>
            <button type="submit" name="aipg_clear_log" class="button button-secondary"
                onclick="return confirm('<?php esc_attr_e( 'Clear all log entries? This cannot be undone.', 'ai-post-generator' ); ?>')">
                🗑 <?php esc_html_e( 'Clear Log', 'ai-post-generator' ); ?>
            </button>
        </form>

    <?php endif; ?>

</div>
