/**
 * SendForce Mail Relay — WordPress Dashboard Widget JS
 *
 * @package SendForce
 */

/* global jQuery, Chart, sendForceWidget */
( function ( $ ) {
	'use strict';

	var smwChart = null;

	function smwBuildChart( data ) {
		var canvas = document.getElementById( 'smw-chart' );
		if ( ! canvas ) { return; }

		if ( smwChart ) {
			smwChart.destroy();
			smwChart = null;
		}

		var ctx        = canvas.getContext( '2d' );
		var gradSent   = ctx.createLinearGradient( 0, 0, 0, 160 );
		gradSent.addColorStop( 0, 'rgba(29, 209, 161, 0.28)' );
		gradSent.addColorStop( 1, 'rgba(29, 209, 161, 0)' );

		var gradFailed = ctx.createLinearGradient( 0, 0, 0, 160 );
		gradFailed.addColorStop( 0, 'rgba(239, 68, 68, 0.20)' );
		gradFailed.addColorStop( 1, 'rgba(239, 68, 68, 0)' );

		smwChart = new Chart( canvas, {
			type: 'line',
			data: {
				labels: data.labels,
				datasets: [
					{
						label: 'Sent',
						data: data.sent,
						borderColor: '#1dd1a1',
						backgroundColor: gradSent,
						borderWidth: 2,
						pointBackgroundColor: '#1dd1a1',
						pointBorderColor: '#fff',
						pointBorderWidth: 1.5,
						pointRadius: 3,
						pointHoverRadius: 5,
						tension: 0.4,
						fill: true
					},
					{
						label: 'Failed',
						data: data.failed,
						borderColor: '#EF4444',
						backgroundColor: gradFailed,
						borderWidth: 2,
						pointBackgroundColor: '#EF4444',
						pointBorderColor: '#fff',
						pointBorderWidth: 1.5,
						pointRadius: 3,
						pointHoverRadius: 5,
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
						padding: 10,
						cornerRadius: 6,
						titleFont: { size: 10 },
						bodyFont: { size: 12, weight: '600' }
					}
				},
				scales: {
					x: {
						grid: { display: false },
						border: { display: false },
						ticks: { color: '#94A3B8', font: { size: 10 }, maxRotation: 0, maxTicksLimit: 8 }
					},
					y: {
						beginAtZero: true,
						grid: { color: '#F1F5F9' },
						border: { display: false },
						ticks: { color: '#94A3B8', font: { size: 10 }, precision: 0, maxTicksLimit: 5 }
					}
				},
				interaction: { mode: 'nearest', axis: 'x', intersect: false }
			}
		} );

		// Update stats footer.
		$( '#smw-total' ).text( data.total );
		$( '#smw-sent' ).text( data.total_sent );
		$( '#smw-failed' ).text( data.total_failed );
	}

	function smwLoad( range ) {
		$( '#smw-loader' ).show();

		$.post( sendForceWidget.ajax_url, {
			action: 'sendforce_chart_data',
			nonce:  sendForceWidget.nonce,
			range:  range
		}, function ( response ) {
			$( '#smw-loader' ).hide();
			if ( response.success ) {
				smwBuildChart( response.data );
			}
		} ).fail( function () {
			$( '#smw-loader' ).hide();
		} );
	}

	$( document ).ready( function () {
		if ( ! $( '#smw-chart' ).length ) { return; }

		// Initial load.
		smwLoad( '7' );

		// Range change.
		$( document ).on( 'change', '#smw-range', function () {
			smwLoad( $( this ).val() );
		} );
	} );

} )( jQuery );
