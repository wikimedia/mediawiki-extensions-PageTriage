$( function () {
	// view for a single list item

	mw.pageTriage.ListItem = Backbone.View.extend( {
		tagName: 'div',
		className: 'mwe-pt-list-item',
		template: mw.template.get( 'ext.pageTriage.views.list', 'listItem.underscore' ),

		// listen for changes to the model and re-render.
		initialize: function () {
			this.model.bind( 'change', this.render, this );
			this.model.bind( 'destroy', this.remove, this );
		},

		render: function () {
			const data = this.model.toJSON();

			if ( mw.config.get( 'wgPageTriageEnableReviewButton' ) ) {
				data.reviewRightHelpText = '';
			} else {
				data.reviewRightHelpText = mw.msg( 'pagetriage-no-patrol-right' );
			}

			// insert the template into the document. fill with the current model.
			this.$el.html( this.template( data ) );

			// initialize page status tooltip
			this.$el.find( '.mwe-pt-status-icon' ).tipoff( this.model.get( 'page_status_html' ) );

			return this;
		}

	} );
} );
