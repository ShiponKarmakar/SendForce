/**
 * SendForce custom dropdown component.
 *
 * Replaces every <select.sendforce-select> with a fully styleable, animated
 * dropdown panel. The original <select> stays in the DOM so form submission,
 * server-side processing, JS that listens for "change" events, and screen
 * readers all keep working unchanged.
 *
 * Usage: just enqueue this script and the matching CSS — no per-element
 * markup changes required.
 */
( function () {
	'use strict';

	var ATTR_ENHANCED = 'data-sf-dd-enhanced';

	// Inline SVGs as strings (cheaper than DOM-builds, no runtime parser cost).
	var CHEVRON_SVG =
		'<svg class="sf-dd-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
		'stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
		'<polyline points="6 9 12 15 18 9"/></svg>';
	var CHECK_SVG =
		'<svg class="sf-dd-option-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
		'stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
		'<polyline points="20 6 9 17 4 12"/></svg>';

	// Shared "open" state — we close any other dropdown before opening a new one.
	var openWrapper = null;

	function escapeHtml( str ) {
		return String( str ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function buildOptions( select ) {
		var html = '';
		var children = select.children;

		for ( var i = 0; i < children.length; i++ ) {
			var node = children[ i ];

			if ( node.tagName === 'OPTGROUP' ) {
				html += '<div class="sf-dd-group-label">' + escapeHtml( node.label ) + '</div>';
				var opts = node.children;
				for ( var j = 0; j < opts.length; j++ ) {
					html += renderOption( opts[ j ] );
				}
			} else if ( node.tagName === 'OPTION' ) {
				html += renderOption( node );
			}
		}
		return html;
	}

	function renderOption( opt ) {
		var selected = opt.selected ? ' is-selected' : '';
		var disabled = opt.disabled ? ' disabled' : '';
		var disClass = opt.disabled ? '' : '';
		return (
			'<button type="button" class="sf-dd-option' + selected + disClass + '" role="option" ' +
			'data-value="' + escapeHtml( opt.value ) + '" ' +
			'aria-selected="' + ( opt.selected ? 'true' : 'false' ) + '"' + disabled + '>' +
			CHECK_SVG +
			'<span>' + escapeHtml( opt.textContent ) + '</span>' +
			'</button>'
		);
	}

	function getSelectedLabel( select ) {
		var idx = select.selectedIndex;
		if ( idx < 0 ) {
			return { text: '', placeholder: true };
		}
		var opt = select.options[ idx ];
		var text = opt && opt.textContent ? opt.textContent : '';
		// Treat empty-value first-option as a placeholder.
		var placeholder = ! text || ( opt && opt.value === '' && idx === 0 );
		return { text: text || '— select —', placeholder: !! placeholder };
	}

	function closeDropdown( wrapper ) {
		if ( ! wrapper ) {
			return;
		}
		wrapper.setAttribute( 'data-open', 'false' );
		var trigger = wrapper.querySelector( '.sf-dd-trigger' );
		if ( trigger ) {
			trigger.setAttribute( 'aria-expanded', 'false' );
		}
		if ( openWrapper === wrapper ) {
			openWrapper = null;
		}
	}

	function openDropdown( wrapper ) {
		if ( openWrapper && openWrapper !== wrapper ) {
			closeDropdown( openWrapper );
		}
		wrapper.setAttribute( 'data-open', 'true' );
		var trigger = wrapper.querySelector( '.sf-dd-trigger' );
		if ( trigger ) {
			trigger.setAttribute( 'aria-expanded', 'true' );
		}
		openWrapper = wrapper;

		// Scroll the currently selected option into view.
		var sel = wrapper.querySelector( '.sf-dd-option.is-selected' );
		if ( sel ) {
			sel.scrollIntoView( { block: 'nearest' } );
		}
	}

	function selectOption( select, wrapper, value ) {
		var changed = select.value !== value;
		select.value = value;

		// Refresh the trigger label.
		var labelEl = wrapper.querySelector( '.sf-dd-label' );
		var info = getSelectedLabel( select );
		labelEl.textContent = info.text;
		labelEl.classList.toggle( 'is-placeholder', info.placeholder );

		// Refresh option-selected state.
		var opts = wrapper.querySelectorAll( '.sf-dd-option' );
		for ( var i = 0; i < opts.length; i++ ) {
			var isSel = opts[ i ].getAttribute( 'data-value' ) === value;
			opts[ i ].classList.toggle( 'is-selected', isSel );
			opts[ i ].setAttribute( 'aria-selected', isSel ? 'true' : 'false' );
		}

		closeDropdown( wrapper );

		// Keep external listeners (jQuery, vanilla, framework) working.
		if ( changed ) {
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
	}

	function moveActive( wrapper, direction ) {
		var opts = Array.prototype.slice.call(
			wrapper.querySelectorAll( '.sf-dd-option:not([disabled])' )
		);
		if ( ! opts.length ) {
			return;
		}
		var current = wrapper.querySelector( '.sf-dd-option.is-active' );
		var idx = current ? opts.indexOf( current ) : -1;
		idx = ( idx + direction + opts.length ) % opts.length;
		opts.forEach( function ( o ) { o.classList.remove( 'is-active' ); } );
		opts[ idx ].classList.add( 'is-active' );
		opts[ idx ].scrollIntoView( { block: 'nearest' } );
	}

	function enhance( select ) {
		if ( select.getAttribute( ATTR_ENHANCED ) === '1' ) {
			return;
		}
		// Skip multi-selects — too complex for this minimal component.
		if ( select.multiple ) {
			return;
		}
		select.setAttribute( ATTR_ENHANCED, '1' );

		var info = getSelectedLabel( select );

		var wrapper = document.createElement( 'div' );
		wrapper.className = 'sf-dd';
		wrapper.setAttribute( 'data-open', 'false' );

		wrapper.innerHTML =
			'<button type="button" class="sf-dd-trigger" aria-haspopup="listbox" aria-expanded="false">' +
			'<span class="sf-dd-label' + ( info.placeholder ? ' is-placeholder' : '' ) + '">' +
			escapeHtml( info.text ) +
			'</span>' +
			CHEVRON_SVG +
			'</button>' +
			'<div class="sf-dd-panel" role="listbox" tabindex="-1">' +
			buildOptions( select ) +
			'</div>';

		// Inherit disabled state.
		if ( select.disabled ) {
			wrapper.querySelector( '.sf-dd-trigger' ).setAttribute( 'disabled', 'disabled' );
		}

		// Insert wrapper directly before the native select, then hide the native.
		select.parentNode.insertBefore( wrapper, select );
		select.classList.add( 'sf-dd-native' );
		select.setAttribute( 'aria-hidden', 'true' );
		select.setAttribute( 'tabindex', '-1' );

		// Trigger click toggles the panel.
		var trigger = wrapper.querySelector( '.sf-dd-trigger' );
		trigger.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( select.disabled ) {
				return;
			}
			if ( wrapper.getAttribute( 'data-open' ) === 'true' ) {
				closeDropdown( wrapper );
			} else {
				openDropdown( wrapper );
			}
		} );

		// Option click → select & close.
		wrapper.addEventListener( 'click', function ( e ) {
			var opt = e.target.closest( '.sf-dd-option' );
			if ( ! opt || opt.hasAttribute( 'disabled' ) ) {
				return;
			}
			selectOption( select, wrapper, opt.getAttribute( 'data-value' ) );
		} );

		// Keyboard support.
		trigger.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === ' ' ) {
				e.preventDefault();
				openDropdown( wrapper );
				moveActive( wrapper, e.key === 'ArrowUp' ? -1 : 1 );
			}
		} );
		wrapper.addEventListener( 'keydown', function ( e ) {
			if ( wrapper.getAttribute( 'data-open' ) !== 'true' ) {
				return;
			}
			if ( e.key === 'Escape' ) {
				e.preventDefault();
				closeDropdown( wrapper );
				trigger.focus();
			} else if ( e.key === 'ArrowDown' ) {
				e.preventDefault();
				moveActive( wrapper, 1 );
			} else if ( e.key === 'ArrowUp' ) {
				e.preventDefault();
				moveActive( wrapper, -1 );
			} else if ( e.key === 'Enter' ) {
				e.preventDefault();
				var active = wrapper.querySelector( '.sf-dd-option.is-active' );
				if ( active ) {
					selectOption( select, wrapper, active.getAttribute( 'data-value' ) );
					trigger.focus();
				}
			}
		} );

		// External code may set select.value programmatically — mirror back.
		select.addEventListener( 'sendforce:rebuild', function () {
			rebuild( select, wrapper );
		} );
	}

	function rebuild( select, wrapper ) {
		var panel = wrapper.querySelector( '.sf-dd-panel' );
		panel.innerHTML = buildOptions( select );
		var info = getSelectedLabel( select );
		var labelEl = wrapper.querySelector( '.sf-dd-label' );
		labelEl.textContent = info.text;
		labelEl.classList.toggle( 'is-placeholder', info.placeholder );
	}

	function enhanceAll( root ) {
		var scope = root || document;
		var nodes = scope.querySelectorAll(
			'select.sendforce-select:not([' + ATTR_ENHANCED + '="1"]), ' +
			'select.sendforce-select-full:not([' + ATTR_ENHANCED + '="1"]), ' +
			'select.sendforce-select-sm:not([' + ATTR_ENHANCED + '="1"])'
		);
		for ( var i = 0; i < nodes.length; i++ ) {
			enhance( nodes[ i ] );
		}
	}

	// Close on outside click.
	document.addEventListener( 'click', function ( e ) {
		if ( ! openWrapper ) {
			return;
		}
		if ( openWrapper.contains( e.target ) ) {
			return;
		}
		closeDropdown( openWrapper );
	} );

	// Initial pass on DOM ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () { enhanceAll(); } );
	} else {
		enhanceAll();
	}

	// Re-scan when SendForce admin code adds connections / channels dynamically.
	// External code can trigger the same scan with: window.sendForceEnhanceSelects()
	window.sendForceEnhanceSelects = enhanceAll;
}() );
