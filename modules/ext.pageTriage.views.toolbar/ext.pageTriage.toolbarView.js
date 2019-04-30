$( function () {
	// view for the floating toolbar

	// create an event aggregator
	var eventBus = _.extend( {}, Backbone.Events ),
		// the current article
		article = new mw.pageTriage.Article( {
			eventBus: eventBus,
			pageId: mw.config.get( 'wgArticleId' ),
			includeHistory: true
		} );

	article.fetch(
		{
			success: function () {
				var toolbar,
					// array of tool instances
					tools,
					// array of flyouts disabled for the page creator
					disabledForCreators = [ 'tags', 'mark', 'delete' ];

				// overall toolbar view
				// currently, this is the main application view.
				mw.pageTriage.ToolbarView = Backbone.View.extend( {
					template: mw.template.get( 'ext.pageTriage.views.toolbar', 'toolbarView.underscore' ),
					// token for setting user options
					optionsToken: '',

					initialize: function () {
						// An array of tool instances to put on the bar, ordered top-to-bottom
						tools = [];

						tools.push( new mw.pageTriage.MinimizeView( { eventBus: eventBus, model: article, toolbar: this } ) );

						// article information
						if ( this.isFlyoutEnabled( 'articleInfo' ) ) {
							tools.push( new mw.pageTriage.ArticleInfoView( { eventBus: eventBus, model: article } ) );
						}

						// wikilove
						if ( this.isFlyoutEnabled( 'wikiLove' ) ) {
							tools.push( new mw.pageTriage.WikiLoveView( { eventBus: eventBus, model: article } ) );
						}

						// mark as reviewed
						if ( this.isFlyoutEnabled( 'mark' ) ) {
							tools.push( new mw.pageTriage.MarkView( { eventBus: eventBus, model: article } ) );
						}

						// add tags
						if ( this.isFlyoutEnabled( 'tags' ) ) {
							tools.push( new mw.pageTriage.TagsView( { eventBus: eventBus, model: article } ) );
						}

						// delete
						if ( this.isFlyoutEnabled( 'delete' ) ) {
							tools.push( new mw.pageTriage.DeleteView( { eventBus: eventBus, model: article } ) );
						}

						// next article, should be always on
						tools.push( new mw.pageTriage.NextView( { eventBus: eventBus, model: article } ) );
					},

					/**
					 * Check if the flyout is enabled
					 *
					 * @param {string} flyout
					 * @return {boolean}
					 */
					isFlyoutEnabled: function ( flyout ) {
						var modules = mw.config.get( 'wgPageTriageCurationModules' );

						// this flyout is disabled for curation toolbar
						if ( typeof modules[ flyout ] === 'undefined' ) {
							return false;
						}

						// this flyout is disabled for current namespace
						if ( modules[ flyout ].namespace.indexOf( mw.config.get( 'wgNamespaceNumber' ) ) === -1 ) {
							return false;
						}

						// this flyout is disabled for this user as he is the creator of the article
						if ( disabledForCreators.indexOf( flyout ) !== -1 && article.get( 'user_name' ) === mw.user.getName() ) {
							return false;
						}

						return true;
					},

					render: function () {
						var that = this;
						// build the bar and insert into the page.
						// insert the empty toolbar into the document.
						$( 'body' ).append( this.template() );

						_.each( tools, function ( tool ) {
							// append the individual tool template to the toolbar's big tool div part
							// this is the icon and hidden div. (the actual tool content)
							$( '#mwe-pt-toolbar-main' ).append( tool.place() );
						} );

						// make it draggable
						$( '#mwe-pt-toolbar' ).draggable( {
							containment: 'window', // keep the curation bar inside the window
							delay: 200, // these options prevent unwanted drags when attempting to click buttons
							distance: 10,
							cancel: '.mwe-pt-tool-content'
						} );

						// make clicking on the minimized toolbar expand to normal size
						$( '#mwe-pt-toolbar-vertical' ).on( 'click', function () {
							that.maximize( true );
						} );

						// since transform only works in IE 9 and higher, use writing-mode
						// to rotate the minimized toolbar content in older versions
						if ( $.client.test( { msie: [ [ '<', 9 ] ] }, null, true ) ) {
							$( '#mwe-pt-toolbar-vertical' ).css( 'writing-mode', 'tb-rl' );
						}

						// make the close button do something
						$( '.mwe-pt-toolbar-close-button' ).on( 'click', function () {
							that.hide( true );
						} );

						// Auto-resize all textareas as they type.
						$( '#mwe-pt-toolbar' ).off( 'input.mwe-pt-tool-flyout' )
							.on( 'input.mwe-pt-tool-flyout', 'textarea', function () {
								var newHeight = this.scrollHeight + 2, // +2 to account for line-height.
									maxHeight = 152; // Arbitrary, roughly 12 lines of text.

								if ( newHeight > maxHeight ) {
									this.style.height = maxHeight;
									this.style.overflowY = 'scroll';
								} else {
									this.style.height = 'auto';
									this.style.height = newHeight + 'px';
									this.style.overflowY = 'hidden';
								}
							} );

						// Show the toolbar based on saved prefs.
						switch ( mw.user.options.get( 'userjs-curationtoolbar' ) ) {
							case 'hidden':
								this.hide();
								break;
							case 'minimized':
								this.minimize();
								$( '#mw-content-text .patrollink' ).hide();
								break;
							case 'maximized':
							/* falls through */
							default:
								this.maximize();
								$( '#mw-content-text .patrollink' ).hide();
								break;
						}
					},

					hide: function ( savePref ) {
						// hide everything
						$( '#mwe-pt-toolbar' ).hide();
						// reset the curation toolbar to original state
						$( '#mwe-pt-toolbar-inactive' ).css( 'display', 'none' );
						$( '#mwe-pt-toolbar-active' ).css( 'display', 'block' );
						$( '#mwe-pt-toolbar' ).removeClass( 'mwe-pt-toolbar-small' ).addClass( 'mwe-pt-toolbar-big' );
						if ( typeof savePref !== 'undefined' && savePref === true ) {
							this.setToolbarPreference( 'hidden' );
						}
						// insert link to reopen into the toolbox (if it doesn't already exist)
						if ( $( '#t-curationtoolbar' ).length === 0 ) {
							this.insertLink();
						}
						// Show hidden patrol link in case they want to use that instead
						$( '#mw-content-text .patrollink' ).show();
					},

					minimize: function ( savePref ) {
						var dir = $( 'body' ).css( 'direction' ),
							toolbarPosCss = dir === 'ltr' ?
								{
									left: 'auto',
									right: 0
								} :
								// For RTL, flip
								{
									left: 0,
									right: 'auto'
								};

						// close any open tools by triggering showTool with empty tool param
						eventBus.trigger( 'showTool', '' );
						// hide the regular toolbar content
						$( '#mwe-pt-toolbar-active' ).hide();
						// show the minimized toolbar content
						$( '#mwe-pt-toolbar-inactive' ).show();
						// switch to smaller size
						$( '#mwe-pt-toolbar' ).removeClass( 'mwe-pt-toolbar-big' ).addClass( 'mwe-pt-toolbar-small' )
							// dock to the side of the screen
							.css( toolbarPosCss );
						// set a pref for the user so the minimize state is remembered
						if ( typeof savePref !== 'undefined' && savePref === true ) {
							this.setToolbarPreference( 'minimized' );
						}
					},

					maximize: function ( savePref ) {
						var dir = $( 'body' ).css( 'direction' ),
							toolbarPosCss = dir === 'ltr' ?
								{
									left: 'auto',
									right: 0
								} :
								// For RTL, flip
								{
									left: 0,
									right: 'auto'
								};

						// hide the minimized toolbar content
						$( '#mwe-pt-toolbar-inactive' ).hide();
						// show the regular toolbar content
						$( '#mwe-pt-toolbar-active' ).show();
						// switch to larger size
						$( '#mwe-pt-toolbar' ).removeClass( 'mwe-pt-toolbar-small' ).addClass( 'mwe-pt-toolbar-big' )
							// reset alignment to the side of the screen (since the toolbar is wider now)
							.css( toolbarPosCss );
						// set a pref for the user so the minimize state is remembered
						if ( typeof savePref !== 'undefined' && savePref === true ) {
							this.setToolbarPreference( 'maximized' );
						}
					},
					setToolbarPreference: function ( state ) {
						return new mw.Api().saveOption( 'userjs-curationtoolbar', state );
					},
					insertLink: function () {
						var that = this,
							$link = $( '<li>' ).attr( 'id', 't-curationtoolbar' ).append(
								$( '<a>' ).attr( 'href', '#' )
							);

						$link.find( 'a' )
							.text( mw.msg( 'pagetriage-toolbar-linktext' ) )
							.on( 'click', function () {
								if ( $( '#mwe-pt-toolbar' ).is( ':hidden' ) ) {
									$( '#mwe-pt-toolbar' ).show();
									$( '#mw-content-text .patrollink' ).hide();
									that.setToolbarPreference( 'maximized' );
								}
								this.blur();
								return false;
							} );
						$( '#p-tb' ).find( 'ul' ).append( $link );
						return true;
					}
				} );

				// create an instance of the toolbar view
				toolbar = new mw.pageTriage.ToolbarView( { eventBus: eventBus } );
				toolbar.render();
				article.set( 'successfulModelLoading', 1 );
			}
		}
	);
} );
