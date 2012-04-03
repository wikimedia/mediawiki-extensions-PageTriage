$( function() {
	// view for the article list
	
	// create an event aggregator
	var eventBus = _.extend( {}, Backbone.Events );

	// instantiate the collection of articles
	var articles = new mw.pageTriage.ArticleList( { eventBus: eventBus } );
	
	// grab pageTriage statistics
	var stats = new mw.pageTriage.Stats( { eventBus: eventBus } );

	// set the default sort order.
	articles.comparator = function( article ) {
		return -article.get( "creation_date" );
	};

	// overall list view
	// currently, this is the main application view.
	mw.pageTriage.ListView = Backbone.View.extend( {

		initialize: function( options ) {
			this.eventBus = options.eventBus; // access the eventBus

			// these events are triggered when items are added to the articles collection
			this.position = 0;
			articles.bind( 'add', this.addOne, this );
			articles.bind( 'reset', this.addAll, this );
			stats.bind( 'change', this.addStats, this );
		
			// this event is triggered when the collection finishes loading.
			//articles.bind( 'all', this.render, this );

			// on init, make sure to load the contents of the collection.
			articles.fetch();
			stats.fetch();
		},

		render: function() {
			// reset the position indicator
			this.position = 0;
			
			var controlNav = new mw.pageTriage.ListControlNav( { eventBus: this.eventBus, model: articles } );
			controlNav.render();
		},
		
		// add stats data to the navigation
		addStats: function( stats ) {
			var statsNav = new mw.pageTriage.ListStatsNav( { eventBus: this.eventBus, model: stats } );
			statsNav.render();
		},

		// add a single article to the list
		addOne: function( article ) {
			// define position, for making alternating background colors.
			// this is added at the last minute, so it gets updated when the sort changes.
			if(! this.position ) {
				this.position = 0;
			}
			article.set( 'position', this.position++ );
			
			// pass in the specific article instance
			var view = new mw.pageTriage.ListItem( { eventBus: this.eventBus, model: article } );
			this.$( "#mwe-pt-list-view" ).append( view.render().el );
			this.$( ".mwe-pt-list-triage-button" ).button({
				label: mw.msg( 'pagetriage-triage' ),
				icons: { primary:'ui-icon-search' }
			});
		},

		// add all the items in the articles collection
		addAll: function() {
			$("#mwe-pt-list-view").empty(); // remove the spinner before displaying.
			articles.each( this.addOne );
			this.eventBus.trigger( 'listAddAll' );
	    }

	} );

	// create an instance of the list view, which makes everything go.
	var list = new mw.pageTriage.ListView( { eventBus: eventBus } );
	list.render();
} );
