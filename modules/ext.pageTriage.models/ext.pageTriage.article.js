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
				article.set( 'user_creation_date_pretty', user_creation_date_parsed.toString( gM( 'pagetriage-info-timestamp-date-format' ) ) );
			} else {
				article.set( 'user_creation_date_pretty', '');
			}

			// TODO: What if userName doesn't exist?
			if( article.get( 'user_name' ) ) {
				var info = this.userInfo( article.get( 'user_name' ) );

				// decide which byline message to use depending on if the editor is new or not
				// but don't show new editor for ip users
				if ( article.get( 'user_id' ) > '0' && article.get( 'user_autoconfirmed' ) < '1' ) {
					var bylineMessage = 'pagetriage-byline-new-editor';
				} else {
					var bylineMessage = 'pagetriage-byline';
				}

				// put it all together in the byline
				var byline = mw.msg(
					bylineMessage,
					info.userPageLink,
					info.userTalkPageLink,
					mw.msg( 'pipe-separator' ),
					info.userContribsLink
				);

				article.set( 'author_byline', byline );
				article.set( 'user_title_url', this.buildRedLink( info.userTitle ) );
				article.set( 'user_talk_title_url', this.buildRedLink( info.userTalkTitle ) );
				article.set( 'user_contribs_title', info.userContribsTitle );
			}
			
			// set the article status
			// delete status
			if ( article.get( 'afd_status' ) == "1" || article.get( 'blp_prod_status' ) == "1" ||
				article.get( 'csd_status' ) == "1" || article.get( 'prod_status' ) == "1" )
			{
				article.set( 'page_status', mw.msg( 'pagetriage-page-status-delete' ) );
			// unreviewed status
			} else if ( article.get( 'patrol_status' ) == "0" ) {
				article.set( 'page_status', mw.msg( 'pagetriage-page-status-unreviewed' ) );
			// reviewed status
			} else {
				if ( article.get( 'ptrp_last_reviewed_by' ) != 0 && article.get( 'reviewer' ) ) {
					var reviewerInfo = article.userInfo( article.get( 'reviewer' ) );
					article.set(
						'page_status',
						mw.msg(
							'pagetriage-page-status-reviewed',
							Date.parseExact( article.get( 'ptrp_reviewed_updated' ), 'yyyyMMddHHmmss' ).toString( gM( 'pagetriage-info-timestamp-date-format' ) ),
							reviewerInfo.userPageLink,
							reviewerInfo.userTalkPageLink,
							mw.msg( 'pipe-separator' ),
							reviewerInfo.userContribsLink
						)
					);
				} else {
					article.set( 'page_status', mw.msg( 'pagetriage-page-status-reviewed-anonymous' ) );
				}
			}
				
			article.set( 'title_url_format', mw.util.wikiUrlencode( article.get( 'title' ) ) );

			var titleUrl = mw.util.wikiGetlink( article.get( 'title' ) );
			if ( Number( article.get( 'is_redirect' ) ) === 1 ) {
				titleUrl = this.buildLink( titleUrl, 'redirect=no' );
			}
			article.set( 'title_url', titleUrl );
		},

		/**
		 * Helper function that generates useful user data, this function will be accessed in
		 * multiple places
		 */
		userInfo: function ( userName ) {
			var info = {
				userTitle: new mw.Title( userName, mw.config.get('wgNamespaceIds')['user'] ),
				userTalkTitle: new mw.Title( userName, mw.config.get('wgNamespaceIds')['user_talk'] ),
				userContribsTitle: new mw.Title( mw.msg( 'pagetriage-special-contributions' ) + '/' + userName )
			};

			userLinkClass = info.userTitle.exists() ? '' : 'new';
			userTalkLinkClass = info.userTalkTitle.exists() ? '' : 'new';

			// build the user page link
			info.userPageLink = mw.html.element(
				'a',
				{
					'href': this.buildRedLink( info.userTitle ),
					'class': userLinkClass
				},
				userName
			);

			// build the user talk page link
			info.userTalkPageLink = mw.html.element(
				'a',
				{
					'href': this.buildRedLink( info.userTalkTitle ),
					'class': userTalkLinkClass
				},
				mw.msg( 'sp-contributions-talk' )
			);

			// build the user contribs link
			info.userContribsLink = mw.html.element(
				'a',
				{
					'href': info.userContribsTitle.getUrl()
				},
				mw.msg( 'contribslink' )
			);

			return info;
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
			return mw.util.wikiScript( 'api' ) + '?' + params + '&' + $.param( { page_id: this.pageId } );
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

			// pull any saved filter settings from the user's cookies
			var savedFilterSettings = $.cookie( 'NewPagesFeedFilterOptions' );
			if ( savedFilterSettings ) {
				this.setParams( $.parseJSON( savedFilterSettings ) );
			}
		},

		url: function() {
			var d = new Date();
			var params = $.param( {
				action: 'pagetriagelist',
				format: 'json',
				timestamp: d.getTime()
			} );
			return mw.util.wikiScript( 'api' ) + '?' + params + '&' + $.param( this.apiParams );
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

		// Save the filter parameters to a cookie
		saveFilterParams: function() {
			var cookieString = this.encodeFilterParams();
			$.cookie( 'NewPagesFeedFilterOptions', cookieString, { expires: 1 } );
		},

		getParam: function( key ) {
			return this.apiParams[key];
		}

	} );

} );
