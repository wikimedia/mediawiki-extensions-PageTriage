$( function() {
	// view for the floating toolbar

	// create an event aggregator
	var eventBus = _.extend( {}, Backbone.Events );

	// instantiate the collection of articles
	var articles = new mw.pageTriage.ArticleList( { eventBus: eventBus } );

	// overall toolbar view
	// currently, this is the main application view.
	mw.pageTriage.ToolbarView = Backbone.View.extend( {
	} );

	// create an instance of the list view, which makes everything go.
	var list = new mw.pageTriage.ToolbarView( { eventBus: eventBus } );
	list.render();
} );
