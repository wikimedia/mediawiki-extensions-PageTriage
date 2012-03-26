$( function() {
	// view for the article list

	// instantiate the collection of articles
	var articles = new mw.pageTriage.ArticleList;

	// single list item
	var ListItem = Backbone.View.extend( {
		tagName: "div",
		template: _.template( $( "#listItemTemplate" ).html() ),

		// listen for changes to the model and re-render.
		initialize: function() {
			this.model.bind('change', this.render, this);
			this.model.bind('destroy', this.remove, this);
		},

		render: function() {
			// insert the template into the document.  fill with the current model.
			this.$el.html( this.template( this.model.toJSON() ) );
			return this;
		}		

	} );

	// overall list view
	// currently, this is the main application view.
	var ListView = Backbone.View.extend( {

		initialize: function() {

			// these events are triggered when items are added to the articles collection
			articles.bind( 'add', this.addOne, this );
			articles.bind( 'reset', this.addAll, this );
		
			// this event is triggered when the collection finishes loading.
			articles.bind( 'all', this.render, this );

			// on init, make sure to load the contents of the collection.
			articles.fetch();
		},

		render: function() {
			// TODO: refresh the view (show/hide the parts that aren't attached to the ListItem view)
		},

		// add a single article to the list
		addOne: function( article ) {
			// pass in the specific article instance
			var view = new ListItem( { model: article } );
			this.$( "#listView" ).append( view.render().el );
		},

		// add all the items in the articles collection
		addAll: function() {
			$("#listView").empty(); // remove the spinner before displaying.
			articles.each( this.addOne );
	    }

	} );

	// create an instance of the list view, which makes everything go.
	var list = new ListView();
} );
