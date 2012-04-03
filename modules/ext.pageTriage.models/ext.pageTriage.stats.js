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
			stats.set( 'ptrTopTriager', this.formatTopTriager( stats.get( 'toptriager' ) ) );
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
			// extract the useful bits of json.
			return response.pagetriagestats.stats;
		}
	} );
} );
