<?php
/**
 * Routing tab — Smart Routing Rules.
 *
 * Inline editing UX (1.0.19): every rule is a card on this page that's
 * always editable; no modal. JS handles per-card save / reorder / add.
 *
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$sendforce_routes        = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sendforce_routes ORDER BY priority ASC, id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
$sendforce_connections   = (array) get_option( 'sendforce_connections', array() );
$sendforce_known_plugins = SendForce_Router::known_source_plugins();
$sendforce_routing_on    = SendForce_Router::is_routing_enabled();
$sendforce_total_routes  = count( $sendforce_routes );
?>

<div class="sendforce-page-header">
	<div>
		<h1><?php esc_html_e( 'Smart Routing Rules', 'sendforce-mail-relay' ); ?></h1>
		<p class="sendforce-subtitle"><?php esc_html_e( 'Send different emails through different connections based on rules. Rules evaluate top-down — first match wins.', 'sendforce-mail-relay' ); ?></p>
	</div>
	<div class="sendforce-page-header-actions">
		<button type="button" id="sendforce-add-route" class="sendforce-btn sendforce-btn-primary"<?php echo empty( $sendforce_connections ) ? ' disabled' : ''; ?>>
			<span class="dashicons dashicons-plus-alt2"></span>
			<?php esc_html_e( 'Add Rule', 'sendforce-mail-relay' ); ?>
		</button>
	</div>
</div>

<?php if ( empty( $sendforce_connections ) ) : ?>
	<div class="sendforce-card">
		<div class="sendforce-card-body">
			<p><?php esc_html_e( 'You need at least one configured email connection before you can add routing rules. Go to Settings → Add Connection.', 'sendforce-mail-relay' ); ?></p>
		</div>
	</div>
<?php endif; ?>

<!-- Master Smart Routing toggle -->
<div class="sendforce-card sendforce-route-master" id="sendforce-route-master">
	<div class="sendforce-route-master-inner">
		<label class="sendforce-toggle-label sendforce-route-master-toggle">
			<input type="checkbox" id="sendforce-routing-enabled" class="sendforce-toggle-input" <?php checked( $sendforce_routing_on ); ?> />
			<span class="sendforce-toggle"></span>
			<span class="sendforce-route-master-text">
				<strong><?php esc_html_e( 'Enable Smart Routing', 'sendforce-mail-relay' ); ?></strong>
				<span class="sendforce-text-muted"><?php esc_html_e( 'When off, all emails go through your Primary connection — your rules are kept but ignored.', 'sendforce-mail-relay' ); ?></span>
			</span>
		</label>
		<span class="sendforce-route-master-status<?php echo esc_attr( $sendforce_routing_on ? ' is-on' : ' is-off' ); ?>" id="sendforce-routing-status">
			<?php echo $sendforce_routing_on ? esc_html__( 'Active', 'sendforce-mail-relay' ) : esc_html__( 'Paused', 'sendforce-mail-relay' ); ?>
		</span>
	</div>
</div>

<!-- Active rules — inline editable cards. -->
<div id="sendforce-routes-list" class="sendforce-routes-list" data-route-count="<?php echo absint( $sendforce_total_routes ); ?>">

	<?php if ( empty( $sendforce_routes ) ) : ?>
		<div class="sendforce-card sendforce-route-empty" id="sendforce-route-empty">
			<div class="sendforce-route-empty-inner">
				<span class="dashicons dashicons-randomize"></span>
				<h2><?php esc_html_e( 'No routing rules yet', 'sendforce-mail-relay' ); ?></h2>
				<p class="sendforce-text-muted"><?php esc_html_e( 'Without rules, all emails go through your Primary connection. Add a rule to route specific emails (e.g. WooCommerce orders, @gmail.com recipients) through a different connection.', 'sendforce-mail-relay' ); ?></p>

				<?php if ( ! empty( $sendforce_connections ) ) : ?>
					<div class="sendforce-route-empty-actions">
						<button type="button" id="sendforce-add-route-empty" class="sendforce-btn sendforce-btn-primary">
							<span class="dashicons dashicons-plus-alt2"></span>
							<?php esc_html_e( 'Add your first rule', 'sendforce-mail-relay' ); ?>
						</button>
					</div>

					<div class="sendforce-route-presets">
						<p class="sendforce-route-presets-label"><?php esc_html_e( 'Or start from a preset:', 'sendforce-mail-relay' ); ?></p>
						<div class="sendforce-route-presets-grid">
							<button type="button" class="sendforce-btn sendforce-btn-outline sendforce-btn-sm sendforce-route-preset" data-preset="woocommerce">
								<span class="dashicons dashicons-cart"></span>
								<?php esc_html_e( 'WooCommerce orders', 'sendforce-mail-relay' ); ?>
							</button>
							<button type="button" class="sendforce-btn sendforce-btn-outline sendforce-btn-sm sendforce-route-preset" data-preset="gmail">
								<span class="dashicons dashicons-email-alt"></span>
								<?php esc_html_e( '@gmail.com recipients', 'sendforce-mail-relay' ); ?>
							</button>
							<button type="button" class="sendforce-btn sendforce-btn-outline sendforce-btn-sm sendforce-route-preset" data-preset="forms">
								<span class="dashicons dashicons-feedback"></span>
								<?php esc_html_e( 'Contact form submissions', 'sendforce-mail-relay' ); ?>
							</button>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>

	<?php else : ?>
		<?php
		$sendforce_card_index = 0;
		foreach ( $sendforce_routes as $sendforce_r ) :
			$sendforce_card_index++;
			$sendforce_conds = json_decode( $sendforce_r['conditions'], true );
			if ( ! is_array( $sendforce_conds ) ) {
				$sendforce_conds = array();
			}

			// Resolve current target connection's display label and detect when
			// it points at a deleted / missing connection (silent fall-through risk).
			$sendforce_action_id      = (string) ( $sendforce_r['action_value'] ?? '' );
			$sendforce_action_type    = (string) ( $sendforce_r['action_type'] ?? 'connection' );
			$sendforce_action_label   = '';
			$sendforce_action_missing = false;

			if ( 'roundrobin' === $sendforce_action_type ) {
				$sendforce_rr_ids     = array_filter( array_map( 'trim', explode( ',', $sendforce_action_id ) ) );
				$sendforce_rr_labels  = array();
				$sendforce_rr_missing = array();
				foreach ( $sendforce_rr_ids as $sendforce_rr_id ) {
					$sendforce_rr_label = '';
					if ( 'phpmail' === $sendforce_rr_id ) {
						$sendforce_rr_label = __( 'WordPress phpmail()', 'sendforce-mail-relay' );
					} else {
						foreach ( $sendforce_connections as $sendforce_c ) {
							if ( ( $sendforce_c['id'] ?? '' ) === $sendforce_rr_id ) {
								$sendforce_rr_label = $sendforce_c['label'] ?? $sendforce_c['provider'] ?? __( '(unnamed)', 'sendforce-mail-relay' );
								break;
							}
						}
					}
					if ( '' === $sendforce_rr_label ) {
						$sendforce_action_missing = true;
						$sendforce_rr_missing[]   = $sendforce_rr_id;
					} else {
						$sendforce_rr_labels[] = $sendforce_rr_label;
					}
				}
				$sendforce_action_label = $sendforce_rr_labels
					? __( 'Round-robin:', 'sendforce-mail-relay' ) . ' ' . implode( ', ', $sendforce_rr_labels )
					: '';
			} elseif ( 'phpmail' === $sendforce_action_id ) {
				$sendforce_action_label = __( 'WordPress phpmail()', 'sendforce-mail-relay' );
			} elseif ( '' !== $sendforce_action_id ) {
				foreach ( $sendforce_connections as $sendforce_c ) {
					if ( ( $sendforce_c['id'] ?? '' ) === $sendforce_action_id ) {
						$sendforce_action_label = $sendforce_c['label'] ?? $sendforce_c['provider'] ?? __( '(unnamed)', 'sendforce-mail-relay' );
						break;
					}
				}
				if ( '' === $sendforce_action_label ) {
					$sendforce_action_missing = true;
				}
			}
			?>
			<div class="sendforce-route-card<?php echo empty( $sendforce_r['enabled'] ) ? ' is-disabled' : ''; ?>"
				data-route-id="<?php echo absint( $sendforce_r['id'] ); ?>"
				data-route-conditions="<?php echo esc_attr( wp_json_encode( $sendforce_conds ) ); ?>">

				<div class="sendforce-route-card-header">
					<span class="sendforce-route-card-index">#<?php echo absint( $sendforce_card_index ); ?></span>
					<span class="sendforce-route-card-title">
						<?php echo esc_html( $sendforce_r['label'] ?: __( '(unnamed rule)', 'sendforce-mail-relay' ) ); ?>
					</span>
					<?php if ( $sendforce_action_label ) : ?>
						<span class="sendforce-route-card-target">
							<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
							<?php echo esc_html( $sendforce_action_label ); ?>
						</span>
					<?php endif; ?>
					<?php if ( $sendforce_action_missing ) : ?>
						<span class="sendforce-route-card-warning" title="<?php esc_attr_e( 'This rule points at a connection that has been deleted. The rule is silently skipped at runtime — pick a new target connection or disable the rule.', 'sendforce-mail-relay' ); ?>">
							<span class="dashicons dashicons-warning" aria-hidden="true"></span>
							<?php esc_html_e( 'Connection missing', 'sendforce-mail-relay' ); ?>
						</span>
					<?php endif; ?>
					<span class="sendforce-route-card-flex"></span>
					<span class="sendforce-route-card-saved-flag" aria-live="polite"></span>
					<button type="button" class="sendforce-route-card-btn sendforce-route-up" title="<?php esc_attr_e( 'Move up', 'sendforce-mail-relay' ); ?>" aria-label="<?php esc_attr_e( 'Move up', 'sendforce-mail-relay' ); ?>">
						<span class="dashicons dashicons-arrow-up-alt2"></span>
					</button>
					<button type="button" class="sendforce-route-card-btn sendforce-route-down" title="<?php esc_attr_e( 'Move down', 'sendforce-mail-relay' ); ?>" aria-label="<?php esc_attr_e( 'Move down', 'sendforce-mail-relay' ); ?>">
						<span class="dashicons dashicons-arrow-down-alt2"></span>
					</button>
					<button type="button" class="sendforce-route-card-btn sendforce-route-delete" title="<?php esc_attr_e( 'Delete rule', 'sendforce-mail-relay' ); ?>" aria-label="<?php esc_attr_e( 'Delete rule', 'sendforce-mail-relay' ); ?>">
						<span class="dashicons dashicons-trash"></span>
					</button>
				</div>

				<div class="sendforce-route-card-body">
					<div class="sendforce-field">
						<label class="sendforce-label"><?php esc_html_e( 'Rule Label', 'sendforce-mail-relay' ); ?></label>
						<input type="text" class="sendforce-input sendforce-route-label-input" value="<?php echo esc_attr( $sendforce_r['label'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. WooCommerce order emails via SES', 'sendforce-mail-relay' ); ?>" />
					</div>

					<div class="sendforce-field" style="margin-top:14px;">
						<label class="sendforce-label"><?php esc_html_e( 'Conditions (ALL must match)', 'sendforce-mail-relay' ); ?></label>
						<div class="sendforce-route-conditions"></div>
						<button type="button" class="sendforce-btn sendforce-btn-outline sendforce-btn-sm sendforce-route-add-cond" style="margin-top:8px;">
							<span class="dashicons dashicons-plus"></span>
							<?php esc_html_e( 'Add Condition', 'sendforce-mail-relay' ); ?>
						</button>
					</div>

					<div class="sendforce-route-row">
						<div class="sendforce-field sendforce-route-row-action">
							<label class="sendforce-label"><?php esc_html_e( 'Then send via', 'sendforce-mail-relay' ); ?></label>
							<select class="sendforce-select sendforce-select-full sendforce-route-action-input">
								<?php foreach ( $sendforce_connections as $sendforce_c ) : ?>
									<option value="<?php echo esc_attr( $sendforce_c['id'] ?? '' ); ?>" <?php selected( $sendforce_action_id, $sendforce_c['id'] ?? '' ); ?>>
										<?php echo esc_html( $sendforce_c['label'] ?? $sendforce_c['provider'] ?? __( '(unnamed)', 'sendforce-mail-relay' ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="sendforce-field sendforce-route-row-toggle">
							<label class="sendforce-label">&nbsp;</label>
							<label class="sendforce-toggle-label">
								<input type="checkbox" class="sendforce-toggle-input sendforce-route-enabled-input" <?php checked( ! empty( $sendforce_r['enabled'] ) ); ?> />
								<span class="sendforce-toggle"></span>
								<span><?php esc_html_e( 'Enabled', 'sendforce-mail-relay' ); ?></span>
							</label>
						</div>
					</div>
				</div>

				<div class="sendforce-route-card-footer">
					<span class="sendforce-route-dirty-hint"><?php esc_html_e( 'Unsaved changes', 'sendforce-mail-relay' ); ?></span>
					<button type="button" class="sendforce-btn sendforce-btn-outline sendforce-btn-sm sendforce-route-revert">
						<?php esc_html_e( 'Revert', 'sendforce-mail-relay' ); ?>
					</button>
					<button type="button" class="sendforce-btn sendforce-btn-primary sendforce-btn-sm sendforce-route-save">
						<span class="dashicons dashicons-saved"></span>
						<?php esc_html_e( 'Save', 'sendforce-mail-relay' ); ?>
					</button>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>

</div>

<!-- Test Routing Tool -->
<div class="sendforce-card" style="margin-top:20px;">
	<div class="sendforce-card-header">
		<h2><?php esc_html_e( 'Test Routing', 'sendforce-mail-relay' ); ?></h2>
		<span class="sendforce-text-muted"><?php esc_html_e( 'Paste a sample email to see which rule (if any) would match.', 'sendforce-mail-relay' ); ?></span>
	</div>
	<div class="sendforce-card-body">
		<div class="sendforce-form-grid">
			<div class="sendforce-field">
				<label class="sendforce-label"><?php esc_html_e( 'Recipient', 'sendforce-mail-relay' ); ?></label>
				<input type="email" id="sendforce-test-route-to" class="sendforce-input" placeholder="customer@gmail.com" />
			</div>
			<div class="sendforce-field">
				<label class="sendforce-label"><?php esc_html_e( 'From email (optional)', 'sendforce-mail-relay' ); ?></label>
				<input type="email" id="sendforce-test-route-from" class="sendforce-input" placeholder="shop@example.com" />
			</div>
			<div class="sendforce-field">
				<label class="sendforce-label"><?php esc_html_e( 'Subject', 'sendforce-mail-relay' ); ?></label>
				<input type="text" id="sendforce-test-route-subject" class="sendforce-input" placeholder="<?php esc_attr_e( 'Your order is on the way', 'sendforce-mail-relay' ); ?>" />
			</div>
			<div class="sendforce-field">
				<label class="sendforce-label"><?php esc_html_e( 'Source plugin (simulated)', 'sendforce-mail-relay' ); ?></label>
				<select id="sendforce-test-route-source" class="sendforce-select">
					<option value=""><?php esc_html_e( '— auto-detect —', 'sendforce-mail-relay' ); ?></option>
					<?php foreach ( $sendforce_known_plugins as $sendforce_slug => $sendforce_label ) : ?>
						<option value="<?php echo esc_attr( $sendforce_slug ); ?>"><?php echo esc_html( $sendforce_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<button type="button" id="sendforce-test-route-btn" class="sendforce-btn sendforce-btn-primary" style="margin-top:14px;">
			<?php esc_html_e( 'Run Test', 'sendforce-mail-relay' ); ?>
		</button>
		<div id="sendforce-test-route-result" style="margin-top:14px;"></div>
	</div>
</div>

<?php
// Bootstrap JS data — list of available connections + condition types + known source plugins.
$sendforce_js_conns = array();
foreach ( $sendforce_connections as $sendforce_c ) {
	$sendforce_js_conns[] = array(
		'id'    => $sendforce_c['id'] ?? '',
		'label' => $sendforce_c['label'] ?? $sendforce_c['provider'] ?? __( '(unnamed)', 'sendforce-mail-relay' ),
	);
}

wp_add_inline_script(
	'sendforce-admin',
	'var sendForceRoutingData = ' . wp_json_encode( array(
		'connections'   => $sendforce_js_conns,
		'sourcePlugins' => $sendforce_known_plugins,
		'routingOn'     => $sendforce_routing_on,
		// Translatable strings for routing.js (a separate script that can't see
		// admin.js's sfT helper). Read via the local rT() helper in routing.js.
		'strings'       => array(
			// Condition type labels.
			'cond_to_domain'        => __( 'Recipient domain', 'sendforce-mail-relay' ),
			'cond_to_pattern'       => __( 'Recipient pattern (glob: * and ?)', 'sendforce-mail-relay' ),
			'cond_from_email'       => __( 'From email', 'sendforce-mail-relay' ),
			'cond_from_domain'      => __( 'From domain', 'sendforce-mail-relay' ),
			'cond_source_plugin'    => __( 'Source plugin', 'sendforce-mail-relay' ),
			'cond_subject_contains' => __( 'Subject contains', 'sendforce-mail-relay' ),
			'cond_time_between'     => __( 'Time of day (hour-hour, 24h)', 'sendforce-mail-relay' ),
			'cond_weekday'          => __( 'Weekday(s) (1=Mon … 7=Sun)', 'sendforce-mail-relay' ),
			// Operator labels.
			'op_equals'             => __( 'equals', 'sendforce-mail-relay' ),
			'op_not_equals'         => __( 'not equals', 'sendforce-mail-relay' ),
			'op_contains'           => __( 'contains', 'sendforce-mail-relay' ),
			'op_starts_with'        => __( 'starts with', 'sendforce-mail-relay' ),
			'op_ends_with'          => __( 'ends with', 'sendforce-mail-relay' ),
			// Preset rule labels.
			'preset_woocommerce'    => __( 'WooCommerce orders', 'sendforce-mail-relay' ),
			'preset_gmail'          => __( '@gmail.com recipients', 'sendforce-mail-relay' ),
			'preset_forms'          => __( 'Contact form submissions', 'sendforce-mail-relay' ),
			// Buttons / statuses / modal.
			'saving'                => __( 'Saving…', 'sendforce-mail-relay' ),
			'testing'               => __( 'Testing…', 'sendforce-mail-relay' ),
			'run_test'              => __( 'Run Test', 'sendforce-mail-relay' ),
			'discard'               => __( 'Discard', 'sendforce-mail-relay' ),
			'new_rule'              => __( 'New rule', 'sendforce-mail-relay' ),
			'unnamed_rule'          => __( '(unnamed rule)', 'sendforce-mail-relay' ),
			'del_rule_title'        => __( 'Delete routing rule?', 'sendforce-mail-relay' ),
			'del_rule_msg'          => __( 'This rule will be removed permanently. Emails matching its conditions will fall back to your Primary connection.', 'sendforce-mail-relay' ),
			'del_rule_confirm'      => __( 'Delete rule', 'sendforce-mail-relay' ),
			// Dialog defaults + errors.
			'are_you_sure'          => __( 'Are you sure?', 'sendforce-mail-relay' ),
			'confirm'               => __( 'Confirm', 'sendforce-mail-relay' ),
			'cancel'                => __( 'Cancel', 'sendforce-mail-relay' ),
			'notice'                => __( 'Notice', 'sendforce-mail-relay' ),
			'ok'                    => __( 'OK', 'sendforce-mail-relay' ),
			'save_failed'           => __( 'Save failed', 'sendforce-mail-relay' ),
			'err_save_rule'         => __( 'Failed to save rule.', 'sendforce-mail-relay' ),
			'err_test_failed'       => __( 'Test failed.', 'sendforce-mail-relay' ),
		),
	) ) . ';',
	'before'
);
