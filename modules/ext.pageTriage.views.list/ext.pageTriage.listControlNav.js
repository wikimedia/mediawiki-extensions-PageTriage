$( function () {
	// controls navbar

	mw.pageTriage.ListControlNav = Backbone.View.extend( {
		tagName: 'div',
		template: _.template( $( '#listControlNavTemplate' ).html() ),
		filterMenuVisible: 0,
		filterStatus: mw.msg( 'pagetriage-filter-stat-all' ),
		newFilterStatus: [],

		initialize: function ( options ) {
			var that = this;

			this.eventBus = options.eventBus; // access the eventBus

			if ( mw.config.get( 'wgPageTriageStickyControlNav' ) ) {
				this.setWaypoint(); // create scrolling waypoint

				// reset the width when the window is resized
				$( window ).resize( _.debounce( that.setWidth, 80 ) );

				// when the list view is updated, refresh the stats and see if
				// we need to change the float state of the navbar
				this.eventBus.bind( 'articleListChange', function () {
					that.setPosition();
				} );

				// when a request is made to refresh the list, do it
				this.eventBus.bind( 'refreshListRequest', function () {
					that.refreshList();
					that.refreshStats();
				} );
			}

			this.eventBus.bind( 'renderStats', function ( stats ) {
				// fill in the counter when the stats view gets loaded.
				$( '#mwe-pt-control-stats' ).html( mw.msg(
					'pagetriage-stats-filter-page-count',
					stats.get( 'ptrFilterCount' )
				) );
			} );
		},

		render: function () {
			var that = this;

			// render and return the template. fill with the current model.
			$( '#mwe-pt-list-control-nav-content' ).html( this.template() );

			// align the filter dropdown box with the dropdown control widget
			// yield to other JS first per bug 46367
			setTimeout( function () {
				var startSide = $( 'body' ).hasClass( 'rtl' ) ? 'right' : 'left',
					newStart = $( '#mwe-pt-filter-dropdown-control' ).width() - 20;
				$( '#mwe-pt-control-dropdown' ).css( startSide, newStart );
				$( '#mwe-pt-control-dropdown-pokey' ).css( startSide, newStart + 5 );
			} );

			//
			// now that the template's been inserted, set up some events for controlling it
			//

			// make a submit button
			$( '#mwe-pt-filter-set-button' ).button( {
				label: mw.msg( 'pagetriage-filter-set-button' )
			} );
			$( '#mwe-pt-filter-set-button' ).click( function ( e ) {
				that.filterSync();
				that.refreshStats();
				that.toggleFilterMenu( 'close' );
				e.stopPropagation();
			} );

			$( '#mwe-pt-filter-user' ).keypress( function ( e ) {
				if ( e.which === 13 ) {
					$( '#mwe-pt-filter-set-button' ).click();
					e.preventDefault();
					return false;
				}
			} );

			$( '#mwe-pt-filter-reviewed-edits,#mwe-pt-filter-unreviewed-edits' ).click(
				function ( e ) {
					that.setSubmitButtonState();
					e.stopPropagation();
				}
			);

			// the filter dropdown menu control
			$( '#mwe-pt-filter-dropdown-control' ).click( function ( e ) {
				that.toggleFilterMenu();
				e.stopPropagation();
			} );

			// Initialize sort links
			// Uncomment this when 7147 is merged
			// $( '#mwe-pt-sort-buttons' ).buttonset();
			$( '#mwe-pt-sort-newest' ).click( function ( e ) {
				that.model.setParam( 'dir', 'newestfirst' );
				that.model.saveFilterParams();
				that.refreshList();
				e.stopPropagation();
			} );
			$( '#mwe-pt-sort-oldest' ).click( function ( e ) {
				that.model.setParam( 'dir', 'oldestfirst' );
				that.model.saveFilterParams();
				that.refreshList();
				e.stopPropagation();
			} );

			// Select the username option when its input gets focus
			$( '#mwe-pt-filter-user' ).focus( function () {
				$( '#mwe-pt-filter-user-selected' ).prop( 'checked', true );
			} );

			// make sure the menus are synced with the filter settings
			this.menuSync();
		},

		// refresh the stats when a namespace is changed
		refreshStats: function () {
			this.options.stats.apiParams = this.getApiParams();
			this.options.stats.fetch();
		},

		// Refresh the page list
		refreshList: function () {
			$( '#mwe-pt-refresh-button-holder' ).prepend( $.createSpinner( 'refresh-spinner' ) ); // show spinner
			this.model.setParam( 'offset', 0 );
			this.model.setParam( 'pageoffset', 0 );
			this.model.fetch( {
				add: false,
				success: function () {
					$.removeSpinner( 'refresh-spinner' ); // remove spinner
				}
			} );
		},

		// Create a waypoint trigger that floats the navbar when the user scrolls down
		setWaypoint: function () {
			var that = this;
			$( '#mwe-pt-list-control-nav-anchor' ).waypoint( 'destroy' );  // remove the old, maybe inaccurate ones.
			$.waypoints.settings.scrollThrottle = 30;
			$( '#mwe-pt-list-control-nav-anchor' ).waypoint( function ( event, direction ) {
				if ( direction === 'down' ) {
					$( '#mwe-pt-list-control-nav' ).parent().addClass( 'stickyTop' );
					that.setWidth();
					// pad the top of the list so it doesn't jump when the navbar changes to fixed positioning.
					$( '#mwe-pt-list-view' ).css( 'padding-top', $( '#mwe-pt-list-control-nav' ).height() );
				} else {
					$( '#mwe-pt-list-control-nav' ).parent().removeClass( 'stickyTop' );
					$( '#mwe-pt-list-view' ).css( 'padding-top', 0 );
				}
				event.stopPropagation();
			} );
		},

		// See if the navbar needs to be floated (for non-scrolling events)
		setPosition: function () {
			// Different browsers represent the document's scroll position differently.
			// I would expect jQuery to deal with this in some graceful fashion, but nooo...
			var scrollTop = $( 'body' ).scrollTop() || $( 'html' ).scrollTop() || $( window ).scrollTop();

			if ( $( '#mwe-pt-list-view' ).offset().top > scrollTop ) {
				// turn off floating nav, bring the bar back into the list.
				$( '#mwe-pt-list-control-nav' ).parent().removeClass( 'stickyTop' );
				$( '#mwe-pt-list-view' ).css( 'padding-top', 0 );
			} else {
				// top nav isn't visible.  turn on the floating navbar
				$( '#mwe-pt-list-control-nav' ).parent().addClass( 'stickyTop' );
				// pad the top of the list so it doesn't jump when the navbar changes to fixed positioning.
				$( '#mwe-pt-list-view' ).css( 'padding-top', $( '#mwe-pt-list-control-nav' ).height() );
			}
			this.setWidth();
		},

		// Set the width of the floating bar when the window resizes, if it's floating
		setWidth: function () {
			// border is 2 pixels
			$( '#mwe-pt-list-control-nav' ).css( 'width', $( '#mw-content-text' ).width() - 2 + 'px' );
		},

		// Toggle whether or not the filter drop-down interface is displayed
		toggleFilterMenu: function ( action ) {
			var that = this,
				arrowClosed = $( 'body' ).hasClass( 'rtl' ) ? '&#x25c2;' : '&#x25b8;';
			if ( ( action && action === 'close' ) || this.filterMenuVisible ) {
				$( '#mwe-pt-dropdown-arrow' ).html( arrowClosed );
				$( '#mwe-pt-control-dropdown' ).css( 'visibility', 'hidden' );
				$( '#mwe-pt-control-dropdown-pokey' ).css( 'visibility', 'hidden' );
				$( 'body' ).unbind( 'click' ); // remove these events since they're not needed til next time.
				$( '#mwe-pt-control-dropdown' ).unbind( 'click' );
				this.filterMenuVisible = 0;
			} else if ( ( action && action === 'open' ) || !this.filterMenuVisible ) {
				this.menuSync();
				$( '#mwe-pt-control-dropdown' ).css( 'visibility', 'visible' );
				$( '#mwe-pt-control-dropdown-pokey' ).css( 'visibility', 'visible' );
				$( '#mwe-pt-dropdown-arrow' ).html( '&#x25be;' ); // ▾ down-pointing triangle

				// close the menu when the user clicks away
				$( 'body' ).click( 'click', function () {
					that.toggleFilterMenu( 'close' );
				} );

				// this event "covers up" the body event, which keeps the menu from closing when
				// the user clicks inside.
				$( '#mwe-pt-control-dropdown' ).click( function ( e ) {
					e.stopPropagation();
				} );

				this.filterMenuVisible = 1;
			}

			this.setSubmitButtonState();
		},

		// Make sure the user didn't uncheck both reviewed edits and unreviewed edits
		setSubmitButtonState: function () {
			if (
				!$( '#mwe-pt-filter-reviewed-edits' ).prop( 'checked' ) &&
				!$( '#mwe-pt-filter-unreviewed-edits' ).prop( 'checked' )
			) {
				$( '#mwe-pt-filter-set-button' ).button( 'disable' );
			} else {
				$( '#mwe-pt-filter-set-button' ).button( 'enable' );
			}
		},

		getApiParams: function () {
			// fetch the values from the menu
			var apiParams = {};
			if ( $( '#mwe-pt-filter-namespace' ).val() ) {
				apiParams.namespace = $( '#mwe-pt-filter-namespace' ).val();
			}

			// these are conditionals because the keys shouldn't exist if the checkbox isn't checked.
			if ( $( '#mwe-pt-filter-reviewed-edits' ).prop( 'checked' ) ) {
				apiParams.showreviewed = '1';
			}

			if ( $( '#mwe-pt-filter-unreviewed-edits' ).prop( 'checked' ) ) {
				apiParams.showunreviewed = '1';
			}

			if ( $( '#mwe-pt-filter-nominated-for-deletion' ).prop( 'checked' ) ) {
				apiParams.showdeleted = '1';
			}

			if ( $( '#mwe-pt-filter-bot-edits' ).prop( 'checked' ) ) {
				apiParams.showbots = '1';
			}

			if ( $( '#mwe-pt-filter-redirects' ).prop( 'checked' ) ) {
				apiParams.showredirs = '1';
			}

			if ( $( '#mwe-pt-filter-user-selected' ).prop( 'checked' ) && $( '#mwe-pt-filter-user' ).val() ) {
				apiParams.username = $( '#mwe-pt-filter-user' ).val();
			}

			/* api doesn't support this yet
			if( $('#mwe-pt-filter-tag').val() ) {
				apiParams[''] = $('#mwe-pt-filter-tag').val();
			}
			*/

			if ( $( '#mwe-pt-filter-no-categories' ).prop( 'checked' ) ) {
				// jscs: disable requireCamelCaseOrUpperCaseIdentifiers
				apiParams.no_category = '1';
				// jscs: enable requireCamelCaseOrUpperCaseIdentifiers
			}

			if ( $( '#mwe-pt-filter-orphan' ).prop( 'checked' ) ) {
				// jscs: disable requireCamelCaseOrUpperCaseIdentifiers
				apiParams.no_inbound_links = '1';
				// jscs: enable requireCamelCaseOrUpperCaseIdentifiers
			}

			if ( $( '#mwe-pt-filter-non-autoconfirmed' ).prop( 'checked' ) ) {
				// jscs: disable requireCamelCaseOrUpperCaseIdentifiers
				apiParams.non_autoconfirmed_users = '1';
				// jscs: enable requireCamelCaseOrUpperCaseIdentifiers
			}

			if ( $( '#mwe-pt-filter-learners' ).prop( 'checked' ) ) {
				apiParams.learners = '1';
			}

			if ( $( '#mwe-pt-filter-blocked' ).prop( 'checked' ) ) {
				// jscs: disable requireCamelCaseOrUpperCaseIdentifiers
				apiParams.blocked_users = '1';
				// jscs: enable requireCamelCaseOrUpperCaseIdentifiers
			}

			return apiParams;
		},

		// Sync the filters with the contents of the menu
		filterSync: function () {
			// fetch the values from the menu
			var apiParams = this.getApiParams();

			// persist the limit and direction parameters
			apiParams.limit = this.model.getParam( 'limit' );
			apiParams.dir = this.model.getParam( 'dir' );

			// the model in this context is mw.pageTriage.ArticleList
			this.model.setParams( apiParams );
			this.model.saveFilterParams();
			this.model.fetch();

			this.menuSync(); // make sure the menu is now up-to-date.
		},

		// Sync the menu and other UI elements with the contents of the filters
		menuSync: function () {
			var ns, nsText, username;

			this.newFilterStatus = [];

			$( '#mwe-pt-filter-namespace' ).val( this.model.getParam( 'namespace' ) );

			// update the status display
			if ( this.model.getParam( 'namespace' ) > -1 ) { // still true for ns 0
				ns = this.model.getParam( 'namespace' );
				if ( Number( ns ) === 0 ) {
					nsText = mw.msg( 'blanknamespace' );
				} else {
					nsText = mw.config.get( 'wgFormattedNamespaces' )[ ns ];
				}
			}

			this.menuCheckboxUpdate( $( '#mwe-pt-filter-reviewed-edits' ), 'showreviewed', 'pagetriage-filter-stat-reviewed' );
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-unreviewed-edits' ), 'showunreviewed', 'pagetriage-filter-stat-unreviewed' );
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-nominated-for-deletion' ), 'showdeleted', 'pagetriage-filter-stat-nominated-for-deletion' );
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-bot-edits' ), 'showbots', 'pagetriage-filter-stat-bots' );
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-redirects' ), 'showredirs', 'pagetriage-filter-stat-redirects' );

			username = this.model.getParam( 'username' );
			if ( username ) {
				this.newFilterStatus.push( mw.msg( 'pagetriage-filter-stat-username', username ) );
				$( '#mwe-pt-filter-user-selected' ).prop( 'checked', true );
			}
			$( '#mwe-pt-filter-user' ).val( username );

			/* api doesn't support this
			$( '#mwe-pt-filter-tag' ).val( this.model.getParam('') );
			*/

			this.menuCheckboxUpdate( $( '#mwe-pt-filter-no-categories' ), 'no_category', 'pagetriage-filter-stat-no-categories' );
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-orphan' ), 'no_inbound_links', 'pagetriage-filter-stat-orphan' );
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-non-autoconfirmed' ), 'non_autoconfirmed_users', 'pagetriage-filter-stat-non-autoconfirmed' );
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-learners' ), 'learners', 'pagetriage-filter-stat-learners' );
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-blocked' ), 'blocked_users', 'pagetriage-filter-stat-blocked' );

			this.filterStatus = this.newFilterStatus.join( mw.msg( 'comma-separator' ) );
			$( '#mwe-pt-filter-status' ).text( this.filterStatus );

			if ( !$( 'input[name=mwe-pt-filter-radio]:checked' ).val() ) {
				// none of the radio buttons are selected.  pick the default.
				$( '#mwe-pt-filter-all' ).prop( 'checked', true );
			}

			// Sync the sort toggle
			if ( this.model.getParam( 'dir' ) === 'oldestfirst' ) {
				$( '#mwe-pt-sort-oldest' ).prop( 'checked', true );
				// FIXME: Why is this commented out?
				// $( 'label[for="mwe-pt-sort-oldest"]' ).addClass( 'ui-state-active' );
				// $( 'label[for="mwe-pt-sort-newest"]' ).removeClass( 'ui-state-active' );
			} else {
				$( '#mwe-pt-sort-newest' ).prop( 'checked', true );
				// FIXME: Why is this commented out?
				// $( 'label[for="mwe-pt-sort-newest"]' ).addClass( 'ui-state-active' );
				// $( 'label[for="mwe-pt-sort-oldest"]' ).removeClass( 'ui-state-active' );
			}
		},

		/**
		 * Update a checkbox in the filter menu with data from the model.
		 *
		 * @param {jQuery} $checkbox The JQuery object of the input element.
		 * @param {string} param The value (i.e. 1 or 0, checked or not).
		 * @param {string} message The message name for the filter.
		 */
		menuCheckboxUpdate: function ( $checkbox, param, message ) {
			$checkbox.prop( 'checked', parseInt( this.model.getParam( param ), 10 ) === 1 );
			if ( this.model.getParam( param ) ) {
				this.newFilterStatus.push( mw.msg( message ) );
			}
		}

	} );
} );
