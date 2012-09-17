// Stats represents the dashboard data for pagetriage
$( function() {
	if ( !mw.pageTriage ) {
		mw.pageTriage = {};
	}
	mw.pageTriage.Stats = Backbone.Model.extend( {
		defaults: {
			title: 'PageTriage Dashboard Data',
			pageid: ''
		},

		apiParams: {
			namespace: ''
		},

		initialize: function() {
			this.bind( 'change', this.formatMetadata, this );
		},

		formatMetadata: function ( stats ) {
			stats.set( 'ptrUnreviewedCount', stats.get( 'unreviewedarticle' )['count'] );
			stats.set( 'ptrOldest', this.formatDaysFromNow( stats.get( 'unreviewedarticle' )['oldest'] ) );
			stats.set( 'ptrReviewedCount', stats.get( 'reviewedarticle' )['reviewed_count'] );
		},

		formatDaysFromNow: function ( dateStr ) {
			if ( !dateStr ) {
				return '';
			}

			var now = new Date();
			now = new Date(
					Date.UTC(
						now.getUTCFullYear(),
						now.getUTCMonth(),
						now.getUTCDate(),
						now.getUTCHours(),
						now.getUTCMinutes(),
						now.getUTCSeconds()
					)
			);

			var begin = Date.parseExact( dateStr, 'yyyyMMddHHmmss' );
			begin.setTimezone( 'GMT' );

			var diff = Math.round( ( now.getTime() - begin.getTime() ) / ( 1000 * 60 * 60 * 24 ) );
			if ( diff ) {
				return gM( 'days', diff );
			} else {
				return gM( 'pagetriage-stats-less-than-a-day', diff );
			}
		},

		setParam: function( paramName, paramValue ) {
			this.apiParams[paramName] = paramValue;
		},

		url: function() {
			var url = mw.util.wikiScript( 'api' ) + '?action=pagetriagestats&format=json';
			if ( this.apiParams['namespace'] !== '' ) {
				url += '&'  + $.param( this.apiParams );
			}
			return url;
		},

		parse: function( response ) {
			for ( var title in response.pagetriagestats.stats.userpagestatus ) {
				mw.Title.exist.set( title );
			}
			// extract the useful bits of json.
			return response.pagetriagestats.stats;
		}
	} );
} );
