// Article represents the metadata for a single article.
// ArticleList is a collection of articles for use in the list view

$( function () {
	mw.pageTriage.Article = Backbone.Model.extend( {
		defaults: {
			title: 'Empty Article',
			pageid: ''
		},

		initialize: function ( options ) {
			this.bind( 'change', this.formatMetadata, this );
			this.pageId = options.pageId;

			if ( options.includeHistory ) {
				// fetch the history too?
				// don't do this when fetching via collection, since it'll generate one ajax request per article.
				// don't execute on every model change, just when loading a different page
				this.bind( 'change:pageid', this.addHistory, this );
			}
		},

		formatMetadata: function ( article ) {
			const creationDateParsed = moment.utc( article.get( 'creation_date_utc' ), 'YYYYMMDDHHmmss' ),
				reviewedUpdatedParsed = moment.utc( article.get( 'ptrp_reviewed_updated' ), 'YYYYMMDDHHmmss' ),
				titleObj = new mw.Title( article.get( 'title' ) ),
				nsId = titleObj.getNamespaceId(),
				offset = parseInt( mw.user.options.get( 'timecorrection' ).split( '|' )[ 1 ] );

			// Setting the number of minutes for which a new article
			// should be left alone (unless there are serious issues)
			article.set( 'new_article_warning_minutes', 60 );

			// Set whether it's a draft, which we'll reference in ext.pageTriage.listItem.underscore
			article.set( 'is_draft', nsId === mw.config.get( 'wgPageTriageDraftNamespaceId' ) );

			article.set(
				'creation_date_pretty',
				creationDateParsed.utcOffset( offset ).format( mw.msg( 'pagetriage-creation-dateformat' ) )
			);

			const now = new Date();

			article.set(
				'article_age_in_minutes',
				Math.ceil( ( now - creationDateParsed ) / ( 1000 * 60 ) )
			);

			article.set(
				'reviewed_updated_pretty',
				reviewedUpdatedParsed.utcOffset( offset ).format( mw.msg( 'pagetriage-creation-dateformat' ) )
			);

			// sometimes user info isn't set, so check that first.
			if ( article.get( 'user_creation_date' ) ) {
				const userCreationDateParsed = moment.utc(
					article.get( 'user_creation_date' ),
					'YYYYMMDDHHmmss'
				);
				article.set(
					'user_creation_date_pretty', userCreationDateParsed.utcOffset( offset ).format( mw.msg( 'pagetriage-info-timestamp-date-format' ) ) );
			} else {
				article.set( 'user_creation_date_pretty', '' );
			}

			// TODO: What if userName doesn't exist?
			if ( article.get( 'user_name' ) ) {
				// decide which byline message to use depending on if the editor is new or not
				// but don't show new editor for ip users
				let bylineMessage;
				if ( article.get( 'user_id' ) > '0' && article.get( 'user_autoconfirmed' ) < '1' ) {
					bylineMessage = 'pagetriage-byline-new-editor';
				} else {
					bylineMessage = 'pagetriage-byline';
				}

				// put it all together in the byline
				// The following messages are used here:
				// * pagetriage-byline-new-editor
				// * pagetriage-byline
				const byline = mw.message(
					bylineMessage,
					this.buildLinkTag(
						article.get( 'creator_user_page_url' ),
						article.get( 'user_name' ),
						article.get( 'creator_user_page_exist' )
					),
					this.buildLinkTag(
						article.get( 'creator_user_talk_page_url' ),
						mw.msg( 'sp-contributions-talk' ),
						article.get( 'creator_user_talk_page_exist' )
					),
					mw.msg( 'pipe-separator' ),
					this.buildLinkTag(
						article.get( 'creator_contribution_page_url' ),
						mw.msg( 'contribslink' ),
						true
					)
				).parse();

				article.set( 'author_byline_html', byline );
				article.set(
					'user_title_url',
					this.buildRedLink(
						article.get( 'creator_user_page_url' ),
						article.get( 'creator_user_page_exist' )
					)
				);
				article.set( 'user_contribs_title', article.get( 'creator_contribution_page' ) );
			}

			// Are there any PageTriage messages on the talk page?
			article.set( 'talkpage_feedback_message', false );
			if ( article.get( 'talkpage_feedback_count' ) > 0 ) {
				const $talkPageLink = $( '<a>' )
					.attr( 'href', article.get( 'talk_page_url' ) )
					.text( mw.msg( 'pagetriage-has-talkpage-feedback-link' ) );
				const talkPageMsg = mw.message( 'pagetriage-has-talkpage-feedback', article.get( 'talkpage_feedback_count' ), $talkPageLink ).parse();
				article.set( 'talkpage_feedback_message', talkPageMsg );
			}

			// Set copyvio info
			if ( article.get( 'copyvio' ) ) {
				// As of 2023, the valid values for this on the CopyPatrol side are: en, es, ar,
				// fr, simple. Splitting the wgServerName ensures that Simple English Wikipedia
				// correctly renders as "simple".
				const wikiLanguageCodeForCopyPatrolURL = mw.config.get( 'wgServerName' ).split( '.' )[ 0 ];

				const link = 'https://copypatrol.toolforge.org/' + wikiLanguageCodeForCopyPatrolURL + '?' +
					$.param( {
						filter: 'all',
						filterPage: titleObj.getMainText(),
						drafts: article.get( 'is_draft' ) ? 1 : 0,
						revision: article.get( 'copyvio' )
					} );
				article.set( 'copyvio_link_url', link );
			} else {
				// Make sure 'copyvio' is defined so it doesn't break in the template
				article.set( 'copyvio', false );
			}

			// set last AfC action date label
			article.set( 'last_afc_action_date_label', '' );
			if ( article.get( 'afc_state' ) === '2' ) {
				article.set( 'last_afc_action_date_label', mw.msg( 'pagetriage-afc-date-label-submission' ) );
			} else if ( article.get( 'afc_state' ) === '3' ) {
				article.set( 'last_afc_action_date_label', mw.msg( 'pagetriage-afc-date-label-review' ) );
			} else if ( article.get( 'afc_state' ) === '4' ) {
				article.set( 'last_afc_action_date_label', mw.msg( 'pagetriage-afc-date-label-declined' ) );
			}

			// set the article status
			// delete status
			if ( article.get( 'afd_status' ) === '1' || article.get( 'blp_prod_status' ) === '1' ||
				article.get( 'csd_status' ) === '1' || article.get( 'prod_status' ) === '1' ) {
				article.set( 'page_status_html', mw.message( 'pagetriage-page-status-delete' ).escaped() );
			// unreviewed status
			} else if ( article.get( 'patrol_status' ) === '0' ) {
				article.set( 'page_status_html', mw.message( 'pagetriage-page-status-unreviewed' ).escaped() );
			// auto-reviewed status
			} else if ( article.get( 'patrol_status' ) === '3' ) {
				article.set( 'page_status_html', mw.message( 'pagetriage-page-status-autoreviewed' ).escaped() );
			// reviewed status
			} else if ( article.get( 'ptrp_last_reviewed_by' ) !== 0 && article.get( 'reviewer' ) ) {
				article.set(
					'page_status_html',
					mw.message(
						'pagetriage-page-status-reviewed',
						moment.utc(
							article.get( 'ptrp_reviewed_updated' ),
							'YYYYMMDDHHmmss'
						).utcOffset( offset ).format(
							mw.msg( 'pagetriage-info-timestamp-date-format' )
						),
						this.buildLinkTag(
							article.get( 'reviewer_user_page_url' ),
							article.get( 'reviewer' ),
							article.get( 'reviewer_user_page_exist' )
						),
						this.buildLinkTag(
							article.get( 'reviewer_user_talk_page_url' ),
							mw.msg( 'sp-contributions-talk' ),
							article.get( 'reviewer_user_talk_page_exist' )
						),
						mw.msg( 'pipe-separator' ),
						this.buildLinkTag(
							article.get( 'reviewer_contribution_page_url' ),
							mw.msg( 'contribslink' ),
							true
						)
					).parse()
				);
			// Rare case where the article is reviewed, but ptrp_last_reviewed_by is not set.
			// Possibly triggered by an article getting flipped to redirect, then reverted?
			} else {
				article.set(
					'page_status_html',
					mw.message( 'pagetriage-page-status-reviewed-anonymous' ).escaped()
				);
			}

			article.set( 'title_url_format', mw.util.wikiUrlencode( article.get( 'title' ) ) );

			const titleUrl = new mw.Uri( mw.util.getUrl( article.get( 'title' ) ) );
			if ( Number( article.get( 'is_redirect' ) ) === 1 ) {
				titleUrl.query.redirect = 'no';
			}
			article.set( 'title_url', titleUrl.toString() );
		},

		tagWarningNotice: function () {
			const articleAge = this.get( 'article_age_in_minutes' );

			if ( articleAge <= this.get( 'new_article_warning_minutes' ) ) {
				// Generate a warning if the page is less than 60 minutes old
				return mw.msg( 'pagetriage-tag-warning-notice', articleAge );
			} else {
				return '';
			}
		},

		buildLinkTag: function ( url, text, exists ) {
			let style = '';
			if ( !exists ) {
				url = this.buildRedLink( url, exists );
				style = 'new';
			}
			return $( '<a>' )
				.attr( {
					href: url,
					class: style
				} )
				.text( text );
		},

		buildRedLink: function ( url, exists ) {
			url = new mw.Uri( url );
			if ( !exists ) {
				url.query.action = 'edit';
				url.query.redlink = 1;
			}
			return url.toString();
		},

		// url and parse are used here for retrieving a single article in the curation toolbar.
		// articles are retrieved for list view using the methods in the Articles collection.
		url: function () {
			return mw.util.wikiScript( 'api' ) + '?' + $.param(
				{
					action: 'pagetriagelist',
					format: 'json',
					// eslint-disable-next-line camelcase
					page_id: this.pageId
				}
			);
		},

		parse: function ( response ) {
			if ( response.pagetriagelist !== undefined && response.pagetriagelist.pages !== undefined ) {
				// data came directly from the api
				// extract the useful bits of json.
				return response.pagetriagelist.pages[ 0 ];
			}
			// already parsed by the collection's parse function.
			return response;
		},

		addHistory: function () {
			this.revisions = new mw.pageTriage.RevisionList( { eventBus: this.eventBus, pageId: this.pageId } );
			this.revisions.fetch();
		}
	} );

	mw.pageTriage.ArticleList = Backbone.Collection.extend( {
		moreToLoad: true,
		model: mw.pageTriage.Article,

		/** Current queue mode: 'npp' or 'afc'. */
		mode: 'npp',

		defaultApiParams: {
			limit: 20,
			nppDir: 'newestfirst',
			afcDir: 'oldestreview',
			dir: 'newestfirst',
			namespace: 0,
			showunreviewed: 1,
			showothers: 1
		},

		apiParams: null,

		initialize: function ( options ) {
			this.eventBus = options.eventBus;
			this.eventBus.bind( 'filterSet', this.setParams );
			this.apiParams = this.defaultApiParams;

			// Pull any saved filter settings from the user's option.
			const filterOptionsJson = mw.user.options.get( 'userjs-NewPagesFeedFilterOptions' );
			if ( mw.user.isAnon() || !filterOptionsJson ) {
				return;
			}
			let filterOptions;
			try {
				filterOptions = JSON.parse( filterOptionsJson );
				filterOptions = this.migrateFilterOptions( filterOptions );
			} catch ( e ) {
				// If we can't parse the options, give up.
				mw.log.warn( 'Unable to parse stored filters: ' + filterOptionsJson );
				return;
			}
			this.setMode( filterOptions.mode );
			this.setParams( filterOptions );
		},

		/**
		 * Migrate saved filter options.
		 *
		 * From version 'undefined' to version 2:
		 * - "other pages" were implicitly shown, now they are controlled by 'showothers'
		 *
		 * @param {Object} filterOptions
		 * @return {Object}
		 */
		migrateFilterOptions: function ( filterOptions ) {
			if ( filterOptions.version === 2 ) {
				delete filterOptions.version;
				return filterOptions;
			}

			filterOptions.showothers = '1';
			return filterOptions;
		},

		/**
		 * Set the ArticleList mode.
		 *
		 * TODO This also sets the namespace that will be queried, which means that it'll be saved as the current
		 * filter namespace and so when NPP is selected the NS dropdown will not remember the previous state. Bad?
		 *
		 * @param {string} newMode Either 'npp' or 'afc'.
		 */
		setMode: function ( newMode ) {
			const draftNsId = mw.config.get( 'wgPageTriageDraftNamespaceId' );
			if ( draftNsId && newMode === 'afc' ) {
				this.mode = 'afc';
				this.setParam( 'namespace', draftNsId );
				this.setParam( 'showreviewed', '1' );
				this.setParam( 'showunreviewed', '1' );
			} else {
				this.mode = 'npp';
				this.setParam( 'namespace', 0 );
			}
			this.setParam( 'dir', this.getParam( this.mode + 'Dir' ) );
		},

		/**
		 * Get the current ArticleList mode.
		 *
		 * @return {string} Either 'npp' or 'afc'.
		 */
		getMode: function () {
			return this.mode;
		},

		url: function () {
			const params = $.extend( {
				action: 'pagetriagelist',
				format: 'json'
			}, this.getApiParams() );

			return mw.util.wikiScript( 'api' ) + '?' + $.param( params );
		},

		getApiParams: function () {
			const params = $.extend( {}, this.apiParams );
			// sorting (dir) is stored as 'nppDir' and 'afcDir' so they remain
			// independent but only one is sent as 'dir' based on the mode
			delete params.nppDir;
			delete params.afcDir;
			// mode is defined in the model but is not an API parameter, so remove it.
			delete params.mode;
			// afc_state is stored in the model as '1', '2', '3', '4', or 'all', but
			// the api parameter should be an integer. Omitting the parameter entirely
			// means there is no filtering, which is what 'all' should do. See T304574
			if ( params.afc_state && params.afc_state === 'all' ) {
				delete params.afc_state;
			}
			return params;
		},

		parse: function ( response ) {
			// See if the fetch returned an extra page. This lets us know if there are more pages
			// to load in a subsequent fetch. We also check to see if the response contains
			// information about pages missing metadata; if that property is set then we assume that
			// there may be more articles to load (T202815).
			this.moreToLoad = false;
			if ( response.pagetriagelist.pages && response.pagetriagelist.pages.length > this.apiParams.limit ) {
				// Remove the extra page from the list
				response.pagetriagelist.pages.pop();
				this.moreToLoad = true;
			}
			if ( response.pagetriagelist &&
				response.pagetriagelist.pages_missing_metadata &&
				response.pagetriagelist.pages_missing_metadata.length
			) {
				mw.log.warn( 'Metadata is missing for some pages.', JSON.stringify( response.pagetriagelist.pages_missing_metadata ) );
				this.moreToLoad = true;
			}

			// extract the useful bits of json.
			return response.pagetriagelist.pages;
		},

		setParams: function ( apiParams ) {
			this.apiParams = apiParams;
		},

		setParam: function ( paramName, paramValue ) {
			this.apiParams[ paramName ] = paramValue;
		},

		/**
		 * Get the JSON string that will be saved as a user preference value to store the current
		 * filter state.
		 *
		 * @return {string}
		 */
		encodeFilterParams: function () {
			const params = this.apiParams;
			params.mode = this.getMode();
			params.version = 2;
			return JSON.stringify( params );
		},

		// Save the filter parameters to a user's option
		saveFilterParams: function () {
			if ( !mw.user.isAnon() ) {
				return new mw.Api().saveOption( 'userjs-NewPagesFeedFilterOptions', this.encodeFilterParams() );
			}
		},

		getParam: function ( key ) {
			return this.apiParams[ key ];
		}

	} );

} );
