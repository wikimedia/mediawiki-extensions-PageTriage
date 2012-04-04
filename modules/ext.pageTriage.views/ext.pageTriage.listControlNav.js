$( function() {
	// controls navbar
	
	mw.pageTriage.ListControlNav = Backbone.View.extend( {
		tagName: "div",
		template: _.template( $( "#listControlNavTemplate" ).html() ),
		filterMenuVisible: 0,
		filterStatus: 'All',

		initialize: function( options ) {
			var _this = this;

			this.eventBus = options.eventBus; // access the eventBus

			// make a floating top navbar
			$.waypoints.settings.scrollThrottle = 30;
			$( '#mwe-pt-list-control-nav' ).waypoint( function( event, direction ) {
				$( this ).parent().toggleClass( 'stickyTop', direction === "down" );
				
				// pad the element that scrolls under the bar, so it doesn't jump beneath it when the bar
				// changes to fixed positioning.
				if( direction === 'down' ) {
					$( '#mwe-pt-list-view' ).css('padding-top', $( '#mwe-pt-list-control-nav' ).height() );
				} else {
					$( '#mwe-pt-list-view' ).css('padding-top', 0 );
				}
				
				_this.resize();
				event.stopPropagation();
			});
			
			// do things that need doing on window resize
			$( window ).resize( _.debounce(_this.resize, 100 ) );

			this.eventBus.bind( "renderStats", function( stats ) {
				// fill in the counter when the stats view gets loaded.
				$( "#mwe-pt-control-stats" ).html( gM( 'pagetriage-article-count', stats.get('ptr_untriaged_article_count') ) );
			} );

			// update the filter display on load.
			this.menuSync();
		},

		render: function() {
			var _this = this;
			// render and return the template.  fill with the current model.
			$( "#mwe-pt-list-control-nav-content").html( this.template( { filterStatus: this.filterStatus } ) );
			
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
				_this.filterSync();
				_this.toggleFilterMenu();				
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
				this.menuSync();
				$( '#mwe-pt-control-dropdown' ).css( 'visibility', 'visible' );
				$( '#mwe-pt-dropdown-arrow' ).html( '&#x25be;' );
				this.filterMenuVisible = 1;				
			}
		},
		
		// sync the filters with the contents of the menu
		filterSync: function() {			
			// fetch the values from the menu
			var apiParams = {};
			if( $('#mwe-pt-filter-namespace').val() ) {
				apiParams['namespace'] = $('#mwe-pt-filter-namespace').val();
			}

			// these are conditionals because the keys shouldn't exist if the checkbox isn't checked.
			if( $('#mwe-pt-filter-triaged-edits').prop('checked') ) {
				apiParams['showtriaged'] = '1';
			}
			
			/*
			if( $('#mwe-pt-filter-nominated-for-deletion').prop('checked') ) {
				apiParams[''] = '1';
			}
			*/

			if( $('#mwe-pt-filter-bot-edits').prop('checked') ) {
				apiParams['showbots'] = '1';
			}

			if( $('#mwe-pt-filter-redirects').prop('checked') ) {
				apiParams['showredirs'] = '1';
			}

			/*
			api doesn't support these.
			if( $('#mwe-pt-filter-user').val() ) {
				apiParams[''] = $('#mwe-pt-filter-user').val();
			}
			
			if( $('#mwe-pt-filter-tag').val() ) {
				apiParams[''] = $('#mwe-pt-filter-tag').val();
			}
			*/

			if( $('#mwe-pt-filter-no-categories').prop('checked') ) {
				apiParams['no_category'] = '1';
			}

			if( $('#mwe-pt-filter-orphan').prop('checked') ) {
				apiParams['no_inbound_links'] = '1';
			}

			if( $('#mwe-pt-filter-non-autoconfirmed').prop('checked') ) {
				apiParams['non_autoconfirmed_users'] = '1';
			}

			if( $('#mwe-pt-filter-blocked').prop('checked') ) {
				apiParams['blocked_users'] = '1';
			}
			
			// persist the limit parameter
			apiParams['limit'] = this.model.getParam('limit');
						
			this.model.setParams( apiParams );
			this.model.fetch();

			this.menuSync(); // make sure the menu is now up-to-date.
			this.render();
		},
		
		// sync the menu with the contents of the filters
		menuSync: function() {
			var newFilterStatus = [];

			$( '#mwe-pt-filter-namespace' ).val( this.model.getParam( 'namespace' ) );

			// update the status display
			if( this.model.getParam( 'namespace' ) > -1 ) { // still true for ns 0
				newFilterStatus.push( gM( 'pagetriage-filter-stat-namespace', this.model.getParam( 'namespace' ) ) );	
			}
			
			// TODO: update the status for everything else.
				
			$( '#mwe-pt-filter-triaged-edits' ).prop( 'checked', this.model.getParam( 'showtriaged' )=="1"?true:false );
			// api doesn't support this?
			//$( '#mwe-pt-filter-nominated-for-deletion' ).prop( 'checked', this.model.getParam('')=="1"?true:false );
			$( '#mwe-pt-filter-bot-edits' ).prop( 'checked', this.model.getParam( 'showbots' )=="1"?true:false );
			$( '#mwe-pt-filter-redirects' ).prop( 'checked', this.model.getParam( 'showredirs' )=="1"?true:false );
			
			/* api doesn't support these
			$( '#mwe-pt-filter-user' ).val( this.model.getParam('') );
			$( '#mwe-pt-filter-tag' ).val( this.model.getParam('') );
			*/
			
			$( '#mwe-pt-filter-no-categories' ).prop( 'checked', this.model.getParam( 'no_category' )=="1"?true:false );
			$( '#mwe-pt-filter-orphan' ).prop( 'checked', this.model.getParam( 'no_inbound_links' )=="1"?true:false );
			$( '#mwe-pt-filter-non-autoconfirmed' ).prop( 'checked', this.model.getParam( 'non_autoconfirmed_users' )=="1"?true:false );
			$( '#mwe-pt-filter-blocked' ).prop( 'checked', this.model.getParam( 'blocked_users' )=="1"?true:false );
			
			this.filterStatus = newFilterStatus.join('.');			
		}
		
	} );
} );
