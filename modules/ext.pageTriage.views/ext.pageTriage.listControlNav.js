$( function() {
	// controls navbar
	
	mw.pageTriage.ListControlNav = Backbone.View.extend( {
		tagName: "div",
		template: _.template( $( "#listControlNavTemplate" ).html() ),

		initialize: function() {
			// make a floating top navbar
			// TODO: there's a bump when the control div detaches from the page.
			//       fill some element under it to make it scroll smoothly
			$( '.top' ).addClass( 'hidden' );
			$.waypoints.settings.scrollThrottle = 30;
			var _this = this;
			$( '#mwe-pt-list-control-nav' ).waypoint( function( event, direction ) {
				$( this ).parent().toggleClass( 'sticky', direction === "down" );
				_this.resize();
				event.stopPropagation();
			});
			
			// do things that need doing on window resize
			var resizeTimer;
			$( window ).resize( function() {
				clearTimeout(resizeTimer);
				resizeTimer = setTimeout(this.resize, 100);
			});
		},

		render: function() {
			// insert the template into the document.  fill with the current model.
			this.$el.html( this.template(  ) );
			return this;
		},
		
		resize: function() {
			// set the width of the floating bar when the window resizes, if it's floating.
			// the left nav is 176 pixels
			// the right margin is 16 pixels
			$( '#mwe-pt-list-control-nav' ).css( 'width', $(window).width() - 176 - 16 + "px" );
		}

	} );
} );
