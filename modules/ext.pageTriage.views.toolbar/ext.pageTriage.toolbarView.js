$( function() {
	// view for the floating toolbar

	// create an event aggregator
	var eventBus = _.extend( {}, Backbone.Events );

	// instantiate the collection of articles
	var articles = new mw.pageTriage.ArticleList( { eventBus: eventBus } );

	// overall toolbar view
	// currently, this is the main application view.
	mw.pageTriage.ToolbarView = Backbone.View.extend( {
		initialize: function() {
			// decide here which tools to put on the bar, based on namespace, status, etc.
			// create instances of each of those tools
			
		},
		
		render: function() {			
			// build the bar and insert into the page.
			
			console.log( 'would insert toolbar on this page' );
		}
		
	} );

	// create an instance of the list view, which makes everything go.
	var toolbar = new mw.pageTriage.ToolbarView( { eventBus: eventBus } );
	toolbar.render();
} );
