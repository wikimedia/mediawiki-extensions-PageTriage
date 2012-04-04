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
			stats.set( 'ptr_untriaged_article_count', stats.get( 'untriagedarticle' )['count'] );
			
			var topTriager = {};
			for ( var i in stats.get( 'toptriager' )['data'] ) {
				var title = new mw.Title( stats.get( 'toptriager' )['data'][i]['user_name'], mw.config.get('wgNamespaceIds')['user'] );
				topTriager[i] = { 
					title: title,
					linkCSS: mw.Title.exists( title) ? '' : 'class="new"',
					userName: stats.get( 'toptriager' )['data'][i]['user_name']
				};
			}

			stats.set( 'ptrTopTriager',  topTriager );
			stats.set( 'ptrTopTriagerStr', gM( 'pagetriage-stats-top-triagers', Number( stats.get( 'toptriager' ).total ) ) );
			stats.set( 'ptrAverage', this.formatDaysFromNow( stats.get( 'untriagedarticle' )['age-50th-percentile'] ) );
			stats.set( 'ptrOldest', this.formatDaysFromNow( stats.get( 'untriagedarticle' )['age-100th-percentile'] ) );
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

		formatTopTriager: function ( topTriager ) {
			if ( topTriager.total > 0 ) {
				var topTriagerList = '';
				for ( var key in topTriager.data ) {
					if ( topTriagerList ) {
						topTriagerList += ', ' + topTriager.data[key].user_name;
					} else {
						topTriagerList += topTriager.data[key].user_name;
					}
				}
				return gM( 'pagetriage-stats-top-triagers', Number( topTriager.total ), topTriagerList );
			} else {
				return '';
			}
		},

		url: mw.util.wikiScript( 'api' ) + '?action=pagetriagestats&format=json',

		parse: function( response ) {
			for ( var title in response.userpagestatus ) {
				mw.Title.exist.set( title );
			}
			// extract the useful bits of json.
			return response.pagetriagestats.stats;
		}
	} );
} );
