// Stats represents the dashboard data for pagetriage

const Stats = Backbone.Model.extend( {
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
		stats.set( 'ptrUnreviewedArticleCount', stats.get( 'unreviewedarticle' ).count );
		stats.set( 'ptrUnreviewedRedirectCount', stats.get( 'unreviewedredirect' ).count );
		stats.set( 'ptrOldest', this.formatDaysFromNow( stats.get( 'unreviewedarticle' ).oldest ) );
		stats.set( 'ptrReviewedArticleCount', stats.get( 'reviewedarticle' ).reviewed_count );
		stats.set( 'ptrReviewedRedirectCount', stats.get( 'reviewedredirect' ).reviewed_count );
		if ( stats.get( 'afcMode' ) ) {
			stats.set( 'ptrUnreviewedDraftCount', stats.get( 'unrevieweddraft' ).count );
			stats.set( 'ptrUnreviewedDraftOldest', this.formatDaysFromNow( stats.get( 'unrevieweddraft' ).oldest ) );
		}
		stats.set( 'ptrFilterCount', stats.get( 'filteredarticle' ) );
	},

	formatDaysFromNow: function ( dateStr ) {
		if ( !dateStr ) {
			return '';
		}

		let now = new Date();
		now = new Date(
			now.getUTCFullYear(),
			now.getUTCMonth(),
			now.getUTCDate(),
			now.getUTCHours(),
			now.getUTCMinutes(),
			now.getUTCSeconds()
		);

		const begin = moment.utc( dateStr );

		const diffInDays = Math.round(
			( now.getTime() - begin.valueOf() ) /
			( 1000 * 60 * 60 * 24 )
		);
		if ( diffInDays ) {
			return mw.msg( 'days', mw.language.convertNumber( diffInDays ) );
		} else {
			return mw.msg( 'pagetriage-stats-less-than-a-day', mw.language.convertNumber( diffInDays ) );
		}
	},

	setParam: function ( paramName, paramValue ) {
		this.apiParams[ paramName ] = paramValue;
	},

	url: function () {
		let url = mw.util.wikiScript( 'api' ) + '?action=pagetriagestats&format=json';
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

module.exports = Stats;
