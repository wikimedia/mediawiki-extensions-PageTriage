$( function() {
	// view for the floating toolbar

	// create an event aggregator
	var eventBus = _.extend( {}, Backbone.Events );

	// instantiate the collection of articles
	var articles = new mw.pageTriage.ArticleList( { eventBus: eventBus } );
	var tools = array();

	// overall toolbar view
	// currently, this is the main application view.
	mw.pageTriage.ToolbarView = Backbone.View.extend( {
		initialize: function() {
			// decide here which tools to put on the bar, based on namespace, status, etc.
			// create instances of each of those tools, and build an ordered tools array.
			tools = array();
			
			// add an articleInfo for testing.
			tools[] = new mw.pageTriage.articleInfoView( { eventBus: eventBus } );
			
			// and a generic abstract toolView (which does nothing)
			tools[] = new mw.pageTriage.toolView( { eventBus: eventBus } );
			
		},
		
		render: function() {			
			// build the bar and insert into the page.
			
			_.each( tools, function( tool ) {
				console.log("tool title: " + tool.title);
			} );
			
			console.log( 'would insert toolbar on this page' );
		}
		
	} );

	// create an instance of the toolbar view
	var toolbar = new mw.pageTriage.ToolbarView( { eventBus: eventBus } );
	toolbar.render();
} );
