// Move to the next page

$( function() {
	// create an event aggregator
	var eventBus = _.extend( {}, Backbone.Events );

	// instantiate the collection of articles
	var nextArticles = new mw.pageTriage.ArticleList( { eventBus: eventBus } );
	
	mw.pageTriage.NextView = mw.pageTriage.ToolView.extend( {
		id: 'mwe-pt-next',
		icon: 'icon_skip.png', // the default icon
		title: 'Next',

		apiParams: nextArticles.apiParams,

		initialize: function( options ) {
			this.eventBus = options.eventBus;
		},

		setParams: function() {
			// these settings are not overwritable
			this.apiParams.limit  = 1;
			this.apiParams.action = 'pagetriagelist';
			this.apiParams.format = 'json';
			this.apiParams.offset = this.model.get( 'creation_date' );
			this.apiParams.pageoffset = this.model.get( 'pageid' );
		},
		
		click: function() {
			var page, _this = this;

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
				success: function( result ) {
					if ( result.pagetriagelist && result.pagetriagelist.result === 'success'
						&& result.pagetriagelist.pages[0]
					) {
						page = result.pagetriagelist.pages[0];
						if( page.title ) {
							var url = mw.config.get('wgArticlePath').replace(
								'$1', mw.util.wikiUrlencode( page.title )
							);
							if( page.is_redirect == '1' ) {
								var mark = ( url.indexOf( '?' ) === -1 ) ? '?' : '&';
								url += mark + "redirect=no";
							}
							window.location.href = url;
						} else {
							_this.disable();
						}
					} else {
						_this.disable();
					}
				},
				error: function( xhr ) {
					_this.disable();
				}
			} );

		}

	} );

} );
