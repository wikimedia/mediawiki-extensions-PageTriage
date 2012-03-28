$( function() {
	// controls navbar
	
	mw.pageTriage.ListControlNav = Backbone.View.extend( {
		tagName: "div",
		template: _.template( $( "#listControlNavTemplate" ).html() ),

		// listen for changes to the model and re-render.
		initialize: function() {
		},

		render: function() {
			// insert the template into the document.  fill with the current model.
			this.$el.html( this.template(  ) );
			return this;
		}		

	} );
} );
