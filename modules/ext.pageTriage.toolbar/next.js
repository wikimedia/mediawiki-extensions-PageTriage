// Move to the next page
const { ArticleList } = require( 'ext.pageTriage.util' );
const
	// create an event aggregator
	eventBus = _.extend( {}, Backbone.Events ),
	// instantiate the collection of articles
	nextArticles = new ArticleList( { eventBus: eventBus } ),
	ToolView = require( './ToolView.js' );

module.exports = ToolView.extend( {
	id: 'mwe-pt-next',
	icon: 'icon_skip.png', // the default icon
	tooltip: 'pagetriage-next-tooltip',

	apiParams: nextArticles.getApiParams(),

	initialize: function ( options ) {
		this.eventBus = options.eventBus;
		this.moduleConfig = options.moduleConfig || {};
		this.pageTriageUi = options.pageTriageUi;
	},

	setParams: function () {
		// these settings are not overwritable
		this.apiParams.limit = 1;
		this.apiParams.action = 'pagetriagelist';
		this.apiParams.offset = this.model.get( 'creation_date_utc' );
		this.apiParams.pageoffset = this.model.get( 'pageid' );
	},

	click: function () {
		let page;
		const that = this;

		// find the next page.
		this.eventBus.trigger( 'showTool', this );

		// set the parameters for retrieving the next article
		this.setParams();

		// attempt to get the next page
		new mw.Api().get( this.apiParams )
			.then( ( result ) => {
				let url;
				// If API returns the content for next page 'result.pagetriagelist.pages[ 0 ]'
				// then user should be able to advance to next page
				if (
					result.pagetriagelist &&
					result.pagetriagelist.pages &&
					result.pagetriagelist.pages[ 0 ]
				) {
					page = result.pagetriagelist.pages[ 0 ];
					if ( page.title ) {
						url = new URL( mw.config.get( 'wgArticlePath' ).replace(
							'$1', mw.util.wikiUrlencode( page.title )
						), location.href );
						if ( page.is_redirect === '1' ) {
							url.searchParams.set( 'redirect', 'no' );
						}
						if ( that.pageTriageUi ) {
							url.searchParams.set( 'pagetriage_ui', that.pageTriageUi );
						}
						window.location.href = url.toString();
					} else {
						that.disable();
					}
				} else {
					that.disable();
				}
			}, () => {
				that.disable();
			} );
	}
} );
