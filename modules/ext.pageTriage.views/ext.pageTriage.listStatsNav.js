$( function() {
	// statistics bar
	
	mw.pageTriage.ListStatsNav = Backbone.View.extend( {
		tagName: "div",
		template: _.template( $( "#listStatsNavTemplate" ).html() ),

		initialize: function( options ) {
			this.eventBus = options.eventBus;
		},

		render: function() {
			// insert the template into the document.  fill with the current model.
			this.$el.html( this.template( this.model.toJSON() ) );
			
			// broadcast the stats in case any other views want to display bits of them.
			// (the control view displays a summary)
			this.eventBus.trigger( 'renderStats', this.model );
			return this;
		}		

	} );
} );
