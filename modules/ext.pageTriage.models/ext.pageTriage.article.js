$( function() {
	mw.pageTriage = {
		Article: Backbone.Model.extend( {
			defaults: {
				title: 'Empty Article',
				pageid: ''
			},

		} ),
	};
	
	// can't include this in the declaration above because it references the
	// object created therein.
	mw.pageTriage.ArticleList = Backbone.Collection.extend( {
		model: mw.pageTriage.Article,
		url: mw.util.wikiScript( 'api' ) + '?action=pagetriagelist&format=json',

		parse: function( response ) {
			// extract the useful bits of json.
			return response.pagetriagelist.pages;
		}
	} );
	
} );
