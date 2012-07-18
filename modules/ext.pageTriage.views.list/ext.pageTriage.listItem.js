$( function() {
	// view for a single list item

	mw.pageTriage.ListItem = Backbone.View.extend( {
		tagName: "div",
		className: "mwe-pt-list-item",
		template: mw.pageTriage.viewUtil.template( { 'view': 'list', 'template': 'listItem.html' } ),

		// listen for changes to the model and re-render.
		initialize: function() {
			this.model.bind('change', this.render, this);
			this.model.bind('destroy', this.remove, this);
		},

		render: function() {
			var data = this.model.toJSON();
			if ( mw.config.get( 'wgPageTriageEnableReviewButton' ) ) {
				data.reviewRightHelpText = '';
			} else {
				data.reviewRightHelpText = gM( 'pagetriage-no-patrol-right' );
			}
			// insert the template into the document.  fill with the current model.
			this.$el.html( this.template( data ) );
			return this;
		}

	} );
} );
