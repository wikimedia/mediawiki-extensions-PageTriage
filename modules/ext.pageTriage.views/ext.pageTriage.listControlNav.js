$( function() {
	// controls navbar
	
	mw.pageTriage.ListControlNav = Backbone.View.extend( {
		tagName: "div",
		template: _.template( $( "#listControlNavTemplate" ).html() ),
		filterMenuVisible: 0,

		initialize: function() {
			var _this = this;
			
			// make a floating top navbar
			// TODO: there's a bump when the control div detaches from the page.
			//       fill some element under it to make it scroll smoothly
			$( '.top' ).addClass( 'hidden' );
			$.waypoints.settings.scrollThrottle = 30;
			$( '#mwe-pt-list-control-nav' ).waypoint( function( event, direction ) {
				$( this ).parent().toggleClass( 'sticky', direction === "down" );
				_this.resize();
				event.stopPropagation();
			});
			
			// do things that need doing on window resize
			var resizeTimer;
			$( window ).resize( function() {
				clearTimeout(resizeTimer);
				resizeTimer = setTimeout(this.resize, 100);
			});
								
			// hover for the dropdown menu control
			/*
			$( '#mwe-pt-filter-dropdown-control' ).hover( function() {
				_this.toggleFilterMenu();
			} );
			*/
		},

		render: function() {
			_this = this;
			// render and return the template.  fill with the current model.
			$( "#mwe-pt-list-control-nav").html( this.template() );
			
			// now that the template's been inserted, set up some events for controlling it
			
			// the filter dropdown menu control
			$( '#mwe-pt-filter-dropdown-control' ).click( function( e ) {
				_this.toggleFilterMenu();
				e.stopPropagation;
			} );
		},
		
		resize: function() {
			// set the width of the floating bar when the window resizes, if it's floating.
			// the left nav is 176 pixels
			// the right margin is 16 pixels
			// border is 2 pixels
			$( '#mwe-pt-list-control-nav' ).css( 'width', $(window).width() - 176 - 16 - 2 + "px" );
		},
		
		toggleFilterMenu: function() {
			if( this.filterMenuVisible ) {
				$( '#mwe-pt-dropdown-arrow' ).html( '&#x25b8;' );
				$( '#mwe-pt-control-dropdown' ).css( 'visibility', 'hidden' );
				this.filterMenuVisible = 0;
			} else {
				$( '#mwe-pt-control-dropdown' ).css( 'visibility', 'visible' );
				$( '#mwe-pt-dropdown-arrow' ).html( '&#x25be;' );
				this.filterMenuVisible = 1;				
			}
		}
	} );
} );
