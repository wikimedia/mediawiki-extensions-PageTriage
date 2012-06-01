// Revision represents a single revision (aka history item)
// RevisionList is a collection of revisions for a single page
//
$( function() {
	if ( !mw.pageTriage ) {
		// make sure this object exists, since this might be run first.
		mw.pageTriage = {};
	}
	
	mw.pageTriage.Revision = Backbone.Model.extend( {
		defaults: {
			title: 'Empty Revision'
		},

		initialize: function() {
			//this.bind( 'change', this.formatMetadata, this );
		}

/*
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
			if( userName ) {
				var userTitle     = new mw.Title( userName, mw.config.get('wgNamespaceIds')['user'] );
				var userTalkTitle = new mw.Title( userName, mw.config.get('wgNamespaceIds')['user_talk'] );

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
			var url = mw.util.wikiScript( 'api' ) + '?action=pagetriagegetmetadata&format=json&' + $.param( { page_id: this.pageId } );
			return url;
		},
		
		parse: function( response ) {
			if( response.pagetriagegetmetadata ) {
				// data came from the getmetadata api call
				return response.pagetriagegetmetadata.page[ this.pageId ];				
			} else {
				// data came from the list api call
				// already parsed by the collection's parse function.
				return response;
			}
		},
*/
		
	} );


	mw.pageTriage.RevisionList = Backbone.Collection.extend( {
		model: mw.pageTriage.Revision,

		apiParams: {},

		initialize: function( options ) {
			this.eventBus = options.eventBus;
			this.pageId = options.pageId;
			this.apiParams.pageids = options.pageId; // pass this to the api
		},

		url: function() {
			return mw.util.wikiScript( 'api' ) + '?action=query&prop=revisions&format=json&' + $.param( this.apiParams );
		},

		parse: function( response ) {
			// extract the useful bits of json.
			return response.query.pages[this.pageId].revisions;
		},

		setParams: function( apiParams ) {
			this.apiParams = apiParams;
		},

		setParam: function( paramName, paramValue ) {
			this.apiParams[paramName] = paramValue;
		},

		getParam: function( key ) {
			return this.apiParams[key];
		}

	} );

} );
