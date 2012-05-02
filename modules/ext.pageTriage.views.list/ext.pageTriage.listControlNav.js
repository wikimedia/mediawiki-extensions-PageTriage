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
			
			if ( mw.config.get( 'wgPageTriageStickyControlNav' ) ) {
				this.setWaypoint(); // create scrolling waypoint
	
				// reset the width when the window is resized
				$( window ).resize( _.debounce( _this.setWidth, 100 ) );
	
				// when the list view is updated, see if we need to change the
				// float state of the navbar
				this.eventBus.bind( 'articleListChange', function() {
					_this.setPosition();
				} );
			}

			this.eventBus.bind( "renderStats", function( stats ) {
				// fill in the counter when the stats view gets loaded.
				$( "#mwe-pt-control-stats" ).html( gM( 'pagetriage-article-count', stats.get('ptr_unreviewed_article_count') ) );
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
			$( "#mwe-pt-control-dropdown-pokey" ).css({left: newLeft + 5});

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
				_this.toggleFilterMenu();
				e.stopPropagation();
			} );

			// Activate sort links
			$( '#mwe-pt-sort-newest' ).click( function() {
				_this.model.setParam( 'dir', 'newestfirst' );
				_this.model.setParam( 'offset', 0 );
				_this.model.setParam( 'pageoffset', 0 );
				_this.model.fetch();
				return false;
			} );
			$( '#mwe-pt-sort-oldest' ).click( function() {
				_this.model.setParam( 'dir', 'oldestfirst' );
				_this.model.setParam( 'offset', 0 );
				_this.model.setParam( 'pageoffset', 0 );
				_this.model.fetch();
				return false;
			} );
		},

		// Create a waypoint trigger that floats the navbar when the user scrolls down
		setWaypoint: function() {
			$( '#mw-content-text' ).waypoint('destroy');  // remove the old, maybe inaccurate ones.
			var _this = this;
			$.waypoints.settings.scrollThrottle = 30;
			$( '#mw-content-text' ).waypoint( function( event, direction ) {
				if( direction === 'down' ) {
					$( '#mwe-pt-list-control-nav' ).parent().addClass('stickyTop');
					_this.setWidth();
					// pad the top of the list so it doesn't jump when the navbar changes to fixed positioning.
					$( '#mwe-pt-list-view' ).css( 'padding-top', $( '#mwe-pt-list-control-nav' ).height() );
				} else {
					$( '#mwe-pt-list-control-nav' ).parent().removeClass('stickyTop');
					$( '#mwe-pt-list-view' ).css( 'padding-top', 0 );
				}
				event.stopPropagation();
			} );
		},
		
		// See if the navbar needs to be floated (for non-scrolling events)
		setPosition: function() {
			// Different browsers represent the document's scroll position differently.
			// I would expect jQuery to deal with this in some graceful fashion, but nooo...
			var scrollTop = $('body').scrollTop() || $('html').scrollTop() || $(window).scrollTop();
			
			if( $( '#mwe-pt-list-view' ).offset().top > scrollTop ) {
				// turn off floating nav, bring the bar back into the list.
				$( '#mwe-pt-list-control-nav' ).parent().removeClass('stickyTop');
				$( '#mwe-pt-list-view' ).css( 'padding-top', 0 );
			} else {
				// top nav isn't visible.  turn on the floating navbar
				$( '#mwe-pt-list-control-nav' ).parent().addClass('stickyTop');
				this.setWidth();
				// pad the top of the list so it doesn't jump when the navbar changes to fixed positioning.
				$( '#mwe-pt-list-view' ).css( 'padding-top', $( '#mwe-pt-list-control-nav' ).height() );
			}
		},

		setWidth: function() {
			// set the width of the floating bar when the window resizes, if it's floating.
			// border is 2 pixels
			$( '#mwe-pt-list-control-nav' ).css( 'width', $( '#mw-content-text' ).width() - 2 + 'px' );
		},

		toggleFilterMenu: function( action ) {
			var _this = this;
			if( (action && action == 'close') || this.filterMenuVisible ) {
				$( '#mwe-pt-dropdown-arrow' ).html( '&#x25b8;' );
				$( '#mwe-pt-control-dropdown' ).css( 'visibility', 'hidden' );
				$( '#mwe-pt-control-dropdown-pokey' ).css( 'visibility', 'hidden' );
				$( 'body' ).unbind( 'click' ); // remove these events since they're not needed til next time.
				$( '#mwe-pt-control-dropdown' ).unbind( 'click' );
				this.filterMenuVisible = 0;
			} else if( (action && action == 'open') || !this.filterMenuVisible ) {
				this.menuSync();
				$( '#mwe-pt-control-dropdown' ).css( 'visibility', 'visible' );
				$( '#mwe-pt-control-dropdown-pokey' ).css( 'visibility', 'visible' );
				$( '#mwe-pt-dropdown-arrow' ).html( '&#x25be;' );

				// close the menu when the user clicks away
				$( 'body' ).click( 'click', function() {
					_this.toggleFilterMenu( 'close' );
				} );

				// this event "covers up" the body event, which keeps the menu from closing when
				// the user clicks inside.
				$( '#mwe-pt-control-dropdown' ).click( function( e ) {
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
			if( $('#mwe-pt-filter-reviewed-edits').prop('checked') ) {
				apiParams['showreviewed'] = '1';
			}

			if( $('#mwe-pt-filter-nominated-for-deletion').prop('checked') ) {
				apiParams['showdeleted'] = '1';
			}

			if( $('#mwe-pt-filter-bot-edits').prop('checked') ) {
				apiParams['showbots'] = '1';
			}

			if( $('#mwe-pt-filter-redirects').prop('checked') ) {
				apiParams['showredirs'] = '1';
			}

			if( $('#mwe-pt-filter-user-selected').prop('checked') && $('#mwe-pt-filter-user').val() ) {
				apiParams['username'] = $('#mwe-pt-filter-user').val();
			}

			/* api doesn't support this yet
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
			apiParams['dir'] = this.model.getParam('dir');
						
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
				if( Number(ns) === 0 ) {
					nsText = gM( 'blanknamespace' );
				} else {
					nsText = mw.config.get( 'wgFormattedNamespaces' )[ns];
				}
				this.newFilterStatus.push( gM( 'pagetriage-filter-stat-namespace', nsText ) );
			}

			this.menuCheckboxUpdate( $( '#mwe-pt-filter-reviewed-edits' ), 'showreviewed', 'pagetriage-filter-stat-reviewed');
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-nominated-for-deletion' ), 'showdeleted', 'pagetriage-filter-stat-nominated-for-deletion');
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-bot-edits' ), 'showbots', 'pagetriage-filter-stat-bots');
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-redirects' ), 'showredirs', 'pagetriage-filter-stat-redirects');

			var username = this.model.getParam( 'username' );
			if( username ) {
				this.newFilterStatus.push( gM( 'pagetriage-filter-stat-username', username ) );
				$( '#mwe-pt-filter-user-selected' ).prop( 'checked', true );
			}
			$( '#mwe-pt-filter-user' ).val( username );

			/* api doesn't support this
			$( '#mwe-pt-filter-tag' ).val( this.model.getParam('') );
			*/

			this.menuCheckboxUpdate( $( '#mwe-pt-filter-no-categories' ), 'no_category', 'pagetriage-filter-stat-no-categories');
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-orphan' ), 'no_inbound_links', 'pagetriage-filter-stat-orphan');
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-non-autoconfirmed' ), 'non_autoconfirmed_users', 'pagetriage-filter-stat-non-autoconfirmed');
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-blocked' ), 'blocked_users', 'pagetriage-filter-stat-blocked');

			this.filterStatus = this.newFilterStatus.join(' &#xb7; ');
			
			if( ! $("input[name=mwe-pt-filter-radio]:checked").val() ) {
				// none of the radio buttons are selected.  pick the default.
				$( '#mwe-pt-filter-all' ).prop( 'checked', true );
			}
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
