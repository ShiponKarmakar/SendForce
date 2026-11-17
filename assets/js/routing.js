/**
 * Smart Routing — admin UI behavior (1.0.19 inline edition).
 *
 * Each rule is rendered as an inline editable card on the Routing tab; this
 * file wires up:
 *   - Per-card condition builder (add/remove/change rows).
 *   - "Unsaved changes" dirty tracking + per-card Save / Revert.
 *   - Add new rule (prepends an unsaved card).
 *   - Up/down reorder buttons.
 *   - Master "Enable Smart Routing" toggle.
 *   - Three onboarding presets in the empty state.
 *   - Test Routing dry-run.
 */
( function ( $ ) {
	'use strict';

	if ( typeof window.sendForce === 'undefined' || typeof window.sendForceRoutingData === 'undefined' ) {
		return;
	}

	var data = window.sendForceRoutingData;

	// i18n helper — read a routing string by key from the localized bundle
	// (see routing-tab.php), falling back to bundled English. %s/%d supported.
	function rT( key, fallback ) {
		var str  = ( data.strings && data.strings[ key ] ) || fallback;
		var args = Array.prototype.slice.call( arguments, 2 );
		return String( str ).replace( /%[sd]/g, function() {
			return args.length ? args.shift() : '';
		} );
	}

	// ---- Styled confirm / alert dialog --------------------------------------
	//
	// Replaces native browser confirm() / alert() with a SendForce-styled
	// modal. Exposed on window.sendForceConfirm so other admin scripts can
	// reuse it.
	//
	//   sendForceConfirm({
	//     title: 'Delete rule',
	//     message: 'This cannot be undone.',
	//     confirmText: 'Delete',
	//     confirmVariant: 'danger',   // 'danger' | 'primary'
	//     onConfirm: function () { ... }
	//   });
	//
	// Returns a Promise (or works via callbacks for older code paths).

	function sendForceConfirm( opts ) {
		opts = opts || {};
		var title          = opts.title          || rT( 'are_you_sure', 'Are you sure?' );
		var message        = opts.message        || '';
		var confirmText    = opts.confirmText    || rT( 'confirm', 'Confirm' );
		var cancelText     = opts.cancelText     || rT( 'cancel', 'Cancel' );
		var confirmVariant = opts.confirmVariant || 'primary';
		var allowCancel    = ( opts.allowCancel !== false );

		// Tear down any stale dialog so multiple invocations don't stack.
		$( '.sendforce-dialog-backdrop' ).remove();

		var $backdrop = $( '<div class="sendforce-dialog-backdrop" role="presentation"></div>' );
		var iconClass = ( 'danger' === confirmVariant ) ? 'dashicons-warning' : 'dashicons-info';

		var html = ''
			+ '<div class="sendforce-dialog" role="dialog" aria-modal="true" aria-labelledby="sendforce-dialog-title">'
			+ '<div class="sendforce-dialog-icon sendforce-dialog-icon--' + escapeHtml( confirmVariant ) + '">'
			+ '<span class="dashicons ' + iconClass + '" aria-hidden="true"></span>'
			+ '</div>'
			+ '<div class="sendforce-dialog-body">'
			+ '<h3 id="sendforce-dialog-title">' + escapeHtml( title ) + '</h3>'
			+ ( message ? '<p>' + escapeHtml( message ) + '</p>' : '' )
			+ '</div>'
			+ '<div class="sendforce-dialog-footer">'
			+ ( allowCancel ? '<button type="button" class="sendforce-btn sendforce-btn-outline sendforce-dialog-cancel">' + escapeHtml( cancelText ) + '</button>' : '' )
			+ '<button type="button" class="sendforce-btn sendforce-btn-' + escapeHtml( confirmVariant ) + ' sendforce-dialog-confirm">' + escapeHtml( confirmText ) + '</button>'
			+ '</div>'
			+ '</div>';

		$backdrop.append( html );
		$( document.body ).append( $backdrop );

		// Lock body scroll while dialog is open.
		var prevOverflow = document.body.style.overflow;
		document.body.style.overflow = 'hidden';

		return new Promise( function ( resolve ) {
			function close( result ) {
				$backdrop.removeClass( 'is-open' );
				setTimeout( function () {
					$backdrop.remove();
					document.body.style.overflow = prevOverflow;
					if ( result && typeof opts.onConfirm === 'function' ) { opts.onConfirm(); }
					if ( ! result && typeof opts.onCancel === 'function' ) { opts.onCancel(); }
					resolve( result );
				}, 120 );
			}

			$backdrop.on( 'click', function ( e ) {
				if ( e.target === $backdrop[ 0 ] && allowCancel ) { close( false ); }
			} );
			$backdrop.on( 'click', '.sendforce-dialog-cancel', function () { close( false ); } );
			$backdrop.on( 'click', '.sendforce-dialog-confirm', function () { close( true ); } );
			$( document ).one( 'keydown.sendforceDialog', function ( e ) {
				if ( e.key === 'Escape' && allowCancel ) { close( false ); }
				if ( e.key === 'Enter' ) { close( true ); }
			} );

			// Autofocus the confirm button + animate in on next frame.
			setTimeout( function () {
				$backdrop.addClass( 'is-open' );
				$backdrop.find( '.sendforce-dialog-confirm' ).trigger( 'focus' );
			}, 10 );
		} );
	}

	// Convenience alert (info-only, single OK button, no destructive option).
	function sendForceAlert( title, message ) {
		return sendForceConfirm( {
			title:       title || rT( 'notice', 'Notice' ),
			message:     message || '',
			confirmText: rT( 'ok', 'OK' ),
			cancelText:  '',
			allowCancel: false,
		} );
	}

	window.sendForceConfirm = sendForceConfirm;
	window.sendForceAlert   = sendForceAlert;

	// ---- Condition catalog ---------------------------------------------------

	var conditionTypes = [
		{ value: 'to_domain',        label: rT( 'cond_to_domain', 'Recipient domain' ), ops: [ 'equals', 'contains', 'ends_with' ], placeholder: 'gmail.com' },
		{ value: 'to_pattern',       label: rT( 'cond_to_pattern', 'Recipient pattern (glob: * and ?)' ), ops: [ 'equals' ], placeholder: '*@example.com' },
		{ value: 'from_email',       label: rT( 'cond_from_email', 'From email' ),       ops: [ 'equals', 'contains', 'ends_with' ], placeholder: 'shop@example.com' },
		{ value: 'from_domain',      label: rT( 'cond_from_domain', 'From domain' ),      ops: [ 'equals', 'contains', 'ends_with' ], placeholder: 'example.com' },
		{ value: 'source_plugin',    label: rT( 'cond_source_plugin', 'Source plugin' ),    ops: [ 'equals' ],   placeholder: 'woocommerce', isDropdown: true },
		{ value: 'subject_contains', label: rT( 'cond_subject_contains', 'Subject contains' ), ops: [ 'contains' ], placeholder: 'invoice' },
		{ value: 'time_between',     label: rT( 'cond_time_between', 'Time of day (hour-hour, 24h)' ), ops: [ 'equals' ], placeholder: '09-18' },
		{ value: 'weekday',          label: rT( 'cond_weekday', 'Weekday(s) (1=Mon … 7=Sun)' ), ops: [ 'equals' ], placeholder: '1,2,3,4,5' }
	];

	// Operator value → translated label. Falls back to the humanized value.
	function opLabel( op ) {
		return rT( 'op_' + op, op.replace( /_/g, ' ' ) );
	}

	function findCondType( type ) {
		for ( var i = 0; i < conditionTypes.length; i++ ) {
			if ( conditionTypes[ i ].value === type ) { return conditionTypes[ i ]; }
		}
		return conditionTypes[ 0 ];
	}

	// ---- Condition row builder ----------------------------------------------

	function buildConditionRow( cond ) {
		cond = cond || { type: 'to_domain', op: 'equals', value: '' };
		var ct = findCondType( cond.type );

		var $row = $( '<div class="sendforce-route-cond"></div>' );

		var $type = $( '<select class="sendforce-select sendforce-cond-type"></select>' );
		conditionTypes.forEach( function ( t ) {
			var sel = ( t.value === cond.type ) ? ' selected' : '';
			$type.append( '<option value="' + t.value + '"' + sel + '>' + t.label + '</option>' );
		} );

		var $op = $( '<select class="sendforce-select sendforce-cond-op"></select>' );
		ct.ops.forEach( function ( o ) {
			var sel = ( o === cond.op ) ? ' selected' : '';
			$op.append( '<option value="' + o + '"' + sel + '>' + escapeHtml( opLabel( o ) ) + '</option>' );
		} );

		var $value;
		if ( ct.isDropdown && data.sourcePlugins ) {
			$value = $( '<select class="sendforce-select sendforce-cond-value"></select>' );
			Object.keys( data.sourcePlugins ).forEach( function ( slug ) {
				var sel = ( slug === cond.value ) ? ' selected' : '';
				$value.append( '<option value="' + slug + '"' + sel + '>' + data.sourcePlugins[ slug ] + '</option>' );
			} );
		} else {
			$value = $( '<input type="text" class="sendforce-input sendforce-cond-value" />' )
				.attr( 'placeholder', ct.placeholder )
				.val( cond.value || '' );
		}

		var $rm = $( '<button type="button" class="sendforce-action-btn sendforce-action-btn-delete" title="Remove">' +
			'<span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>' );

		// Cell wrappers own the flex sizing so the layout stays correct whether
		// the inner element is a native <select>, an enhanced .sf-dd wrapper, or
		// a text <input> (custom-select only wraps selects).
		var $cType  = $( '<div class="sendforce-route-cond-cell sendforce-route-cond-cell--type"></div>' ).append( $type );
		var $cOp    = $( '<div class="sendforce-route-cond-cell sendforce-route-cond-cell--op"></div>' ).append( $op );
		var $cValue = $( '<div class="sendforce-route-cond-cell sendforce-route-cond-cell--value"></div>' ).append( $value );

		$row.append( $cType ).append( $cOp ).append( $cValue ).append( $rm );

		// Type changed → rebuild op + value to match new type's allowed list.
		$type.on( 'change', function () {
			var $card = $row.closest( '.sendforce-route-card' );
			var newCond = { type: $( this ).val(), op: 'equals', value: '' };
			var $newRow = buildConditionRow( newCond );
			$row.replaceWith( $newRow );
			if ( typeof window.sendForceEnhanceSelects === 'function' ) {
				window.sendForceEnhanceSelects( $newRow.get( 0 ) );
			}
			markDirty( $card );
		} );

		$rm.on( 'click', function () {
			var $card = $row.closest( '.sendforce-route-card' );
			$row.remove();
			markDirty( $card );
		} );

		return $row;
	}

	// ---- Card hydration ------------------------------------------------------
	//
	// Server renders each card without populating the conditions builder (the
	// rule's conditions ride along as a JSON blob in data-route-conditions).
	// On load we read that blob and build the rows, then wipe it so we don't
	// double-hydrate.

	function hydrateCard( $card ) {
		var raw = $card.attr( 'data-route-conditions' );
		var conds = [];
		try { conds = raw ? JSON.parse( raw ) : []; } catch ( e ) { conds = []; }

		var $cont = $card.find( '.sendforce-route-conditions' ).empty();
		if ( ! conds.length ) {
			$cont.append( buildConditionRow() );
		} else {
			conds.forEach( function ( c ) { $cont.append( buildConditionRow( c ) ); } );
		}

		// Cache the original snapshot for revert.
		$card.data( 'snapshot', cardSnapshot( $card ) );
		$card.removeAttr( 'data-route-conditions' );
		$card.removeClass( 'is-dirty' );
	}

	$( '.sendforce-route-card' ).each( function () { hydrateCard( $( this ) ); } );

	// Beautify any selects that were rendered by JS (condition rows etc.).
	function enhanceSelects( $scope ) {
		if ( typeof window.sendForceEnhanceSelects === 'function' ) {
			window.sendForceEnhanceSelects( $scope ? $scope.get( 0 ) : document );
		}
	}
	enhanceSelects();

	// Update the header "title" + "→ Connection" pill whenever the user edits
	// the corresponding inputs. Keeps the card's identity legible at a glance.
	function refreshCardHeader( $card ) {
		var label = $card.find( '.sendforce-route-label-input' ).val() || '';
		var connId = $card.find( '.sendforce-route-action-input' ).val() || '';
		var connLabel = '';
		( data.connections || [] ).forEach( function ( c ) {
			if ( c.id === connId ) { connLabel = c.label; }
		} );
		var $title = $card.find( '.sendforce-route-card-title' );
		var isNew = parseInt( $card.attr( 'data-route-id' ), 10 ) === 0;
		$title.text( label || ( isNew ? rT( 'new_rule', 'New rule' ) : rT( 'unnamed_rule', '(unnamed rule)' ) ) );
		var $pill = $card.find( '.sendforce-route-card-target' );
		if ( connLabel ) {
			if ( ! $pill.length ) {
				$pill = $( '<span class="sendforce-route-card-target"></span>' );
				$card.find( '.sendforce-route-card-title' ).after( $pill );
			}
			$pill.empty()
				.append( '<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>' )
				.append( document.createTextNode( ' ' + connLabel ) );
		} else if ( $pill.length ) {
			$pill.remove();
		}
	}

	$( document ).on( 'input', '.sendforce-route-label-input', function () {
		refreshCardHeader( $( this ).closest( '.sendforce-route-card' ) );
	} );
	$( document ).on( 'change', '.sendforce-route-action-input', function () {
		refreshCardHeader( $( this ).closest( '.sendforce-route-card' ) );
	} );

	// ---- Dirty tracking ------------------------------------------------------

	function cardSnapshot( $card ) {
		var conds = [];
		$card.find( '.sendforce-route-cond' ).each( function () {
			conds.push( {
				type:  $( this ).find( '.sendforce-cond-type' ).val(),
				op:    $( this ).find( '.sendforce-cond-op' ).val(),
				value: $( this ).find( '.sendforce-cond-value' ).val()
			} );
		} );
		return JSON.stringify( {
			label:        $card.find( '.sendforce-route-label-input' ).val(),
			action_value: $card.find( '.sendforce-route-action-input' ).val(),
			enabled:      $card.find( '.sendforce-route-enabled-input' ).is( ':checked' ) ? 1 : 0,
			conditions:   conds
		} );
	}

	function markDirty( $card ) {
		if ( ! $card.length ) { return; }
		$card.addClass( 'is-dirty' );
	}

	function markClean( $card ) {
		$card.removeClass( 'is-dirty' );
		$card.data( 'snapshot', cardSnapshot( $card ) );
	}

	// Any change inside a card flags it dirty.
	$( document ).on(
		'input change',
		'.sendforce-route-card .sendforce-route-label-input, ' +
		'.sendforce-route-card .sendforce-route-action-input, ' +
		'.sendforce-route-card .sendforce-route-enabled-input, ' +
		'.sendforce-route-card .sendforce-cond-type, ' +
		'.sendforce-route-card .sendforce-cond-op, ' +
		'.sendforce-route-card .sendforce-cond-value',
		function () {
			markDirty( $( this ).closest( '.sendforce-route-card' ) );
		}
	);

	// Browser warning when leaving with unsaved changes.
	$( window ).on( 'beforeunload', function () {
		if ( $( '.sendforce-route-card.is-dirty' ).length ) {
			return 'You have unsaved routing rule changes.';
		}
	} );

	// ---- Save ----------------------------------------------------------------

	function showCardError( $card, message ) {
		var $footer = $card.find( '.sendforce-route-card-footer' );
		var $err    = $card.find( '.sendforce-route-error' );
		if ( ! $err.length ) {
			$err = $( '<span class="sendforce-route-error" role="alert"></span>' );
			$footer.prepend( $err );
		}
		$err.text( message ).show();
		setTimeout( function () { $err.fadeOut( 300, function () { $err.remove(); } ); }, 3500 );
	}

	function saveCard( $card ) {
		var conds   = [];
		var invalid = false;

		// Clear any previous validation marker.
		$card.find( '.sendforce-cond-value, [data-cond-value-input]' ).each( function () {
			$( this ).removeClass( 'sendforce-route-invalid' );
		} );

		$card.find( '.sendforce-route-cond' ).each( function () {
			var $r    = $( this );
			var type  = $r.find( '.sendforce-cond-type' ).val();
			var op    = $r.find( '.sendforce-cond-op' ).val();
			var value = $.trim( $r.find( '.sendforce-cond-value' ).val() || '' );
			if ( '' === value ) {
				invalid = true;
				$r.find( '.sendforce-cond-value' ).addClass( 'sendforce-route-invalid' );
			}
			conds.push( { type: type, op: op, value: value } );
		} );

		// Need at least one condition.
		if ( ! conds.length ) {
			showCardError( $card, 'Add at least one condition.' );
			return;
		}
		if ( invalid ) {
			showCardError( $card, 'Fill in every condition value before saving.' );
			return;
		}
		// Need a target connection.
		if ( ! $card.find( '.sendforce-route-action-input' ).val() ) {
			showCardError( $card, 'Pick a target connection.' );
			return;
		}

		var $btn = $card.find( '.sendforce-route-save' );
		var origHtml = $btn.html();
		$btn.prop( 'disabled', true ).text( rT( 'saving', 'Saving…' ) );

		$.post( window.sendForce.ajax_url, {
			action:       'sendforce_save_route',
			nonce:        window.sendForce.nonce,
			id:           parseInt( $card.attr( 'data-route-id' ), 10 ) || 0,
			label:        $card.find( '.sendforce-route-label-input' ).val(),
			enabled:      $card.find( '.sendforce-route-enabled-input' ).is( ':checked' ) ? 1 : 0,
			action_type:  'connection',
			action_value: $card.find( '.sendforce-route-action-input' ).val(),
			conditions:   conds
		}, function ( resp ) {
			$btn.prop( 'disabled', false ).html( origHtml );
			if ( resp.success ) {
				markClean( $card );
				if ( resp.data && resp.data.reload ) {
					// Reload to refresh new-rule IDs and indices server-side.
					window.location.reload();
				}
			} else {
				sendForceAlert( rT( 'save_failed', 'Save failed' ), resp.data || rT( 'err_save_rule', 'Failed to save rule.' ) );
			}
		} ).fail( function () {
			$btn.prop( 'disabled', false ).html( origHtml );
			sendForceAlert( 'Network error', 'Could not reach the server. Check your connection and try again.' );
		} );
	}

	$( document ).on( 'click', '.sendforce-route-save', function () {
		saveCard( $( this ).closest( '.sendforce-route-card' ) );
	} );

	// ---- Revert (restore snapshot taken at hydrate time) --------------------

	$( document ).on( 'click', '.sendforce-route-revert', function () {
		var $card = $( this ).closest( '.sendforce-route-card' );
		var id    = parseInt( $card.attr( 'data-route-id' ), 10 ) || 0;

		// Brand-new (never-saved) cards: Discard simply removes them from the DOM.
		// If that leaves zero cards, reload so the empty-state placeholder renders.
		if ( ! id ) {
			$card.remove();
			if ( ! $( '#sendforce-routes-list .sendforce-route-card' ).length ) {
				window.location.reload();
			}
			return;
		}

		var snap;
		try { snap = JSON.parse( $card.data( 'snapshot' ) || '{}' ); } catch ( e ) { return; }

		$card.find( '.sendforce-route-label-input' ).val( snap.label || '' );
		$card.find( '.sendforce-route-enabled-input' ).prop( 'checked', !! parseInt( snap.enabled, 10 ) );

		// Set the connection select's value, then fire a NATIVE event so the
		// custom-select wrapper rebuilds its visible label. jQuery's .trigger()
		// for custom events doesn't reach addEventListener handlers — we have
		// to dispatch a real Event.
		var actionEl = $card.find( '.sendforce-route-action-input' ).get( 0 );
		if ( actionEl ) {
			actionEl.value = snap.action_value || '';
			actionEl.dispatchEvent( new Event( 'sendforce:rebuild' ) );
			actionEl.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}

		var $cont = $card.find( '.sendforce-route-conditions' ).empty();
		var conds = ( snap.conditions && snap.conditions.length ) ? snap.conditions : [ {} ];
		conds.forEach( function ( c ) { $cont.append( buildConditionRow( c ) ); } );
		enhanceSelects( $cont );

		refreshCardHeader( $card );
		$card.removeClass( 'is-dirty' );
	} );

	// ---- Add Condition (per card) -------------------------------------------

	$( document ).on( 'click', '.sendforce-route-add-cond', function () {
		var $card = $( this ).closest( '.sendforce-route-card' );
		var $cont = $card.find( '.sendforce-route-conditions' );
		var $row  = buildConditionRow();
		$cont.append( $row );
		enhanceSelects( $row );
		markDirty( $card );
	} );

	// ---- Add new rule --------------------------------------------------------

	function buildBlankCardHtml( opts ) {
		opts = opts || {};
		var connectionsHtml = '';
		( data.connections || [] ).forEach( function ( c ) {
			var sel = ( opts.action_value === c.id ) ? ' selected' : '';
			connectionsHtml += '<option value="' + c.id + '"' + sel + '>' + escapeHtml( c.label ) + '</option>';
		} );

		var label = opts.label || '';

		return '' +
			'<div class="sendforce-route-card is-new is-dirty" data-route-id="0">' +
				'<div class="sendforce-route-card-header">' +
					'<span class="sendforce-route-card-index">#new</span>' +
					'<span class="sendforce-route-card-title">' + ( label ? escapeHtml( label ) : escapeHtml( rT( 'new_rule', 'New rule' ) ) ) + '</span>' +
					'<span class="sendforce-route-card-flex"></span>' +
					'<span class="sendforce-route-card-saved-flag" aria-live="polite"></span>' +
					'<button type="button" class="sendforce-route-card-btn sendforce-route-up" title="Move up"><span class="dashicons dashicons-arrow-up-alt2"></span></button>' +
					'<button type="button" class="sendforce-route-card-btn sendforce-route-down" title="Move down"><span class="dashicons dashicons-arrow-down-alt2"></span></button>' +
					'<button type="button" class="sendforce-route-card-btn sendforce-route-delete" title="Discard"><span class="dashicons dashicons-trash"></span></button>' +
				'</div>' +
				'<div class="sendforce-route-card-body">' +
					'<div class="sendforce-field">' +
						'<label class="sendforce-label">Rule Label</label>' +
						'<input type="text" class="sendforce-input sendforce-route-label-input" value="' + escapeHtml( label ) + '" placeholder="e.g. WooCommerce order emails via SES" />' +
					'</div>' +
					'<div class="sendforce-field" style="margin-top:14px;">' +
						'<label class="sendforce-label">Conditions (ALL must match)</label>' +
						'<div class="sendforce-route-conditions"></div>' +
						'<button type="button" class="sendforce-btn sendforce-btn-outline sendforce-btn-sm sendforce-route-add-cond" style="margin-top:8px;">' +
							'<span class="dashicons dashicons-plus"></span> Add Condition' +
						'</button>' +
					'</div>' +
					'<div class="sendforce-route-row">' +
						'<div class="sendforce-field sendforce-route-row-action">' +
							'<label class="sendforce-label">Then send via</label>' +
							'<select class="sendforce-select sendforce-select-full sendforce-route-action-input">' + connectionsHtml + '</select>' +
						'</div>' +
						'<div class="sendforce-field sendforce-route-row-toggle">' +
							'<label class="sendforce-label">&nbsp;</label>' +
							'<label class="sendforce-toggle-label">' +
								'<input type="checkbox" class="sendforce-toggle-input sendforce-route-enabled-input" checked />' +
								'<span class="sendforce-toggle"></span>' +
								'<span>Enabled</span>' +
							'</label>' +
						'</div>' +
					'</div>' +
				'</div>' +
				'<div class="sendforce-route-card-footer">' +
					'<span class="sendforce-route-dirty-hint">Unsaved changes</span>' +
					'<button type="button" class="sendforce-btn sendforce-btn-outline sendforce-btn-sm sendforce-route-revert">Discard</button>' +
					'<button type="button" class="sendforce-btn sendforce-btn-primary sendforce-btn-sm sendforce-route-save">' +
						'<span class="dashicons dashicons-saved"></span> Save' +
					'</button>' +
				'</div>' +
			'</div>';
	}

	function appendNewCard( seedConditions, seedLabel, seedActionValue ) {
		// Empty-state placeholder gets replaced when the first card lands.
		$( '#sendforce-route-empty' ).remove();

		var $card = $( buildBlankCardHtml( {
			label:        seedLabel        || '',
			action_value: seedActionValue  || ( ( data.connections[ 0 ] || {} ).id || '' )
		} ) );

		var $cont = $card.find( '.sendforce-route-conditions' );
		var rows  = ( seedConditions && seedConditions.length ) ? seedConditions : [ {} ];
		rows.forEach( function ( c ) { $cont.append( buildConditionRow( c ) ); } );

		// Override revert on a brand-new card → simply discard it.
		$card.find( '.sendforce-route-revert' ).text( rT( 'discard', 'Discard' ) );

		$( '#sendforce-routes-list' ).prepend( $card );

		$card.data( 'snapshot', JSON.stringify( {
			label: '', action_value: '', enabled: 1, conditions: []
		} ) );

		// Apply custom-select styling to the new card's selects + show target pill.
		enhanceSelects( $card );
		refreshCardHeader( $card );

		// Scroll the new card into view + focus the label.
		$( 'html, body' ).animate( {
			scrollTop: $card.offset().top - 80
		}, 200, function () {
			$card.find( '.sendforce-route-label-input' ).trigger( 'focus' );
		} );
	}

	$( document ).on( 'click', '#sendforce-add-route, #sendforce-add-route-empty', function () {
		appendNewCard();
	} );

	// ---- Empty-state presets -------------------------------------------------

	var presets = {
		woocommerce: {
			label: rT( 'preset_woocommerce', 'WooCommerce orders' ),
			conditions: [ { type: 'source_plugin', op: 'equals', value: 'woocommerce' } ]
		},
		gmail: {
			label: rT( 'preset_gmail', '@gmail.com recipients' ),
			conditions: [ { type: 'to_domain', op: 'equals', value: 'gmail.com' } ]
		},
		forms: {
			label: rT( 'preset_forms', 'Contact form submissions' ),
			conditions: [ { type: 'source_plugin', op: 'equals', value: 'contact-form-7' } ]
		}
	};

	$( document ).on( 'click', '.sendforce-route-preset', function () {
		var key = $( this ).attr( 'data-preset' );
		var p = presets[ key ];
		if ( ! p ) { return; }
		appendNewCard( p.conditions, p.label );
	} );

	// ---- Delete --------------------------------------------------------------

	$( document ).on( 'click', '.sendforce-route-delete', function () {
		var $card = $( this ).closest( '.sendforce-route-card' );
		var id    = parseInt( $card.attr( 'data-route-id' ), 10 ) || 0;

		// Brand-new (unsaved) cards: just remove from DOM.
		if ( ! id ) {
			$card.remove();
			return;
		}

		sendForceConfirm( {
			title:          rT( 'del_rule_title', 'Delete routing rule?' ),
			message:        rT( 'del_rule_msg', 'This rule will be removed permanently. Emails matching its conditions will fall back to your Primary connection.' ),
			confirmText:    rT( 'del_rule_confirm', 'Delete rule' ),
			confirmVariant: 'danger',
			onConfirm: function () {
				$.post( window.sendForce.ajax_url, {
					action: 'sendforce_delete_route',
					nonce:  window.sendForce.nonce,
					id:     id
				}, function () {
					$card.fadeOut( 150, function () {
						$( this ).remove();
						renumberCards();
						if ( ! $( '#sendforce-routes-list .sendforce-route-card' ).length ) {
							window.location.reload();
						}
					} );
				} );
			}
		} );
	} );

	// ---- Reorder (up/down) ---------------------------------------------------

	function persistOrder() {
		var order = [];
		$( '#sendforce-routes-list .sendforce-route-card' ).each( function () {
			var id = parseInt( $( this ).attr( 'data-route-id' ), 10 ) || 0;
			if ( id ) { order.push( id ); }
		} );
		if ( ! order.length ) { return; }
		$.post( window.sendForce.ajax_url, {
			action: 'sendforce_reorder_routes',
			nonce:  window.sendForce.nonce,
			order:  order
		} );
	}

	function renumberCards() {
		// Count only SAVED cards; unsaved (data-route-id="0") cards keep "#new".
		// Otherwise an unsaved card at the top would push saved-card numbers off
		// by one (e.g. show #2, #3 instead of #1, #2).
		var n = 0;
		$( '#sendforce-routes-list .sendforce-route-card' ).each( function () {
			var id   = parseInt( $( this ).attr( 'data-route-id' ), 10 ) || 0;
			var $idx = $( this ).find( '.sendforce-route-card-index' );
			if ( id > 0 ) {
				n++;
				$idx.text( '#' + n );
			}
		} );
	}

	$( document ).on( 'click', '.sendforce-route-up', function () {
		var $card = $( this ).closest( '.sendforce-route-card' );
		var $prev = $card.prev( '.sendforce-route-card' );
		if ( ! $prev.length ) { return; }
		$card.insertBefore( $prev );
		renumberCards();
		persistOrder();
	} );

	$( document ).on( 'click', '.sendforce-route-down', function () {
		var $card = $( this ).closest( '.sendforce-route-card' );
		var $next = $card.next( '.sendforce-route-card' );
		if ( ! $next.length ) { return; }
		$card.insertAfter( $next );
		renumberCards();
		persistOrder();
	} );

	// ---- Master "Enable Smart Routing" toggle -------------------------------

	$( document ).on( 'change', '#sendforce-routing-enabled', function () {
		var on   = $( this ).is( ':checked' );
		var $st  = $( '#sendforce-routing-status' );
		$st.text( on ? 'Active' : 'Paused' )
		   .toggleClass( 'is-on',  on )
		   .toggleClass( 'is-off', ! on );

		$.post( window.sendForce.ajax_url, {
			action:  'sendforce_toggle_routing',
			nonce:   window.sendForce.nonce,
			enabled: on ? 1 : 0
		} );
	} );

	// ---- Test Routing tool ---------------------------------------------------

	$( document ).on( 'click', '#sendforce-test-route-btn', function () {
		var $btn    = $( this );
		var $result = $( '#sendforce-test-route-result' );
		$btn.prop( 'disabled', true ).text( rT( 'testing', 'Testing…' ) );
		$result.text( '' );

		$.post( window.sendForce.ajax_url, {
			action:  'sendforce_test_route',
			nonce:   window.sendForce.nonce,
			to:      $( '#sendforce-test-route-to' ).val(),
			from:    $( '#sendforce-test-route-from' ).val(),
			subject: $( '#sendforce-test-route-subject' ).val(),
			source:  $( '#sendforce-test-route-source' ).val()
		}, function ( resp ) {
			$btn.prop( 'disabled', false ).text( rT( 'run_test', 'Run Test' ) );
			if ( resp.success && resp.data.matched ) {
				$result.html( '<div class="sendforce-notice sendforce-notice-success">' +
					'<span class="dashicons dashicons-yes-alt"></span>' +
					'<div>Matched rule <strong>' + escapeHtml( resp.data.rule_label ) +
					'</strong> → routes to connection <strong>' + escapeHtml( resp.data.connection ) +
					'</strong></div></div>' );
			} else if ( resp.success ) {
				$result.html( '<div class="sendforce-notice sendforce-notice-info">' +
					'<span class="dashicons dashicons-info"></span>' +
					'<div>No rule matched. This email would go through your <strong>Primary connection</strong>.</div></div>' );
			} else {
				$result.text( resp.data || rT( 'err_test_failed', 'Test failed.' ) );
			}
		} );
	} );

	// ---- Util ---------------------------------------------------------------

	function escapeHtml( s ) {
		return String( s == null ? '' : s )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	}

}( jQuery ) );
