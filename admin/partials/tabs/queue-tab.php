<?php
/**
 * Queue tab template.
 *
 * @package SendForce
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sendforce_stats      = SendForce_Queue::get_stats();
$sendforce_page_num   = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$status                  = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$sendforce_queue_data = SendForce_Queue::get_queue(
	array(
		'page'     => $sendforce_page_num,
		'per_page' => 20,
		'status'   => $status,
	)
);

$sendforce_items       = $sendforce_queue_data['items'];
$sendforce_total       = $sendforce_queue_data['total'];
$sendforce_total_pages = ceil( $sendforce_total / 20 );
$sendforce_base_url    = admin_url( 'admin.php?page=sendforce-mail-relay&tab=queue' );
?>

<?php
$sendforce_queue_enabled = ! empty( get_option( 'sendforce_settings', array() )['queue_enabled'] );
$sendforce_next_cron_ts  = wp_next_scheduled( 'sendforce_process_queue' );
?>
<div class="sendforce-page-header">
	<div>
		<h1><?php esc_html_e( 'Email Queue', 'sendforce-mail-relay' ); ?></h1>
		<p class="sendforce-subtitle"><?php esc_html_e( 'Manage queued, processing, and failed emails', 'sendforce-mail-relay' ); ?></p>
	</div>
	<div class="sendforce-page-header-actions">
		<?php if ( $sendforce_queue_enabled ) : ?>
			<button
				type="button"
				class="sendforce-btn sendforce-btn-primary sendforce-btn-sm"
				id="sendforce-queue-process-now"
				<?php disabled( 0 === ( absint( $sendforce_stats['queued'] ) + absint( $sendforce_stats['processing'] ) ) ); ?>
			>
				<span class="dashicons dashicons-controls-play"></span>
				<?php esc_html_e( 'Process Queue Now', 'sendforce-mail-relay' ); ?>
			</button>
		<?php endif; ?>
	</div>
</div>

<?php if ( ! $sendforce_queue_enabled ) : ?>
	<div class="notice notice-info inline" style="margin:0 0 16px;padding:10px 14px;">
		<p style="margin:0;">
			<?php
			echo wp_kses_post( sprintf(
				/* translators: %s: Settings tab URL */
				__( 'The email queue is currently <strong>disabled</strong>. Outgoing emails are sent immediately. To enable queue mode, turn on <strong>Email Queue → Enable Email Queue</strong> in <a href="%s">Settings</a>.', 'sendforce-mail-relay' ),
				esc_url( admin_url( 'admin.php?page=sendforce-mail-relay&tab=settings' ) )
			) );
			?>
		</p>
	</div>
<?php elseif ( $sendforce_next_cron_ts ) : ?>
	<p class="sendforce-text-muted" style="margin:0 0 12px;font-size:13px;">
		<span class="dashicons dashicons-clock" style="font-size:14px;vertical-align:text-bottom;"></span>
		<?php
		echo esc_html( sprintf(
			/* translators: %s: human-readable next-run time */
			__( 'Next automatic run: %s (every minute via WP-Cron).', 'sendforce-mail-relay' ),
			wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $sendforce_next_cron_ts )
		) );
		?>
	</p>
<?php endif; ?>

