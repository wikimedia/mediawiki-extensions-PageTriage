$( function () {
	// controls navbar

	mw.pageTriage.ListControlNav = Backbone.View.extend( {
		tagName: 'div',
		template: mw.template.get( 'ext.pageTriage.views.list', 'listControlNav.underscore' ),
		filterMenuVisible: 0,
		filterStatus: mw.msg( 'pagetriage-filter-stat-all' ),
		newFilterStatus: {},

		initialize: function ( options ) {
			var that = this;

			this.eventBus = options.eventBus; // access the eventBus

			if ( mw.config.get( 'wgPageTriageStickyControlNav' ) ) {
				this.setWaypoint(); // create scrolling waypoint

				// reset the width when the window is resized
				$( window ).on( 'resize', _.debounce( that.setWidth, 80 ) );

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
				$( '#mwe-pt-control-stats' ).text( mw.msg(
					'pagetriage-stats-filter-page-count',
					stats.get( 'ptrFilterCount' )
				) );
			} );

		},

		/**
		 * Get namespace options to present to the user in the NPP controls.
		 *
		 * Based on wgPageTriageNamespaces
		 *
		 * @return {jQuery[]}
		 */
		getNamespaceOptions: function () {
			var wgFormattedNamespaces = mw.config.get( 'wgFormattedNamespaces' ),
				pageTriageNamespaces = mw.config.get( 'pageTriageNamespaces' ),
				draftNamespaceId = mw.config.get( 'wgPageTriageDraftNamespaceId' ),
				draftIndex = pageTriageNamespaces.indexOf( draftNamespaceId );

			// Remove draft from namespaces shown in NPP controls
			if ( draftIndex !== -1 ) {
				pageTriageNamespaces.splice( draftIndex, 1 );
			}

			return pageTriageNamespaces.map( function ( ns ) {
				var text = ns === 0 ?
					mw.msg( 'pagetriage-filter-article' ) :
					wgFormattedNamespaces[ ns ];
				return $( '<option>' ).attr( 'value', ns ).text( text );
			} );
		},

		render: function () {
			var that = this;

			// render and return the template. fill with the current model.
			$( '#mwe-pt-list-control-nav-content' ).html( this.template( this.model.toJSON() ) );

			// Insert the HTML for npp and afc date range filters
			$( '#date-range-npp-filters' ).html( this.getDateRangeFilterSectionHtml( 'npp' ) );
			$( '#date-range-afc-filters' ).html( this.getDateRangeFilterSectionHtml( 'afc' ) );

			// Capture afc state-specific sort options for later
			this.$afcDeclinedSortOptions = $( '.mwe-pt-afc-sort-declined' ).detach();
			this.$afcSubmittedSortOptions = $( '.mwe-pt-afc-sort-submitted' ).detach();

			// align the filter dropdown box with the dropdown control widget
			// yield to other JS first per bug 46367
			setTimeout( function () {
				var startSide = $( 'body' ).hasClass( 'rtl' ) ? 'right' : 'left',
					newStart = $( '#mwe-pt-filter-dropdown-control' ).width() - 8,
					arrowClosed = $( 'body' ).hasClass( 'rtl' ) ? '&#x25c2;' : '&#x25b8;';
				$( '#mwe-pt-control-dropdown' ).css( startSide, newStart );
				$( '#mwe-pt-control-dropdown-pokey' ).css( startSide, newStart + 5 );
				// Set drop down arrow.
				$( '#mwe-pt-dropdown-arrow' ).html( arrowClosed );
			} );

			// Set namespace options.
			$( '#mwe-pt-filter-namespace' ).empty().append( this.getNamespaceOptions() );

			//
			// now that the template's been inserted, set up some events for controlling it
			//

			// Queue-mode radio buttons.
			$( '.mwe-pt-queuemode-radio' ).on( 'change', function ( e ) {
				that.model.setMode( $( this ).val() );
				that.filterSync();
				that.refreshStats();
				$( '#mw-content-text' ).toggleClass( 'mwe-pt-mode-afc', $( this ).val() === 'afc' );
				e.stopPropagation();
			} );

			// make a submit button
			$( '#mwe-pt-filter-set-button' ).button( {
				label: mw.msg( 'pagetriage-filter-set-button' )
			} );
			$( '#mwe-pt-filter-set-button' ).on( 'click', function ( e ) {
				that.filterSync();
				that.refreshStats();
				that.toggleFilterMenu( 'close' );
				e.stopPropagation();
			} );

			$( '#mwe-pt-filter-user' ).on( 'keypress', function ( e ) {
				if ( e.which === 13 ) {
					$( '#mwe-pt-filter-set-button' ).trigger( 'click' );
					e.preventDefault();
					return false;
				}
			} );

			$(
				'#mwe-pt-filter-reviewed-edits,#mwe-pt-filter-unreviewed-edits,' +
				'#mwe-pt-filter-nominated-for-deletion,#mwe-pt-filter-redirects,' +
				'#mwe-pt-filter-others'
			).on( 'click',
				function ( e ) {
					that.setSubmitButtonState();
					e.stopPropagation();
				}
			);

			// the filter dropdown menu control
			$( '#mwe-pt-filter-dropdown-control' ).on( 'click', function ( e ) {
				that.toggleFilterMenu();
				e.stopPropagation();
			} );

			// NPP sort radio controls, AfC sort select/options
			$( 'input[name=sort], #mwe-pt-sort-afc' ).on( 'change', function ( e ) {
				// for querying
				that.model.setParam( 'dir', e.target.value );
				// for storage
				that.model.setParam( that.model.getMode() + 'Dir', e.target.value );
				that.model.saveFilterParams();
				that.refreshList();
				e.stopPropagation();
			} );

			// Select the username option when its input gets focus
			$( '#mwe-pt-filter-user' ).on( 'focus', function () {
				$( '#mwe-pt-filter-user-selected' ).prop( 'checked', true );
			} );

			// make sure the menus are synced with the filter settings
			this.menuSync();

			return this;
		},

		/**
		 * Refresh the stats when filtering options are changed.
		 */
		refreshStats: function () {
			this.options.stats.apiParams = this.getApiParams( false );
			this.options.stats.fetch();
		},

		// Refresh the page list
		refreshList: function () {
			this.model.setParam( 'offset', 0 );
			this.model.setParam( 'pageoffset', 0 );

			// Show spinner and refresh the list.
			this.modelFetch( { add: false } );
		},

		// Create a waypoint trigger that floats the navbar when the user scrolls down
		setWaypoint: function () {
			var that = this;
			$( '#mwe-pt-list-control-nav-anchor' ).waypoint( 'destroy' ); // remove the old, maybe inaccurate ones.
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
				arrowClosed = $( 'body' ).hasClass( 'rtl' ) ? '&#x25c2;' : '&#x25b8;',
				$controlDropdown = $( '#mwe-pt-control-dropdown' ),
				$controlDropdownPokey = $( '#mwe-pt-control-dropdown-pokey' );

			if ( ( action && action === 'close' ) || this.filterMenuVisible ) {
				$( '#mwe-pt-dropdown-arrow' ).html( arrowClosed );
				$controlDropdown.css( 'visibility', 'hidden' );
				$controlDropdownPokey.css( 'visibility', 'hidden' );
				$( 'body' ).off( 'click' ); // remove these events since they're not needed til next time.
				$controlDropdown.off( 'click' );
				this.filterMenuVisible = 0;
			} else if ( ( action && action === 'open' ) || !this.filterMenuVisible ) {
				this.menuSync();
				$controlDropdown.css( 'visibility', 'visible' );
				$controlDropdownPokey.css( 'visibility', 'visible' );
				$( '#mwe-pt-dropdown-arrow' ).html( '&#x25be;' ); // ▾ down-pointing triangle

				// close the menu when the user clicks away
				$( 'body' ).on( 'click', function () {
					that.toggleFilterMenu( 'close' );
				} );

				// this event "covers up" the body event, which keeps the menu from closing when
				// the user clicks inside.
				$controlDropdown.on( 'click', function ( e ) {
					e.stopPropagation();
				} );

				this.filterMenuVisible = 1;
			}

			this.setSubmitButtonState();
		},

		// To be able to submit the form, at least one 'state' and one 'type' have to be checked
		setSubmitButtonState: function () {
			if ( this.model.getMode() !== 'npp' ) {
				return;
			}

			var anyState = $( '#mwe-pt-filter-reviewed-edits' ).prop( 'checked' ) ||
				$( '#mwe-pt-filter-unreviewed-edits' ).prop( 'checked' );

			var anyType = $( '#mwe-pt-filter-nominated-for-deletion' ).prop( 'checked' ) ||
				$( '#mwe-pt-filter-redirects' ).prop( 'checked' ) ||
				$( '#mwe-pt-filter-others' ).prop( 'checked' );

			$( '#mwe-pt-filter-set-button' ).button(
				anyState && anyType ? 'enable' : 'disable'
			);
		},

		/**
		 * Set ORES, Copyvio related API parameters based on the NPP or AFC context.
		 *
		 * @param {string} context
		 * @return {Object}
		 */
		getApiParamsOres: function ( context ) {
			var apiParams = {},
				mapParamsToSelectors = {
					'predicted-class-stub': 'show_predicted_class_stub',
					'predicted-class-start': 'show_predicted_class_start',
					'predicted-class-c': 'show_predicted_class_c',
					'predicted-class-b': 'show_predicted_class_b',
					'predicted-class-good': 'show_predicted_class_good',
					'predicted-class-featured': 'show_predicted_class_featured',
					'predicted-issues-attack': 'show_predicted_issues_attack',
					'predicted-issues-copyvio': 'show_predicted_issues_copyvio',
					'predicted-issues-none': 'show_predicted_issues_none',
					'predicted-issues-spam': 'show_predicted_issues_spam',
					'predicted-issues-vandalism': 'show_predicted_issues_vandalism'
				};
			Object.keys( mapParamsToSelectors ).forEach( function ( selector ) {
				if ( $( '#mwe-pt-filter-%context-%selector'.replace( '%context', context )
					.replace( '%selector', selector ) )
					.prop( 'checked' ) ) {
					apiParams[ mapParamsToSelectors[ selector ] ] = '1';
				}
			} );
			return apiParams;
		},

		/**
		 * Fetch values from the form, used when building the API query.
		 *
		 * @param {boolean} [isListView] If true, will include params relevant to list view (dir
		 * and limit). If false (i.e. we are getting statistics), list view specific params are
		 * excluded.
		 * @return {Object}
		 */
		getApiParams: function ( isListView ) {
			var apiParams = {};

			if ( this.model.getMode() === 'npp' ) {
				apiParams = this.getApiParamsNpp();
				apiParams.namespace = $( '#mwe-pt-filter-namespace' ).val();
				apiParams.nppDir = $( '#mwe-pt-sort-oldest' ).prop( 'checked' ) ?
					'oldestfirst' : 'newestfirst';
			} else {
				// AfC
				apiParams.namespace = mw.config.get( 'wgNamespaceIds' ).draft;
				// eslint-disable-next-line camelcase
				apiParams.afc_state = $( '[name=mwe-pt-filter-afc-radio]:checked' ).val();
				apiParams.showunreviewed = '1';
				apiParams.showreviewed = '1';
				apiParams.afcDir = $( '#mwe-pt-sort-afc' ).val();
			}

			// Merge Date Range params.
			apiParams = $.extend( this.getApiParamsDateRange( this.model.getMode() ), apiParams );

			// Merge in ORES params.
			apiParams = $.extend( this.getApiParamsOres( this.model.getMode() ), apiParams );

			// Only set if fetching API params for the feed (since the stats endpoint doesn't
			// recognize dir and limit).
			if ( isListView ) {
				apiParams.dir = apiParams[ this.model.getMode() + 'Dir' ];
				apiParams.limit = this.model.getParam( 'limit' );
			}

			return apiParams;
		},

		/**
		 * Get API parameters from the form for the NPP queue.
		 *
		 * @return {Object}
		 */
		getApiParamsNpp: function () {
			// Start with showing unreviewed pages by default. Sometimes when switching to
			// NPP mode from AfC the filters lose their stickiness and nothing is shown.
			var apiParams = {};

			// || 0 is a safeguard. Old code suggested for some reason
			// the namespace filter may at times not exist.
			apiParams.namespace = $( '#mwe-pt-filter-namespace' ).val() || 0;

			// These are conditionals because the keys shouldn't exist if the checkbox isn't checked.
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

			if ( $( '#mwe-pt-filter-others' ).prop( 'checked' ) ) {
				apiParams.showothers = '1';
			}

			if ( $( '#mwe-pt-filter-user-selected' ).prop( 'checked' ) && $( '#mwe-pt-filter-user' ).val() ) {
				apiParams.username = $( '#mwe-pt-filter-user' ).val();
			}

			if ( $( '#mwe-pt-filter-no-categories' ).prop( 'checked' ) ) {
				// eslint-disable-next-line camelcase
				apiParams.no_category = '1';
			}

			if ( $( '#mwe-pt-filter-unreferenced' ).prop( 'checked' ) ) {
				apiParams.unreferenced = '1';
			}

			if ( $( '#mwe-pt-filter-orphan' ).prop( 'checked' ) ) {
				// eslint-disable-next-line camelcase
				apiParams.no_inbound_links = '1';
			}

			if ( $( '#mwe-pt-filter-recreated' ).prop( 'checked' ) ) {
				apiParams.recreated = '1';
			}

			if ( $( '#mwe-pt-filter-non-autoconfirmed' ).prop( 'checked' ) ) {
				// eslint-disable-next-line camelcase
				apiParams.non_autoconfirmed_users = '1';
			}

			if ( $( '#mwe-pt-filter-learners' ).prop( 'checked' ) ) {
				apiParams.learners = '1';
			}

			if ( $( '#mwe-pt-filter-blocked' ).prop( 'checked' ) ) {
				// eslint-disable-next-line camelcase
				apiParams.blocked_users = '1';
			}

			// Sanity-check the values to make sure we don't send invalid options in
			if ( !apiParams.showreviewed && !apiParams.showunreviewed ) {
				// One of these must be set; default to unreviewed
				apiParams.showunreviewed = '1';
			}

			if ( !apiParams.showdeleted && !apiParams.showredirs && !apiParams.showothers ) {
				apiParams.showdeleted = '1';
				apiParams.showothers = '1';
			}

			return apiParams;
		},

		/**
		 * Get API parameters from the form for the NPP or AFC context queue.
		 *
		 * @param {string} context
		 * @return {Object}
		 */
		getApiParamsDateRange: function ( context ) {
			var apiParams = {};
			var offset = parseInt( mw.user.options.get( 'timecorrection' ).split( '|' )[ 1 ] );
			if ( $( '#mwe-pt-filter-' + context + '-date-range-from' ).val() ) {
				// Interpret the entered date as UTC initially, then subtract the offset so that it
				// gets correctly treated as user time zone.
				var fromDate = moment.utc( $( '#mwe-pt-filter-' + context + '-date-range-from' ).val() )
					.subtract( offset, 'minutes' );
				// eslint-disable-next-line camelcase
				apiParams.date_range_from = fromDate.toISOString(); // Pass the date in ISO timestamp format "2019-08-17T06:59:59.000Z"
			}

			if ( $( '#mwe-pt-filter-' + context + '-date-range-to' ).val() ) {
				// Interpret the entered date as UTC initially, then subtract the offset so that it
				// gets correctly treated as user time zone.
				var toDate = moment.utc( $( '#mwe-pt-filter-' + context + '-date-range-to' ).val() )
					.subtract( offset, 'minutes' );
				// Set toDate's time the end of day 23:59:59
				toDate.add( 1, 'day' ).subtract( 1, 'second' );
				// eslint-disable-next-line camelcase
				apiParams.date_range_to = toDate.toISOString(); // Pass the date in ISO timestamp format "2019-08-17T06:59:59.000Z"
			}

			return apiParams;
		},

		/**
		 * Reload data from the model, showing a spinner while waiting for a response.
		 *
		 * @param {Object} [options]
		 */
		modelFetch: function ( options ) {
			// Show spinner.
			$( '#mwe-pt-refresh-button-holder' ).prepend( $.createSpinner( 'refresh-spinner' ) );
			$( '#mwe-pt-refresh-button' ).button( 'disable' );

			this.model.fetch( $.extend( {
				success: function () {
					$.removeSpinner( 'refresh-spinner' );
					$( '#mwe-pt-refresh-button' ).button( 'enable' );
				},
				error: function () {
					$.removeSpinner( 'refresh-spinner' );
					$( '#mwe-pt-refresh-button' ).button( 'enable' );
				}
			}, options ) );
		},

		/**
		 * Sync the filters with the contents of the menu.
		 *
		 * This is called when the filters or queue mode has changed.
		 */
		filterSync: function () {
			// fetch the values from the menu
			var apiParams = this.getApiParams( true );

			// the model in this context is mw.pageTriage.ArticleList
			this.model.setParams( apiParams );
			this.model.saveFilterParams();

			this.modelFetch();

			this.menuSync(); // make sure the menu is now up-to-date.
		},

		// Sync the menu and other UI elements with the contents of the filters
		menuSync: function () {
			var that = this;
			this.newFilterStatus = {
				namespace: [],
				state: [],
				type: [],
				'predicted-class': [],
				'predicted-issues': [],
				top: [],
				// eslint-disable-next-line camelcase
				date_range: []
			};

			// Show/hide controls based on what feed mode we're in.
			$( '#mw-content-text' ).toggleClass( 'mwe-pt-mode-afc', this.model.getMode() === 'afc' );

			// Sync menu content.
			if ( this.model.getMode() === 'npp' ) {
				this.menuSyncNpp();
			} else { // AfC
				this.menuSyncAfc();
			}

			// Sync menu date range params content according to mode.
			this.menuSyncDateRange( this.model.getMode() );

			// Set the "Showing: ..." filter status.
			this.filterStatus = Object.keys( this.newFilterStatus )
				.map( function ( group ) {
					var filters = that.newFilterStatus[ group ];
					if ( filters.length === 0 ) {
						return '';
					}
					if ( group === 'top' || ( that.model.getMode() === 'afc' && group === 'state' ) ) {
						// For the top-level filter, don't put it in parentheses and don't prefix it with a group label (T199277)
						return filters[ 0 ];
					}
					return mw.msg( 'pagetriage-filter-stat-' + group ) + ' ' +
						mw.msg( 'parentheses', filters.join( mw.msg( 'comma-separator' ) ) );
				} )
				.filter( function ( x ) {
					return x !== '';
				} )
				.join( mw.msg( 'comma-separator' ) );
			$( '#mwe-pt-filter-status' ).text( this.filterStatus );
		},

		/**
		 * Process menu checkbox updates based on the NPP or AFC context.
		 *
		 * @param {string} context
		 */
		menuCheckboxUpdateOres: function ( context ) {
			var selectors = [
					'predicted-class-stub',
					'predicted-class-start',
					'predicted-class-c',
					'predicted-class-b',
					'predicted-class-good',
					'predicted-class-featured',
					'predicted-issues-vandalism',
					'predicted-issues-spam',
					'predicted-issues-attack',
					'predicted-issues-copyvio',
					'predicted-issues-none'
				],
				key;
			for ( key in selectors ) {
				this.menuCheckboxUpdate(
					$( '#mwe-pt-filter-' + context + '-' + selectors[ key ] ),
					// Convert the selector to the format used as an API parameter.
					'show_' + selectors[ key ].replace( /-/g, '_' ),
					'pagetriage-filter-stat-' + selectors[ key ],
					selectors[ key ].match( /^predicted-class-/ ) ? 'predicted-class' : 'predicted-issues'
				);
			}
		},

		/**
		 * Sync the menu and other UI elements with the filters, for the NPP queue.
		 */
		menuSyncNpp: function () {
			// Make sure the radio button for the feed is correct, and the corresponding filter menu is shown.
			$( '#mwe-pt-radio-npp' ).prop( 'checked', true );

			var namespace = this.model.getParam( 'namespace' );
			$( '#mwe-pt-filter-namespace' ).val( namespace );
			this.newFilterStatus.namespace.push( $( '#mwe-pt-filter-namespace' ).find( ':selected' ).text() );

			this.menuCheckboxUpdate( $( '#mwe-pt-filter-reviewed-edits' ), 'showreviewed', 'pagetriage-filter-stat-reviewed', 'state' );
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-unreviewed-edits' ), 'showunreviewed', 'pagetriage-filter-stat-unreviewed', 'state' );

			this.menuCheckboxUpdate( $( '#mwe-pt-filter-nominated-for-deletion' ), 'showdeleted', 'pagetriage-filter-stat-nominated-for-deletion', 'type' );
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-redirects' ), 'showredirs', 'pagetriage-filter-stat-redirects', 'type' );
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-others' ), 'showothers', 'pagetriage-filter-stat-others', 'type' );

			this.menuCheckboxUpdateOres( 'npp' );

			var username = this.model.getParam( 'username' );
			if ( username ) {
				this.newFilterStatus.top.push( mw.msg( 'pagetriage-filter-stat-username', username ) );
				$( '#mwe-pt-filter-user-selected' ).prop( 'checked', true );
			}
			$( '#mwe-pt-filter-user' ).val( username );

			this.menuCheckboxUpdate( $( '#mwe-pt-filter-no-categories' ), 'no_category', 'pagetriage-filter-stat-no-categories', 'top' );
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-unreferenced' ), 'unreferenced', 'pagetriage-filter-stat-unreferenced', 'top' );
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-orphan' ), 'no_inbound_links', 'pagetriage-filter-stat-orphan', 'top' );
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-recreated' ), 'recreated', 'pagetriage-filter-stat-recreated', 'top' );
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-non-autoconfirmed' ), 'non_autoconfirmed_users', 'pagetriage-filter-stat-non-autoconfirmed', 'top' );
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-learners' ), 'learners', 'pagetriage-filter-stat-learners', 'top' );
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-blocked' ), 'blocked_users', 'pagetriage-filter-stat-blocked', 'top' );
			this.menuCheckboxUpdate( $( '#mwe-pt-filter-bot-edits' ), 'showbots', 'pagetriage-filter-stat-bots', 'top' );

			if ( !$( 'input[name=mwe-pt-filter-radio]:checked' ).val() ) {
				// None of the radio buttons are selected. Pick the default.
				$( '#mwe-pt-filter-all' ).prop( 'checked', true );
			}

			// Sync the sort toggle
			if ( this.model.getParam( 'nppDir' ) === 'oldestfirst' ) {
				$( '#mwe-pt-sort-oldest' ).prop( 'checked', true );
			} else {
				$( '#mwe-pt-sort-newest' ).prop( 'checked', true );
			}
		},

		/**
		 * Sync Date Range elements with the filters, for NPP or AFC context queue.
		 *
		 * @param {string} context
		 */
		menuSyncDateRange: function ( context ) {
			var offset = parseInt( mw.user.options.get( 'timecorrection' ).split( '|' )[ 1 ] );

			var dateRangeFrom = this.model.getParam( 'date_range_from' ); // ISO format
			if ( dateRangeFrom ) {
				dateRangeFrom = moment( dateRangeFrom );
				var formattedDateFrom = dateRangeFrom.utcOffset( offset )
					.format( mw.msg( 'pagetriage-filter-date-range-format-showing' ) );
				this.newFilterStatus.date_range.push( mw.msg( 'pagetriage-filter-stat-date_range_from', formattedDateFrom ) );
				$( '#mwe-pt-filter-' + context + '-date-range-from' ).val( dateRangeFrom.utcOffset( offset )
					.format( mw.msg( 'pagetriage-filter-date-range-format-input-field' ) ) );
			}

			var dateRangeTo = this.model.getParam( 'date_range_to' ); // ISO format
			if ( dateRangeTo ) {
				dateRangeTo = moment( dateRangeTo );
				var formattedDateTo = dateRangeTo.utcOffset( offset )
					.format( mw.msg( 'pagetriage-filter-date-range-format-showing' ) );
				this.newFilterStatus.date_range.push( mw.msg( 'pagetriage-filter-stat-date_range_to', formattedDateTo ) );
				$( '#mwe-pt-filter-' + context + '-date-range-to' ).val( dateRangeTo.utcOffset( offset )
					.format( mw.msg( 'pagetriage-filter-date-range-format-input-field' ) ) );
			}
		},

		getValidAfcSortOptionId: function ( afcState, afcDir ) {
			if ( afcDir === 'newestfirst' ) {
				return 'mwe-pt-sort-afc-newestfirst';
			}
			if ( afcDir === 'oldestfirst' ) {
				return 'mwe-pt-sort-afc-oldestfirst';
			}

			if ( afcState === '2' || afcState === '3' ) {
				return afcDir === 'newestreview' ?
					'mwe-pt-sort-afc-newestsubmitted' :
					'mwe-pt-sort-afc-oldestsubmitted';
			}

			if ( afcState === '4' ) {
				return afcDir === 'newestreview' ?
					'mwe-pt-sort-afc-newestdeclined' :
					'mwe-pt-sort-afc-oldestdeclined';
			}

			// default to something always valid
			return 'mwe-pt-sort-afc-newestfirst';
		},

		/**
		 * Sync the menu and other UI elements with the filters, for the AfC queue.
		 */
		menuSyncAfc: function () {
			this.menuCheckboxUpdateOres( 'afc' );

			$( '#mwe-pt-radio-afc' ).prop( 'checked', true );

			var afcStateValue = this.model.getParam( 'afc_state' );
			$( 'input[name=mwe-pt-filter-afc-radio][value=' + afcStateValue + ']' )
				.prop( 'checked', true );

			// Add/remove sorting options based on filter state.
			if ( afcStateValue === '4' ) { // Declined
				this.$afcSubmittedSortOptions.detach();
				this.$afcDeclinedSortOptions.appendTo( $( 'select#mwe-pt-sort-afc' ) );
			} else if ( afcStateValue === '2' || afcStateValue === '3' ) { // Awaiting or Under review
				this.$afcDeclinedSortOptions.detach();
				this.$afcSubmittedSortOptions.appendTo( $( 'select#mwe-pt-sort-afc' ) );
			} else {
				this.$afcSubmittedSortOptions.detach();
				this.$afcDeclinedSortOptions.detach();
			}

			var sortOptionId = this.getValidAfcSortOptionId(
				this.model.getParam( 'afc_state' ),
				this.model.getParam( 'afcDir' )
			);
			$( '#' + sortOptionId ).prop( 'selected', true );

			// Set the "Showing: ..." filter status.
			var afcStateName = $( 'input[name=mwe-pt-filter-afc-radio]:checked' ).data( 'afc-state-name' );
			this.newFilterStatus.state.push( mw.msg( 'pagetriage-afc-state-' + afcStateName ) );
		},

		/**
		 * Update a checkbox in the filter menu with data from the model.
		 *
		 * @param {jQuery} $checkbox The JQuery object of the input element.
		 * @param {string} param The value (i.e. 1 or 0, checked or not).
		 * @param {string} message The message name for the filter.
		 * @param {string} filterGroup Group the filter is in ('state', 'top', 'predicted-class' or 'predicted-issue')
		 */
		menuCheckboxUpdate: function ( $checkbox, param, message, filterGroup ) {
			$checkbox.prop( 'checked', parseInt( this.model.getParam( param ), 10 ) === 1 );
			if ( this.model.getParam( param ) ) {
				this.newFilterStatus[ filterGroup ].push( mw.msg( message ) );
			}
		},

		/**
		 * Get Html for date range filters
		 *
		 * @param {string} context The value 'npp' or 'afc'
		 * @return {string}
		 */
		getDateRangeFilterSectionHtml: function ( context ) {
			return '<span class="mwe-pt-control-label">' +
						'<b>' + mw.msg( 'pagetriage-filter-date-range-heading' ) + '</b>' +
					'</span>' +
					'<div class="mwe-pt-control-options">' +
						this.getDateRangeFilterFieldsHtml( context, 'from' ) +
						'</br>' +
						this.getDateRangeFilterFieldsHtml( context, 'to' ) +
					'</div>';
		},

		/**
		 * Get Html for date range label and input field
		 *
		 * @param {string} context The value 'npp' or 'afc'
		 * @param {string} dateRangeType The date range value 'to' or 'from'
		 * @return {string}
		 */
		getDateRangeFilterFieldsHtml: function ( context, dateRangeType ) {
			return '<label for="mwe-pt-filter-' + context + '-date-range-' + dateRangeType + '">' +
						mw.msg( 'pagetriage-filter-date-range-' + dateRangeType ) + ' ' +
					'</label>' +
					'<input type="date" name="mwe-pt-filter-' + context + '-date-range-' + dateRangeType + '"' +
						'id="mwe-pt-filter-' + context + '-date-range-' + dateRangeType + '"' +
						'placeholder="' + mw.msg( 'pagetriage-filter-date-range-format-placeholder' ) + '"/>';
		}
	} );
} );
