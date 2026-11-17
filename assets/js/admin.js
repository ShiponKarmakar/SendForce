/**
 * SendForce Mail Relay Admin JavaScript
 *
 * @package SendForce
 */

/* global jQuery, sendForce */
(function( $ ) {
	'use strict';

	// i18n helper — read a localized string by key (registered via
	// wp_localize_script() in class-sendforce-admin.php as sendForce.strings),
	// falling back to the bundled English text when a key is unavailable.
	// Trailing arguments replace %s / %d placeholders in order, so plural and
	// interpolated strings stay translatable.
	// The Setup screen links here with ?from=setup. These two helpers take the
	// person back when the step they came to do is finished.
	function sfCameFromSetup() {
		return 'setup' === new URLSearchParams( window.location.search ).get( 'from' );
	}

	function sfSetupResumeUrl() {
		// `resume` tells the Setup screen this is a deliberate return, so it
		// renders instead of redirecting to the Dashboard.
		return 'admin.php?page=sendforce-mail-relay&tab=setup&resume=1';
	}

	function sfT( key, fallback ) {
		var str  = ( window.sendForce && sendForce.strings && sendForce.strings[ key ] ) || fallback;
		var args = Array.prototype.slice.call( arguments, 2 );
		return String( str ).replace( /%[sd]/g, function() {
			return args.length ? args.shift() : '';
		} );
	}

	$( document ).ready( function() {

		// =========================================================
		// CONNECTION FORM — provider grid expand/collapse
		// =========================================================

		(function() {
			var $wrap = $( '#sendforce-provider-grid-wrap' );
			var $btn  = $( '#sendforce-provider-expand' );
			if ( $wrap.length && $btn.length ) {
				// The wrap is rendered collapsed server-side (see settings-tab.php)
				// so there's no flash of all-providers before JS runs. Re-assert
				// here as a no-op safety net in case the markup ever changes.
				$wrap.addClass( 'collapsed' );

				$btn.on( 'click', function() {
					var $extras = $wrap.find( '.sendforce-provider-card[data-extra="1"]' );
					if ( $wrap.hasClass( 'collapsed' ) ) {
						$wrap.removeClass( 'collapsed' ).addClass( 'expanded' );
						$extras.css({ opacity: 0, transform: 'translateY(-10px)' });
						$extras.each( function( i ) {
							var $card = $( this );
							setTimeout( function() {
								$card.css({ transition: 'opacity 0.3s ease, transform 0.3s ease', opacity: 1, transform: 'translateY(0)' });
							}, i * 40 );
						});
						$btn.find( '.sendforce-expand-text' ).text( sfT( 'show_less', 'Show Less' ) );
					} else {
						$extras.css({ transition: 'opacity 0.2s ease, transform 0.2s ease', opacity: 0, transform: 'translateY(-10px)' });
						setTimeout( function() {
							$wrap.removeClass( 'expanded' ).addClass( 'collapsed' );
							$extras.css({ opacity: '', transform: '', transition: '' });
						}, 220 );
						$btn.find( '.sendforce-expand-text' ).text( sfT( 'show_all_providers', 'Show All Providers' ) );
					}
				});

				// If an extra provider is already active (editing), auto-expand.
				if ( $wrap.find( '.sendforce-provider-card.active[data-extra="1"]' ).length ) {
					$btn.trigger( 'click' );
				}
			}
		})();

		// =========================================================
		// CONNECTION FORM — provider card + connection type tabs
		// =========================================================

		// Provider card click (inside the connection form).
		$( document ).on( 'click', '.sendforce-provider-card', function() {
			var $card    = $( this );
			var provider = $card.data( 'provider' );
			var hasApi   = '1' === String( $card.data( 'api' ) );

			// Update active state.
			$( '.sendforce-provider-card' ).removeClass( 'active' );
			$card.addClass( 'active' );

			// Set hidden input.
			$( '#sf-conn-provider' ).val( provider );

			// Auto-fill connection name from provider label if field is empty
			// or if it currently contains another provider's name (was auto-filled, not manually typed).
			var $label       = $( '#sf-conn-label' );
			var currentLabel = $label.val().trim();
			var allProviderNames = [];
			$( '.sendforce-provider-name' ).each( function() {
				allProviderNames.push( $( this ).text().trim() );
			});
			var wasAutoFilled = ( '' === currentLabel ) || ( allProviderNames.indexOf( currentLabel ) !== -1 );
			if ( wasAutoFilled ) {
				var providerName = $card.find( '.sendforce-provider-name' ).text().trim();
				if ( providerName ) {
					$label.val( providerName );
				}
			}

			// PHP Mail: no SMTP/API config needed — hide everything below the grid.
			if ( 'phpmail' === provider ) {
				$( '.sendforce-connection-type-card' ).slideUp( 200 );
				$( '.sendforce-smtp-config-form' ).slideUp( 200 );
				$( '.sendforce-api-config-form' ).slideUp( 200 );
				$( '#sf-conn-type' ).val( 'smtp' );
				return;
			}

			// Show/hide connection type tabs.
			if ( hasApi ) {
				$( '.sendforce-connection-type-card' ).slideDown( 200 );
			} else {
				$( '.sendforce-connection-type-card' ).slideUp( 200 );
				$( '#sf-conn-type' ).val( 'smtp' );
				$( '.sendforce-conn-tab' ).removeClass( 'active' );
				$( '.sendforce-conn-tab[data-type="smtp"]' ).addClass( 'active' );
			}

			// Ensure SMTP config is visible when switching away from phpmail.
			$( '.sendforce-smtp-config-form' ).show();

			// Show/hide SES SMTP region row.
			$( '.sendforce-ses-region-field' )[ 'ses' === provider ? 'slideDown' : 'slideUp' ]( 200 );

			// SMTP host/port/encryption fields are ALWAYS visible now — users
			// should be able to verify what server they're connecting to,
			// regardless of provider. Known providers pre-fill the fields,
			// and "Other SMTP" leaves them blank for the user to fill in.
			$( '.sendforce-custom-smtp-fields' ).slideDown( 200 );

			if ( 'other' === provider ) {
				// Clear any previous provider's defaults so the form starts
				// fresh — otherwise switching from "Elastic Email" to
				// "Other SMTP" would leave smtp.elasticemail.com/2525 in
				// the host/port fields.
				$( '#sf-conn-host' ).val( '' );
				$( '#sf-conn-port' ).val( 587 );
				$( '#sf-conn-enc' ).val( 'tls' );
				$( '#sf-conn-auth' ).prop( 'checked', true );
				$( '#sf-conn-username' ).val( '' );
				$( '#sf-conn-username-label' ).text( sfT( 'username', 'Username' ) );
				$( '#sf-conn-username-help' ).removeClass( 'sendforce-help-warning' ).hide().text( '' );
				$( '#sf-conn-password-label' ).text( sfT( 'password', 'Password' ) );
				$( '#sf-conn-apikey-label' ).text( sfT( 'api_key', 'API Key' ) );
			} else {

				// Fetch provider defaults via AJAX.
				$.post( sendForce.ajax_url, {
					action:   'sendforce_get_provider',
					nonce:    sendForce.nonce,
					provider: provider
				}, function( response ) {
					if ( response.success ) {
						var data = response.data;
						$( '#sf-conn-host' ).val( data.host );
						$( '#sf-conn-port' ).val( data.port );
						$( '#sf-conn-enc' ).val( data.encryption );
						$( '#sf-conn-auth' ).prop( 'checked', true );

						// Update label text.
						$( '#sf-conn-username-label' ).text( data.username_label || sfT( 'username', 'Username' ) );
						$( '#sf-conn-password-label' ).text( data.password_label || sfT( 'password', 'Password' ) );

						// Username help / default.
						$( '#sf-conn-username-help' ).hide().text( '' );
						if ( data.username_default ) {
							$( '#sf-conn-username' ).val( data.username_default );
							$( '#sf-conn-username-help' )
								.text( sfT( 'default_for_provider', 'Default for this provider: %s', data.username_default ) )
								.show();
						} else if ( data.username_help ) {
							$( '#sf-conn-username' ).val( '' );
							$( '#sf-conn-username-help' )
								.addClass( 'sendforce-help-warning' )
								.text( data.username_help )
								.show();
						} else {
							$( '#sf-conn-username' ).val( '' );
						}

						// API key label.
						if ( data.api_key_label ) {
							$( '#sf-conn-apikey-label' ).text( data.api_key_label );
						}
						// Provider-specific API key hint (format, gotchas, where
						// to find it). Distinct from #sf-conn-apikey-help which
						// shows the "Saved. Leave blank to keep current" notice.
						var $apikeyHint = $( '#sf-conn-apikey-provider-hint' );
						if ( data.api_key_help ) {
							if ( ! $apikeyHint.length ) {
								$apikeyHint = $( '<span id="sf-conn-apikey-provider-hint" class="sendforce-help"></span>' )
									.css({ display: 'block', marginTop: '4px', color: 'var(--sf-text-secondary)' });
								$( '#sf-conn-apikey' ).after( $apikeyHint );
							}
							$apikeyHint.text( data.api_key_help ).show();
						} else if ( $apikeyHint.length ) {
							$apikeyHint.hide();
						}
					}
				});
			}

			// Show/hide provider-specific API extra fields.
			$( '[class*="sf-conn-api-"]' ).hide();
			$( '.sf-conn-api-' + provider ).show();

			// OAuth Connect block — only for Gmail and Microsoft 365.
			$( '.sf-conn-oauth' ).toggle( 'gmail' === provider || 'outlook' === provider );

			sfConnUpdateView();
		});

		// On page load, auto-fill connection name from the default-selected provider.
		(function() {
			var $activeCard = $( '.sendforce-provider-card.active' );
			var $label      = $( '#sf-conn-label' );
			if ( $activeCard.length && ! $label.val().trim() ) {
				var providerName = $activeCard.find( '.sendforce-provider-name' ).text().trim();
				if ( providerName ) {
					$label.val( providerName );
				}
			}
		})();

		// Connection type tab click (inside the connection form).
		$( document ).on( 'click', '.sendforce-conn-tab', function() {
			var type = $( this ).data( 'type' );
			$( '.sendforce-conn-tab' ).removeClass( 'active' );
			$( this ).addClass( 'active' );
			$( '#sf-conn-type' ).val( type );
			sfConnUpdateView();
		});

		function sfConnUpdateView() {
			var type   = $( '#sf-conn-type' ).val();
			var hasApi = $( '.sendforce-connection-type-card' ).is( ':visible' ) ||
			             $( '.sendforce-provider-card.active' ).data( 'api' ) === 1;

			if ( 'api' === type && hasApi ) {
				$( '.sendforce-smtp-config-form' ).slideUp( 200 );
				$( '.sendforce-api-config-form' ).slideDown( 200 );
				$( '#sf-conn-desc' ).text( sfT( 'api_mode_desc', 'Send emails directly via the provider HTTP API. Faster and more reliable than SMTP.' ) );
			} else {
				$( '.sendforce-smtp-config-form' ).slideDown( 200 );
				$( '.sendforce-api-config-form' ).slideUp( 200 );
				$( '#sf-conn-desc' ).text( sfT( 'smtp_mode_desc', 'Send emails via the traditional SMTP protocol.' ) );
			}
		}

		// SES region change updates host field.
		$( document ).on( 'change', '#sf-conn-ses-region, #sf-conn-ses-api-region', function() {
			var region = $( this ).val();
			$( '#sf-conn-host' ).val( 'email-smtp.' + region + '.amazonaws.com' );
			$( '#sf-conn-ses-region' ).val( region );
			$( '#sf-conn-ses-api-region' ).val( region );
		});

		// =========================================================
		// CONNECTION CRUD
		// =========================================================

		// Show Add Connection form.
		$( '#sendforce-add-connection-btn' ).on( 'click', function() {
			sfConnFormReset();
			$( '#sendforce-conn-form-title' ).text( sendForce.strings.add_connection );
			$( '#sendforce-connection-form-card' ).slideDown( 250 );
			$( '#sendforce-connection-form-card' )[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
		});

		// Show Edit Connection form.
		$( document ).on( 'click', '.sendforce-edit-connection', function() {
			var id   = $( this ).data( 'id' );
			var conn = null;

			if ( sendForce.connections ) {
				$.each( sendForce.connections, function( i, c ) {
					if ( c.id === id ) { conn = c; return false; }
				});
			}

			sfConnFormReset();
			$( '#sendforce-conn-form-title' ).text( sendForce.strings.edit_connection );

			if ( conn ) {
				sfConnFormPopulate( conn );
			} else {
				$( '#sf-conn-id' ).val( id );
			}

			$( '#sendforce-connection-form-card' ).slideDown( 250 );
			$( '#sendforce-connection-form-card' )[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
		});

		// Cancel — hide form.
		$( '#sendforce-conn-cancel' ).on( 'click', function() {
			$( '#sendforce-connection-form-card' ).slideUp( 200 );
		});

		// =========================================================
		// GENERIC CONFIRM MODAL
		// sfShowConfirmModal( opts ) — opts: { title, message, confirmText, showIcon, onConfirm }
		// =========================================================

		var pendingConfirmCallback = null;

		function sfShowConfirmModal( opts ) {
			opts = $.extend({
				title:       sfT( 'confirm', 'Confirm' ),
				message:     sfT( 'confirm_generic', 'Are you sure? This action cannot be undone.' ),
				confirmText: sfT( 'delete', 'Delete' ),
				showIcon:    true,
				onConfirm:   null
			}, opts || {} );

			$( '#sendforce-confirm-title' ).text( opts.title );
			$( '.sendforce-confirm-message' ).text( opts.message );
			$( '.sendforce-confirm-btn-label' ).text( opts.confirmText );
			$( '#sendforce-confirm-btn-icon' ).toggle( opts.showIcon );

			pendingConfirmCallback = opts.onConfirm;

			$( '#sendforce-confirm-modal' ).show();
			setTimeout( function() {
				$( '#sendforce-confirm-delete' ).focus();
			}, 50 );
		}

		function sfHideConfirmModal() {
			$( '#sendforce-confirm-modal' ).hide();
			pendingConfirmCallback = null;
		}

		// Confirm modal — confirm action.
		$( document ).on( 'click', '#sendforce-confirm-delete', function() {
			var cb = pendingConfirmCallback;
			sfHideConfirmModal();
			if ( typeof cb === 'function' ) { cb(); }
		});

		// Confirm modal — cancel / overlay / Escape.
		$( document ).on( 'click', '#sendforce-confirm-cancel, .sendforce-confirm-overlay', function() {
			sfHideConfirmModal();
		});

		$( document ).on( 'keydown', function( e ) {
			if ( 27 === e.which && $( '#sendforce-confirm-modal' ).is( ':visible' ) ) {
				sfHideConfirmModal();
			}
		});

		// ---- Delete connection ----
		$( document ).on( 'click', '.sendforce-delete-connection', function() {
			var $btn = $( this );
			var id   = $btn.data( 'id' );

			sfShowConfirmModal({
				title:       sfT( 'del_conn_title', 'Delete Connection' ),
				message:     sfT( 'del_conn_msg', 'Are you sure you want to delete this connection? This action cannot be undone.' ),
				confirmText: sfT( 'delete', 'Delete' ),
				onConfirm: function() {
					$btn.prop( 'disabled', true ).text( sendForce.strings.deleting );

					$.post( sendForce.ajax_url, {
						action:  'sendforce_delete_connection',
						nonce:   sendForce.nonce,
						conn_id: id
					}, function( response ) {
						if ( response.success ) {
							$( '[data-conn-id="' + id + '"]' ).fadeOut( 200, function() {
								$( this ).remove();
								if ( sendForce.connections ) {
									sendForce.connections = sendForce.connections.filter( function( c ) {
										return c.id !== id;
									});
								}
							});
						} else {
							$btn.prop( 'disabled', false ).text( sfT( 'delete', 'Delete' ) );
							alert( response.data || sfT( 'err_del_conn', 'Error deleting connection.' ) );
						}
					}).fail( function() {
						$btn.prop( 'disabled', false ).text( sfT( 'delete', 'Delete' ) );
					});
				}
			});
		});

		// ---- Bulk delete logs ----
		$( document ).on( 'click', '#sendforce-bulk-delete-logs', function() {
			var $form   = $( '#sendforce-log-bulk-form' );
			var checked = $form.find( 'input[name="log_ids[]"]:checked' );

			if ( checked.length === 0 ) {
				return;
			}

			sfShowConfirmModal({
				title:       sfT( 'del_logs_title', 'Delete Selected Logs' ),
				message:     ( checked.length > 1
					? sfT( 'del_logs_msg_many', 'Are you sure you want to delete %d selected logs? This action cannot be undone.', checked.length )
					: sfT( 'del_logs_msg_one', 'Are you sure you want to delete %d selected log? This action cannot be undone.', checked.length ) ),
				confirmText: sfT( 'delete', 'Delete' ),
				onConfirm: function() {
					$form.submit();
				}
			});
		});

		// ---- Queue item delete ----
		$( document ).on( 'click', '.sendforce-queue-delete-link', function( e ) {
			e.preventDefault();
			var href = $( this ).data( 'href' );

			sfShowConfirmModal({
				title:       sfT( 'del_queue_title', 'Delete Queue Item' ),
				message:     sfT( 'del_queue_msg', 'Are you sure you want to delete this queued email? This action cannot be undone.' ),
				confirmText: sfT( 'delete', 'Delete' ),
				onConfirm: function() {
					window.location.href = href;
				}
			});
		});

		// Live GUID-shape detection for the Client Secret / API Key field.
		// AADSTS7000215 is one of the most common Microsoft 365 setup mistakes:
		// users paste the "Secret ID" column (a GUID) from Azure instead of
		// the "Value" column (the actual secret). Microsoft client secrets
		// are NEVER GUIDs, so we warn the moment a GUID-shaped value is
		// pasted into the Client Secret field for an Outlook connection.
		$( document ).on( 'input paste', '#sf-conn-apikey', function() {
			var $field   = $( this );
			var $warning = $( '#sf-conn-apikey-guid-warning' );
			// Defer one tick so the value reflects the paste.
			setTimeout( function() {
				var value    = ( $field.val() || '' ).trim();
				var provider = ( $( '#sf-conn-provider' ).val() || '' ).toLowerCase();
				var isGuid   = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test( value );
				$warning.toggle( isGuid && ( provider === 'outlook' || provider === 'gmail' ) );
			}, 0 );
		});

		// Save connection.
		$( '#sendforce-conn-save' ).on( 'click', function() {
			var $btn    = $( this );
			var $notice = $( '#sendforce-conn-notice' );
			var savingLabel = sfT( 'saving', 'Saving…' );

			// Busy state: spinner inside the button +
			// inline "Validating data" status text below. The button retains
			// its width via inline-block so the layout doesn't jump.
			$btn.prop( 'disabled', true )
				.html( '<span class="dashicons dashicons-update sendforce-spin" style="vertical-align:middle;margin-right:4px;"></span>' + savingLabel );
			$notice.removeClass( 'success error' )
				.addClass( 'sendforce-notice-validating' )
				.text( sfT( 'validating', 'Validating data. Please wait…' ) )
				.show();

			var data = {
				action:          'sendforce_save_connection',
				nonce:           sendForce.nonce,
				conn_id:         $( '#sf-conn-id' ).val(),
				label:           $( '#sf-conn-label' ).val(),
				provider:        $( '#sf-conn-provider' ).val(),
				connection_type: $( '#sf-conn-type' ).val(),
				host:            $( '#sf-conn-host' ).val(),
				port:            $( '#sf-conn-port' ).val(),
				encryption:      $( '#sf-conn-enc' ).val(),
				auth:            $( '#sf-conn-auth' ).is( ':checked' ) ? 'true' : 'false',
				username:        $( '#sf-conn-username' ).val(),
				password:        $( '#sf-conn-password' ).val(),
				api_key:         $( '#sf-conn-apikey' ).val(),
				ses_region:      $( '#sf-conn-ses-region' ).val() || $( '#sf-conn-ses-api-region' ).val(),
				ses_access_key:  $( '#sf-conn-ses-access-key' ).val(),
				mailgun_domain:  $( '#sf-conn-mailgun-domain' ).val(),
				mailgun_region:  $( '#sf-conn-mailgun-region' ).val(),
				smtpcom_channel: $( '#sf-conn-smtpcom-channel' ).val(),
				socketlabs_server_id: $( '#sf-conn-socketlabs-id' ).val(),
				outlook_tenant_id: $( '#sf-conn-outlook-tenant-id' ).val(),
				outlook_client_id: $( '#sf-conn-outlook-client-id' ).val(),
				gmail_client_id: $( '#sf-conn-gmail-client-id' ).val(),
				rate_limit:      $( '#sf-conn-rate-limit' ).val()
			};

			$.post( sendForce.ajax_url, data, function( response ) {
				$btn.prop( 'disabled', false ).text( sfT( 'save_connection', 'Save Connection' ) );
				$notice.removeClass( 'sendforce-notice-validating' );

				if ( response.success ) {
					var conn = response.data;

					// If this was the very first connection, reload to build the table.
					if ( ! $( '#sendforce-connections-card' ).length ) {
						setTimeout( function() { window.location.reload(); }, 800 );
						return;
					}

					// Update or add row in table.
					var $existingRow = $( '[data-conn-id="' + conn.id + '"]' );
					var $newRow      = sfConnBuildRow( conn );

					if ( $existingRow.length ) {
						$existingRow.replaceWith( $newRow );
					} else {
						$( '#sendforce-connections-tbody' ).append( $newRow );
					}

					// Update sendForce.connections array.
					if ( sendForce.connections ) {
						var existsInArray = false;
						$.each( sendForce.connections, function( i, c ) {
							if ( c.id === conn.id ) {
								sendForce.connections[i] = $.extend( sendForce.connections[i], {
									id:              conn.id,
									label:           conn.label,
									provider:        conn.provider,
									connection_type: conn.connection_type.toLowerCase(),
									has_password:    conn.has_password,
									has_api_key:     conn.has_api_key,
									rate_limit:      parseInt( data.rate_limit, 10 ) || 0
								});
								existsInArray = true;
								return false;
							}
						});
						if ( ! existsInArray ) {
							sendForce.connections.push({
								id:              conn.id,
								label:           conn.label,
								provider:        conn.provider,
								connection_type: conn.connection_type.toLowerCase(),
								has_password:    conn.has_password,
								has_api_key:     conn.has_api_key,
								rate_limit:      parseInt( data.rate_limit, 10 ) || 0
							});
						}
					}

					// Update sidebar Primary/Fallback selects.
					sfConnUpdateSelects( conn );

					// Auto-set as Primary Connection if none is selected yet.
					var $defaultSelect = $( 'select[name="sendforce_settings[default_connection]"]' );
					if ( $defaultSelect.length && ! $defaultSelect.val() ) {
						$defaultSelect.val( conn.id );
					}

					$notice.addClass( 'success' ).text( sendForce.strings.conn_saved ).show();

					// Came here from the Setup screen? Go back to it so the
					// wizard can carry on with the test-email step, instead of
					// leaving the person stranded on Settings.
					if ( sfCameFromSetup() ) {
						setTimeout( function() { window.location = sfSetupResumeUrl(); }, 900 );
						return;
					}

					// Hide form after brief delay.
					setTimeout( function() {
						$( '#sendforce-connection-form-card' ).slideUp( 200 );
						$notice.hide();
					}, 1500 );

				} else {
					$notice.addClass( 'error' ).text( response.data || sfT( 'err_save_conn', 'Error saving connection.' ) ).show();
				}
			}).fail( function() {
				$btn.prop( 'disabled', false ).text( sfT( 'save_connection', 'Save Connection' ) );
				$notice.removeClass( 'sendforce-notice-validating' )
					.addClass( 'error' )
					.text( sfT( 'request_failed', 'Request failed. Please try again.' ) )
					.show();
			});
		});

		// ----- Helper: reset connection form to defaults -----
		function sfConnFormReset() {
			$( '#sf-conn-id' ).val( '' );
			$( '#sf-conn-label' ).val( '' );
			$( '#sf-conn-provider' ).val( 'other' );
			$( '#sf-conn-type' ).val( 'smtp' );
			$( '#sf-conn-host' ).val( '' );
			$( '#sf-conn-port' ).val( '587' );
			$( '#sf-conn-enc' ).val( 'tls' );
			$( '#sf-conn-auth' ).prop( 'checked', true );
			$( '#sf-conn-username' ).val( '' );
			$( '#sf-conn-password' ).val( '' ).attr( 'placeholder', '' );
			$( '#sf-conn-apikey' ).val( '' ).attr( 'placeholder', '' );
			$( '#sf-conn-ses-region' ).val( 'us-east-1' );
			$( '#sf-conn-ses-api-region' ).val( 'us-east-1' );
			$( '#sf-conn-ses-access-key' ).val( '' );
			$( '#sf-conn-mailgun-domain' ).val( '' );
			$( '#sf-conn-mailgun-region' ).val( 'us' );
			$( '#sf-conn-smtpcom-channel' ).val( '' );
			$( '#sf-conn-socketlabs-id' ).val( '' );
			$( '#sf-conn-rate-limit' ).val( '0' );

			// Reset labels.
			$( '#sf-conn-username-label' ).text( sfT( 'username', 'Username' ) );
			$( '#sf-conn-password-label' ).text( sfT( 'password', 'Password' ) );
			$( '#sf-conn-apikey-label' ).text( sfT( 'api_key', 'API Key' ) );
			$( '#sf-conn-username-help' ).hide().text( '' );
			$( '#sf-conn-password-help' ).hide();
			$( '#sf-conn-apikey-help' ).hide();

			// Reset provider cards.
			$( '.sendforce-provider-card' ).removeClass( 'active' );
			$( '.sendforce-provider-card[data-provider="other"]' ).addClass( 'active' );

			// Reset tabs.
			$( '.sendforce-conn-tab' ).removeClass( 'active' );
			$( '.sendforce-conn-tab[data-type="smtp"]' ).addClass( 'active' );

			// Reset to "Other SMTP" defaults.
			$( '.sendforce-connection-type-card' ).hide();
			$( '.sendforce-custom-smtp-fields' ).show();
			$( '.sendforce-ses-region-field' ).hide();
			$( '.sendforce-smtp-config-form' ).show();  // Always show SMTP config on reset.
			$( '.sendforce-api-config-form' ).hide();
			$( '[class*="sf-conn-api-"]' ).hide();
			$( '.sf-conn-oauth' ).hide();

			$( '#sendforce-conn-notice' ).hide().removeClass( 'success error' );
		}

		// ----- Helper: populate form from a connection object -----
		function sfConnFormPopulate( conn ) {
			$( '#sf-conn-id' ).val( conn.id );
			$( '#sf-conn-label' ).val( conn.label );
			$( '#sf-conn-provider' ).val( conn.provider );
			$( '#sf-conn-type' ).val( conn.connection_type || 'smtp' );
			$( '#sf-conn-host' ).val( conn.host || '' );
			$( '#sf-conn-port' ).val( conn.port || 587 );
			$( '#sf-conn-enc' ).val( conn.encryption || 'tls' );
			$( '#sf-conn-auth' ).prop( 'checked', conn.auth !== false );
			$( '#sf-conn-username' ).val( conn.username || '' );
			$( '#sf-conn-password' ).val( '' );
			$( '#sf-conn-apikey' ).val( '' );

			// Show masked dots in the placeholder when a secret is already saved,
			// so the field doesn't look empty just because we never re-send secrets to the browser.
			var SF_MASK = '••••••••••••••••';
			$( '#sf-conn-password' ).attr( 'placeholder', conn.has_password ? SF_MASK : '' );
			$( '#sf-conn-apikey' ).attr( 'placeholder', conn.has_api_key ? SF_MASK : '' );
			$( '#sf-conn-ses-region' ).val( conn.ses_region || 'us-east-1' );
			$( '#sf-conn-ses-api-region' ).val( conn.ses_region || 'us-east-1' );
			$( '#sf-conn-ses-access-key' ).val( conn.ses_access_key || '' );
			$( '#sf-conn-mailgun-domain' ).val( conn.mailgun_domain || '' );
			$( '#sf-conn-mailgun-region' ).val( conn.mailgun_region || 'us' );
			$( '#sf-conn-smtpcom-channel' ).val( conn.smtpcom_channel || '' );
			$( '#sf-conn-socketlabs-id' ).val( conn.socketlabs_server_id || '' );
			$( '#sf-conn-outlook-tenant-id' ).val( conn.outlook_tenant_id || '' );
			$( '#sf-conn-outlook-client-id' ).val( conn.outlook_client_id || '' );
			$( '#sf-conn-gmail-client-id' ).val( conn.gmail_client_id || '' );
			$( '#sf-conn-rate-limit' ).val( conn.rate_limit || 0 );

			if ( conn.has_password ) { $( '#sf-conn-password-help' ).show(); }
			if ( conn.has_api_key )  { $( '#sf-conn-apikey-help' ).show(); }

			// Activate provider card.
			$( '.sendforce-provider-card' ).removeClass( 'active' );
			$( '.sendforce-provider-card[data-provider="' + conn.provider + '"]' ).addClass( 'active' );

			// Activate connection type tab.
			$( '.sendforce-conn-tab' ).removeClass( 'active' );
			$( '.sendforce-conn-tab[data-type="' + ( conn.connection_type || 'smtp' ) + '"]' ).addClass( 'active' );

			// PHP Mail: hide SMTP/API config entirely.
			if ( 'phpmail' === conn.provider ) {
				$( '.sendforce-connection-type-card' ).hide();
				$( '.sendforce-smtp-config-form' ).hide();
				$( '.sendforce-api-config-form' ).hide();
				return;
			}

			// Show API tabs if provider supports it.
			var hasApi = $( '.sendforce-provider-card[data-provider="' + conn.provider + '"]' ).data( 'api' ) === 1 ||
			             $( '.sendforce-provider-card[data-provider="' + conn.provider + '"]' ).data( 'api' ) === '1';
			if ( hasApi ) {
				$( '.sendforce-connection-type-card' ).show();
			} else {
				$( '.sendforce-connection-type-card' ).hide();
			}

			// Show/hide SES region.
			if ( 'ses' === conn.provider ) {
				$( '.sendforce-ses-region-field' ).show();
			} else {
				$( '.sendforce-ses-region-field' ).hide();
			}

			// Show/hide custom SMTP fields.
			if ( 'other' === conn.provider ) {
				$( '.sendforce-custom-smtp-fields' ).show();
			} else {
				$( '.sendforce-custom-smtp-fields' ).hide();
			}

			// Show/hide provider-specific API fields.
			$( '[class*="sf-conn-api-"]' ).hide();
			$( '.sf-conn-api-' + conn.provider ).show();

			// OAuth providers (gmail, outlook): show the Connect button, refresh
			// the connected state, and stash the connection id for the click handler.
			var isOauth = ( 'gmail' === conn.provider || 'outlook' === conn.provider );
			$( '.sf-conn-oauth' ).toggle( isOauth );
			if ( isOauth ) {
				$( '#sf-conn-oauth-connect' )
					.data( 'conn-id',  conn.id || '' )
					.data( 'provider', conn.provider );
				$( '#sf-conn-oauth-disconnect' ).data( 'conn-id', conn.id || '' );
				sfRenderOauthStatus( conn );
			}

			sfConnUpdateView();

			// Fetch labels from server.
			if ( 'other' !== conn.provider ) {
				$.post( sendForce.ajax_url, {
					action:   'sendforce_get_provider',
					nonce:    sendForce.nonce,
					provider: conn.provider
				}, function( response ) {
					if ( response.success ) {
						var data = response.data;
						$( '#sf-conn-username-label' ).text( data.username_label || sfT( 'username', 'Username' ) );
						$( '#sf-conn-password-label' ).text( data.password_label || sfT( 'password', 'Password' ) );
						if ( data.api_key_label ) {
							$( '#sf-conn-apikey-label' ).text( data.api_key_label );
						}
					}
				});
			}
		}

		// ----- Helper: build a table row for a connection -----
		function sfConnBuildRow( conn ) {
			var editLabel   = sfEsc( sfT( 'edit', 'Edit' ) );
			var deleteLabel = sfEsc( sfT( 'delete', 'Delete' ) );
			var editAria    = sfEsc( sfT( 'edit_conn_aria', 'Edit connection' ) );
			var deleteAria  = sfEsc( sfT( 'delete_conn_aria', 'Delete connection' ) );
			var editBtn   = '<button type="button" class="sendforce-action-btn sendforce-action-btn-edit sendforce-edit-connection" data-id="' + sfEsc( conn.id ) + '" title="' + editLabel + '" aria-label="' + editAria + '">' +
				'<span class="dashicons dashicons-edit" aria-hidden="true"></span>' +
				'<span class="sendforce-action-btn-label">' + editLabel + '</span>' +
				'</button>';
			var deleteBtn = '<button type="button" class="sendforce-action-btn sendforce-action-btn-delete sendforce-delete-connection" data-id="' + sfEsc( conn.id ) + '" title="' + deleteLabel + '" aria-label="' + deleteAria + '">' +
				'<span class="dashicons dashicons-trash" aria-hidden="true"></span>' +
				'<span class="sendforce-action-btn-label">' + deleteLabel + '</span>' +
				'</button>';
			var $tr = $( '<tr></tr>' ).attr( 'data-conn-id', conn.id );
			$tr.html(
				'<td><strong>' + sfEsc( conn.label || conn.provider ) + '</strong></td>' +
				'<td>' + sfEsc( conn.provider_label || conn.provider ) + '</td>' +
				'<td>' + sfEsc( conn.connection_type || 'SMTP' ) + '</td>' +
				'<td class="sendforce-conn-actions">' + editBtn + ' ' + deleteBtn + '</td>'
			);
			return $tr;
		}

		// ----- Helper: update Primary/Fallback selects after add/save -----
		function sfConnUpdateSelects( conn ) {
			var $default  = $( 'select[name="sendforce_settings[default_connection]"]' );
			var $fallback = $( 'select[name="sendforce_settings[fallback_connection]"]' );
			var optHtml   = '<option value="' + sfEsc( conn.id ) + '">' + sfEsc( conn.label || conn.provider_label ) + '</option>';

			if ( ! $default.find( 'option[value="' + conn.id + '"]' ).length ) {
				$default.append( optHtml );
			} else {
				$default.find( 'option[value="' + conn.id + '"]' ).text( conn.label || conn.provider_label );
			}

			if ( ! $fallback.find( 'option[value="' + conn.id + '"]' ).length ) {
				$fallback.append( optHtml );
			} else {
				$fallback.find( 'option[value="' + conn.id + '"]' ).text( conn.label || conn.provider_label );
			}

			// Tell the custom-select component to rebuild these two so the
			// new option appears in the styled UI instead of just the hidden
			// native select.
			$default.add( $fallback ).each( function () {
				this.dispatchEvent( new Event( 'sendforce:rebuild' ) );
			} );
		}

		// Simple HTML-escape helper.
		function sfEsc( str ) {
			return String( str )
				.replace( /&/g, '&amp;' )
				.replace( /</g, '&lt;' )
				.replace( />/g, '&gt;' )
				.replace( /"/g, '&quot;' );
		}

		// =========================================================
		// SEND TEST EMAIL
		// =========================================================
		$( '#sendforce_send_test' ).on( 'click', function() {
			var $btn         = $( this );
			var $email       = $( '#sendforce_test_email' );
			var $wrap        = $( '#sendforce_test_result_wrap' );
			var $banner      = $( '#sendforce_test_result_banner' );
			var $headline    = $( '#sendforce_test_result_headline' );
			var $icon        = $( '#sendforce_test_result_icon' );
			var $logWrap     = $( '#sendforce_smtp_log_wrap' );
			var $log         = $( '#sendforce_smtp_log' );
			var email        = $email.val();

			if ( ! email ) {
				$banner.removeClass( 'success' ).addClass( 'error' );
				$icon.text( '✕' );
				$headline.text( sendForce.strings.error );
				$logWrap.hide();
				$wrap.show();
				return;
			}

			$btn.prop( 'disabled', true );
			$btn.find( '.sendforce-btn-icon' ).hide();
			$btn.append( '<span class="sendforce-spinner" id="sf-btn-spinner"></span>' );
			$wrap.hide();

			$.post( sendForce.ajax_url, {
				action: 'sendforce_send_test_email',
				nonce:  sendForce.nonce,
				to:     email
			}, function( response ) {
				$btn.prop( 'disabled', false );
				$btn.find( '.sendforce-btn-icon' ).show();
				$( '#sf-btn-spinner' ).remove();

				var msg      = typeof response.data === 'string' ? response.data : JSON.stringify( response.data );
				var logIndex = msg.indexOf( 'SMTP Debug Log:' );
				var headline = msg;
				var smtpLog  = '';

				if ( logIndex !== -1 ) {
					headline = msg.substring( 0, logIndex ).trim();
					smtpLog  = msg.substring( logIndex + 'SMTP Debug Log:'.length ).trim();
				}

				// Split the headline into a short summary + the full provider
				// response body (when present) so the failed-send banner doesn't
				// have to display a 600-char one-liner. Detection: anything that
				// looks like "[HTTP <code>] <body>" or "{...}" or "AADSTS..." gets
				// promoted into the Provider response panel below the banner.
				var detail = '';
				var apiMatch = headline.match( /^(.*?)\s*(\[HTTP\s+\d+\][\s\S]*|\{[\s\S]*\}|AADSTS\d+:[\s\S]*)$/ );
				if ( apiMatch ) {
					headline = apiMatch[1].trim();
					detail   = apiMatch[2].trim();
					// If the detail is JSON, try to pretty-print it for readability.
					var jsonStart = detail.indexOf( '{' );
					if ( jsonStart !== -1 ) {
						var jsonPart = detail.slice( jsonStart );
						try {
							var parsed = JSON.parse( jsonPart );
							detail = detail.slice( 0, jsonStart ) + JSON.stringify( parsed, null, 2 );
						} catch ( e ) {
							// Not valid JSON — leave detail as raw text.
						}
					}
				}

				if ( response.success ) {
					$banner.removeClass( 'error' ).addClass( 'success' );
					$icon.text( '✓' );
				} else {
					$banner.removeClass( 'success' ).addClass( 'error' );
					$icon.text( '✕' );
				}

				$headline.text( headline );

				// Show / hide the Provider response panel based on whether we
				// extracted a body from the headline.
				var $detailWrap = $( '#sendforce_test_detail_wrap' );
				var $detailBody = $( '#sendforce_test_detail_body' );
				if ( detail ) {
					$detailBody.text( detail );
					$detailWrap.show();
				} else {
					$detailWrap.hide();
				}

				if ( smtpLog ) {
					$log.text( smtpLog );
					$logWrap.show();
				} else {
					$logWrap.hide();
				}

				$wrap.hide().fadeIn( 200 );

				// Came from the Setup screen and the send worked? Go back so the
				// wizard can show the finished state. Only on success: a failure
				// has to stay on screen, since this is where the provider's
				// error and the SMTP log are shown.
				if ( response.success && sfCameFromSetup() ) {
					setTimeout( function() { window.location = sfSetupResumeUrl(); }, 1200 );
				}

			}).fail( function() {
				$btn.prop( 'disabled', false );
				$btn.find( '.sendforce-btn-icon' ).show();
				$( '#sf-btn-spinner' ).remove();
				$banner.removeClass( 'success' ).addClass( 'error' );
				$icon.text( '✕' );
				$headline.text( sendForce.strings.error );
				$( '#sendforce_test_detail_wrap' ).hide();
				$logWrap.hide();
				$wrap.hide().fadeIn( 200 );
			});
		});

		// Copy button on the Provider response panel — drops the raw response
		// text on the clipboard so users can paste into a support ticket.
		$( document ).on( 'click', '#sendforce_test_detail_copy', function( e ) {
			e.preventDefault();
			var $btn  = $( this );
			var text  = $( '#sendforce_test_detail_body' ).text();
			var orig  = $btn.html();
			var done  = function () { $btn.html( '<span class="dashicons dashicons-yes"></span> ' + sfEsc( sfT( 'copied', 'Copied' ) ) ); setTimeout( function () { $btn.html( orig ); }, 1500 ); };
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( done, function () {});
			} else {
				// Fallback for older browsers without the async clipboard API.
				var $ta = $( '<textarea>' ).val( text ).appendTo( 'body' ).select();
				try { document.execCommand( 'copy' ); done(); } catch ( err ) {}
				$ta.remove();
			}
		});

		// =========================================================
		// EMAIL STATISTICS LINE CHART (Dashboard)
		// =========================================================

		var sfEmailChart = null;

		function sfInitChart( data ) {
			var canvas = document.getElementById( 'sendforce-email-chart' );
			if ( ! canvas ) { return; }

			if ( sfEmailChart ) {
				sfEmailChart.destroy();
				sfEmailChart = null;
			}

			var ctx          = canvas.getContext( '2d' );
			var gradSent     = ctx.createLinearGradient( 0, 0, 0, 240 );
			gradSent.addColorStop( 0, 'rgba(29, 209, 161, 0.25)' );
			gradSent.addColorStop( 1, 'rgba(29, 209, 161, 0)' );

			var gradFailed   = ctx.createLinearGradient( 0, 0, 0, 240 );
			gradFailed.addColorStop( 0, 'rgba(239, 68, 68, 0.18)' );
			gradFailed.addColorStop( 1, 'rgba(239, 68, 68, 0)' );

			sfEmailChart = new Chart( canvas, {
				type: 'line',
				data: {
					labels: data.labels,
					datasets: [
						{
							label: 'Sent',
							data: data.sent,
							borderColor: '#1dd1a1',
							backgroundColor: gradSent,
							borderWidth: 2.5,
							pointBackgroundColor: '#1dd1a1',
							pointBorderColor: '#fff',
							pointBorderWidth: 2,
							pointRadius: 4,
							pointHoverRadius: 6,
							tension: 0.4,
							fill: true
						},
						{
							label: 'Failed',
							data: data.failed,
							borderColor: '#EF4444',
							backgroundColor: gradFailed,
							borderWidth: 2.5,
							pointBackgroundColor: '#EF4444',
							pointBorderColor: '#fff',
							pointBorderWidth: 2,
							pointRadius: 4,
							pointHoverRadius: 6,
							tension: 0.4,
							fill: true
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: { display: false },
						tooltip: {
							mode: 'index',
							intersect: false,
							backgroundColor: '#1E293B',
							titleColor: '#94A3B8',
							bodyColor: '#F8FAFC',
							padding: 12,
							cornerRadius: 8,
							titleFont: { size: 11 },
							bodyFont: { size: 13, weight: '600' },
							callbacks: {
								title: function( items ) { return items[0].label; },
								label: function( item ) {
									return '  ' + item.dataset.label + ': ' + item.formattedValue;
								}
							}
						}
					},
					scales: {
						x: {
							grid: { display: false },
							border: { display: false },
							ticks: {
								color: '#94A3B8',
								font: { size: 11 },
								maxRotation: 0
							}
						},
						y: {
							beginAtZero: true,
							grid: { color: '#F1F5F9', lineWidth: 1 },
							border: { display: false, dash: [ 4, 4 ] },
							ticks: {
								color: '#94A3B8',
								font: { size: 11 },
								precision: 0,
								maxTicksLimit: 6
							}
						}
					},
					interaction: { mode: 'nearest', axis: 'x', intersect: false }
				}
			} );

			// Update footer stats.
			$( '#sendforce-stat-total' ).text( data.total );
			$( '#sendforce-stat-sent' ).text( data.total_sent );
			$( '#sendforce-stat-failed' ).text( data.total_failed );
		}

		function sfLoadChartData( range ) {
			var $canvas = $( '#sendforce-email-chart' );
			if ( ! $canvas.length ) { return; }

			$canvas.closest( '.sendforce-linechart-wrap' ).addClass( 'sendforce-chart-loading' );

			$.post( sendForce.ajax_url, {
				action: 'sendforce_chart_data',
				nonce:  sendForce.nonce,
				range:  range
			}, function( response ) {
				$canvas.closest( '.sendforce-linechart-wrap' ).removeClass( 'sendforce-chart-loading' );
				if ( response.success ) {
					sfInitChart( response.data );
				}
			} ).fail( function() {
				$canvas.closest( '.sendforce-linechart-wrap' ).removeClass( 'sendforce-chart-loading' );
			} );
		}

		// Init on dashboard page load.
		if ( $( '#sendforce-email-chart' ).length ) {
			sfLoadChartData( '7' );
		}

		// Range filter.
		$( document ).on( 'change', '#sendforce-chart-range', function() {
			sfLoadChartData( $( this ).val() );
		} );

		// =========================================================
		// VIEW LOG MODAL
		// =========================================================
		$( document ).on( 'click', '.sendforce-view-log', function() {
			var logId = $( this ).data( 'log-id' );

			$.post( sendForce.ajax_url, {
				action:  'sendforce_view_log',
				nonce:   sendForce.nonce,
				log_id:  logId
			}, function( response ) {
				if ( response.success ) {
					var data = response.data;
					$( '#sendforce-log-to' ).text( data.to );
					$( '#sendforce-log-subject' ).text( data.subject );
					$( '#sendforce-log-status' ).text( data.status );
					$( '#sendforce-log-date' ).text( data.created_at );
					$( '#sendforce-log-headers' ).text( data.headers || '—' );
					$( '#sendforce-log-message' ).text( data.message || '(not logged)' );

					if ( data.error ) {
						$( '#sendforce-log-error' ).text( data.error );
						$( '#sendforce-log-error-row' ).show();
					} else {
						$( '#sendforce-log-error-row' ).hide();
					}

					if ( data.fallback_used ) {
						$( '#sendforce-log-fallback-row' ).show();
					} else {
						$( '#sendforce-log-fallback-row' ).hide();
					}

					if ( data.debug_data ) {
						$( '#sendforce-log-debug' ).text( data.debug_data );
						$( '#sendforce-log-debug-row' ).show();
					} else {
						$( '#sendforce-log-debug-row' ).hide();
					}

					// Stash the pre-formatted diagnostic snapshot on the modal
					// so the "Copy diagnostic snapshot" button (1.0.30+) can
					// grab it later via .data() — saves re-fetching from the
					// server on click.
					$( '#sendforce-log-modal' ).data( 'sendforce-snapshot', data.snapshot || '' );

					$( '#sendforce-log-modal' ).fadeIn( 150 );
				}
			});
		});

		// "Copy diagnostic snapshot" — bundles all the visible log fields
		// (plus plugin/WP/PHP versions) into one clipboard-friendly text blob
		// the user can paste straight into a support ticket. Secrets in
		// headers / debug_data have already been redacted server-side by
		// SendForce_API_Mailer::make_request(), so this is safe to share.
		$( document ).on( 'click', '#sendforce-log-copy-snapshot', function( e ) {
			e.preventDefault();
			var $btn = $( this );
			var snap = $( '#sendforce-log-modal' ).data( 'sendforce-snapshot' ) || '';
			if ( ! snap ) return;
			var orig = $btn.html();
			var done = function () {
				$btn.html( '<span class="dashicons dashicons-yes"></span> ' + sfEsc( sfT( 'copied', 'Copied' ) ) );
				setTimeout( function () { $btn.html( orig ); }, 1500 );
			};
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( snap ).then( done, function () {});
			} else {
				var $ta = $( '<textarea>' ).val( snap ).appendTo( 'body' ).select();
				try { document.execCommand( 'copy' ); done(); } catch ( err ) {}
				$ta.remove();
			}
		});

		// Check-all checkbox.
		$( '#sendforce-check-all' ).on( 'change', function() {
			$( 'input[name="log_ids[]"]' ).prop( 'checked', $( this ).is( ':checked' ) );
		});

		// Close modals.
		$( document ).on( 'click', '.sendforce-modal-close, .sendforce-modal-overlay', function() {
			$( '.sendforce-modal' ).fadeOut( 100 );
		});

		$( document ).on( 'keydown', function( e ) {
			if ( 27 === e.keyCode ) {
				$( '.sendforce-modal' ).fadeOut( 100 );
			}
		});


		// =========================================================
		// ALERTS — channel toggle
		// =========================================================
		$( document ).on( 'change', '.sendforce-channel-toggle', function() {
			var channel = $( this ).data( 'channel' );
			var enabled = $( this ).is( ':checked' );

			$.post( sendForce.ajax_url, {
				action:  'sendforce_toggle_channel',
				nonce:   sendForce.nonce,
				channel: channel,
				enabled: enabled ? 'true' : 'false'
			}, function( response ) {
				if ( ! response.success ) {
					$( '.sendforce-channel-toggle[data-channel="' + channel + '"]' ).prop( 'checked', ! enabled );
				}
			}).fail( function() {
				$( '.sendforce-channel-toggle[data-channel="' + channel + '"]' ).prop( 'checked', ! enabled );
			});
		});

		// Alerts: open configure channel modal.
		$( document ).on( 'click', '.sendforce-configure-channel', function() {
			var channel = $( this ).data( 'channel' );
			var label   = $( this ).data( 'label' );

			$( '#sendforce-channel-modal-title' ).text( sfT( 'configure_channel', 'Configure %s', label ) );
			$( '#sendforce-channel-key' ).val( channel );
			$( '.sendforce-channel-fields' ).hide();
			$( '.sendforce-channel-fields[data-for="' + channel + '"]' ).show();

			var saved = ( typeof sendForceChannelData !== 'undefined' && sendForceChannelData[ channel ] )
				? sendForceChannelData[ channel ] : {};

			if ( 'telegram' === channel ) {
				$( '#sendforce-telegram-bot-token' ).val( saved.bot_token || '' );
				$( '#sendforce-telegram-chat-id' ).val( saved.chat_id || '' );
			} else if ( 'slack' === channel ) {
				$( '#sendforce-slack-webhook' ).val( saved.webhook_url || '' );
			} else if ( 'discord' === channel ) {
				$( '#sendforce-discord-webhook' ).val( saved.webhook_url || '' );
			} else if ( 'pushover' === channel ) {
				$( '#sendforce-pushover-user-key' ).val( saved.user_key || '' );
				$( '#sendforce-pushover-api-token' ).val( saved.api_token || '' );
			} else if ( 'teams' === channel ) {
				$( '#sendforce-teams-webhook' ).val( saved.webhook_url || '' );
			} else if ( 'google_chat' === channel ) {
				$( '#sendforce-google-chat-webhook' ).val( saved.webhook_url || '' );
			} else if ( 'webhook' === channel ) {
				$( '#sendforce-webhook-url' ).val( saved.webhook_url || '' );
			}

			$( '#sendforce-channel-modal-notice' ).hide().removeClass( 'success error sendforce-notice-inline' ).text( '' );
			$( '#sendforce-channel-modal' ).fadeIn( 150 );
		});

		// Live HTTPS warning for webhook URL fields. Plain HTTP webhooks
		// send the failure-alert message body (which may reveal site name,
		// failed recipient address, and the actual error including provider
		// API responses) over an unencrypted connection. Modern providers
		// (Slack, Discord, Teams, Google Chat) require HTTPS anyway; this
		// catches "http://" typos and homemade webhook receivers before the
		// user saves a leaky config.
		var sfWebhookSelectors = [
			'#sendforce-slack-webhook',
			'#sendforce-discord-webhook',
			'#sendforce-teams-webhook',
			'#sendforce-google-chat-webhook',
			'#sendforce-webhook-url',
		].join( ', ' );
		$( document ).on( 'input paste blur', sfWebhookSelectors, function () {
			var $field = $( this );
			setTimeout( function () {
				var value = ( $field.val() || '' ).trim();
				// Look for the existing hint container or create one inline.
				var $warn = $field.next( '.sendforce-webhook-https-warning' );
				if ( ! $warn.length ) {
					$warn = $( '<div class="sendforce-webhook-https-warning sendforce-help" style="display:none;color:#b32d2e;font-weight:600;margin-top:4px;"><span class="dashicons dashicons-warning" style="vertical-align:text-bottom;"></span> This webhook URL is HTTP, not HTTPS. The alert message (site name, failed recipient, error details) will be sent unencrypted. Use the https:// version of this endpoint instead.</div>' );
					$field.after( $warn );
				}
				$warn.toggle( /^http:\/\//i.test( value ) );
			}, 0 );
		});

		$( document ).on( 'click', '.sendforce-channel-modal-close', function() {
			$( '#sendforce-channel-modal' ).fadeOut( 100 );
		});

		$( document ).on( 'click', '#sendforce-channel-modal .sendforce-modal-overlay', function() {
			$( '#sendforce-channel-modal' ).fadeOut( 100 );
		});

		// Alerts: save channel settings.
		$( '#sendforce-channel-save' ).on( 'click', function() {
			var channel = $( '#sendforce-channel-key' ).val();
			var $btn    = $( this );
			var data    = {
				action:  'sendforce_save_channel',
				nonce:   sendForce.nonce,
				channel: channel
			};

			if ( 'telegram' === channel ) {
				data.bot_token = $( '#sendforce-telegram-bot-token' ).val();
				data.chat_id   = $( '#sendforce-telegram-chat-id' ).val();
			} else if ( 'slack' === channel ) {
				data.webhook_url = $( '#sendforce-slack-webhook' ).val();
			} else if ( 'discord' === channel ) {
				data.webhook_url = $( '#sendforce-discord-webhook' ).val();
			} else if ( 'pushover' === channel ) {
				data.user_key  = $( '#sendforce-pushover-user-key' ).val();
				data.api_token = $( '#sendforce-pushover-api-token' ).val();
			} else if ( 'teams' === channel ) {
				data.webhook_url = $( '#sendforce-teams-webhook' ).val();
			} else if ( 'google_chat' === channel ) {
				data.webhook_url = $( '#sendforce-google-chat-webhook' ).val();
			} else if ( 'webhook' === channel ) {
				data.webhook_url = $( '#sendforce-webhook-url' ).val();
			}

			$btn.prop( 'disabled', true ).text( sfT( 'saving', 'Saving…' ) );

			$.post( sendForce.ajax_url, data, function( response ) {
				$btn.prop( 'disabled', false ).text( sfT( 'save', 'Save' ) );
				var $notice = $( '#sendforce-channel-modal-notice' );
				$notice.removeClass( 'success error sendforce-notice-inline' );

				if ( response.success ) {
					if ( typeof sendForceChannelData !== 'undefined' ) {
						if ( ! sendForceChannelData[ channel ] ) { sendForceChannelData[ channel ] = {}; }
						if ( 'telegram' === channel ) {
							sendForceChannelData[ channel ].bot_token = data.bot_token;
							sendForceChannelData[ channel ].chat_id   = data.chat_id;
						} else if ( 'slack' === channel ) {
							sendForceChannelData[ channel ].webhook_url = data.webhook_url;
						} else if ( 'discord' === channel ) {
							sendForceChannelData[ channel ].webhook_url = data.webhook_url;
						} else if ( 'pushover' === channel ) {
							sendForceChannelData[ channel ].user_key  = data.user_key;
							sendForceChannelData[ channel ].api_token = data.api_token;
						} else if ( 'teams' === channel ) {
							sendForceChannelData[ channel ].webhook_url = data.webhook_url;
						} else if ( 'google_chat' === channel ) {
							sendForceChannelData[ channel ].webhook_url = data.webhook_url;
						} else if ( 'webhook' === channel ) {
							sendForceChannelData[ channel ].webhook_url = data.webhook_url;
						}
					}
					$notice.addClass( 'sendforce-notice-inline success' ).text( response.data ).show();
				} else {
					$notice.addClass( 'sendforce-notice-inline error' ).text( response.data || sfT( 'err_save_settings', 'Error saving settings.' ) ).show();
				}
			}).fail( function() {
				$btn.prop( 'disabled', false ).text( sfT( 'save', 'Save' ) );
				$( '#sendforce-channel-modal-notice' ).addClass( 'sendforce-notice-inline error' ).text( sfT( 'request_failed_short', 'Request failed.' ) ).show();
			});
		});

		// Alerts: send test alert.
		$( '#sendforce-channel-test' ).on( 'click', function() {
			var channel = $( '#sendforce-channel-key' ).val();
			var $btn    = $( this );

			var data = {
				action:  'sendforce_send_test_alert',
				nonce:   sendForce.nonce,
				channel: channel
			};

			if ( 'telegram' === channel ) {
				data.bot_token = $( '#sendforce-telegram-bot-token' ).val();
				data.chat_id   = $( '#sendforce-telegram-chat-id' ).val();
			} else if ( 'slack' === channel ) {
				data.webhook_url = $( '#sendforce-slack-webhook' ).val();
			} else if ( 'discord' === channel ) {
				data.webhook_url = $( '#sendforce-discord-webhook' ).val();
			} else if ( 'pushover' === channel ) {
				data.user_key  = $( '#sendforce-pushover-user-key' ).val();
				data.api_token = $( '#sendforce-pushover-api-token' ).val();
			} else if ( 'teams' === channel ) {
				data.webhook_url = $( '#sendforce-teams-webhook' ).val();
			} else if ( 'google_chat' === channel ) {
				data.webhook_url = $( '#sendforce-google-chat-webhook' ).val();
			} else if ( 'webhook' === channel ) {
				data.webhook_url = $( '#sendforce-webhook-url' ).val();
			}

			$btn.prop( 'disabled', true ).text( sfT( 'sending', 'Sending...' ) );

			$.post( sendForce.ajax_url, data, function( response ) {
				$btn.prop( 'disabled', false ).text( sfT( 'send_test_short', 'Send Test' ) );
				var $notice = $( '#sendforce-channel-modal-notice' );
				$notice.removeClass( 'success error sendforce-notice-inline' );

				if ( response.success ) {
					$notice.addClass( 'sendforce-notice-inline success' ).text( response.data ).show();
				} else {
					$notice.addClass( 'sendforce-notice-inline error' ).text( response.data || sfT( 'err_send_test', 'Failed to send test.' ) ).show();
				}
			}).fail( function() {
				$btn.prop( 'disabled', false ).text( sfT( 'send_test_short', 'Send Test' ) );
				$( '#sendforce-channel-modal-notice' ).addClass( 'sendforce-notice-inline error' ).text( sfT( 'request_failed_short', 'Request failed.' ) ).show();
			});
		});

		// ----- Copy Debug Info -----
		$( document ).on( 'click', '#sendforce-copy-debug', function( e ) {
			e.preventDefault();
			var $btn    = $( this );
			var $out    = $( '#sendforce-debug-output' );
			var $status = $( '#sendforce-debug-status' );
			$btn.prop( 'disabled', true );
			$status.text( sfT( 'collecting', 'Collecting…' ) );

			$.post( sendForce.ajax_url, {
				action: 'sendforce_sysinfo',
				nonce:  sendForce.nonce
			}, function( response ) {
				$btn.prop( 'disabled', false );
				if ( ! response.success ) {
					$status.text( response.data || sfT( 'err_debug', 'Failed to collect debug info.' ) );
					return;
				}
				var text = response.data.text || '';
				$out.val( text ).show();

				var copyDone = function() {
					$status.text( sfT( 'copied_clipboard', 'Copied to clipboard ✓' ) );
				};
				var copyFail = function() {
					$status.text( sfT( 'copy_manual', 'Could not auto-copy — select the text above and copy manually.' ) );
				};

				if ( navigator.clipboard && window.isSecureContext ) {
					navigator.clipboard.writeText( text ).then( copyDone ).catch( copyFail );
				} else {
					try {
						$out[0].select();
						document.execCommand( 'copy' );
						copyDone();
					} catch ( err ) {
						copyFail();
					}
				}
			}).fail( function() {
				$btn.prop( 'disabled', false );
				$status.text( sfT( 'request_failed_short', 'Request failed.' ) );
			});
		});

		// ----- Connect / Disconnect (Gmail + Microsoft 365 OAuth) -----
		function sfRenderOauthStatus( conn ) {
			var $status      = $( '#sf-conn-oauth-status .sendforce-oauth-status-text' );
			var $connectBtn  = $( '#sf-conn-oauth-connect' );
			var $disconnect  = $( '#sf-conn-oauth-disconnect' );

			// The `password` field is dual-purpose (SMTP password OR OAuth refresh token),
			// so we only interpret it as a refresh token when the connection is in API mode.
			// In SMTP mode the OAuth panel is still visible (in case the user is switching),
			// but we treat the password as a legitimate SMTP password and show "Not connected".
			var apiMode     = ( conn.connection_type || '' ) === 'api';
			var hasToken    = apiMode && !! conn.has_password;
			var hasSecret   = !! conn.has_api_key;
			var providerId  = ( 'gmail' === conn.provider )
				? !! conn.gmail_client_id
				: ( 'outlook' === conn.provider ? !! conn.outlook_client_id : false );
			var fullyReady  = hasToken && hasSecret && providerId;
			var partial     = hasToken && ( ! hasSecret || ! providerId );

			$status.removeClass( 'is-connected is-incomplete' );

			if ( fullyReady ) {
				$status.addClass( 'is-connected' )
					.text( conn.oauth_email
						? sfT( 'connected_as', 'Connected as %s', conn.oauth_email )
						: sfT( 'connected', 'Connected.' ) );
				$connectBtn.text( sfT( 'reconnect', 'Reconnect' ) );
				$disconnect.show();
			} else if ( partial ) {
				// Refresh token is stored (in API mode) but Client ID / Secret missing — orphaned.
				$status.addClass( 'is-incomplete' )
					.text( sfT( 'oauth_orphaned', 'Connection has a stored OAuth token but the Client ID or Client Secret is missing. Re-enter your OAuth credentials and click Reconnect.' ) );
				$connectBtn.text( sfT( 'reconnect', 'Reconnect' ) );
				$disconnect.show();
			} else {
				var helpText = apiMode
					? sfT( 'oauth_not_connected', 'Not connected. Save the connection, then click Connect.' )
					: sfT( 'oauth_switch_api', 'Switch the connection to API mode above to use OAuth.' );
				$status.text( helpText );
				$connectBtn.text( sfT( 'oauth_connect', 'Connect with provider' ) );
				$disconnect.hide();
			}
		}

		// Helper: handle the OAuth result returned by the popup callback page.
		// Reloads the settings tab with notice params so the existing notice-
		// rendering code path (further down in this file) shows the message
		// and the connection-status panel re-renders.
		function sfHandleOauthResult( data ) {
			if ( ! data ) return;
			var status  = data.status  || 'error';
			var message = data.message || '';
			var url = window.location.pathname
				+ '?page=sendforce-mail-relay&tab=settings'
				+ '&sf_oauth_status='  + encodeURIComponent( status )
				+ '&sf_oauth_message=' + encodeURIComponent( message );
			window.location.replace( url );
		}

		// Install the OAuth result listeners ONCE per page load. Three independent
		// channels are used because cross-origin popup navigation (to Microsoft /
		// Google and back) breaks `window.opener` in modern browsers under
		// Cross-Origin-Opener-Policy, so postMessage alone is unreliable.
		//
		// Channel 1: localStorage event — popup writes to localStorage, parent
		//   listens for storage event. Works across the cross-origin gap because
		//   localStorage is shared per-origin and the storage event fires in
		//   OTHER same-origin windows when a value changes.
		// Channel 2: postMessage — best-effort fallback when window.opener
		//   survives the cross-origin trip (older browsers / no COOP header).
		// Channel 3: popup-closed polling — set up per-click below; if the
		//   popup is closed without either of the above firing, parent reloads
		//   anyway to refresh status from the DB.
		if ( ! window.sendforceOAuthListenerInstalled ) {
			window.sendforceOAuthListenerInstalled = true;
			window.addEventListener( 'storage', function( e ) {
				if ( e.key !== 'sendforce_oauth_result' || ! e.newValue ) return;
				try {
					var data = JSON.parse( e.newValue );
					try { localStorage.removeItem( 'sendforce_oauth_result' ); } catch ( err ) {}
					sfHandleOauthResult( data );
				} catch ( err ) {}
			}, false );
			window.addEventListener( 'message', function( event ) {
				if ( event.origin !== window.location.origin ) return;
				if ( ! event.data || event.data.type !== 'sendforce_oauth' ) return;
				sfHandleOauthResult( event.data );
			}, false );
		}

		$( document ).on( 'click', '#sf-conn-oauth-connect', function( e ) {
			e.preventDefault();
			var $btn    = $( this );
			var connId  = $btn.data( 'conn-id' );
			var provider = $btn.data( 'provider' );
			if ( ! connId ) {
				sfShowConfirmModal({
					title:       sfT( 'oauth_save_first_title', 'Save the connection first' ),
					message:     sfT( 'oauth_save_first_msg', 'Enter your Client ID and Client Secret, then click "Save Connection". After it\'s saved you can click "Connect with provider" to authorise.' ),
					confirmText: sfT( 'ok', 'OK' ),
					showIcon:    false,
					onConfirm:   function() {}
				});
				return;
			}
			$btn.prop( 'disabled', true ).text( sfT( 'oauth_opening', 'Opening provider…' ) );
			$.post( sendForce.ajax_url, {
				action:   'sendforce_start_oauth',
				nonce:    sendForce.nonce,
				conn_id:  connId,
				provider: provider
			}, function( response ) {
				if ( response.success && response.data && response.data.authorize_url ) {
					// Clear any stale result from a previous attempt so the
					// storage listener doesn't fire on a leftover value.
					try { localStorage.removeItem( 'sendforce_oauth_result' ); } catch ( err ) {}

					// Open the provider's consent screen in a centered popup so
					// the user doesn't lose their settings page. If the browser
					// blocks the popup, fall back to a full-page redirect.
					var w = 600, h = 720;
					var left = Math.max( 0, ( window.screen.width  - w ) / 2 );
					var top  = Math.max( 0, ( window.screen.height - h ) / 2 );
					var features = 'width=' + w + ',height=' + h
						+ ',left=' + left + ',top=' + top
						+ ',resizable=yes,scrollbars=yes,status=no,toolbar=no,menubar=no,location=yes';
					var popup = window.open( response.data.authorize_url, 'sendforce_oauth', features );
					if ( ! popup || popup.closed || typeof popup.closed === 'undefined' ) {
						// Popup blocked — fall back to current-window redirect.
						window.location.href = response.data.authorize_url;
						return;
					}
					try { popup.focus(); } catch ( e ) {}
					// Restore the button so the user can retry if they close
					// the popup without finishing.
					$btn.prop( 'disabled', false ).text( sfT( 'oauth_connect', 'Connect with provider' ) );

					// Channel 3: poll for popup close as a final safety net.
					// If the popup closed and neither localStorage nor
					// postMessage has fired (e.g. user closed it manually,
					// or the callback page crashed), check localStorage one
					// more time and otherwise just reload the settings tab
					// so the connection panel re-reads its state from the DB.
					var pollHandle = setInterval( function() {
						if ( ! popup.closed ) return;
						clearInterval( pollHandle );
						// Give the storage event a beat to land first.
						setTimeout( function() {
							var stored = null;
							try { stored = localStorage.getItem( 'sendforce_oauth_result' ); } catch ( err ) {}
							if ( stored ) {
								try { localStorage.removeItem( 'sendforce_oauth_result' ); } catch ( err ) {}
								try {
									sfHandleOauthResult( JSON.parse( stored ) );
									return;
								} catch ( err ) {}
							}
							// No result captured — popup was closed mid-flow.
							// Reload anyway so the connection-status panel
							// reflects whatever the DB now shows.
							window.location.reload();
						}, 250 );
					}, 500 );
				} else {
					$btn.prop( 'disabled', false ).text( sfT( 'oauth_connect', 'Connect with provider' ) );
					sfShowConfirmModal({
						title:       sfT( 'oauth_start_fail_title', 'Could not start authorisation' ),
						message:     ( response && response.data ) ? String( response.data ) : sfT( 'oauth_start_fail_msg', 'Failed to start OAuth flow.' ),
						confirmText: sfT( 'ok', 'OK' ),
						showIcon:    false,
						onConfirm:   function() {}
					});
				}
			}).fail( function() {
				$btn.prop( 'disabled', false ).text( sfT( 'oauth_connect', 'Connect with provider' ) );
				sfShowConfirmModal({
					title:       sfT( 'network_error', 'Network error' ),
					message:     sfT( 'network_error_msg', 'Could not reach the server. Please try again.' ),
					confirmText: sfT( 'ok', 'OK' ),
					showIcon:    false,
					onConfirm:   function() {}
				});
			});
		});

		$( document ).on( 'click', '#sf-conn-oauth-disconnect', function( e ) {
			e.preventDefault();
			var $btn    = $( this );
			var connId  = $btn.data( 'conn-id' );
			sfShowConfirmModal({
				title:       sfT( 'oauth_disconnect_title', 'Disconnect this account?' ),
				message:     sfT( 'oauth_disconnect_msg', 'You will need to reconnect to send via this connection. Stored OAuth tokens will be removed.' ),
				confirmText: sfT( 'disconnect', 'Disconnect' ),
				showIcon:    true,
				onConfirm:   function() {
					$btn.prop( 'disabled', true );
					$.post( sendForce.ajax_url, {
						action:  'sendforce_disconnect_oauth',
						nonce:   sendForce.nonce,
						conn_id: connId
					}, function( response ) {
						$btn.prop( 'disabled', false );
						if ( response.success ) {
							// Re-render the OAuth status to reflect disconnection.
							sfRenderOauthStatus( { has_password: false } );
						} else {
							sfShowConfirmModal({
								title:       sfT( 'oauth_disconnect_fail_title', 'Disconnect failed' ),
								message:     ( response && response.data ) ? String( response.data ) : sfT( 'oauth_disconnect_fail_msg', 'Failed to disconnect.' ),
								confirmText: sfT( 'ok', 'OK' ),
								showIcon:    false,
								onConfirm:   function() {}
							});
						}
					}).fail( function() {
						$btn.prop( 'disabled', false );
						sfShowConfirmModal({
							title:       sfT( 'network_error', 'Network error' ),
							message:     sfT( 'network_error_msg', 'Could not reach the server. Please try again.' ),
							confirmText: sfT( 'ok', 'OK' ),
							showIcon:    false,
							onConfirm:   function() {}
						});
					});
				}
			});
		});

		// Show success/error notice when returning from the OAuth provider.
		(function showOauthReturnNotice() {
			var params = new URLSearchParams( window.location.search );
			var status = params.get( 'sf_oauth_status' );
			var msg    = params.get( 'sf_oauth_message' );
			if ( ! status || ! msg ) {
				return;
			}
			var $notice = $(
				'<div class="notice notice-' + ( 'success' === status ? 'success' : 'error' ) + ' is-dismissible">' +
				'<p>' + $( '<div>' ).text( msg ).html() + '</p>' +
				'</div>'
			);
			$( '.wrap' ).first().prepend( $notice );
			// Strip the params from the URL so the notice doesn't reappear on refresh.
			if ( window.history && window.history.replaceState ) {
				params.delete( 'sf_oauth_status' );
				params.delete( 'sf_oauth_message' );
				params.delete( 'sf_oauth' );
				var newUrl = window.location.pathname + ( params.toString() ? '?' + params.toString() : '' );
				window.history.replaceState( {}, '', newUrl );
			}
		})();

		// Queue tab: "Process Queue Now" — runs the queue processor on demand
		// instead of waiting for the next WP-Cron tick. Shares the same UX
		// pattern as the Email Log "Clean Now" button below.
		$( document ).on( 'click', '#sendforce-queue-process-now', function() {
			var $btn = $( this );
			if ( $btn.prop( 'disabled' ) ) {
				return;
			}
			var originalHtml = $btn.html();
			$btn.prop( 'disabled', true ).html( '<span class="dashicons dashicons-update sendforce-spin"></span> ' + sfEsc( sfT( 'processing', 'Processing...' ) ) );

			$.post( sendForce.ajax_url, {
				action: 'sendforce_process_queue_now',
				nonce:  sendForce.nonce
			}, function( response ) {
				if ( response && response.success ) {
					var msg = ( response.data && response.data.message ) || sfT( 'queue_processed', 'Queue processed.' );
					var $notice = $( '<div class="notice notice-success is-dismissible"><p>' + $( '<div>' ).text( msg ).html() + '</p></div>' );
					$( '.wrap' ).first().prepend( $notice );
					setTimeout( function() { window.location.reload(); }, 800 );
				} else {
					$btn.prop( 'disabled', false ).html( originalHtml );
					var err = ( response && response.data && response.data.message ) || sfT( 'queue_fail_msg', 'Queue processing failed.' );
					sfShowConfirmModal({
						title:       sfT( 'queue_fail_title', 'Queue processing failed' ),
						message:     err,
						confirmText: sfT( 'ok', 'OK' ),
						showIcon:    false,
						onConfirm:   function() {}
					});
				}
			}).fail( function() {
				$btn.prop( 'disabled', false ).html( originalHtml );
				sfShowConfirmModal({
					title:       sfT( 'network_error', 'Network error' ),
					message:     sfT( 'network_error_msg', 'Could not reach the server. Please try again.' ),
					confirmText: sfT( 'ok', 'OK' ),
					showIcon:    false,
					onConfirm:   function() {}
				});
			});
		});

		// Email Log: "Clean Now" — runs the auto-clean immediately.
		$( '#sendforce-cleanup-now' ).on( 'click', function() {
			var $btn = $( this );
			if ( $btn.prop( 'disabled' ) ) {
				return;
			}

			sfShowConfirmModal({
				title:       sfT( 'clean_logs_title', 'Clean logs now?' ),
				message:     sfT( 'clean_logs_msg', 'This will immediately delete log entries older than your configured retention period. This action cannot be undone.' ),
				confirmText: sfT( 'clean_now', 'Clean Now' ),
				showIcon:    true,
				onConfirm:   function() {
					var originalHtml = $btn.html();
					$btn.prop( 'disabled', true ).html( '<span class="dashicons dashicons-update sendforce-spin"></span> ' + sfEsc( sfT( 'cleaning', 'Cleaning...' ) ) );

					$.post( sendForce.ajax_url, {
						action: 'sendforce_cleanup_logs_now',
						nonce: sendForce.nonce
					}, function( response ) {
						if ( response && response.success ) {
							var msg = ( response.data && response.data.message ) || sfT( 'cleanup_complete', 'Cleanup complete.' );
							var $notice = $( '<div class="notice notice-success is-dismissible"><p>' + $( '<div>' ).text( msg ).html() + '</p></div>' );
							$( '.wrap' ).first().prepend( $notice );
							setTimeout( function() { window.location.reload(); }, 800 );
						} else {
							$btn.prop( 'disabled', false ).html( originalHtml );
							var err = ( response && response.data && response.data.message ) || sfT( 'cleanup_fail_msg', 'Cleanup failed.' );
							sfShowConfirmModal({
								title:       sfT( 'cleanup_fail_title', 'Cleanup failed' ),
								message:     err,
								confirmText: sfT( 'ok', 'OK' ),
								showIcon:    false,
								onConfirm:   function() {}
							});
						}
					}).fail( function() {
						$btn.prop( 'disabled', false ).html( originalHtml );
						sfShowConfirmModal({
							title:       sfT( 'network_error', 'Network error' ),
							message:     sfT( 'network_error_msg', 'Could not reach the server. Please try again.' ),
							confirmText: sfT( 'ok', 'OK' ),
							showIcon:    false,
							onConfirm:   function() {}
						});
					});
				}
			});
		});

	});

	/*
	 * General Settings groups (Settings tab).
	 *
	 * 1. A link to a setting inside a closed group (the Simulation Mode badge
	 *    and the Email Test notice use #sendforce-simulation) opens that
	 *    group, scrolls to the setting and briefly highlights it.
	 * 2. Which groups are open survives Save: options.php reloads the page,
	 *    and without this every group but Sending would snap shut, hiding the
	 *    setting just changed. Session storage only; failures are ignored.
	 */
	$( function() {
		var groups = document.querySelectorAll( '.sendforce-gs-group' );
		if ( ! groups.length ) {
			return;
		}
		var KEY = 'sendforceGsOpen';

		function revealHash() {
			var id = window.location.hash.slice( 1 );
			var target = id ? document.getElementById( id ) : null;
			var group = target && target.closest ? target.closest( '.sendforce-gs-group' ) : null;
			if ( ! group ) {
				return;
			}
			group.open = true;
			target.scrollIntoView( { block: 'center' } );
			target.classList.remove( 'is-target' );
			void target.offsetWidth; // Restart the highlight animation.
			target.classList.add( 'is-target' );
		}

		try {
			// One-shot: applies to the reload right after Save, then a normal
			// visit starts again with only Sending open.
			var saved = JSON.parse( window.sessionStorage.getItem( KEY ) || 'null' );
			window.sessionStorage.removeItem( KEY );
			if ( saved && saved.length === groups.length ) {
				Array.prototype.forEach.call( groups, function( g, i ) {
					g.open = !! saved[ i ];
				} );
			}
		} catch ( e ) {}

		var form = document.getElementById( 'sendforce-general-form' );
		if ( form ) {
			form.addEventListener( 'submit', function() {
				try {
					window.sessionStorage.setItem( KEY, JSON.stringify( Array.prototype.map.call( groups, function( g ) {
						return g.open ? 1 : 0;
					} ) ) );
				} catch ( e ) {}
			} );
		}

		revealHash();
		window.addEventListener( 'hashchange', revealHash );
	} );
})( jQuery );
