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

		afcStateIdToLabel: function ( stateId ) {
			return ( {
				1: 'pagetriage-afc-state-unsubmitted',
				2: 'pagetriage-afc-state-pending',
				3: 'pagetriage-afc-state-reviewing',
				4: 'pagetriage-afc-state-declined'
			} )[ stateId ];
		},

		formatMetadata: function ( article ) {
			// jscs: disable requireCamelCaseOrUpperCaseIdentifiers
			var bylineMessage, userCreationDateParsed, byline, titleUrl,
				creationDateParsed = Date.parseExact( article.get( 'creation_date' ), 'yyyyMMddHHmmss' ),
				reviewedUpdatedParsed = Date.parseExact( article.get( 'ptrp_reviewed_updated' ), 'yyyyMMddHHmmss' ),
				titleObj = new mw.Title( article.get( 'title' ) ),
				nsId = titleObj.getNamespaceId();

			// Set whether it's a draft, which we'll reference in ext.pageTriage.listItem.underscore
			article.set( 'is_draft', nsId === mw.config.get( 'wgPageTriageDraftNamespaceId' ) );

			article.set(
				'creation_date_pretty',
				creationDateParsed.toString( mw.msg( 'pagetriage-creation-dateformat' ) )
			);

			article.set(
				'reviewed_updated_pretty',
				reviewedUpdatedParsed.toString( mw.msg( 'pagetriage-creation-dateformat' ) )
			);

			// sometimes user info isn't set, so check that first.
			if ( article.get( 'user_creation_date' ) ) {
				userCreationDateParsed = Date.parseExact(
					article.get( 'user_creation_date' ),
					'yyyyMMddHHmmss'
				);
				article.set(
					'user_creation_date_pretty', userCreationDateParsed.toString( mw.msg( 'pagetriage-info-timestamp-date-format' ) ) );
			} else {
				article.set( 'user_creation_date_pretty', '' );
			}

			// TODO: What if userName doesn't exist?
			if ( article.get( 'user_name' ) ) {
				// decide which byline message to use depending on if the editor is new or not
				// but don't show new editor for ip users
				if ( article.get( 'user_id' ) > '0' && article.get( 'user_autoconfirmed' ) < '1' ) {
					bylineMessage = 'pagetriage-byline-new-editor';
				} else {
					bylineMessage = 'pagetriage-byline';
				}

				// put it all together in the byline
				byline = mw.msg(
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
				);

				article.set( 'author_byline', byline );
				article.set(
					'user_title_url',
					this.buildRedLink(
						article.get( 'creator_user_page_url' ),
						article.get( 'creator_user_page_exist' )
					)
				);
				article.set(
					'user_talk_title_url',
					this.buildRedLink(
						article.get( 'creator_user_talk_page_url' ),
						article.get( 'creator_user_talk_page_exist' )
					)
				);
				article.set( 'user_contribs_title', article.get( 'creator_contribution_page' ) );
			}

			// Set the afc_state_value based on the ID.
			article.set( 'afc_state_value', '' );
			if ( parseInt( article.get( 'afc_state' ) ) > 0 ) {
				article.set( 'afc_state_value', mw.msg( this.afcStateIdToLabel( article.get( 'afc_state' ) ) ) );
			}

			// Set copyvio info
			if ( article.get( 'copyvio' ) ) {
				article.set(
					'copyvio_link_url',
					'https://tools.wmflabs.org/copypatrol/en?' + $.param( {
						filter: 'all',
						searchCriteria: 'page_exact',
						searchText: titleObj.getMainText(),
						drafts: article.get( 'is_draft' ) ? 1 : 0
					} )
				);
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
				article.set( 'page_status', mw.msg( 'pagetriage-page-status-delete' ) );
			// unreviewed status
			} else if ( article.get( 'patrol_status' ) === '0' ) {
				article.set( 'page_status', mw.msg( 'pagetriage-page-status-unreviewed' ) );
			// auto-reviewed status
			} else if ( article.get( 'patrol_status' ) === '3' ) {
				article.set( 'page_status', mw.msg( 'pagetriage-page-status-autoreviewed' ) );
			// reviewed status
			} else {
				if ( article.get( 'ptrp_last_reviewed_by' ) !== 0 && article.get( 'reviewer' ) ) {
					article.set(
						'page_status',
						mw.msg(
							'pagetriage-page-status-reviewed',
							Date.parseExact(
								article.get( 'ptrp_reviewed_updated' ),
								'yyyyMMddHHmmss'
							).toString(
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
						)
					);
				} else {
					article.set( 'page_status', mw.msg( 'pagetriage-page-status-reviewed-anonymous' ) );
				}
			}

			article.set( 'title_url_format', mw.util.wikiUrlencode( article.get( 'title' ) ) );

			titleUrl = mw.util.getUrl( article.get( 'title' ) );
			if ( Number( article.get( 'is_redirect' ) ) === 1 ) {
				titleUrl = this.buildLink( titleUrl, 'redirect=no' );
			}
			article.set( 'title_url', titleUrl );
			// jscs: enable requireCamelCaseOrUpperCaseIdentifiers
		},

		tagWarningNotice: function () {
			var now, begin, diff,
				dateStr = this.get( 'creation_date_utc' );
			if ( !dateStr ) {
				return '';
			}

			now = new Date();
			now = new Date(
				now.getUTCFullYear(),
				now.getUTCMonth(),
				now.getUTCDate(),
				now.getUTCHours(),
				now.getUTCMinutes(),
				now.getUTCSeconds()
			);

			begin = Date.parseExact( dateStr, 'yyyyMMddHHmmss' );
			diff = Math.round( ( now.getTime() - begin.getTime() ) / ( 1000 * 60 ) );

			// only generate a warning if the page is less than 30 minutes old
			if ( diff < 30 ) {
				if ( diff < 1 ) {
					diff = 1;
				}
				return mw.msg( 'pagetriage-tag-warning-notice', diff );
			} else {
				return '';
			}
		},

		buildLinkTag: function ( url, text, exists ) {
			var style = '';
			if ( !exists ) {
				url = this.buildRedLink( url, exists );
				style = 'new';
			}
			return mw.html.element(
				'a',
				{
					href: url,
					'class': style
				},
				text
			);
		},

		buildRedLink: function ( url, exists ) {
			if ( !exists ) {
				url = this.buildLink( url, 'action=edit&redlink=1' );
			}
			return url;
		},

		buildLink: function ( url, param ) {
			var mark;
			if ( param ) {
				mark = ( url.indexOf( '?' ) === -1 ) ? '?' : '&';
				url += mark + param;
			}
			return url;
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
			} else {
				// already parsed by the collection's parse function.
				return response;
			}
		},

		addHistory: function () {
			this.revisions = new mw.pageTriage.RevisionList( { eventBus: this.eventBus, pageId: this.pageId } );
			this.revisions.fetch();
		}
	} );

	mw.pageTriage.ArticleList = Backbone.Collection.extend( {
		moreToLoad: true,
		model: mw.pageTriage.Article,
		optionsToken: '',

		/** Current queue mode: 'npp' or 'afc'. */
		mode: 'npp',

		apiParams: {
			limit: 20,
			nppDir: 'newestfirst',
			afcDir: 'oldestreview',
			dir: 'newestfirst',
			namespace: 0,
			showreviewed: 1,
			showunreviewed: 1,
			showdeleted: 1
		},

		initialize: function ( options ) {
			var filterOptionsJson, filterOptions;
			this.eventBus = options.eventBus;
			this.eventBus.bind( 'filterSet', this.setParams );

			// Pull any saved filter settings from the user's option.
			filterOptionsJson = mw.user.options.get( 'userjs-NewPagesFeedFilterOptions' );
			if ( !mw.user.isAnon() && filterOptionsJson ) {
				try {
					filterOptions = JSON.parse( filterOptionsJson );
				} catch ( e ) {
					// If we can't parse the options, give up.
					mw.log.warn( 'Unable to parse stored filters: ' + filterOptionsJson );
					return;
				}
				this.setMode( filterOptions.mode );
				// Mode is the only one that's not an API parameter.
				delete filterOptions.mode;
				this.setParams( filterOptions );
			}
		},

		/**
		 * Set the ArticleList mode.
		 * @TODO This also sets the namespace that will be queried, which means that it'll be saved as the current
		 * filter namespace and so when NPP is selected the NS dropdown will not remember the previous state. Bad?
		 * @param {string} newMode Either 'npp' or 'afc'.
		 */
		setMode: function ( newMode ) {
			var draftNsId = mw.config.get( 'wgPageTriageDraftNamespaceId' );
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
		 * @return {string} Either 'npp' or 'afc'.
		 */
		getMode: function () {
			return this.mode;
		},

		url: function () {
			var params = $.extend( {
				action: 'pagetriagelist',
				format: 'json'
			}, this.apiParams );
			// sorting (dir) is stored as 'nppDir' and 'afcDir' so they remain
			// independent but only one is sent as 'dir' based on the mode
			delete params.nppDir;
			delete params.afcDir;
			// mode is defined in the model but is not an API parameter, so remove it.
			delete params.mode;
			return mw.util.wikiScript( 'api' ) + '?' + $.param( params );
		},

		parse: function ( response ) {
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
			var params;
			params = this.apiParams;
			params.mode = this.getMode();
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