<!-- Queue Stats -->
<div class="sendforce-stats-grid sendforce-stats-sm">
	<div class="sendforce-stat-card sendforce-stat-mini">
		<div class="sendforce-stat-icon sendforce-stat-icon-queued">
			<span class="dashicons dashicons-clock"></span>
		</div>
		<div class="sendforce-stat-info">
			<span class="sendforce-stat-number"><?php echo esc_html( $sendforce_stats['queued'] ); ?></span>
			<span class="sendforce-stat-label"><?php esc_html_e( 'Queued', 'sendforce-mail-relay' ); ?></span>
		</div>
	</div>

	<div class="sendforce-stat-card sendforce-stat-mini">
		<div class="sendforce-stat-icon sendforce-stat-icon-processing">
			<span class="dashicons dashicons-update"></span>
		</div>
		<div class="sendforce-stat-info">
			<span class="sendforce-stat-number"><?php echo esc_html( $sendforce_stats['processing'] ); ?></span>
			<span class="sendforce-stat-label"><?php esc_html_e( 'Processing', 'sendforce-mail-relay' ); ?></span>
		</div>
	</div>

	<div class="sendforce-stat-card sendforce-stat-mini">
		<div class="sendforce-stat-icon sendforce-stat-icon-sent">
			<span class="dashicons dashicons-yes-alt"></span>
		</div>
		<div class="sendforce-stat-info">
			<span class="sendforce-stat-number"><?php echo esc_html( $sendforce_stats['sent'] ); ?></span>
			<span class="sendforce-stat-label"><?php esc_html_e( 'Sent', 'sendforce-mail-relay' ); ?></span>
		</div>
	</div>

	<div class="sendforce-stat-card sendforce-stat-mini">
		<div class="sendforce-stat-icon sendforce-stat-icon-failed">
			<span class="dashicons dashicons-dismiss"></span>
		</div>
		<div class="sendforce-stat-info">
			<span class="sendforce-stat-number"><?php echo esc_html( $sendforce_stats['failed'] ); ?></span>
			<span class="sendforce-stat-label"><?php esc_html_e( 'Failed', 'sendforce-mail-relay' ); ?></span>
		</div>
	</div>
</div>

