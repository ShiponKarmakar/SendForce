<?php
/**
 * Setup screen — what a new install lands on after activation.
 *
 * Leads with migration when another SMTP plugin is active, because someone who
 * already has one configured should not have to retype credentials. The import
 * panels here are the same markup the conflict notice uses, driven by
 * assets/js/conflict-notice.js.
 *
 * Every class here is prefixed `sendforce-ob-` (onboarding). The plugin already
 * has a `.sendforce-setup-card` on the Dashboard tab, and reusing that name
 * dragged in its gradient and flex layout.
 *
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sendforce_admin      = new SendForce_Admin();
$sendforce_others     = $sendforce_admin->get_active_smtp_plugins();
$sendforce_importable = array_filter( $sendforce_others, function ( $i ) { return $i['importable']; } );
$sendforce_other_only = array_filter( $sendforce_others, function ( $i ) { return ! $i['importable']; } );
$sendforce_conns      = (array) get_option( 'sendforce_connections', array() );
$sendforce_has_conn   = ! empty( $sendforce_conns );

// Has anything been sent yet? Cheap existence check, not a count.
global $wpdb;
$sendforce_logs_table = $wpdb->prefix . 'sendforce_logs';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is $wpdb->prefix + literal.
$sendforce_has_sent = (bool) $wpdb->get_var( "SELECT 1 FROM {$sendforce_logs_table} LIMIT 1" );

$sendforce_done_count = ( $sendforce_has_conn ? 1 : 0 ) + ( $sendforce_has_sent ? 1 : 0 );
$sendforce_url        = function ( $tab ) {
	return admin_url( 'admin.php?page=sendforce-mail-relay' . ( $tab ? '&tab=' . $tab : '' ) );
};

// Reaching this screen counts as having been offered setup, so a later
// deactivate/reactivate does not hijack the admin again (see sendforce_activate).
if ( ! get_option( 'sendforce_setup_done' ) ) {
	update_option( 'sendforce_setup_done', 1, false );
}

// Outcome of an import that just happened on this screen (see ajax_import_run).
// Read once, then cleared, so a later visit does not repeat it.
$sendforce_import_result = get_transient( 'sendforce_import_result_' . get_current_user_id() );
if ( $sendforce_import_result ) {
	delete_transient( 'sendforce_import_result_' . get_current_user_id() );
}

$sendforce_steps = array(
	array(
		'icon'  => 'dashicons-admin-links',
		'title' => __( 'Connect your email provider', 'sendforce-mail-relay' ),
		'desc'  => $sendforce_has_conn
			? sprintf(
				/* translators: %d: number of connections */
				_n( '%d connection is ready to send.', '%d connections are ready to send.', count( $sendforce_conns ), 'sendforce-mail-relay' ),
				count( $sendforce_conns )
			)
			: __( 'Choose SMTP or an API provider such as Amazon SES, Mailgun, SendGrid or Brevo, and enter its credentials.', 'sendforce-mail-relay' ),
		'done'  => $sendforce_has_conn,
		// `from=setup` tells Settings to bring the person back here once the
		// connection saves, so the wizard carries on instead of stranding them.
		'url'   => $sendforce_url( 'settings' ) . ( $sendforce_has_conn ? '' : '&from=setup' ),
		'cta'   => $sendforce_has_conn ? __( 'Review', 'sendforce-mail-relay' ) : __( 'Set up a connection', 'sendforce-mail-relay' ),
		// When there is something to import, that is the better path — one
		// click and no credentials to find — so this one stops competing
		// with it for attention.
		'quiet' => ! empty( $sendforce_importable ),
	),
	array(
		'icon'  => 'dashicons-email-alt',
		'title' => __( 'Send a test email', 'sendforce-mail-relay' ),
		'desc'  => $sendforce_has_sent
			? __( 'Email is flowing through SendForce. You can review every send in the Email Log.', 'sendforce-mail-relay' )
			: __( 'Confirm delivery works before you rely on it — and before you deactivate your old plugin.', 'sendforce-mail-relay' ),
		'done'  => $sendforce_has_sent,
		// Same `from=setup` marker as step 1, so a successful test brings the
		// person back here to the finished state instead of stranding them.
		'url'   => $sendforce_has_sent ? $sendforce_url( 'email-log' ) : $sendforce_url( 'email-test' ) . '&from=setup',
		// There is nothing to test until a connection exists, so this does not
		// take the eye before step 1 is done.
		'quiet' => ! $sendforce_has_conn,
		'cta'   => $sendforce_has_sent ? __( 'View the log', 'sendforce-mail-relay' ) : __( 'Send a test', 'sendforce-mail-relay' ),
	),
);
?>

