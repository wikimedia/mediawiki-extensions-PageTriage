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
			if ( !dateStr ) {
				return '';
			}

			var now = new Date();
			now = new Date(
				now.getUTCFullYear(),
				now.getUTCMonth(),
				now.getUTCDate(),
				now.getUTCHours(),
				now.getUTCMinutes(),
				now.getUTCSeconds()
			);

			var begin = moment.utc( dateStr, 'YYYYMMDDHHmmss' );

			var diff = Math.round( ( now.getTime() - begin.valueOf() ) / ( 1000 * 60 * 60 * 24 ) );
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
				// afc_state is stored in the model as '1', '2', '3', '4', or 'all',
				// but the api parameter should be an integer. Omitting the parameter
				// entirely means there is no filtering, which is what 'all' should
				// do. See T304574
				if ( this.apiParams.afc_state && this.apiParams.afc_state === 'all' ) {
					delete this.apiParams.afc_state;
				}
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
