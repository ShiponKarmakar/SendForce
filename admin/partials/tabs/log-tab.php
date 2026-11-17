<?php
/**
 * Email log tab template — custom table (no WP_List_Table).
 *
 * @package SendForce
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sendforce_page_num = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$status    = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$sendforce_date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$sendforce_date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$sendforce_provider  = isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( $_GET['provider'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// Validate date format (YYYY-MM-DD).
$sendforce_date_from = ( $sendforce_date_from && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $sendforce_date_from ) ) ? $sendforce_date_from : '';
$sendforce_date_to   = ( $sendforce_date_to && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $sendforce_date_to ) ) ? $sendforce_date_to : '';

$per_page          = 20;
$sendforce_log_data = SendForce_Logger::get_logs(
	array(
		'page'      => $sendforce_page_num,
		'per_page'  => $per_page,
		'status'    => $status,
		'search'    => $search,
		'date_from' => $sendforce_date_from,
		'date_to'   => $sendforce_date_to,
		'provider'  => $sendforce_provider,
		'orderby'   => 'created_at',
		'order'     => 'DESC',
	)
);

$sendforce_items       = $sendforce_log_data['items'];
$sendforce_total       = $sendforce_log_data['total'];
$sendforce_total_pages = ceil( $sendforce_total / $per_page );
$sendforce_base_url    = admin_url( 'admin.php?page=sendforce-mail-relay&tab=email-log' );
$sendforce_date_query  = ( $sendforce_date_from ? '&date_from=' . rawurlencode( $sendforce_date_from ) : '' ) . ( $sendforce_date_to ? '&date_to=' . rawurlencode( $sendforce_date_to ) : '' ) . ( $search ? '&s=' . rawurlencode( $search ) : '' ) . ( $sendforce_provider ? '&provider=' . rawurlencode( $sendforce_provider ) : '' );
$sendforce_has_filters = $search || $sendforce_date_from || $sendforce_date_to || $sendforce_provider;

// Provider dropdown options — listed from distinct values actually present in
// the log so the user only sees relevant choices (no empty "SendGrid" option
// when they've never sent through SendGrid).
$sendforce_log_providers = SendForce_Logger::get_distinct_providers();
$sendforce_provider_labels = array();
if ( class_exists( 'SendForce_Providers' ) ) {
	foreach ( SendForce_Providers::get_providers() as $sendforce_pkey => $sendforce_pdata ) {
		$sendforce_provider_labels[ $sendforce_pkey ] = $sendforce_pdata['label'] ?? ucfirst( $sendforce_pkey );
	}
}

// Auto-clean status (used by the status row above the filter form).
$sendforce_log_settings        = get_option( 'sendforce_settings', array() );
$sendforce_log_retention_days  = isset( $sendforce_log_settings['log_retention_days'] ) ? absint( $sendforce_log_settings['log_retention_days'] ) : 30;
$sendforce_log_next_cleanup_ts = wp_next_scheduled( 'sendforce_cleanup_logs' );
$sendforce_log_next_cleanup    = $sendforce_log_next_cleanup_ts ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $sendforce_log_next_cleanup_ts ) : __( 'Not scheduled', 'sendforce-mail-relay' );
$sendforce_log_settings_url    = admin_url( 'admin.php?page=sendforce-mail-relay&tab=settings' );

// Count by status.
global $wpdb;
$sendforce_total_all    = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sendforce_logs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$sendforce_total_sent   = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sendforce_logs WHERE status = 'sent'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$sendforce_total_failed = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sendforce_logs WHERE status = 'failed'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
?>

<?php
$sendforce_export_base = wp_nonce_url(
	add_query_arg(
		array_filter(
			array(
				'action'    => 'sendforce_export_logs',
				'status'    => $status,
				's'         => $search,
				'date_from' => $sendforce_date_from,
				'date_to'   => $sendforce_date_to,
				'provider'  => $sendforce_provider,
			)
		),
		admin_url( 'admin-post.php' )
	),
	'sendforce_export_logs'
);
?>
<div class="sendforce-page-header">
	<div>
		<h1><?php esc_html_e( 'Email Log', 'sendforce-mail-relay' ); ?></h1>
		<p class="sendforce-subtitle"><?php esc_html_e( 'View all emails sent from your WordPress site', 'sendforce-mail-relay' ); ?></p>
	</div>
	<div class="sendforce-page-header-actions">
		<a href="<?php echo esc_url( add_query_arg( 'format', 'csv', $sendforce_export_base ) ); ?>" class="sendforce-btn sendforce-btn-sm sendforce-btn-outline">
			<span class="dashicons dashicons-download"></span>
			<?php esc_html_e( 'Export CSV', 'sendforce-mail-relay' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'format', 'json', $sendforce_export_base ) ); ?>" class="sendforce-btn sendforce-btn-sm sendforce-btn-outline">
			<span class="dashicons dashicons-media-code"></span>
			<?php esc_html_e( 'Export JSON', 'sendforce-mail-relay' ); ?>
		</a>
	</div>
</div>

<div class="sendforce-card">
	<div class="sendforce-card-header sendforce-log-header">
		<div class="sendforce-filter-tabs">
			<a href="<?php echo esc_url( $sendforce_base_url . $sendforce_date_query ); ?>" class="sendforce-filter-tab <?php echo empty( $status ) ? 'active' : ''; ?>">
				<?php esc_html_e( 'All', 'sendforce-mail-relay' ); ?> (<?php echo esc_html( $sendforce_total_all ); ?>)
			</a>
			<a href="<?php echo esc_url( $sendforce_base_url . '&status=sent' . $sendforce_date_query ); ?>" class="sendforce-filter-tab <?php echo 'sent' === $status ? 'active' : ''; ?>">
				<?php esc_html_e( 'Sent', 'sendforce-mail-relay' ); ?> (<?php echo esc_html( $sendforce_total_sent ); ?>)
			</a>
			<a href="<?php echo esc_url( $sendforce_base_url . '&status=failed' . $sendforce_date_query ); ?>" class="sendforce-filter-tab <?php echo 'failed' === $status ? 'active' : ''; ?>">
				<?php esc_html_e( 'Failed', 'sendforce-mail-relay' ); ?> (<?php echo esc_html( $sendforce_total_failed ); ?>)
			</a>
		</div>
	</div>

	<!-- Auto-clean status row -->
	<div class="sendforce-log-autoclean" id="sendforce-log-autoclean">
		<div class="sendforce-log-autoclean-info">
			<span class="dashicons dashicons-trash"></span>
			<span class="sendforce-log-autoclean-text">
				<?php if ( 0 === $sendforce_log_retention_days ) : ?>
					<strong><?php esc_html_e( 'Auto-clean:', 'sendforce-mail-relay' ); ?></strong>
					<span class="sendforce-log-autoclean-disabled"><?php esc_html_e( 'Disabled — logs are kept forever.', 'sendforce-mail-relay' ); ?></span>
					<a href="<?php echo esc_url( $sendforce_log_settings_url ); ?>"><?php esc_html_e( 'Change in Settings', 'sendforce-mail-relay' ); ?></a>
				<?php else : ?>
					<strong><?php esc_html_e( 'Auto-clean:', 'sendforce-mail-relay' ); ?></strong>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: retention days */
							_n( 'Delete logs older than %d day', 'Delete logs older than %d days', $sendforce_log_retention_days, 'sendforce-mail-relay' ),
							$sendforce_log_retention_days
						)
					);
					?>
					· <?php esc_html_e( 'Next run:', 'sendforce-mail-relay' ); ?> <?php echo esc_html( $sendforce_log_next_cleanup ); ?>
					· <a href="<?php echo esc_url( $sendforce_log_settings_url ); ?>"><?php esc_html_e( 'Change', 'sendforce-mail-relay' ); ?></a>
				<?php endif; ?>
			</span>
		</div>
		<div class="sendforce-log-autoclean-actions">
			<button
				type="button"
				class="sendforce-btn sendforce-btn-sm sendforce-btn-outline"
				id="sendforce-cleanup-now"
				<?php disabled( 0 === $sendforce_log_retention_days ); ?>
			>
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Clean Now', 'sendforce-mail-relay' ); ?>
			</button>
		</div>
	</div>

	<!-- Filter bar: keyword + date range -->
	<form method="get" class="sendforce-log-filter-bar">
		<input type="hidden" name="page" value="sendforce-mail-relay" />
		<input type="hidden" name="tab" value="email-log" />
		<?php if ( $status ) : ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>" />
		<?php endif; ?>

		<div class="sendforce-log-filter-fields">
			<!-- Keyword search -->
			<div class="sendforce-log-filter-group">
				<label class="sendforce-log-filter-label" for="sendforce_log_search">
					<span class="dashicons dashicons-search"></span>
					<?php esc_html_e( 'Keyword', 'sendforce-mail-relay' ); ?>
				</label>
				<input
					type="search"
					id="sendforce_log_search"
					name="s"
					value="<?php echo esc_attr( $search ); ?>"
					class="sendforce-input sendforce-log-filter-input"
					placeholder="<?php esc_attr_e( 'Email or subject...', 'sendforce-mail-relay' ); ?>"
				/>
			</div>

			<!-- Date From -->
			<div class="sendforce-log-filter-group">
				<label class="sendforce-log-filter-label" for="sendforce_date_from">
					<span class="dashicons dashicons-calendar-alt"></span>
					<?php esc_html_e( 'From Date', 'sendforce-mail-relay' ); ?>
				</label>
				<input
					type="date"
					id="sendforce_date_from"
					name="date_from"
					value="<?php echo esc_attr( $sendforce_date_from ); ?>"
					class="sendforce-input sendforce-log-filter-input sendforce-date-input"
					max="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>"
				/>
			</div>

			<!-- Date To -->
			<div class="sendforce-log-filter-group">
				<label class="sendforce-log-filter-label" for="sendforce_date_to">
					<span class="dashicons dashicons-calendar-alt"></span>
					<?php esc_html_e( 'To Date', 'sendforce-mail-relay' ); ?>
				</label>
				<input
					type="date"
					id="sendforce_date_to"
					name="date_to"
					value="<?php echo esc_attr( $sendforce_date_to ); ?>"
					class="sendforce-input sendforce-log-filter-input sendforce-date-input"
					max="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>"
				/>
			</div>

			<!-- Provider (Connection) filter (1.0.30) — only rendered when the
			     log table actually has provider data to choose from. Shows
			     human-readable labels via SendForce_Providers when known
			     (e.g. "Amazon SES" rather than "ses"); falls back to the raw
			     slug for unknown providers added by 3rd-party extensions. -->
			<?php if ( ! empty( $sendforce_log_providers ) ) : ?>
				<div class="sendforce-log-filter-group">
					<label class="sendforce-log-filter-label" for="sendforce_provider">
						<span class="dashicons dashicons-admin-network"></span>
						<?php esc_html_e( 'Connection', 'sendforce-mail-relay' ); ?>
					</label>
					<select
						id="sendforce_provider"
						name="provider"
						class="sendforce-input sendforce-log-filter-input"
					>
						<option value=""><?php esc_html_e( 'All connections', 'sendforce-mail-relay' ); ?></option>
						<?php foreach ( $sendforce_log_providers as $sendforce_p ) :
							$sendforce_label = $sendforce_provider_labels[ $sendforce_p ] ?? ucfirst( $sendforce_p );
							?>
							<option value="<?php echo esc_attr( $sendforce_p ); ?>" <?php selected( $sendforce_provider, $sendforce_p ); ?>>
								<?php echo esc_html( $sendforce_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<!-- Action buttons -->
			<div class="sendforce-log-filter-actions">
				<button type="submit" class="sendforce-btn sendforce-btn-primary sendforce-btn-sm">
					<span class="dashicons dashicons-search"></span>
					<?php esc_html_e( 'Filter', 'sendforce-mail-relay' ); ?>
				</button>
				<?php if ( $sendforce_has_filters || $status ) : ?>
					<a href="<?php echo esc_url( $sendforce_base_url . ( $status ? '&status=' . rawurlencode( $status ) : '' ) ); ?>" class="sendforce-btn sendforce-btn-sm sendforce-btn-outline">
						<?php esc_html_e( 'Clear', 'sendforce-mail-relay' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( $sendforce_has_filters ) : ?>
			<div class="sendforce-active-filters">
				<?php if ( $search ) : ?>
					<span class="sendforce-active-filter-chip">
						<?php esc_html_e( 'Keyword:', 'sendforce-mail-relay' ); ?> <strong><?php echo esc_html( $search ); ?></strong>
					</span>
				<?php endif; ?>
				<?php if ( $sendforce_date_from ) : ?>
					<span class="sendforce-active-filter-chip">
						<?php esc_html_e( 'From:', 'sendforce-mail-relay' ); ?> <strong><?php echo esc_html( wp_date( 'M j, Y', strtotime( $sendforce_date_from ) ) ); ?></strong>
					</span>
				<?php endif; ?>
				<?php if ( $sendforce_date_to ) : ?>
					<span class="sendforce-active-filter-chip">
						<?php esc_html_e( 'To:', 'sendforce-mail-relay' ); ?> <strong><?php echo esc_html( wp_date( 'M j, Y', strtotime( $sendforce_date_to ) ) ); ?></strong>
					</span>
				<?php endif; ?>
				<span class="sendforce-text-muted sendforce-filter-result-count">
					<?php /* translators: %d: number of results */ ?>
					<?php echo esc_html( sprintf( _n( '%d result', '%d results', $sendforce_total, 'sendforce-mail-relay' ), $sendforce_total ) ); ?>
				</span>
			</div>
		<?php endif; ?>
	</form>

	<?php if ( empty( $sendforce_items ) ) : ?>
		<div class="sendforce-empty-state">
			<span class="dashicons dashicons-email"></span>
			<p><?php esc_html_e( 'No email logs found.', 'sendforce-mail-relay' ); ?></p>
		</div>
	<?php else : ?>
		<!-- Bulk delete form -->
		<form method="post" id="sendforce-log-bulk-form">
			<?php wp_nonce_field( 'sendforce_log_bulk', 'sendforce_log_nonce' ); ?>
			<input type="hidden" name="sendforce_log_action" value="delete" />

			<div class="sendforce-table-wrap">
				<table class="sendforce-table">
					<thead>
						<tr>
							<th class="sendforce-th-cb"><input type="checkbox" id="sendforce-check-all" /></th>
							<th><?php esc_html_e( 'Recipient', 'sendforce-mail-relay' ); ?></th>
							<th><?php esc_html_e( 'Subject', 'sendforce-mail-relay' ); ?></th>
							<th><?php esc_html_e( 'Status', 'sendforce-mail-relay' ); ?></th>
							<th><?php esc_html_e( 'Date', 'sendforce-mail-relay' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'sendforce-mail-relay' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sendforce_items as $sendforce_item ) :
							$sendforce_matched_rule = '';
							if ( ! empty( $sendforce_item->debug_data ) ) {
								$sendforce_debug = json_decode( $sendforce_item->debug_data, true );
								if ( is_array( $sendforce_debug ) && ! empty( $sendforce_debug['matched_rule'] ) ) {
									$sendforce_matched_rule = (string) $sendforce_debug['matched_rule'];
								}
							}
							?>
							<tr>
								<td class="sendforce-td-cb"><input type="checkbox" name="log_ids[]" value="<?php echo absint( $sendforce_item->id ); ?>" /></td>
								<td class="sendforce-td-email"><?php echo esc_html( $sendforce_item->to_email ); ?></td>
								<td>
									<?php echo esc_html( wp_trim_words( $sendforce_item->subject, 8 ) ); ?>
									<?php if ( $sendforce_matched_rule ) : ?>
										<span class="sendforce-log-rule-badge" title="<?php esc_attr_e( 'This email was routed by a Smart Routing rule', 'sendforce-mail-relay' ); ?>">
											<span class="dashicons dashicons-randomize" aria-hidden="true"></span>
											<?php
											printf(
												/* translators: %s: rule label */
												esc_html__( 'via rule: %s', 'sendforce-mail-relay' ),
												esc_html( $sendforce_matched_rule )
											);
											?>
										</span>
									<?php endif; ?>
								</td>
								<td>
									<span class="sendforce-badge sendforce-badge-<?php echo esc_attr( $sendforce_item->status ); ?>">
										<?php echo esc_html( ucfirst( $sendforce_item->status ) ); ?>
									</span>
								</td>
								<td class="sendforce-td-date">
									<?php
									echo esc_html(
										wp_date(
											'M j, Y g:i a',
											strtotime( $sendforce_item->created_at )
										)
									);
									?>
								</td>
								<td class="sendforce-td-actions">
									<button type="button" class="sendforce-btn sendforce-btn-xs sendforce-btn-outline sendforce-view-log" data-log-id="<?php echo absint( $sendforce_item->id ); ?>" aria-label="<?php esc_attr_e( 'View email details', 'sendforce-mail-relay' ); ?>" title="<?php esc_attr_e( 'View details', 'sendforce-mail-relay' ); ?>">
										<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- Bulk actions bar -->
			<div class="sendforce-bulk-bar">
				<button type="button" id="sendforce-bulk-delete-logs" class="sendforce-btn sendforce-btn-sm sendforce-btn-danger">
					<span class="dashicons dashicons-trash"></span>
					<?php esc_html_e( 'Delete Selected', 'sendforce-mail-relay' ); ?>
				</button>
				<span class="sendforce-text-muted">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: total number of log items */
							_n( '%d item', '%d items', $sendforce_total, 'sendforce-mail-relay' ),
							$sendforce_total
						)
					);
					?>
				</span>
			</div>
		</form>

		<?php if ( $sendforce_total_pages > 1 ) : ?>
			<div class="sendforce-pagination">
				<?php
				$sendforce_paginate_base = add_query_arg(
					array_filter(
						array(
							'paged'     => '%#%',
							'status'    => $status,
							's'         => $search,
							'date_from' => $sendforce_date_from,
							'date_to'   => $sendforce_date_to,
							'provider'  => $sendforce_provider,
						)
					)
				);
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => $sendforce_paginate_base,
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
