/**
 * Conflict-detection admin notice behaviour.
 * Loaded only when the notice is rendered (see SendForce_Admin::show_conflict_notice).
 */
( function () {
	'use strict';

	function sendforceHideConflictNotice() {
		var notice = document.getElementById( 'sendforce-conflict-notice' );
		if ( ! notice ) {
			return;
		}
		notice.style.transition = 'opacity 0.25s ease, max-height 0.3s ease, margin 0.3s ease';
		notice.style.overflow   = 'hidden';
		notice.style.maxHeight  = notice.offsetHeight + 'px';
		notice.getBoundingClientRect();
		notice.style.opacity   = '0';
		notice.style.maxHeight = '0';
		notice.style.marginTop = '0';
		setTimeout( function () { notice.remove(); }, 320 );
	}

	function sendforceDismissConflict( slug, nonce ) {
		var data = window.sendForceConflict || {};
		var url  = data.ajaxUrl || ( window.ajaxurl ? window.ajaxurl : '' );
		if ( ! url ) {
			return;
		}
		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', url, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
		xhr.send(
			'action=sendforce_dismiss_conflict' +
			'&nonce=' + encodeURIComponent( nonce ) +
			'&slug='  + encodeURIComponent( slug )
		);
	}

	// ---- Import from another SMTP plugin ----------------------------------
	// All text is set with textContent: plugin names, hosts and notes come
	// from another plugin's settings and must never be parsed as HTML.

	var data    = window.sendForceConflict || {};
	var strings = data.strings || {};

	function t( key, fallback ) {
		var str  = strings[ key ] || fallback;
		var args = Array.prototype.slice.call( arguments, 2 );
		return String( str ).replace( /%[sd]/g, function () { return args.length ? args.shift() : ''; } );
	}

	function el( tag, cls, text ) {
		var node = document.createElement( tag );
		if ( cls ) { node.className = cls; }
		if ( undefined !== text ) { node.textContent = text; }
		return node;
	}

	function post( action, source, context ) {
		var body = 'action=' + encodeURIComponent( action ) +
			'&nonce=' + encodeURIComponent( data.importNonce || '' ) +
			'&source=' + encodeURIComponent( source ) +
			'&context=' + encodeURIComponent( context || '' );
		return fetch( data.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body
		} ).then( function ( r ) { return r.json(); } );
	}

	function message( panel, text, cls ) {
		panel.textContent = '';
		panel.appendChild( el( 'p', cls || '', text ) );
	}

	function renderPreview( panel, source, plan ) {
		panel.textContent = '';
		panel.appendChild( el( 'p', 'sendforce-import-intro', t( 'intro', 'This will be added to SendForce.' ) ) );

		var list       = el( 'ul', 'sendforce-import-items' );
		var importable = 0;
		plan.items.forEach( function ( item ) {
			var li   = el( 'li', 'sendforce-import-item is-' + item.status );
			var head = el( 'div', 'sendforce-import-item-head' );
			head.appendChild( el( 'strong', '', item.label ) );
			if ( 'primary' === item.role || 'fallback' === item.role ) {
				head.appendChild( el( 'span', 'sendforce-import-badge', t( 'role_' + item.role, 'primary' === item.role ? 'Primary' : 'Fallback' ) ) );
			}
			if ( 'already' === item.status ) {
				head.appendChild( el( 'span', 'sendforce-import-badge is-muted', t( 'status_already', 'Already imported' ) ) );
			} else if ( 'unsupported' === item.status ) {
				head.appendChild( el( 'span', 'sendforce-import-badge is-muted', t( 'status_unsupported', "Can't be imported" ) ) );
			} else {
				importable++;
			}
			li.appendChild( head );
			if ( item.summary ) { li.appendChild( el( 'div', 'sendforce-import-summary', item.summary ) ); }
			( item.notes || [] ).forEach( function ( note ) {
				li.appendChild( el( 'div', 'sendforce-import-note', note ) );
			} );
			list.appendChild( li );
		} );
		panel.appendChild( list );

		if ( plan.sender && plan.sender.force && plan.sender.email ) {
			panel.appendChild( el( 'p', 'sendforce-import-summary', t( 'sender', 'Sender identity: %s', plan.sender.email ) ) );
		}

		var actions = el( 'div', 'sendforce-import-actions' );
		if ( importable ) {
			var go = el( 'button', 'sendforce-import-go', t( 'confirm', 'Import' ) );
			go.type = 'button';
			go.addEventListener( 'click', function () { runImport( panel, source ); } );
			actions.appendChild( go );
		} else {
			actions.appendChild( el( 'span', 'sendforce-import-summary', t( 'nothingToDo', 'Everything here has already been imported.' ) ) );
		}
		var cancel = el( 'button', 'sendforce-import-cancel', t( 'cancel', 'Cancel' ) );
		cancel.type = 'button';
		cancel.addEventListener( 'click', function () { panel.hidden = true; panel.textContent = ''; } );
		actions.appendChild( cancel );
		panel.appendChild( actions );
	}

	function runImport( panel, source ) {
		// The Setup screen reloads afterwards and renders the result itself.
		var onSetup = !! panel.closest( '.sendforce-ob' );
		// Everywhere else the page stays put, so the trigger has to stop
		// saying "Import settings" once the import has happened.
		var row     = panel.closest( 'li' );
		var trigger = row ? row.querySelector( '.sendforce-conflict-import' ) : null;
		message( panel, t( 'importing', 'Importing…' ) );
		post( 'sendforce_import_run', source, onSetup ? 'setup' : '' ).then( function ( res ) {
			if ( ! res || ! res.success ) {
				message( panel, ( res && res.data ) ? String( res.data ) : t( 'failed', 'Import failed.' ), 'sendforce-import-error' );
				return;
			}
			// On the Setup screen the steps above this panel are now out of date
			// (a connection exists). Reload so they recalculate; the result is
			// rendered server-side from a transient on the way back in.
			if ( onSetup ) {
				window.location.reload();
				return;
			}
			if ( trigger && trigger.getAttribute( 'data-done-label' ) ) {
				trigger.textContent = trigger.getAttribute( 'data-done-label' );
				trigger.classList.add( 'is-secondary' );
			}
			var r = res.data;
			panel.textContent = '';
			panel.appendChild( el( 'p', 'sendforce-import-done', t( 'done', 'Imported %d connection(s).', r.imported.length ) ) );
			if ( r.rules ) {
				panel.appendChild( el( 'p', 'sendforce-import-summary', t( 'doneRules', 'Created %d Smart Routing rule(s).', r.rules ) ) );
			}
			if ( r.routing_off ) {
				panel.appendChild( el( 'p', 'sendforce-import-note', t( 'routingOff', 'Smart Routing is switched off, so these rules will not apply until you turn it on in the Routing tab.' ) ) );
			}
			if ( r.needs_action && r.needs_action.length ) {
				panel.appendChild( el( 'p', 'sendforce-import-note', t( 'doneAction', 'Needs your attention: %s', r.needs_action.join( ', ' ) ) ) );
			}
			panel.appendChild( el( 'p', 'sendforce-import-summary', t( 'nextStep', 'Deactivate the other plugin when ready.' ) ) );
			var link = el( 'a', 'sendforce-import-go', t( 'review', 'Review in Settings' ) );
			link.href = r.settings_url;
			panel.appendChild( link );
		} ).catch( function () {
			message( panel, t( 'failed', 'Import failed.' ), 'sendforce-import-error' );
		} );
	}

	function sendforceInitImport() {
		Array.prototype.forEach.call( document.querySelectorAll( '.sendforce-conflict-import' ), function ( btn ) {
			btn.addEventListener( 'click', function () {
				var source = this.getAttribute( 'data-source' );
				var panel  = this.closest( 'li' ).querySelector( '.sendforce-import-panel' );
				if ( ! panel ) { return; }
				panel.hidden = false;
				message( panel, t( 'checking', 'Checking settings…' ) );
				post( 'sendforce_import_preview', source ).then( function ( res ) {
					if ( ! res || ! res.success ) {
						message( panel, ( res && res.data ) ? String( res.data ) : t( 'failed', 'Import failed.' ), 'sendforce-import-error' );
						return;
					}
					renderPreview( panel, source, res.data );
				} ).catch( function () {
					message( panel, t( 'failed', 'Import failed.' ), 'sendforce-import-error' );
				} );
			} );
		} );
	}

	function sendforceInitConflictNotice() {
		sendforceInitImport();
		var closeBtn = document.getElementById( 'sendforce-conflict-close' );
		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', sendforceHideConflictNotice );
		}

		var buttons = document.querySelectorAll( '.sendforce-conflict-dismiss-one' );
		Array.prototype.forEach.call( buttons, function ( btn ) {
			btn.addEventListener( 'click', function () {
				var slug  = this.getAttribute( 'data-slug' );
				var nonce = this.getAttribute( 'data-nonce' );
				sendforceDismissConflict( slug, nonce );

				var li = this.closest( 'li' );
				if ( li ) {
					li.style.transition = 'opacity 0.2s ease';
					li.style.opacity    = '0';
					setTimeout( function () {
						li.remove();
						// Only the notice's own rows: not import-preview items, and not
						// the Settings tab's import card, which reuses this list markup.
						var remaining = document.querySelectorAll( '#sendforce-conflict-notice .sendforce-conflict-list > li' );
						if ( remaining.length === 0 ) {
							sendforceHideConflictNotice();
						}
					}, 220 );
				}
			} );
		} );
	}

	// Run immediately if the DOM is already parsed (e.g. if this script is ever
	// loaded with a defer/async strategy, DOMContentLoaded may have already
	// fired — a plain listener would then never run and the buttons would be dead).
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', sendforceInitConflictNotice );
	} else {
		sendforceInitConflictNotice();
	}
}() );
