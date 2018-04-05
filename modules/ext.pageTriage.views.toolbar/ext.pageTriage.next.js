// Move to the next page

$( function () {
	var
		// create an event aggregator
		eventBus = _.extend( {}, Backbone.Events ),
		// instantiate the collection of articles
		nextArticles = new mw.pageTriage.ArticleList( { eventBus: eventBus } );

	mw.pageTriage.NextView = mw.pageTriage.ToolView.extend( {
		id: 'mwe-pt-next',
		icon: 'icon_skip.png', // the default icon
		tooltip: 'pagetriage-next-tooltip',

		apiParams: nextArticles.apiParams,

		initialize: function ( options ) {
			this.eventBus = options.eventBus;
		},

		setParams: function () {
			// these settings are not overwritable
			this.apiParams.limit = 1;
			this.apiParams.action = 'pagetriagelist';
			this.apiParams.format = 'json';
			this.apiParams.offset = this.model.get( 'creation_date_utc' );
			this.apiParams.pageoffset = this.model.get( 'pageid' );
		},

		click: function () {
			var page, that = this;

			// find the next page.
			this.eventBus.trigger( 'showTool', this );

			// set the parameters for retrieving the next article
			this.setParams();

			// attempt to get the next page
			$.ajax( {
				type: 'post',
				url: mw.util.wikiScript( 'api' ),
				data: this.apiParams,
				dataType: 'json',
				success: function ( result ) {
					var url, mark;
					if (
						result.pagetriagelist && result.pagetriagelist.result === 'success' && result.pagetriagelist.pages[ 0 ]
					) {
						page = result.pagetriagelist.pages[ 0 ];
						if ( page.title ) {
							url = mw.config.get( 'wgArticlePath' ).replace(
								'$1', mw.util.wikiUrlencode( page.title )
							);
							// jscs: disable requireCamelCaseOrUpperCaseIdentifiers
							if ( page.is_redirect === '1' ) {
								mark = ( url.indexOf( '?' ) === -1 ) ? '?' : '&';
								url += mark + 'redirect=no';
							}
							// jscs: enable requireCamelCaseOrUpperCaseIdentifiers
							window.location.href = url;
						} else {
							that.disable();
						}
					} else {
						that.disable();
					}
				},
				error: function () {
					that.disable();
				}
			} );

		}

	} );

} );
