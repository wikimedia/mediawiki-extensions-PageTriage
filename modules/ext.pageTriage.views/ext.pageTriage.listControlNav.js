$( function() {
	// controls navbar
	
	mw.pageTriage.ListControlNav = Backbone.View.extend( {
		tagName: "div",
		template: _.template( $( "#listControlNavTemplate" ).html() ),
		filterMenuVisible: 0,
		filterStatus: gM( 'pagetriage-filter-stat-all'),
		newFilterStatus: [],

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
			
			if(! this.filterStatus ) {
				this.filterStatus = gM( 'pagetriage-filter-stat-all');
			}
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
				console.log('set button clicked');
				_this.filterSync();
				_this.toggleFilterMenu();				
				e.stopPropagation();
			} );
			
			// the filter dropdown menu control
			console.log('click event set on body and menu');
			$( '#mwe-pt-filter-dropdown-control' ).click( function( e ) {
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
		
		toggleFilterMenu: function( action ) {
			var _this = this;
			if( (action && action == 'close') || this.filterMenuVisible ) {
				$( '#mwe-pt-dropdown-arrow' ).html( '&#x25b8;' );
				$( '#mwe-pt-control-dropdown' ).css( 'visibility', 'hidden' );
				$( 'body' ).unbind( 'click' ); // remove these events since they're not needed til next time.
				$( '#mwe-pt-control-dropdown' ).unbind( 'click' );
				this.filterMenuVisible = 0;
			} else if( (action && action == 'open') || !this.filterMenuVisible ) {
				this.menuSync();
				$( '#mwe-pt-control-dropdown' ).css( 'visibility', 'visible' );
				$( '#mwe-pt-dropdown-arrow' ).html( '&#x25be;' );

				// close the menu when the user clicks away
				$( 'body' ).click( 'click', function() {
					console.log('body clicked');
					_this.toggleFilterMenu( 'close' );
				} );

				// this event "covers up" the body event, which keeps the menu from closing when
				// the user clicks inside.
				$( '#mwe-pt-control-dropdown' ).click( function( e ) {
					console.log('menu clicked');
					e.stopPropagation();
				} );

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
			this.newFilterStatus = [];

			$( '#mwe-pt-filter-namespace' ).val( this.model.getParam( 'namespace' ) );

			// update the status display
			if( this.model.getParam( 'namespace' ) > -1 ) { // still true for ns 0
				var ns = this.model.getParam( 'namespace' );
				var nsText;
				if( ns == 0 ) {
					nsText = gM( 'pagetriage-filter-ns-article' );
				} else {
					nsText = mw.config.get( 'wgFormattedNamespaces' )[ns];
				}
				this.newFilterStatus.push( gM( 'pagetriage-filter-stat-namespace', nsText ) );	
			}
			
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-triaged-edits' ), 'showtriaged', 'pagetriage-filter-stat-triaged');
			// api doesn't suppor this one.
			//this.menuCheckboxUpdate( $( '#mwe-pt-filter-nominated-for-deletion' ' ), '', '');
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-bot-edits' ), 'showbots', 'pagetriage-filter-stat-bots');
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-redirects' ), 'showredirs', 'pagetriage-filter-stat-redirects');

			/* api doesn't support these
			$( '#mwe-pt-filter-user' ).val( this.model.getParam('') );
			$( '#mwe-pt-filter-tag' ).val( this.model.getParam('') );
			*/

			this.menuCheckboxUpdate( $( '#mwe-pt-filter-no-categories' ), 'no_category', 'pagetriage-filter-stat-no-categories');
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-orphan' ), 'no_inbound_links', 'pagetriage-filter-stat-orphan');
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-non-autoconfirmed' ), 'non_autoconfirmed_users', 'pagetriage-filter-stat-non-autoconfirmed');
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-blocked' ), 'blocked_users', 'pagetriage-filter-stat-blocked');

			this.filterStatus = this.newFilterStatus.join(' &#xb7; ');			
		},
		
		menuCheckboxUpdate: function( $checkbox, param, message ) {
			// update a checkbox in the menu with data from the model.
			$checkbox.prop( 'checked', this.model.getParam( param )=="1"?true:false );
			if( this.model.getParam( param ) ) {
				this.newFilterStatus.push( gM( message ) );
			}
		}
		
	} );
} );
