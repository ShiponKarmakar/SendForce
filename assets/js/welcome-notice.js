/**
 * "Updated to vX" welcome notice behaviour.
 * Loaded only when the notice is rendered (see SendForce_Admin::show_update_welcome_notice).
 */
( function () {
	'use strict';

	var data = window.sendForceWelcome || {};

	// Mark this version's welcome notice as seen for the current user. Uses
	// sendBeacon so the request survives even when the click also navigates
	// the page away (the "See what's new" button).
	function sendforceMarkSeen( version ) {
		if ( ! data.ajaxUrl ) {
			return;
		}
		var fd = new FormData();
		fd.append( 'action', 'sendforce_dismiss_update_welcome' );
		fd.append( 'nonce', data.nonce || '' );
		fd.append( 'version', version || '' );
		if ( navigator.sendBeacon ) {
			navigator.sendBeacon( data.ajaxUrl, fd );
		} else {
			fetch( data.ajaxUrl, { method: 'POST', credentials: 'same-origin', keepalive: true, body: fd } );
		}
	}

	function sendforceInitWelcomeNotice() {
		var notices = document.querySelectorAll( '.sendforce-welcome-notice' );
		Array.prototype.forEach.call( notices, function ( el ) {
			var v = el.getAttribute( 'data-sendforce-version' ) || '';
			// Delegated: WordPress core injects the .notice-dismiss "×" button
			// after load, so listen on the notice itself. Dismissing via the "×"
			// or clicking "See what's new" both count as acknowledging it.
			el.addEventListener( 'click', function ( e ) {
				if ( e.target.closest( '.notice-dismiss' ) || e.target.closest( '.sendforce-welcome-cta' ) ) {
					sendforceMarkSeen( v );
				}
			}, false );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', sendforceInitWelcomeNotice );
	} else {
		sendforceInitWelcomeNotice();
	}
}() );
