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
	article.fetch(
		{
			'success' : function() {
				// array of tool instances
				var tools;

				// overall toolbar view
				// currently, this is the main application view.
				mw.pageTriage.ToolbarView = Backbone.View.extend( {
					template: mw.pageTriage.viewUtil.template( { 'view': 'toolbar', 'template': 'toolbarView.html' } ),
					// token for setting user options
					optionsToken: '',

					initialize: function() {
						// An array of tool instances to put on the bar, ordered top-to-bottom
						tools = new Array;

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
					 * Check if the flyout is enabled for the current namespace
					 */
					isFlyoutEnabled: function( flyout ) {
						var modules = mw.config.get( 'wgPageTriageCurationModules' );

						// this flyout is disabled for curation toolbar
						if ( typeof modules[flyout] === 'undefined' ) {
							return false;
						}

						// this flyout is disabled for current namespace
						return $.inArray( mw.config.get( 'wgNamespaceNumber' ), modules[flyout].namespace ) !== -1;
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

						// make the minimize button do something
						$( '.mwe-pt-toolbar-minimize-button').click( function() {
							_this.minimize( true );
						} );

						// make clicking on the minimized toolbar expand to normal size
						$( '#mwe-pt-toolbar-vertical' ).click( function() {
							_this.maximize( true );
						} );

						// since transform only works in IE 9 and higher, use writing-mode
						// to rotate the minimized toolbar content in older versions
						if ( $.browser.msie && $.browser.version < 9.0 ) {
							$( '#mwe-pt-toolbar-vertical' ).css( 'writing-mode', 'tb-rl' );
						}

						// make the close button do something
						$( '.mwe-pt-toolbar-close-button').click( function() {
							_this.hide( true );
						} );

						// lastUse expires, hide curation toolbar
						if ( mw.config.get( 'wgPageTriagelastUseExpired' ) ) {
							this.hide();
						// show the toolbar based on user preference
						} else {
							switch ( mw.user.options.get( 'curationtoolbar' ) ) {
								case 'hidden':
									this.hide();
									break;
								case 'minimized':
									this.minimize();
									break;
								case 'maximized':
								default:
									this.maximize();
									break;
							}
						}
					},

					hide: function( savePref ) {
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

					setToolbarPreference: function( state, lastUse ) {
						// if we have a token, go ahead and use it
						if ( this.optionsToken ) {
							this.finishSetToolbarPreference( state, lastUse );
						// otherwise request an options token first
						} else {
							var _this = this;
							var tokenRequest = {
								'action': 'tokens',
								'type' : 'options',
								'format': 'json'
							};
							$.ajax( {
								type: 'get',
								url: mw.util.wikiScript( 'api' ),
								data: tokenRequest,
								dataType: 'json',
								success: function( data ) {
									try {
										_this.optionsToken = data.tokens.optionstoken;
									} catch ( e ) {
										throw new Error( 'Could not get token (requires MediaWiki 1.20).' );
									}
									_this.finishSetToolbarPreference( state, lastUse );
								}
							} );
						}
					},

					finishSetToolbarPreference: function( state, lastUse ) {
						var change = 'curationtoolbar=' + state;
						if ( typeof lastUse !== 'undefined' ) {
							change += '|pagetriage-lastuse=' + lastUse;
						}
						var prefRequest = {
							'action': 'options',
							'change': change,
							'token': this.optionsToken,
							'format': 'json'
						};
						$.ajax( {
							type: 'post',
							url: mw.util.wikiScript( 'api' ),
							data: prefRequest,
							dataType: 'json'
						} );
					},

					insertLink: function () {
						var _this = this, $link = $( '<li id="t-curationtoolbar"><a href="#"></a></li>' );
						$link.find( 'a' )
							.text( mw.msg( 'pagetriage-toolbar-linktext' ) )
							.click( function ( e ) {
								if ( $( '#mwe-pt-toolbar' ).is( ':hidden' ) ) {
									var now = new Date();
									now = new Date(
										now.getUTCFullYear(),
										now.getUTCMonth(),
										now.getUTCDate(),
										now.getUTCHours(),
										now.getUTCMinutes(),
										now.getUTCSeconds()
									);

									var mwFormat = now.toString( 'yyyyMMddHHmmss' );

									$( '#mwe-pt-toolbar' ).show();
									_this.setToolbarPreference( 'maximized', mwFormat );
								}
								this.blur();
								return false;
							} );
						$( '#p-tb' ).find( 'ul' ).append( $link );
						return true;
					}
				} );

				// create an instance of the toolbar view
				var toolbar = new mw.pageTriage.ToolbarView( { eventBus: eventBus } );
				toolbar.render();
				article.set( 'successfulModelLoading', 1 );
			}
		}
	);
} );