<!-- Queue Table -->
<div class="sendforce-card">
	<div class="sendforce-card-header">
		<h2><?php esc_html_e( 'Queue Items', 'sendforce-mail-relay' ); ?></h2>
		<div class="sendforce-filter-tabs">
			<a href="<?php echo esc_url( $sendforce_base_url ); ?>" class="sendforce-filter-tab <?php echo empty( $status ) ? 'active' : ''; ?>">
				<?php esc_html_e( 'All', 'sendforce-mail-relay' ); ?> (<?php echo esc_html( $sendforce_stats['total'] ); ?>)
			</a>
			<a href="<?php echo esc_url( $sendforce_base_url . '&status=queued' ); ?>" class="sendforce-filter-tab <?php echo 'queued' === $status ? 'active' : ''; ?>">
				<?php esc_html_e( 'Queued', 'sendforce-mail-relay' ); ?> (<?php echo esc_html( $sendforce_stats['queued'] ); ?>)
			</a>
			<a href="<?php echo esc_url( $sendforce_base_url . '&status=sent' ); ?>" class="sendforce-filter-tab <?php echo 'sent' === $status ? 'active' : ''; ?>">
				<?php esc_html_e( 'Sent', 'sendforce-mail-relay' ); ?> (<?php echo esc_html( $sendforce_stats['sent'] ); ?>)
			</a>
			<a href="<?php echo esc_url( $sendforce_base_url . '&status=failed' ); ?>" class="sendforce-filter-tab <?php echo 'failed' === $status ? 'active' : ''; ?>">
				<?php esc_html_e( 'Failed', 'sendforce-mail-relay' ); ?> (<?php echo esc_html( $sendforce_stats['failed'] ); ?>)
			</a>
		</div>
	</div>

	<?php if ( empty( $sendforce_items ) ) : ?>
		<div class="sendforce-empty-state">
			<span class="dashicons dashicons-update"></span>
			<p><?php esc_html_e( 'No emails in queue.', 'sendforce-mail-relay' ); ?></p>
		</div>
	<?php else : ?>
		<div class="sendforce-table-wrap">
			<table class="sendforce-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Recipient', 'sendforce-mail-relay' ); ?></th>
						<th><?php esc_html_e( 'Subject', 'sendforce-mail-relay' ); ?></th>
						<th><?php esc_html_e( 'Status', 'sendforce-mail-relay' ); ?></th>
						<th><?php esc_html_e( 'Attempts', 'sendforce-mail-relay' ); ?></th>
						<th><?php esc_html_e( 'Next Retry', 'sendforce-mail-relay' ); ?></th>
						<th><?php esc_html_e( 'Created', 'sendforce-mail-relay' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'sendforce-mail-relay' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $sendforce_items as $sendforce_item ) : ?>
						<tr>
							<td class="sendforce-td-email"><?php echo esc_html( $sendforce_item->to_email ); ?></td>
							<td><?php echo esc_html( wp_trim_words( $sendforce_item->subject, 6 ) ); ?></td>
							<td>
								<span class="sendforce-badge sendforce-badge-<?php echo esc_attr( $sendforce_item->status ); ?>">
									<?php echo esc_html( ucfirst( $sendforce_item->status ) ); ?>
								</span>
								<?php
								// The provider's actual error from the last attempt (stored since
								// 1.0.26). Shown so users can tell whether a retry will help
								// without opening the Email Log; full text on hover.
								if ( 'sent' !== $sendforce_item->status && '' !== (string) $sendforce_item->error ) :
									?>
									<span class="sendforce-help" style="display:block;margin-top:4px;color:#b32d2e;max-width:320px;" title="<?php echo esc_attr( $sendforce_item->error ); ?>">
										<?php echo esc_html( mb_strimwidth( (string) $sendforce_item->error, 0, 90, '…' ) ); ?>
									</span>
								<?php endif; ?>
							</td>
							<td>
								<span class="sendforce-attempts"><?php echo esc_html( $sendforce_item->attempts . '/' . $sendforce_item->max_attempts ); ?></span>
							</td>
							<td class="sendforce-td-date">
								<?php
								if ( $sendforce_item->next_retry_at ) {
									echo esc_html( wp_date( 'M j, g:i a', strtotime( $sendforce_item->next_retry_at ) ) );
								} else {
									echo '&mdash;';
								}
								?>
							</td>
							<td class="sendforce-td-date">
								<?php echo esc_html( wp_date( 'M j, g:i a', strtotime( $sendforce_item->created_at ) ) ); ?>
							</td>
							<td class="sendforce-td-actions">
								<?php if ( in_array( $sendforce_item->status, array( 'failed', 'queued' ), true ) ) : ?>
									<a href="<?php echo esc_url( wp_nonce_url( $sendforce_base_url . '&sendforce_queue_action=retry&queue_id=' . $sendforce_item->id, 'sendforce_queue_retry_' . $sendforce_item->id ) ); ?>" class="sendforce-btn sendforce-btn-xs sendforce-btn-outline" aria-label="<?php esc_attr_e( 'Retry queued email', 'sendforce-mail-relay' ); ?>" title="<?php esc_attr_e( 'Retry', 'sendforce-mail-relay' ); ?>">
										<span class="dashicons dashicons-update" aria-hidden="true"></span>
									</a>
								<?php endif; ?>
								<a href="<?php echo esc_url( wp_nonce_url( $sendforce_base_url . '&sendforce_queue_action=delete&queue_id=' . $sendforce_item->id, 'sendforce_queue_delete_' . $sendforce_item->id ) ); ?>" class="sendforce-btn sendforce-btn-xs sendforce-btn-danger sendforce-queue-delete-link" data-href="<?php echo esc_url( wp_nonce_url( $sendforce_base_url . '&sendforce_queue_action=delete&queue_id=' . $sendforce_item->id, 'sendforce_queue_delete_' . $sendforce_item->id ) ); ?>" aria-label="<?php esc_attr_e( 'Delete queued email', 'sendforce-mail-relay' ); ?>" title="<?php esc_attr_e( 'Delete', 'sendforce-mail-relay' ); ?>">
									<span class="dashicons dashicons-trash" aria-hidden="true"></span>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php if ( $sendforce_total_pages > 1 ) : ?>
			<div class="sendforce-pagination">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total'     => $sendforce_total_pages,
							'current'   => $sendforce_page_num,
						)
					)
				);
				?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
