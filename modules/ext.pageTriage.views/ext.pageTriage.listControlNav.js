$( function() {
	// controls navbar
	
	mw.pageTriage.ListControlNav = Backbone.View.extend( {
		tagName: "div",
		template: _.template( $( "#listControlNavTemplate" ).html() ),

		// listen for changes to the model and re-render.
		initialize: function() {
				$('.top').addClass('hidden');
				$.waypoints.settings.scrollThrottle = 30;
				$('#mwe-pt-list-control-nav').waypoint(function(event, direction) {
					$(this).parent().toggleClass('sticky', direction === "down");
					event.stopPropagation();
				});
		},

		render: function() {
			// insert the template into the document.  fill with the current model.
			this.$el.html( this.template(  ) );
			return this;
		}		

	} );
} );
