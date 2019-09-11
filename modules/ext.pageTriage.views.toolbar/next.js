// Move to the next page

var
	// create an event aggregator
	eventBus = _.extend( {}, Backbone.Events ),
	// instantiate the collection of articles
	nextArticles = new mw.pageTriage.ArticleList( { eventBus: eventBus } ),
	ToolView = require( './ToolView.js' );

module.exports = ToolView.extend( {
	id: 'mwe-pt-next',
	icon: 'icon_skip.png', // the default icon
	tooltip: 'pagetriage-next-tooltip',

	apiParams: nextArticles.apiParams,

	initialize: function ( options ) {
		this.eventBus = options.eventBus;
		this.moduleConfig = options.moduleConfig || {};
	},

	setParams: function () {
		// these settings are not overwritable
		this.apiParams.limit = 1;
		this.apiParams.action = 'pagetriagelist';
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
		new mw.Api().get( this.apiParams )
			.done( function ( result ) {
				var url;
				if (
					result.pagetriagelist && result.pagetriagelist.result === 'success' && result.pagetriagelist.pages[ 0 ]
				) {
					page = result.pagetriagelist.pages[ 0 ];
					if ( page.title ) {
						url = new mw.Uri( mw.config.get( 'wgArticlePath' ).replace(
							'$1', mw.util.wikiUrlencode( page.title )
						) );
						if ( page.is_redirect === '1' ) {
							url.query.redirect = 'no';
						}
						window.location.href = url.toString();
					} else {
						// @TODO Remove this debugging output after resolution of https://phabricator.wikimedia.org/T232093
						/* eslint-disable no-console */
						console.error( 'PageTriage: Unable to get next page from page details.' );
						console.error( that.apiParams, result );
						that.disable();
					}
				} else {
					that.disable();
				}
			} )
			.fail( function () {
				that.disable();
			} );
	}
} );
