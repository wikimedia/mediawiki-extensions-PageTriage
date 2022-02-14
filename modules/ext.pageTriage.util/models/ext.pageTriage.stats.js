// Stats represents the dashboard data for pagetriage
$( function () {
	mw.pageTriage.Stats = Backbone.Model.extend( {
		defaults: {
			title: 'PageTriage Dashboard Data',
			pageid: ''
		},

		apiParams: {
			namespace: ''
		},

		initialize: function () {
			this.bind( 'change', this.formatMetadata, this );
		},

		formatMetadata: function ( stats ) {
			stats.set( 'afcMode', stats.get( 'namespace' ) === mw.config.get( 'wgPageTriageDraftNamespaceId' ) );
			stats.set( 'ptrUnreviewedCount', stats.get( 'unreviewedarticle' ).count );
			stats.set( 'ptrOldest', this.formatDaysFromNow( stats.get( 'unreviewedarticle' ).oldest ) );
			stats.set( 'ptrReviewedCount', stats.get( 'reviewedarticle' ).reviewed_count );
			stats.set( 'ptrFilterCount', stats.get( 'filteredarticle' ) );
		},

		formatDaysFromNow: function ( dateStr ) {
			var now, begin, diff;

			if ( !dateStr ) {
				return '';
			}

			now = new Date();
			now = new Date(
				now.getUTCFullYear(),
				now.getUTCMonth(),
				now.getUTCDate(),
				now.getUTCHours(),
				now.getUTCMinutes(),
				now.getUTCSeconds()
			);

			begin = moment.utc( dateStr, 'YYYYMMDDHHmmss' );

			diff = Math.round( ( now.getTime() - begin.valueOf() ) / ( 1000 * 60 * 60 * 24 ) );
			if ( diff ) {
				return mw.msg( 'days', diff );
			} else {
				return mw.msg( 'pagetriage-stats-less-than-a-day', diff );
			}
		},

		setParam: function ( paramName, paramValue ) {
			this.apiParams[ paramName ] = paramValue;
		},

		url: function () {
			var url = mw.util.wikiScript( 'api' ) + '?action=pagetriagestats&format=json';
			if ( this.apiParams.namespace !== '' ) {
				url += '&' + $.param( this.apiParams );
			}
			return url;
		},

		parse: function ( response ) {
			// extract the useful bits of json.
			return response.pagetriagestats.stats;
		}
	} );
} );
