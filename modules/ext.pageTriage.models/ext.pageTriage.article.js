	var Article = Backbone.Model.extend( {
		defaults: {
			title: 'Empty Article',
			pageid: ''
		},

	} );

	var ArticleList = Backbone.Collection.extend( {
		model: Article,
		url: '/w/api.php?action=pagetriagelist&format=json',

		parse: function( response ) {
			// extract the useful bits of json.
			return response.pagetriagelist.pages;
		}

	} );

