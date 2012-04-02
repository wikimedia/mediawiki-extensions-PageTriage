$( function() {
	// controls navbar
	
	mw.pageTriage.ListControlNav = Backbone.View.extend( {
		tagName: "div",
		template: _.template( $( "#listControlNavTemplate" ).html() ),
		filterMenuVisible: 0,

		initialize: function( options ) {
			var _this = this;

			this.eventBus = options.eventBus; // access the eventBus

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
			// TODO: switch this to use _.debounce() instead
			var resizeTimer;
			$( window ).resize( function() {
				clearTimeout(mw.pageTriage.resizeTimer);
				mw.pageTriage.resizeTimer = setTimeout(_this.resize, 100);
			});

			this.eventBus.bind( "renderStats", function( stats ) {
				// fill in the counter when the stats view gets loaded.
				$( "#mwe-pt-control-stats" ).html( gM( 'pagetriage-article-count', stats.get('ptr_untriaged_article_count') ) );
			} );
								
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
			$( "#mwe-pt-list-control-nav-content").html( this.template( ) );
			
			// align the filter dropdown box with the dropdown control widget
			var newLeft = $( '#mwe-pt-filter-dropdown-control' ).width() - 20;
			$( "#mwe-pt-control-dropdown" ).css({left: newLeft});

			//
			// now that the template's been inserted, set up some events for controlling it
			//
			
			// make a button
			$( ".mwe-pt-filter-set-button" ).button( {
				label: mw.msg( 'pagetriage-filter-set-button' ),
				icons: { secondary:'ui-icon-triangle-1-e' }
			} );
			$( ".mwe-pt-filter-set-button" ).click( function( e ) {
				_this.filterSet();
				e.stopPropagation();
			} );
			
			// the filter dropdown menu control
			$( '#mwe-pt-filter-dropdown-control' ).click( function( e ) {
				// close the meny when the user clicks away
				$( 'body' ).one( 'click', function() {
					_this.toggleFilterMenu();
				} );

				// this event "covers up" the body event, which keeps the menu from closing when
				// the user clicks inside.
				$( '#mwe-pt-control-dropdown' ).click( function( e ) {
					e.stopPropagation();
				} );

				_this.toggleFilterMenu();
				e.stopPropagation();
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
		},
		
		filterSet: function() {
			console.log('clicked');
			this.toggleFilterMenu();
			
			// fetch the values from the menu
			var apiParams = {};
			if( $('#mwe-pt-filter-namespace').val() ) {
				apiParams['namespace'] = $('#mwe-pt-filter-namespace').val();
			}

			this.model.apiParams = apiParams;
			this.model.fetch();
		}
		
	} );
} );
