// view for display deletion wizard
$( function () {
	var DateWrapper, pageName, specialDeletionTagging;

	// date wrapper that generates a new Date() object
	DateWrapper = function DateWrapper() {
		this.date = new Date();
		this.months = [
			'January', 'February', 'March', 'April', 'May', 'June', 'July',
			'August', 'September', 'October', 'November', 'December'
		];
	};

	// prototype for dateWrapper
	DateWrapper.prototype = {
		getMonth: function () {
			return this.months[ this.date.getUTCMonth() ];
		},
		getDate: function () {
			return this.date.getUTCDate();
		},
		getYear: function () {
			return this.date.getUTCFullYear();
		}
	};

	pageName = mw.config.get( 'wgPageTriagePagePrefixedText' );

	// Deletion taggging
	specialDeletionTagging = {
		afd: {
			buildDiscussionRequest: function ( reason, data ) {
				data.appendtext = '{{subst:afd2|text=' + reason + ' ~~~~|pg=' + pageName + '}}\n';
				data.summary = 'Creating deletion discussion page for [[' + pageName + ']].';
			},

			buildLogRequest: function ( oldText, reason, tagObj, data ) {
				oldText += '\n';
				data.text = oldText.replace(
					/(<!-- Add new entries to the TOP of the following list -->\n+)/,
					'$1{{subst:afd3|pg=' + pageName + '}}\n'
				);
			},

			getLogPageTitle: function ( prefix ) {
				var date = new DateWrapper();
				return prefix + '/Log/' + date.getYear() + ' ' + date.getMonth() + ' ' + date.getDate();
			}
		},

		rfd: {
			buildDiscussionRequest: function () {
				// No-op
			},

			buildLogRequest: function ( oldText, reason, tagObj, data ) {
				data.text = oldText.replace(
					// FIXME: This is pretty fragile (and English Wikipedia specific).
					/(<!-- Add new entries directly below this line\.? -->)/,
					'$1\n{{subst:rfd2|text=' + reason + '|redirect=' + pageName + '}} ~~~~\n'
				);
			},

			getLogPageTitle: function ( prefix ) {
				var date = new DateWrapper();
				return prefix + '/Log/' + date.getYear() + ' ' + date.getMonth() + ' ' + date.getDate();
			}
		},

		ffd: {
			buildDiscussionRequest: function () {
				// No-op
			},

			buildLogRequest: function ( oldText, reason, tagObj, data ) {
				if ( !oldText ) {
					data.text = '{{subst:Ffd log}}';
				} else {
					data.text = '';
				}

				data.text += data.text + '\n{{subst:ffd2|Reason=' + reason + '|1=' + mw.config.get( 'wgTitle' ) + '}} ~~~~';
				data.summary = 'Adding [[' + pageName + ']].';
				data.recreate = true;
			},

			getLogPageTitle: function ( prefix ) {
				var date = new DateWrapper();
				return prefix + '/' + date.getYear() + ' ' + date.getMonth() + ' ' + date.getDate();
			}
		},

		mfd: {
			buildDiscussionRequest: function ( reason, data ) {
				data.appendtext = '{{subst:mfd2|text=' + reason + ' ~~~~|pg=' + pageName + '}}\n';
				data.summary = 'Creating deletion discussion page for [[' + pageName + ']].';
			},

			buildLogRequest: function ( oldText, reason, tagObj, data ) {
				var date = new DateWrapper(),
					dateHeader = '===' + date.getMonth() + ' ' + date.getDate() + ', ' + date.getYear() + '===\n',
					dateHeaderRegex = new RegExp( '(===\\s*' + date.getMonth() + '\\s+' + date.getDate() + ',\\s+' + date.getYear() + '\\s*===)' ),
					newData = '{{subst:mfd3|pg=' + pageName + '}}';

				if ( dateHeaderRegex.test( oldText ) ) { // we have a section already
					data.text = oldText.replace( dateHeaderRegex, '$1\n' + newData );
				} else { // we need to create a new section
					data.text = oldText.replace( '===', dateHeader + newData + '\n\n===' );
				}

				data.summary = 'Adding [[' + tagObj.prefix + '/' + pageName + ']].';
				data.recreate = true;
			},

			getLogPageTitle: function ( prefix ) {
				return prefix;
			}
		}
	};

	mw.pageTriage.DeleteView = mw.pageTriage.ToolView.extend( {
		id: 'mwe-pt-deletion-wizard',
		icon: 'icon_trash.png',
		title: mw.msg( 'pagetriage-del-title' ),
		tooltip: 'pagetriage-del-tooltip',
		template: mw.template.get( 'ext.pageTriage.views.toolbar', 'delete.underscore' ),
		deletionTagsOptions: {},
		selectedTag: {},
		selectedCat: '',

		/**
		 * Initialize data on startup
		 *
		 * @param {Object} options
		 */
		initialize: function ( options ) {
			this.eventBus = options.eventBus;
			this.model.on( 'change', this.setIcon, this );
			this.reset();
		},

		/**
		 * Reset selected deletion tag data
		 */
		reset: function () {
			this.selectedTag = {};
			this.selectedCat = '';
		},

		// overwrite parent function
		setIcon: function ( dir ) {
			if ( typeof ( dir ) !== 'string' ) {
				dir = 'normal';
			}
			if ( dir === 'normal' && this.isPageNominatedForDeletion() ) {
				dir = 'special';
			}
			this.$icon.attr( 'src', this.iconPath( dir ) );
		},

		/**
		 * Set up deletion tags based on namespace, for main namespace, show 'redirects
		 * for discussion' or 'articles for deletion' for xfd depending on whehter the
		 * article is a redirect
		 */
		setupDeletionTags: function () {
			var xfd;

			// user namespace
			if ( mw.config.get( 'wgCanonicalNamespace' ) === 'User' ) {
				this.deletionTagsOptions = $.pageTriageDeletionTagsOptions.User;
				this.deletionTagsOptions.mfd.label = this.deletionTagsOptions.mfd.tags.miscellanyfordeletion.label;
			// default to main namespace
			} else {
				this.deletionTagsOptions = $.pageTriageDeletionTagsOptions.Main;
				xfd = this.deletionTagsOptions.xfd;
				// redirect
				if ( Number( this.model.get( 'is_redirect' ) ) === 1 ) {
					xfd.label = xfd.tags.redirectsfordiscussion.label;
					delete xfd.tags.articlefordeletion;
				// non-redirect
				} else {
					xfd.label = xfd.tags.articlefordeletion.label;
					delete xfd.tags.redirectsfordiscussion;
				}
			}
		},

		/**
		 * Render deletion tagging template
		 */
		render: function () {
			var modules, key,
				that = this;

			this.setupDeletionTags();
			this.$tel.html( this.template( {
				tags: this.deletionTagsOptions,
				warningNotice: this.model.tagWarningNotice()
			} ) );

			// set the Learn More link URL
			modules = mw.config.get( 'wgPageTriageCurationModules' );
			if ( typeof modules.delete.helplink !== 'undefined' ) {
				$( '#mwe-pt-deletion-wizard .mwe-pt-flyout-help-link' )
					.attr( 'href', modules.delete.helplink );
			}

			// add click event for each category
			$( '#mwe-pt-delete-categories' ).find( 'div' ).each( function () {
				var cat = $( $( this ).html() ).attr( 'cat' );
				$( this ).click(
					function () {
						$( this ).find( 'a' ).blur();
						that.displayTags( cat );
						return false;
					}
				).end();
			} );

			// add click event for tag submission
			$( '#mwe-pt-delete-submit-button' ).button( { disabled: true } )
				.click(
					function () {
						$( '#mwe-pt-delete-submit-button' ).button( 'disable' );
						$( '#mwe-pt-delete-submit' ).append( $.createSpinner( 'delete-spinner' ) ); // show spinner
						that.submit();
						return false;
					}
				).end();

			// show the first category as default
			for ( key in this.deletionTagsOptions ) {
				this.displayTags( key );
				break;
			}
		},

		isPageNominatedForDeletion: function () {
			var i,
				deletion = [ 'csd_status', 'prod_status', 'blp_prod_status', 'afd_status' ];

			for ( i = 0; i < deletion.length; i++ ) {
				if ( this.model.get( deletion[ i ] ) === '1' ) {
					return true;
				}
			}

			return false;
		},

		/**
		 * Build deletion tag check/raido and label
		 *
		 * @param {string} key
		 * @param {Object} tagSet
		 * @param {string} elementType
		 * @return {string}
		 */
		buildTagHTML: function ( key, tagSet, elementType ) {
			// build the checkbox or radio
			var checkbox = mw.html.element(
				'input',
				{
					name: 'mwe-pt-delete',
					type: elementType,
					value: tagSet[ key ].tag,
					'class': 'mwe-pt-delete-checkbox',
					id: 'mwe-pt-checkbox-delete-' + key,
					checked: !!this.selectedTag[ key ]
				}
			);
			return '<div class="mwe-pt-delete-row" id="mwe-pt-delete-row-' + key + '">' +
					'<table><tr>' +
					'<td class="mwe-delete-checkbox-cell">' + checkbox + '</td>' +
					'<td><div id="mwe-pt-delete-' + key + '" class="mwe-pt-delete-label">' +
					mw.html.escape( tagSet[ key ].label ) + '</div>' +
					'<div class="mwe-pt-delete-desc">' +
					mw.html.escape( tagSet[ key ].desc ) +
					'</div><div id="mwe-pt-delete-params-link-' + key + '" class="mwe-pt-delete-params-link"></div>' +
					'<div id="mwe-pt-delete-params-form-' + key + '" class="mwe-pt-delete-params-form">' +
					'</div></td>' +
					'</tr></table></div>';
		},

		/**
		 * Display deletion tags for selected category
		 *
		 * @param {string} cat
		 */
		displayTags: function ( cat ) {
			var tagDesc, key, param,
				that = this,
				tagSet = this.deletionTagsOptions[ cat ].tags,
				elementType = this.deletionTagsOptions[ cat ].multiple ? 'checkbox' : 'radio',
				$tagList = $( '<div id="mwe-pt-delete-list"></div>' ),
				tagCount = 0;

			// unselect any previously selected tags and disable submit button
			this.selectedTag = {};
			$( '#mwe-pt-delete-submit-button' ).button( 'disable' );

			if ( this.deletionTagsOptions[ cat ].desc ) {
				tagDesc = '<div id="mwe-pt-delete-category-desc">' + mw.html.escape( this.deletionTagsOptions[ cat ].desc ) + '</div>';
				$tagList.append( tagDesc );
			}

			$( '#mwe-pt-delete' ).empty();
			// highlight the active category
			$( '.mwe-pt-delete-category' ).removeClass( 'mwe-pt-active' );
			$( '#mwe-pt-category-' + cat ).addClass( 'mwe-pt-active' );
			$( '.mwe-pt-delete-category .mwe-pt-category-pokey' ).hide();
			$( '#mwe-pt-category-' + cat + ' .mwe-pt-category-pokey' ).show();
			$( '#mwe-pt-delete' ).append( $tagList );

			for ( key in tagSet ) {

				// Keep a running total of tags in the category
				if ( tagSet.hasOwnProperty( key ) ) {
					tagCount++;
				}

				// Add the HTML for the tag into the list
				$tagList.append( this.buildTagHTML( key, tagSet, elementType ) );

				// insert the add/edit parameter link if the checkbox has been checked
				if ( $( '#mwe-pt-checkbox-delete-' + key ).prop( 'checked' ) ) {
					this.showParamsLink( key );
				}

				// add click events for checking/unchecking tags to both the
				// checkboxes and tag labels
				$( '#mwe-pt-delete-' + key + ', #mwe-pt-checkbox-delete-' + key ).click(
					// eslint-disable-next-line no-loop-func
					function () {
						// Extract the tag key from the id of whatever was clicked on
						var tagKeyMatches = $( this ).attr( 'id' ).match( /.*-delete-(.*)/ ),
							tagKey = tagKeyMatches[ 1 ];

						$( '#mwe-pt-delete-params-form-' + tagKey ).hide();
						if ( !that.selectedTag[ tagKey ] ) {
							$( '#mwe-pt-checkbox-delete-' + tagKey ).prop( 'checked', true );

							// different category from the selected one, refresh data
							if ( that.selectedCat !== cat ) {
								that.multiHideParamsLink( that.selectedTag );
								that.selectedTag = {};
								that.selectedCat = cat;
							// this category doesn't allow multiple selection
							} else if ( !that.deletionTagsOptions[ cat ].multiple ) {
								that.multiHideParamsLink( that.selectedTag );
								that.selectedTag = {};
							}

							that.selectedTag[ tagKey ] = tagSet[ tagKey ];
							that.showParamsLink( tagKey );
							// show the param form if there is required parameter
							for ( param in tagSet[ tagKey ].params ) {
								if ( tagSet[ tagKey ].params[ param ].input === 'required' ) {
									that.showParamsForm( tagKey );
									break;
								}
							}
						} else {
							// deactivate checkbox
							$( '#mwe-pt-checkbox-delete-' + tagKey ).prop( 'checked', false );
							delete that.selectedTag[ tagKey ];

							if ( $.isEmptyObject( that.selectedTag ) ) {
								that.selectedCat = '';
							}

							that.hideParamsLink( tagKey );
							// If the param form is visible, hide it
							that.hideParamsForm( tagKey );
						}
						that.refreshSubmitButton();
					}
				).end();
			}

			// If there is only one tag in the category, go ahead and select it.
			if ( tagCount === 1 ) {
				$( '#mwe-pt-delete .mwe-pt-delete-checkbox' ).click();
			}
		},

		/**
		 * Refresh the submit button
		 */
		refreshSubmitButton: function () {
			if ( this.objectPropCount( this.selectedTag ) > 0 ) {
				$( '#mwe-pt-delete-submit-button' ).button( 'enable' );
			} else {
				$( '#mwe-pt-delete-submit-button' ).button( 'disable' );
			}
		},

		/**
		 * Show 'Add/Edit parameter' link
		 *
		 * @param {string} key
		 */
		showParamsLink: function ( key ) {
			var param, link,
				allParamsHidden = true,
				text = 'add',
				tag = this.selectedTag[ key ],
				that = this;

			// no params, don't show the link
			if ( $.isEmptyObject( tag.params ) ) {
				return;
			}

			// check if there is non-hidden param
			for ( param in tag.params ) {
				if ( tag.params[ param ].type !== 'hidden' ) {
					allParamsHidden = false;
					// see if any of the parameters have been filled out
					if ( tag.params[ param ].value ) {
						text = 'edit';
					}
				}
			}
			// all params are hidden, don't show the link
			if ( allParamsHidden === true ) {
				return;
			}

			// Give grep a chance to find the usages:
			// pagetriage-button-add-details, pagetriage-button-edit-details
			link = mw.html.element(
				'a',
				{
					href: '#',
					id: 'mwe-pt-delete-params-' + key
				},
				mw.msg( 'pagetriage-button-' + text + '-details' )
			);
			$( '#mwe-pt-delete-params-link-' + key ).html( '+&#160;' + link );
			// Add click event to the link that shows the param form
			$( '#mwe-pt-delete-params-' + key ).click( function () {
				that.showParamsForm( key );
			} );
		},

		/**
		 * Hide 'Add/Edit parameter' link
		 *
		 * @param {string} key
		 */
		hideParamsLink: function ( key ) {
			$( '#mwe-pt-delete-params-link-' + key ).empty();
		},

		/**
		 * Hide 'Add/Edit parameter' link for multiple deletion tags
		 *
		 * @param {Object} obj
		 */
		multiHideParamsLink: function ( obj ) {
			var key;
			for ( key in obj ) {
				this.hideParamsLink( key );
			}
		},

		/**
		 * Show the parameters form
		 *
		 * @param {string} key
		 */
		showParamsForm: function ( key ) {
			var param, paramObj,
				that = this,
				html = '',
				tag = this.selectedTag[ key ],
				firstField = '';

			this.hideParamsLink( key );

			for ( param in tag.params ) {
				paramObj = tag.params[ param ];
				html += this.buildHTML( param, paramObj, key );
				// Remember which field is first so we can focus it later
				if ( !firstField && paramObj.type !== 'hidden' ) {
					firstField = 'mwe-pt-delete-params-' + key + '-' + param;
				}
			}

			html += mw.html.element(
				'button',
				{
					id: 'mwe-pt-delete-set-param-' + key,
					'class': 'mwe-pt-delete-set-param-button ui-button-green'
				},
				mw.msg( 'pagetriage-button-add-details' )
			);
			html += mw.html.element(
				'button',
				{
					id: 'mwe-pt-delete-cancel-param-' + key,
					'class': 'ui-button-red'
				},
				mw.msg( 'cancel' )
			);

			html += '<div id="mwe-pt-delete-params-form-error"></div>';

			// Insert the form content into the flyout
			$( '#mwe-pt-delete-params-form-' + key ).html( html );
			$( '#mwe-pt-delete-params-form-' + key ).show();

			// Add click event for the Set Parameters button
			$( '#mwe-pt-delete-set-param-' + key ).button().click(
				function () {
					if ( that.setParams( key ) ) {
						// Hide the form and show the link to reopen it
						that.hideParamsForm( key );
						that.showParamsLink( key );
					}
				}
			);

			// Add click event for the Cancel button
			$( '#mwe-pt-delete-cancel-param-' + key ).button().click(
				function () {
					var param;
					for ( param in tag.params ) {
						if ( tag.params[ param ].input === 'required' && !tag.params[ param ].value ) {
							delete that.selectedTag[ key ];
							$( '#mwe-pt-checkbox-delete-' + key ).prop( 'checked', false );
							break;
						}
					}

					// Hide the form and show the link to reopen it
					that.hideParamsForm( key );
					// Show the link if this tag is still selected
					if ( that.selectedTag[ key ] ) {
						that.showParamsLink( key );
					}
					that.refreshSubmitButton();
				}
			);

			// If there is an input field, focus the cursor on it
			if ( firstField ) {
				$( '#' + firstField ).focus();
			}
		},

		/**
		 * Hide the parameters form
		 *
		 * @param {string} key
		 */
		hideParamsForm: function ( key ) {
			$( '#mwe-pt-delete-params-form-' + key ).hide();
		},

		/**
		 * Set the parameter values
		 *
		 * @param {string} key
		 * @return {boolean}
		 */
		setParams: function ( key ) {
			var param,
				tag = this.selectedTag[ key ];
			for ( param in tag.params ) {
				tag.params[ param ].value = $( '#mwe-pt-delete-params-' + key + '-' + param ).val();
				if ( tag.params[ param ].input === 'required' && !tag.params[ param ].value ) {
					$( '#mwe-pt-delete-params-form-error' ).text( mw.msg( 'pagetriage-tags-param-missing-required', tag.tag ) );
					return false;
				}
			}

			return true;
		},

		/**
		 * Build the parameter for request
		 *
		 * @param {Object} obj
		 * @return {string}
		 */
		buildParams: function ( obj ) {
			var param,
				paramVal = '';
			for ( param in obj.params ) {
				// this param should be skipped and not be added to tag
				if ( obj.params[ param ].skip ) {
					continue;
				}
				if ( obj.params[ param ].value ) {
					// integer parameter
					if ( !isNaN( parseInt( param ) ) ) {
						paramVal += '|' + obj.params[ param ].value;
					} else {
						paramVal += '|' + param + '=' + obj.params[ param ].value;
					}

				}
			}
			return paramVal;
		},

		/**
		 * Submit the selected tags
		 */
		submit: function () {
			var key, param, apiRequest,
				that = this;

			// no tag to submit
			if ( this.objectPropCount( this.selectedTag ) === 0 ) {
				return;
			}

			// check for any missing parameters
			for ( key in this.selectedTag ) {
				for ( param in this.selectedTag[ key ].params ) {
					if (
						this.selectedTag[ key ].params[ param ].input === 'required' &&
						!this.selectedTag[ key ].params[ param ].value
					) {
						this.handleError(
							mw.msg(
								'pagetriage-tags-param-missing-required',
								this.selectedTag[ key ].tag
							) );
						return;
					}
				}
			}

			// check if page is aleady nominated for deletion
			if ( this.isPageNominatedForDeletion() ) {
				this.handleError( mw.msg( 'pagetriage-tag-deletion-error' ) );
				return;
			}

			// Applying deletion tags should automatically mark the page as reviewed
			apiRequest = {
				action: 'pagetriageaction',
				pageid: mw.config.get( 'wgArticleId' ),
				reviewed: '1',
				token: mw.user.tokens.get( 'editToken' ),
				format: 'json',
				skipnotif: '1'
			};
			$.ajax( {
				type: 'post',
				url: mw.util.wikiScript( 'api' ),
				data: apiRequest,
				success: function ( data ) {
					var key, tagObj;

					if ( data.error ) {
						that.handleError( mw.msg( 'pagetriage-mark-as-reviewed-error', data.error.info ) );
					} else {
						// Log certain types of deletion nominations...
						// If the selected tag category doesn't allow multiple selection
						// and the selected tag has a prefix (like Wikipedia:Articles for
						// deletion) update the log page for that deletion type.
						// TODO: Make logging a JS config-based option
						if ( !that.deletionTagsOptions[ that.selectedCat ].multiple ) {
							for ( key in that.selectedTag ) {
								tagObj = that.selectedTag[ key ];
								if ( tagObj.prefix ) {
									that.logPage( tagObj );
									return;
								}
							}
						}
						that.tagPage();
					}
				},
				dataType: 'json'
			} );
		},

		/**
		 * Handle an error occuring after submit
		 *
		 * @param {string} msg The error message to display
		 */
		handleError: function ( msg ) {
			$.removeSpinner( 'delete-spinner' );
			// Re-enable the submit button (in case it is disabled)
			$( '#mwe-pt-delete-submit-button' ).button( 'enable' );
			// Show error message to the user
			// eslint-disable-next-line no-alert
			alert( msg );
		},

		/**
		 * Add deletion tag template to the page
		 */
		tagPage: function () {
			var key,
				text = '',
				tagText = '',
				paramsText = '',
				that = this,
				tempTag = '',
				tagList = [],
				count = this.objectPropCount( this.selectedTag );

			if ( count === 0 ) {
				return;
			}

			// for multiple tags, they must be in db-xxx format, when combining them in
			// db-multiple, remove 'db-' from each individual tags
			for ( key in this.selectedTag ) {
				tempTag = this.selectedTag[ key ].tag;
				if ( count > 1 ) {
					if ( this.selectedTag[ key ].code !== undefined ) {
						tempTag = this.selectedTag[ key ].code;
					} else {
						tempTag = tempTag.replace( /^db-/gi, '' );
					}
				} else {
					// this template must be substituted
					if ( this.selectedTag[ key ].subst ) {
						// check if there is 'subst:' string yet
						if ( tempTag.match( /^subst:/i ) === null ) {
							tempTag = 'subst:' + tempTag;
						}
					}
				}
				if ( tagText ) {
					tagText += '|';
				}
				tagText += tempTag;
				paramsText += this.buildParams( this.selectedTag[ key ] );
				tagList.push( this.selectedTag[ key ].tag.toLowerCase() );
			}

			if ( count === 1 ) {
				text = '{{' + tagText + paramsText + '}}';
			} else {
				text = '{{' + $.pageTriageDeletionTagsMultiple.tag + '|' + tagText + paramsText + '}}';
			}

			$.ajax( {
				type: 'post',
				url: mw.util.wikiScript( 'api' ),
				data: {
					action: 'pagetriagetagging',
					pageid: mw.config.get( 'wgArticleId' ),
					token: mw.user.tokens.get( 'editToken' ),
					format: 'json',
					top: text,
					deletion: 1,
					taglist: tagList.join( '|' )
				},
				success: function ( data ) {
					if ( data.pagetriagetagging && data.pagetriagetagging.result === 'success' ) {
						that.notifyUser( count, key );
					} else {
						if ( data.error && data.error.code && data.error.code === 'pagetriage-tag-deletion-error' ) {
							that.handleError( mw.msg( 'pagetriage-tag-deletion-error' ) );
						} else {
							that.handleError( mw.msg( 'pagetriage-tagging-error' ) );
						}
					}
				},
				dataType: 'json'
			} );
		},

		/**
		 * Notify the user on talk page
		 *
		 * @param {number} count
		 * @param {string} key
		 */
		notifyUser: function ( count, key ) {
			var selected, topicTitleKey, templateName, template, messagePosterPromise,
				topicTitle,
				paramsText = '',
				that = this;

			if ( count === 0 || !this.selectedTag[ key ] ) {
				return;
			}

			// use generic template for multiple deletion tag
			if ( count > 1 ) {
				selected = $.pageTriageDeletionTagsMultiple;
			} else {
				selected = this.selectedTag[ key ];
				paramsText = this.buildParams( this.selectedTag[ key ] );
			}

			topicTitleKey = selected.talkpagenotiftopictitle;
			topicTitle = mw.pageTriage.contentLanguageMessage( topicTitleKey, pageName ).text();

			templateName = selected.talkpagenotiftpl;

			template = '{{subst:' + templateName + '|' + pageName + paramsText + '}}';

			if ( this.model.get( 'user_name' ) ) {
				messagePosterPromise = mw.messagePoster.factory.create(
					new mw.Title(
						this.model.get( 'creator_user_talk_page' )
					)
				);

				messagePosterPromise.then( function ( messagePoster ) {
					return messagePoster.post(
						topicTitle,
						template
					);
				} ).then( function () {
					that.reset();
					window.location.reload( true );
				}, function () {
					that.handleError( mw.msg( 'pagetriage-del-talk-page-notify-error' ) );
				} );
			}
		},

		/**
		 * Get the content of the current log page, then attempt to add this page
		 * to the log in another request
		 *
		 * @param {Object} tagObj
		 */
		logPage: function ( tagObj ) {
			var that = this,
				title = specialDeletionTagging[ tagObj.tag ].getLogPageTitle( tagObj.prefix );

			$.ajax( {
				type: 'post',
				url: mw.util.wikiScript( 'api' ),
				data: {
					action: 'query',
					prop: 'info|revisions',
					intoken: 'edit', // fetch an edit token
					titles: title,
					format: 'json',
					rvprop: 'content'
				},
				success: function ( data ) {
					var i;
					if ( data && data.query && data.query.pages ) {
						for ( i in data.query.pages ) {
							if ( i === '-1' ) {
								that.handleError( mw.msg( 'pagetriage-del-log-page-missing-error' ) );
								return;
							}
							that.addToLog( title, data.query.pages[ i ].revisions[ 0 ][ '*' ], tagObj );
							break;
						}
					} else {
						that.handleError( mw.msg( 'pagetriage-del-log-page-missing-error' ) );
					}
				},
				dataType: 'json'
			} );
		},

		/**
		 * Add a page to the log
		 *
		 * @param {string} title
		 * @param {string} oldText
		 * @param {Object} tagObj
		 */
		addToLog: function ( title, oldText, tagObj ) {
			var that = this,
				data = {
					action: 'edit',
					title: title,
					token: mw.user.tokens.get( 'editToken' ),
					format: 'json'
				};

			specialDeletionTagging[ tagObj.tag ].buildLogRequest(
				oldText,
				tagObj.params[ '1' ].value,
				tagObj,
				data
			);

			if ( data.text === oldText ) {
				that.handleError( mw.msg( 'pagetriage-del-log-page-adding-error' ) );
				return;
			}

			$.ajax( {
				type: 'post',
				url: mw.util.wikiScript( 'api' ),
				data: data,
				success: function ( data ) {
					if ( data.edit && data.edit.result === 'Success' ) {
						if ( tagObj.discussion ) {
							that.discussionPage( tagObj );
						} else {
							that.tagPage();
						}
					} else {
						that.handleError( mw.msg( 'pagetriage-del-log-page-adding-error' ) );
					}
				},
				dataType: 'json'
			} );
		},

		/**
		 * Generate the discussion page
		 *
		 * @param {Object} tagObj
		 */
		discussionPage: function ( tagObj ) {
			var that = this,
				title = tagObj.prefix + '/' + pageName,
				data = {
					action: 'edit',
					title: title,
					token: mw.user.tokens.get( 'editToken' ),
					format: 'json',
					watchlist: 'watch'
				};

			if ( !specialDeletionTagging[ tagObj.tag ] ) {
				return;
			}

			specialDeletionTagging[ tagObj.tag ].buildDiscussionRequest( tagObj.params[ '1' ].value, data );

			$.ajax( {
				type: 'post',
				url: mw.util.wikiScript( 'api' ),
				data: data,
				success: function ( data ) {
					if ( data.error ) {
						that.handleError( mw.msg( 'pagetriage-del-discussion-page-adding-error' ) );
					} else {
						that.tagPage();
					}
				},
				dataType: 'json'
			} );
		},

		/**
		 * Build the HTML for tag parameter
		 *
		 * @param {string} name
		 * @param {Object} obj
		 * @param {string} key
		 * @return {string}
		 */
		buildHTML: function ( name, obj, key ) {
			var html = '';

			switch ( obj.type ) {
				case 'hidden':
					html += mw.html.element(
						'input',
						{
							type: 'hidden',
							value: ( obj.value ) ? obj.value : '',
							id: 'mwe-pt-delete-params-' + key + '-' + name
						}
					);
					break;
				case 'textarea':
					if ( obj.label ) {
						html += '<div class="mwe-pt-delete-params-question">' + obj.label + '</div>';
					}
					html += mw.html.element(
						'textarea',
						{ id: 'mwe-pt-delete-params-' + key + '-' + name },
						obj.value
					);
					html += '<br/>\n';
					break;
				case 'text':
					/* falls through */
				default:
					html += obj.label + ' ';
					html += mw.html.element(
						'input',
						{
							type: 'text',
							value: ( obj.value ) ? obj.value : '',
							id: 'mwe-pt-delete-params-' + key + '-' + name
						}
					);
					html += '<br/>\n';
					break;
			}

			return html;
		}

	} );

} );
