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
					// array of flyouts  disabled for the page creator
					disabledForCreators = [ 'tags', 'mark', 'delete' ];

				// overall toolbar view
				// currently, this is the main application view.
				mw.pageTriage.ToolbarView = Backbone.View.extend( {
					template: mw.pageTriage.viewUtil.template( { view: 'toolbar', template: 'toolbarView.html' } ),
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
					 */
					isFlyoutEnabled: function ( flyout ) {
						var modules = mw.config.get( 'wgPageTriageCurationModules' );

						// this flyout is disabled for curation toolbar
						if ( typeof modules[ flyout ] === 'undefined' ) {
							return false;
						}

						// this flyout is disabled for current namespace
						if ( $.inArray( mw.config.get( 'wgNamespaceNumber' ), modules[ flyout ].namespace ) === -1 ) {
							return false;
						}

						// this flyout is disabled for this user as he is the creator of the article
						if ( $.inArray( flyout, disabledForCreators ) !== -1 && article.get( 'user_name' ) === mw.user.getName() ) {
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
							containment: 'window',  // keep the curation bar inside the window
							delay: 200,  // these options prevent unwanted drags when attempting to click buttons
							distance: 10,
							cancel: '.mwe-pt-tool-content'
						} );

						// make clicking on the minimized toolbar expand to normal size
						$( '#mwe-pt-toolbar-vertical' ).click( function () {
							that.maximize( true );
						} );

						// since transform only works in IE 9 and higher, use writing-mode
						// to rotate the minimized toolbar content in older versions
						if ( $.client.test( { msie: [ [ '<', 9 ] ] }, null, true ) ) {
							$( '#mwe-pt-toolbar-vertical' ).css( 'writing-mode', 'tb-rl' );
						}

						// make the close button do something
						$( '.mwe-pt-toolbar-close-button' ).click( function () {
							that.hide( true );
						} );

						// lastUse expires, hide curation toolbar
						if ( mw.config.get( 'wgPageTriagelastUseExpired' ) ) {
							this.hide();
						// show the toolbar based on user preference
						} else {
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
						// close any open tools by triggering showTool with empty tool param
						eventBus.trigger( 'showTool', '' );
						// hide the regular toolbar content
						$( '#mwe-pt-toolbar-active' ).hide();
						// show the minimized toolbar content
						$( '#mwe-pt-toolbar-inactive' ).show();
						// switch to smaller size
						$( '#mwe-pt-toolbar' ).removeClass( 'mwe-pt-toolbar-big' ).addClass( 'mwe-pt-toolbar-small' )
							// dock to the side of the screen
							.css( 'left', 'auto' ).css( 'right', '0' );
						// set a pref for the user so the minimize state is remembered
						if ( typeof savePref !== 'undefined' && savePref === true ) {
							this.setToolbarPreference( 'minimized' );
						}
					},

					maximize: function ( savePref ) {
						// hide the minimized toolbar content
						$( '#mwe-pt-toolbar-inactive' ).hide();
						// show the regular toolbar content
						$( '#mwe-pt-toolbar-active' ).show();
						// switch to larger size
						$( '#mwe-pt-toolbar' ).removeClass( 'mwe-pt-toolbar-small' ).addClass( 'mwe-pt-toolbar-big' )
							// reset alignment to the side of the screen (since the toolbar is wider now)
							.css( 'left', 'auto' ).css( 'right', '0' );
						// set a pref for the user so the minimize state is remembered
						if ( typeof savePref !== 'undefined' && savePref === true ) {
							this.setToolbarPreference( 'maximized' );
						}
					},

					setToolbarPreference: function ( state, lastUse ) {
						var that, tokenRequest;

						// if we have a token, go ahead and use it
						if ( this.optionsToken ) {
							this.finishSetToolbarPreference( state, lastUse );
						// otherwise request an options token first
						} else {
							that = this;
							tokenRequest = {
								action: 'tokens',
								type: 'options',
								format: 'json'
							};
							$.ajax( {
								type: 'get',
								url: mw.util.wikiScript( 'api' ),
								data: tokenRequest,
								dataType: 'json',
								success: function ( data ) {
									try {
										that.optionsToken = data.tokens.optionstoken;
									} catch ( e ) {
										throw new Error( 'Could not get token (requires MediaWiki 1.20).' );
									}
									that.finishSetToolbarPreference( state, lastUse );
								}
							} );
						}
					},

					finishSetToolbarPreference: function ( state, lastUse ) {
						var change, prefRequest;

						change = 'userjs-curationtoolbar=' + state;
						if ( typeof lastUse !== 'undefined' ) {
							change += '|pagetriage-lastuse=' + lastUse;
						}
						prefRequest = {
							action: 'options',
							change: change,
							token: this.optionsToken,
							format: 'json'
						};
						$.ajax( {
							type: 'post',
							url: mw.util.wikiScript( 'api' ),
							data: prefRequest,
							dataType: 'json'
						} );
					},

					insertLink: function () {
						var now, mwFormat,
							that = this,
							$link = $( '<li id="t-curationtoolbar"><a href="#"></a></li>' );

						$link.find( 'a' )
							.text( mw.msg( 'pagetriage-toolbar-linktext' ) )
							.click( function () {
								if ( $( '#mwe-pt-toolbar' ).is( ':hidden' ) ) {
									now = new Date();
									now = new Date(
										now.getUTCFullYear(),
										now.getUTCMonth(),
										now.getUTCDate(),
										now.getUTCHours(),
										now.getUTCMinutes(),
										now.getUTCSeconds()
									);

									mwFormat = now.toString( 'yyyyMMddHHmmss' );

									$( '#mwe-pt-toolbar' ).show();
									$( '#mw-content-text .patrollink' ).hide();
									that.setToolbarPreference( 'maximized', mwFormat );
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
