$( function() {
	// view for the floating toolbar

	// create an event aggregator
	var eventBus = _.extend( {}, Backbone.Events );

	// the current article
	var article = new mw.pageTriage.Article( {
		eventBus: eventBus,
		pageId: mw.config.get( 'wgArticleId' ),
		includeHistory: true
	} );
	article.fetch();

	// array of tool instances
	var tools;

	// overall toolbar view
	// currently, this is the main application view.
	mw.pageTriage.ToolbarView = Backbone.View.extend( {
		template: mw.pageTriage.viewUtil.template( { 'view': 'toolbar', 'template': 'toolbarView.html' } ),

		initialize: function() {
			// An array of tool instances to put on the bar, ordered top-to-bottom
			tools = new Array;

			// TODO: decide here which tools to put on the bar, based on namespace, status, etc.
			// if we someday want this configurable on-wiki, this could load some js from
			// the MediaWiki namespace that generates the tools array instead, or we could make
			// some sort of config file thing

			// article information
			tools.push( new mw.pageTriage.ArticleInfoView( { eventBus: eventBus, model: article } ) );
			
			// and mark as reviewed
			tools.push( new mw.pageTriage.MarkView( { eventBus: eventBus, model: article } ) );

			// add tags
			tools.push( new mw.pageTriage.TagsView( { eventBus: eventBus } ) );

			if ( mw.config.get( 'wgPageTriageEnableDeletionWizard' ) ) {
				// delete
				tools.push( new mw.pageTriage.DeleteView( { eventBus: eventBus } ) );
			}

			// next article
			tools.push( new mw.pageTriage.NextView( { eventBus: eventBus } ) );
		},

		render: function() {
			var _this = this;
			// build the bar and insert into the page.

			// insert the empty toolbar into the document.
			$('body').append( this.template() );

			_.each( tools, function( tool ) {
				// append the individual tool template to the toolbar's big tool div part
				// this is the icon and hidden div. (the actual tool content)
				$( '#mwe-pt-toolbar-main' ).append( tool.place() );
			} );
			
			// make it draggable
			$( '#mwe-pt-toolbar' ).draggable( {
				containment: 'window',  // keep the curation bar inside the window
				delay: 200,  // these options prevent unwanted drags when attempting to click buttons
				distance: 10,
				cancel: '.mwe-pt-tool-content'
			} );
			
			var $activeToolbar = $( '#mwe-pt-toolbar-active' );
			var $inactiveToolbar = $( '#mwe-pt-toolbar-inactive' );
						
			// make the close button do something
			$( '#mwe-pt-toolbar-close-button').click( function() {
				// close any open tools.
				eventBus.trigger( 'showTool', this );				
				$activeToolbar.css('display', 'none');
				$inactiveToolbar.css('display', 'block');
				
				// this is a block element and will scale as wide as possible unless constrained
				$( '#mwe-pt-toolbar' ).removeClass( 'mwe-pt-toolbar-big' ).addClass( 'mwe-pt-toolbar-small' );
			} );

			// set up the reopen event
			$( '#mwe-pt-toolbar-inactive' ).click( function() {
				$inactiveToolbar.css('display', 'none');
				$activeToolbar.css('display', 'block');
				$( '#mwe-pt-toolbar' ).removeClass( 'mwe-pt-toolbar-small' ).addClass( 'mwe-pt-toolbar-big' );
			} );
			
		}
	} );

	// create an instance of the toolbar view
	var toolbar = new mw.pageTriage.ToolbarView( { eventBus: eventBus } );
	toolbar.render();
} );
