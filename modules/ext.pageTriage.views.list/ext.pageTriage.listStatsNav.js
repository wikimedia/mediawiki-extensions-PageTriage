$( function() {
	// statistics bar
	
	mw.pageTriage.ListStatsNav = Backbone.View.extend( {
		tagName: "div",
		template: _.template( $( "#listStatsNavTemplate" ).html() ),
		floatNav: false,

		initialize: function( options ) {
			var _this = this;
			this.eventBus = options.eventBus;
			
			// make a floating bottom navbar
			$.waypoints.settings.scrollThrottle = 30;
			$( '#mwe-pt-list-stats-nav-anchor' ).waypoint( function( event, direction ) {
				if( _this.floatNav ) {
					$( '#mwe-pt-list-stats-nav' ).parent().toggleClass( 'stickyBottom', direction === "up" );

					_this.resize();
				}
				
				event.stopPropagation();
			}, {
				offset: '100%'  // bottom of page
			});
			
			// do things that need doing on window resize
			$( window ).resize( _.debounce( _this.resize, 100 ) );

			// when the list view is updated, do this stuff.
			// (mostly, update the floating-ness of the stats bar)
			this.eventBus.bind( "articleListChange", function() {
				_this.setPosition();
			} );
			
			// set the navbar's initial size
			this.resize();
			$.waypoints('refresh');
			
		},

		render: function() {
			// insert the template into the document.  fill with the current model.
			$( "#mwe-pt-list-stats-nav-content" ).html( this.template( this.model.toJSON() ) );
			
			this.setPosition();
			
			// broadcast the stats in case any other views want to display bits of them.
			// (the control view displays a summary)
			this.eventBus.trigger( 'renderStats', this.model );
			return this;
		},
		
		setPosition: function() {
			if( $( '#mwe-pt-list-stats-nav-anchor' ).offset().top < $.waypoints('viewportHeight') ) {
				// turn off floating nav, bring the bar back into the list.
				$( '#mwe-pt-list-stats-nav' ).parent().removeClass('stickyBottom');
				this.floatNav = false;
			} else {
				// bottom nav isn't visible.  turn on the floating navbar
				$( '#mwe-pt-list-stats-nav' ).parent().addClass('stickyBottom');
				this.floatNav = true;
			}
		},
		
		resize: function() {
			// set the width of the floating bar when the window resizes, if it's floating.
			// the left nav is 176 pixels
			// the right margin is 16 pixels
			// border is 2 pixels
			$( '#mwe-pt-list-stats-nav' ).css( 'width', $(window).width() - 176 - 16 - 2 + "px" );
		}

	} );
} );
