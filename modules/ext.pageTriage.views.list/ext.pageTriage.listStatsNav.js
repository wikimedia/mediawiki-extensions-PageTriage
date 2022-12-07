$( function () {
	// statistics bar

	mw.pageTriage.ListStatsNav = Backbone.View.extend( {
		tagName: 'div',
		template: mw.template.get( 'ext.pageTriage.views.list', 'listStatsNav.underscore' ),

		initialize: function ( options ) {
			const that = this;

			this.eventBus = options.eventBus; // access the eventBus

			if ( mw.config.get( 'wgPageTriageStickyStatsNav' ) ) {
				this.setWaypoint(); // create scrolling waypoint

				// reset the width when the window is resized
				$( window ).on( 'resize', _.debounce( that.setWidth, 80 ) );

				// when the list view is updated, see if we need to change the
				// float state of the navbar
				this.eventBus.bind( 'articleListChange', function () {
					that.setPosition();
				} );
			}
		},

		render: function () {
			const that = this;

			// insert the template into the document.  fill with the current model.
			$( '#mwe-pt-list-stats-nav-content' ).html( this.template( this.model.toJSON() ) );

			// Add the stats.
			if ( this.model.attributes.ptrUnreviewedArticleCount && this.model.attributes.ptrOldest && this.model.attributes.ptrUnreviewedRedirectCount ) {
				$( '#mwe-pt-unreviewed-stats' ).text( mw.msg( 'pagetriage-unreviewed-article-count', this.model.attributes.ptrUnreviewedArticleCount, this.model.attributes.ptrUnreviewedRedirectCount, this.model.attributes.ptrOldest ) );
			}
			if ( this.model.attributes.ptrReviewedArticleCount && this.model.attributes.ptrReviewedRedirectCount ) {
				$( '#mwe-pt-reviewed-stats' ).text( mw.msg( 'pagetriage-reviewed-article-count-past-week', this.model.attributes.ptrReviewedArticleCount, this.model.attributes.ptrReviewedRedirectCount ) );
			}

			// run setPosition since the statsNav may need to be floated immediately
			if ( mw.config.get( 'wgPageTriageStickyStatsNav' ) ) {
				this.setPosition();
			}

			// Initialize Refresh List button
			$( '#mwe-pt-refresh-button' ).button().on( 'click', function ( e ) {
				// list refreshing is handled by the ListControlNav since it controls the page list
				that.eventBus.trigger( 'refreshListRequest' );
				e.stopPropagation();
			} );

			let intervalID;

			// Initialize Auto-Refresh List button
			$( '#mwe-pt-autorefresh-checkbox' ).on( 'change', function ( e ) {
				if ( $( '#mwe-pt-autorefresh-checkbox' ).prop( 'checked' ) ) {
					// Fire the function that refreshes the feed every 30 seconds
					intervalID = window.setInterval( function () {
						that.eventBus.trigger( 'refreshListRequest' );
					}, 30000 );
				} else {
					clearInterval( intervalID );
				}

				e.stopPropagation();
			} );

			// broadcast the stats in case any other views want to display bits of them.
			// (the control view displays a summary)
			this.eventBus.trigger( 'renderStats', this.model );

			return this;
		},

		// Create a waypoint trigger that floats the navbar when the user scrolls up
		setWaypoint: function () {
			const that = this;
			$( '#mwe-pt-list-stats-nav-anchor' ).waypoint( 'destroy' ); // remove the old, maybe inaccurate ones.
			$.waypoints.settings.scrollThrottle = 30;
			$( '#mwe-pt-list-stats-nav-anchor' ).waypoint( function ( event, direction ) {
				if ( direction === 'up' ) {
					$( '#mwe-pt-list-stats-nav' ).parent().addClass( 'stickyBottom' );
					that.setWidth();
				} else {
					$( '#mwe-pt-list-stats-nav' ).parent().removeClass( 'stickyBottom' );
				}
				event.stopPropagation();
			}, {
				offset: '100%' // bottom of page
			} );
		},

		// See if the navbar needs to be floated (for non-scrolling events)
		setPosition: function () {
			const viewportBottom = $( window ).scrollTop() + $( window ).height();
			// See if the nav anchor is visible in the current viewport
			if ( viewportBottom > $( '#mwe-pt-list-stats-nav-anchor' ).offset().top ) {
				// turn off floating nav, bring the bar back into the list.
				$( '#mwe-pt-list-stats-nav' ).parent().removeClass( 'stickyBottom' );
			} else {
				// bottom nav isn't visible.  turn on the floating navbar
				$( '#mwe-pt-list-stats-nav' ).parent().addClass( 'stickyBottom' );
			}
			// set the width in case the scrollbar status has changed
			this.setWidth();
			$.waypoints( 'refresh' );
		},

		setWidth: function () {
			// set the width of the floating bar when the window resizes, if it's floating.
			// border is 2 pixels
			$( '#mwe-pt-list-stats-nav' ).css( 'width', $( '#mw-content-text' ).width() - 2 + 'px' );
		}

	} );
} );
