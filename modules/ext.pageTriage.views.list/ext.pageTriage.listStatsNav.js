$( function() {
	// statistics bar

	mw.pageTriage.ListStatsNav = Backbone.View.extend( {
		tagName: "div",
		template: _.template( $( "#listStatsNavTemplate" ).html() ),
		floatNav: false,

		initialize: function( options ) {
			var _this = this;
			
			this.eventBus = options.eventBus; // access the eventBus

			if ( mw.config.get( 'wgPageTriageStickyStatsNav' ) ) {
				this.setWaypoint(); // create scrolling waypoint
				
				// reset the width when the window is resized
				$( window ).resize( _.debounce( _this.setWidth, 80 ) );
	
				// when the list view is updated, see if we need to change the
				// float state of the navbar
				this.eventBus.bind( "articleListChange", function() {
					_this.setPosition();
				} );
			}
		},

		render: function() {
			// insert the template into the document.  fill with the current model.
			$( "#mwe-pt-list-stats-nav-content" ).html( this.template( this.model.toJSON() ) );
			
			if ( mw.config.get( 'wgPageTriageStickyStatsNav' ) ) this.setPosition();

			// broadcast the stats in case any other views want to display bits of them.
			// (the control view displays a summary)
			this.eventBus.trigger( 'renderStats', this.model );
			return this;
		},
		
		// Create a waypoint trigger that floats the navbar when the user scrolls up
		setWaypoint: function() {
			$( '#mwe-pt-list-stats-nav-anchor' ).waypoint( 'destroy' );  // remove the old, maybe inaccurate ones.
			var _this = this;
			$.waypoints.settings.scrollThrottle = 30;
			$( '#mwe-pt-list-stats-nav-anchor' ).waypoint( function( event, direction ) {
				if( _this.floatNav ) {
					$( '#mwe-pt-list-stats-nav' ).parent().toggleClass( 'stickyBottom', direction === "up" );
					_this.setWidth();
				}
				event.stopPropagation();
			}, {
				offset: '100%' // bottom of page
			});
		},

		// See if the navbar needs to be floated (for non-scrolling events)
		setPosition: function() {
			if( $( '#mwe-pt-list-stats-nav-anchor' ).offset().top < $.waypoints( 'viewportHeight' ) ) {
				// turn off floating nav, bring the bar back into the list.
				$( '#mwe-pt-list-stats-nav' ).parent().removeClass( 'stickyBottom' );
				this.floatNav = false;
			} else {
				// bottom nav isn't visible.  turn on the floating navbar
				$( '#mwe-pt-list-stats-nav' ).parent().addClass( 'stickyBottom' );
				this.floatNav = true;
			}
			this.setWidth();
			$.waypoints( 'refresh' );
		},

		setWidth: function() {
			// set the width of the floating bar when the window resizes, if it's floating.
			// border is 2 pixels
			$( '#mwe-pt-list-stats-nav' ).css( 'width', $( '#mw-content-text' ).width() - 2 + 'px' );
		}

	} );
} );
