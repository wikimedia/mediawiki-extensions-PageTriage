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

		initialize: function() {
			this.bind( 'change', this.formatMetadata, this );
		},

		formatMetadata: function ( stats ) {
			stats.set( 'ptr_unreviewed_article_count', stats.get( 'unreviewedarticle' )['count'] );

			var topTriager = {};
			for ( var i in stats.get( 'toptriager' )['data'] ) {
				var title = new mw.Title( stats.get( 'toptriager' )['data'][i]['user_name'], mw.config.get('wgNamespaceIds')['user'] );
				topTriager[i] = {
					title: title,
					linkCSS: title.exists() ? '' : 'class="new"',
					userName: stats.get( 'toptriager' )['data'][i]['user_name']
				};
			}

			stats.set( 'ptrTopTriager',  topTriager );
			stats.set( 'ptrTopTriagerStr', gM( 'pagetriage-stats-top-reviewers', Number( stats.get( 'toptriager' ).total ) ) );
			stats.set( 'ptrAverage', this.formatDaysFromNow( stats.get( 'unreviewedarticle' )['age-50th-percentile'] ) );
			stats.set( 'ptrOldest', this.formatDaysFromNow( stats.get( 'unreviewedarticle' )['age-100th-percentile'] ) );
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

		url: mw.util.wikiScript( 'api' ) + '?action=pagetriagestats&format=json',

		parse: function( response ) {
			for ( var title in response.pagetriagestats.stats.userpagestatus ) {
				mw.Title.exist.set( title );
			}
			// extract the useful bits of json.
			return response.pagetriagestats.stats;
		}
	} );
} );