<div class="sendforce-ob">

	<!-- Hero -->
	<div class="sendforce-ob-hero">
		<span class="sendforce-ob-hero-badge" aria-hidden="true">
			<span class="dashicons dashicons-email-alt"></span>
		</span>
		<div class="sendforce-ob-hero-text">
			<h1><?php esc_html_e( 'Welcome to SendForce', 'sendforce-mail-relay' ); ?></h1>
			<p>
				<?php
				if ( ! empty( $sendforce_importable ) ) {
					esc_html_e( 'Bring your old plugin\'s settings across, connect your provider, and send a test. A couple of minutes, no retyping.', 'sendforce-mail-relay' );
				} else {
					esc_html_e( 'Two steps to reliable email: connect your provider and send a test. It takes a couple of minutes.', 'sendforce-mail-relay' );
				}
				?>
			</p>
		</div>
		<div class="sendforce-ob-progress" role="group" aria-label="<?php esc_attr_e( 'Setup progress', 'sendforce-mail-relay' ); ?>">
			<span class="sendforce-ob-progress-count">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: completed steps, 2: total steps */
						__( '%1$d of %2$d done', 'sendforce-mail-relay' ),
						$sendforce_done_count,
						count( $sendforce_steps )
					)
				);
				?>
			</span>
			<span class="sendforce-ob-progress-track">
				<span class="sendforce-ob-progress-fill" style="width:<?php echo esc_attr( ( $sendforce_done_count / count( $sendforce_steps ) ) * 100 ); ?>%;"></span>
			</span>
		</div>
	</div>

	<?php if ( is_array( $sendforce_import_result ) && ! empty( $sendforce_import_result['imported'] ) ) : ?>
	<!-- Result of the import that just ran, shown after the page reloaded -->
	<div class="sendforce-ob-result" role="status">
		<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
		<div>
			<strong>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of connections */
						_n( 'Imported %d connection.', 'Imported %d connections.', count( $sendforce_import_result['imported'] ), 'sendforce-mail-relay' ),
						count( $sendforce_import_result['imported'] )
					)
				);
				?>
			</strong>
			<?php if ( ! empty( $sendforce_import_result['rules'] ) ) : ?>
				<span>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of Smart Routing rules */
							_n( 'Created %d Smart Routing rule for its per-sender routing.', 'Created %d Smart Routing rules for its per-sender routing.', (int) $sendforce_import_result['rules'], 'sendforce-mail-relay' ),
							(int) $sendforce_import_result['rules']
						)
					);
					?>
				</span>
			<?php endif; ?>
			<?php if ( ! empty( $sendforce_import_result['routing_off'] ) ) : ?>
				<span class="sendforce-ob-result-warn"><?php esc_html_e( 'Smart Routing is switched off, so those rules will not apply until you turn it on.', 'sendforce-mail-relay' ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $sendforce_import_result['needs_action'] ) ) : ?>
				<span class="sendforce-ob-result-warn">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: comma-separated connection names */
							__( 'Needs your attention: %s', 'sendforce-mail-relay' ),
							implode( ', ', $sendforce_import_result['needs_action'] )
						)
					);
					?>
				</span>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( ! empty( $sendforce_importable ) ) : ?>
	<!-- Migration first: nobody should retype credentials they already have -->
	<section class="sendforce-ob-panel sendforce-ob-panel--accent">
		<header class="sendforce-ob-panel-head">
			<span class="dashicons dashicons-migrate" aria-hidden="true"></span>
			<div>
				<h2><?php esc_html_e( 'Bring your settings across', 'sendforce-mail-relay' ); ?></h2>
				<p><?php esc_html_e( 'We found another SMTP plugin on this site. Import its connections and sender settings instead of typing them again — you see a preview first, and nothing is sent anywhere.', 'sendforce-mail-relay' ); ?></p>
			</div>
		</header>
		<ul class="sendforce-ob-plugins">
			<?php foreach ( $sendforce_importable as $sendforce_slug => $sendforce_info ) : ?>
			<li>
				<?php $sendforce_done = SendForce_Importer::already_imported( $sendforce_slug ); ?>
				<div class="sendforce-ob-plugin-row">
					<span class="sendforce-ob-plugin-name">
						<?php echo esc_html( $sendforce_info['name'] ); ?>
						<?php if ( $sendforce_done ) : ?>
							<span class="sendforce-import-badge is-muted"><?php esc_html_e( 'Imported', 'sendforce-mail-relay' ); ?></span>
						<?php endif; ?>
					</span>
					<span class="sendforce-conflict-actions">
						<button type="button" class="sendforce-conflict-import<?php echo $sendforce_done ? ' is-secondary' : ''; ?>"
							data-source="<?php echo esc_attr( $sendforce_slug ); ?>"
							data-done-label="<?php esc_attr_e( 'Check again', 'sendforce-mail-relay' ); ?>">
							<?php echo $sendforce_done ? esc_html__( 'Check again', 'sendforce-mail-relay' ) : esc_html__( 'Import settings', 'sendforce-mail-relay' ); ?>
						</button>
						<a href="<?php echo esc_url( $sendforce_info['deactivate_url'] ); ?>" class="sendforce-conflict-deactivate">
							<?php esc_html_e( 'Deactivate', 'sendforce-mail-relay' ); ?>
						</a>
					</span>
				</div>
				<div class="sendforce-import-panel" data-source="<?php echo esc_attr( $sendforce_slug ); ?>" aria-live="polite" hidden></div>
			</li>
			<?php endforeach; ?>
		</ul>
		<p class="sendforce-ob-note">
			<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
			<?php esc_html_e( 'Deactivate the other plugin once you have sent a test email from SendForce — two SMTP plugins must never be active together.', 'sendforce-mail-relay' ); ?>
		</p>
	</section>
	<?php endif; ?>

	<!-- The two things that must be true before email works -->
	<section class="sendforce-ob-panel">
		<ol class="sendforce-ob-steps">
			<?php foreach ( $sendforce_steps as $sendforce_i => $sendforce_step ) : ?>
			<li class="<?php echo esc_attr( 'sendforce-ob-step' . ( $sendforce_step['done'] ? ' is-done' : '' ) ); ?>">
				<span class="sendforce-ob-step-mark" aria-hidden="true">
					<?php if ( $sendforce_step['done'] ) : ?>
						<span class="dashicons dashicons-yes"></span>
					<?php else : ?>
						<?php echo esc_html( $sendforce_i + 1 ); ?>
					<?php endif; ?>
				</span>
				<span class="dashicons <?php echo esc_attr( $sendforce_step['icon'] ); ?> sendforce-ob-step-icon" aria-hidden="true"></span>
				<div class="sendforce-ob-step-text">
					<h3>
						<?php echo esc_html( $sendforce_step['title'] ); ?>
						<?php if ( $sendforce_step['done'] ) : ?>
							<span class="sendforce-ob-pill"><?php esc_html_e( 'Done', 'sendforce-mail-relay' ); ?></span>
						<?php endif; ?>
					</h3>
					<p><?php echo esc_html( $sendforce_step['desc'] ); ?></p>
				</div>
				<a href="<?php echo esc_url( $sendforce_step['url'] ); ?>" class="sendforce-btn <?php echo ( $sendforce_step['done'] || ! empty( $sendforce_step['quiet'] ) ) ? '' : 'sendforce-btn-primary'; ?>">
					<?php echo esc_html( $sendforce_step['cta'] ); ?>
				</a>
			</li>
			<?php endforeach; ?>
		</ol>
	</section>

	<?php if ( ! empty( $sendforce_other_only ) ) : ?>
	<!-- Active SMTP plugins we cannot import from: offer deactivation only -->
	<section class="sendforce-ob-panel sendforce-ob-panel--warn">
		<header class="sendforce-ob-panel-head">
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<div>
				<h2><?php esc_html_e( 'Other email plugins found', 'sendforce-mail-relay' ); ?></h2>
				<p><?php esc_html_e( 'SendForce cannot import from these yet. Deactivate them before sending through SendForce, or they will compete for every email.', 'sendforce-mail-relay' ); ?></p>
			</div>
		</header>
		<ul class="sendforce-ob-plugins">
			<?php foreach ( $sendforce_other_only as $sendforce_info ) : ?>
			<li>
				<div class="sendforce-ob-plugin-row">
					<span class="sendforce-ob-plugin-name"><?php echo esc_html( $sendforce_info['name'] ); ?></span>
					<span class="sendforce-conflict-actions">
						<a href="<?php echo esc_url( $sendforce_info['deactivate_url'] ); ?>" class="sendforce-conflict-deactivate">
							<?php esc_html_e( 'Deactivate', 'sendforce-mail-relay' ); ?>
						</a>
					</span>
				</div>
			</li>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php endif; ?>

	<?php if ( $sendforce_done_count === count( $sendforce_steps ) ) : ?>
	<!-- The payoff: both steps done. Reached by returning here after the test. -->
	<section class="sendforce-ob-complete">
		<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
		<h2><?php esc_html_e( 'You\'re all set', 'sendforce-mail-relay' ); ?></h2>
		<p>
			<?php
			if ( ! empty( $sendforce_importable ) ) {
				esc_html_e( 'SendForce is handling your site\'s email. Now deactivate your old SMTP plugin — two of them must never run together.', 'sendforce-mail-relay' );
			} else {
				esc_html_e( 'SendForce is handling your site\'s email, and every send is recorded in the Email Log.', 'sendforce-mail-relay' );
			}
			?>
		</p>
		<a href="<?php echo esc_url( $sendforce_url( '' ) ); ?>" class="sendforce-btn sendforce-btn-primary">
			<?php esc_html_e( 'Go to the dashboard', 'sendforce-mail-relay' ); ?>
		</a>
	</section>

	<!-- Shown once it is a reward rather than a distraction -->
	<section class="sendforce-ob-next">
		<h2><?php esc_html_e( 'Then explore', 'sendforce-mail-relay' ); ?></h2>
		<div class="sendforce-ob-next-grid">
			<?php
			$sendforce_next = array(
				array( 'dashicons-randomize', __( 'Smart Routing', 'sendforce-mail-relay' ), __( 'Send receipts and newsletters through different providers.', 'sendforce-mail-relay' ), 'routing' ),
				array( 'dashicons-list-view', __( 'Email Log', 'sendforce-mail-relay' ), __( 'Every send, with the provider response when one fails.', 'sendforce-mail-relay' ), 'email-log' ),
				array( 'dashicons-update', __( 'Email Queue', 'sendforce-mail-relay' ), __( 'Send in the background so checkout and forms stay fast.', 'sendforce-mail-relay' ), 'queue' ),
				array( 'dashicons-bell', __( 'Alerts', 'sendforce-mail-relay' ), __( 'Get told when sending starts failing, before customers notice.', 'sendforce-mail-relay' ), 'alerts' ),
			);
			foreach ( $sendforce_next as $sendforce_card ) :
				?>
				<a class="sendforce-ob-next-card" href="<?php echo esc_url( $sendforce_url( $sendforce_card[3] ) ); ?>">
					<span class="dashicons <?php echo esc_attr( $sendforce_card[0] ); ?>" aria-hidden="true"></span>
					<strong><?php echo esc_html( $sendforce_card[1] ); ?></strong>
					<span><?php echo esc_html( $sendforce_card[2] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php else : ?>
	<p class="sendforce-ob-skip">
		<a href="<?php echo esc_url( $sendforce_url( '' ) ); ?>"><?php esc_html_e( 'Skip for now and go to the dashboard', 'sendforce-mail-relay' ); ?></a>
	</p>
	<?php endif; ?>

</div>
