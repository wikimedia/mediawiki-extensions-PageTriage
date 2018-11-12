/**
 * Script for tooltips you can interact with
 */

( function () {

	$.fn.tipoff = function ( html ) {
		var $tooltip, hideTimer, showTimer,
			that = this;

		// Show the tooltip after 200 milliseconds
		function showTip() {
			showTimer = setTimeout( function () {
				$( '.mw-tipoff', that ).show();
			}, 200 );
		}

		// Hide the tooltip after 100 milliseconds
		function hideTip() {
			hideTimer = setTimeout( function () {
				$( '.mw-tipoff', that ).hide();
			}, 100 );
		}

		// Create the tiptool
		$tooltip = $( '<div>' )
			.addClass( 'mw-tipoff' )
			.css( 'display', 'none' )
			.html( '<div class="mw-tipoff-pokey"></div>' + html );

		// Insert the tooltip into the page
		this.append( $tooltip );

		// Set up triggers for hiding and showing the tooltip
		this.hover(
			function ( e ) {
				clearTimeout( showTimer );
				clearTimeout( hideTimer );
				showTip();
				e.stopPropagation();
			},
			function ( e ) {
				clearTimeout( showTimer );
				clearTimeout( hideTimer );
				hideTip();
				e.stopPropagation();
			}
		);

	};

}() );
