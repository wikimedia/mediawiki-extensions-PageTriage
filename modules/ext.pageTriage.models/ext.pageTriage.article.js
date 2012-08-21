// Article represents the metadata for a single article.
// ArticleList is a collection of articles for use in the list view
//
$( function() {
	if ( !mw.pageTriage ) {
		// make sure this object exists, since this might be run first.
		mw.pageTriage = {};
	}
	mw.pageTriage.Article = Backbone.Model.extend( {
		defaults: {
			title: 'Empty Article',
			pageid: ''
		},

		initialize: function( options ) {
			this.bind( 'change', this.formatMetadata, this );
			this.pageId = options.pageId;

			if( options.includeHistory ) {
				// fetch the history too?
				// don't do this when fetching via collection, since it'll generate one ajax request per article.
				// don't execute on every model change, just when loading a different page
				this.bind( 'change:pageid', this.addHistory, this );
			}
		},

		formatMetadata: function ( article ) {
			var creation_date_parsed = Date.parseExact( article.get( 'creation_date' ), 'yyyyMMddHHmmss' );
			article.set('creation_date_pretty', creation_date_parsed.toString( gM( 'pagetriage-creation-dateformat' ) ) );

			// sometimes user info isn't set, so check that first.
			if( article.get( 'user_creation_date' ) ) {
				var user_creation_date_parsed = Date.parseExact( article.get( 'user_creation_date' ), 'yyyyMMddHHmmss' );
				article.set( 'user_creation_date_pretty', user_creation_date_parsed.toString( gM( 'pagetriage-user-creation-dateformat' ) ) );
			} else {
				article.set( 'user_creation_date_pretty', '');
			}

			var userName = article.get( 'user_name' );
			// TODO: What if userName doesn't exist?
			if( userName ) {
				var userTitle = new mw.Title( userName, mw.config.get('wgNamespaceIds')['user'] );
				var userTalkTitle = new mw.Title( userName, mw.config.get('wgNamespaceIds')['user_talk'] );
				var userContribsTitle = new mw.Title( mw.msg( 'pagetriage-special-contributions' ) + '/' + userName )

				var userLinkClass = userTitle.exists() ? '' : 'new';
				var userTalkLinkClass = userTalkTitle.exists() ? '' : 'new';

				// decide which byline message to use depending on if the editor is new or not
				if ( article.get( 'user_autoconfirmed' ) > 0 ) {
					var bylineMessage = 'pagetriage-byline';
				} else {
					var bylineMessage = 'pagetriage-byline-new-editor';
				}

				// build the user page link
				var userPageLink = mw.html.element(
					'a',
					{
						'href': this.buildRedLink( userTitle ),
						'class': userLinkClass
					},
					userName
				);

				// build the user talk page link
				var userTalkPageLink = mw.html.element(
					'a',
					{
						'href': this.buildRedLink( userTalkTitle ),
						'class': userTalkLinkClass
					},
					mw.msg( 'sp-contributions-talk' )
				);

				// build the user contribs link
				var userContribsLink = mw.html.element(
					'a',
					{
						'href': userContribsTitle.getUrl()
					},
					mw.msg( 'contribslink' )
				);

				// put it all together in the byline
				var byline = mw.msg(
					bylineMessage,
					userPageLink,
					userTalkPageLink,
					mw.msg( 'pipe-separator' ),
					userContribsLink
				);

				article.set( 'author_byline', byline );
				article.set( 'user_title_url', this.buildRedLink( userTitle ) );
				article.set( 'user_talk_title_url', this.buildRedLink( userTalkTitle ) );
				article.set( 'user_contribs_title', new mw.Title( gM( 'pagetriage-special-contributions' ) + '/' + userName ) );
				article.set( 'userPageLinkClass', userTitle.exists() ? '' : 'class="new"' );
				article.set( 'talkPageLinkClass', userTalkTitle.exists() ? '' : 'class="new"' );
			}
			article.set( 'title_url_format', mw.util.wikiUrlencode( article.get( 'title' ) ) );

			var titleUrl = mw.util.wikiGetlink( article.get( 'title' ) );
			if ( Number( article.get( 'is_redirect' ) ) === 1 ) {
				titleUrl = this.buildLink( titleUrl, 'redirect=no' );
			}
			article.set( 'title_url', titleUrl );
		},

		buildRedLink: function ( title ) {
			var url = title.getUrl();
			if ( !title.exists() ) {
				url = this.buildLink( url, 'action=edit&redlink=1' );
			}
			return url;
		},

		buildLink: function ( url, param ) {
			if ( param ) {
				var mark = ( url.indexOf( '?' ) === -1 ) ? '?' : '&';
				url += mark + param;
			}
			return url;
		},

		// url and parse are used here for retrieving a single article in the curation toolbar.
		// articles are retrived for list view using the methods in the Articles collection.
		url: function() {
			var d = new Date();
			var params = $.param( {
				action: 'pagetriagelist',
				format: 'json',
				timestamp: d.getTime()
			} );
			var url = mw.util.wikiScript( 'api' ) + '?' + params + '&' + $.param( { page_id: this.pageId } );
			return url;
		},
		
		parse: function( response ) {
			if( response.pagetriagelist ) {
				// data came directly from the api
				
				// Check if user pages exist or should be redlinks
				for ( var title in response.pagetriagelist.userpagestatus ) {
					mw.Title.exist.set( title );
				}

				// extract the useful bits of json.
				return response.pagetriagelist.pages[0];
			} else {
				// already parsed by the collection's parse function.
				return response;
			}
		},
		
		addHistory: function() {
			this.revisions = new mw.pageTriage.RevisionList( { eventBus: this.eventBus, pageId: this.pageId } );
			this.revisions.fetch();
		}
	} );

	mw.pageTriage.ArticleList = Backbone.Collection.extend( {
		moreToLoad: true,
		model: mw.pageTriage.Article,
		optionsToken: '',

		apiParams: {
			limit: 20,
			dir: 'newestfirst',
			namespace: 0,
			showreviewed: 1,
			showunreviewed: 1,
			showdeleted: 1
			/*
			showredirs: 0
			showbots: 0,
			no_category: 0,
			no_inbound_links: 0,
			non_autoconfirmed_users: 0,
			blocked_users: 0,
			username: null
			*/
		},

		initialize: function( options ) {
			this.eventBus = options.eventBus;
			this.eventBus.bind( "filterSet", this.setParams );

			// pull any saved filter settings from the user's option
			if ( !mw.user.isAnon() && mw.user.options.get( 'NewPagesFeedFilterOptions' ) ) {
				this.setParams( $.parseJSON( mw.user.options.get( 'NewPagesFeedFilterOptions' ) ) );
			}
		},

		url: function() {
			var d = new Date();
			var params = $.param( {
				action: 'pagetriagelist',
				format: 'json',
				timestamp: d.getTime()
			} );
			var url = mw.util.wikiScript( 'api' ) + '?' + params + '&' + $.param( this.apiParams );
			return url;
		},

		parse: function( response ) {
			// See if the fetch returned an extra page or not. This lets us know if there are more
			// pages to load in a subsequent fetch.
			if ( response.pagetriagelist.pages && response.pagetriagelist.pages.length > this.apiParams.limit ) {
				// Remove the extra page from the list
				response.pagetriagelist.pages.pop();
				this.moreToLoad = true;
			} else {
				// We have no more pages to load.
				this.moreToLoad = false;
			}

			// Check if user pages exist or should be redlinks
			for ( var title in response.pagetriagelist.userpagestatus ) {
				mw.Title.exist.set( title );
			}
			// extract the useful bits of json.
			return response.pagetriagelist.pages;
		},

		setParams: function( apiParams ) {
			this.apiParams = apiParams;
		},

		setParam: function( paramName, paramValue ) {
			this.apiParams[paramName] = paramValue;
		},

		encodeFilterParams: function() {
			var encodedString = '';
			var paramArray = new Array;
			var _this = this;
			$.each( this.apiParams, function( key, val ) {
				var str = '"' + key + '":';
				if ( typeof val === 'string' ) {
					val = '"' + val.replace(/[\"]/g, '\\"') + '"';
				}
				str += val;
				paramArray.push( str );
			} );
			encodedString = '{ ' + paramArray.join( ', ' ) + ' }';
			return encodedString;
		},

		// Save the filter parameters to a user's option
		saveFilterParams: function() {
			var _this = this;
			if ( !mw.user.isAnon() ) {
				if ( this.optionsToken ) {
					this.apiSetFilterParams();
				} else {
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
							_this.apiSetFilterParams();
						}
					} );
				}
			}
		},

		apiSetFilterParams: function() {
			var prefRequest = {
				'action': 'options',
				'change': 'NewPagesFeedFilterOptions=' + this.encodeFilterParams(),
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

		getParam: function( key ) {
			return this.apiParams[key];
		}

	} );

} );
