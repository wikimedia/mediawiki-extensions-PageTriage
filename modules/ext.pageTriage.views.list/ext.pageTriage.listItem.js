$( function() {
	// single list list view item
	
	mw.pageTriage.ListItem = Backbone.View.extend( {
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
} );
