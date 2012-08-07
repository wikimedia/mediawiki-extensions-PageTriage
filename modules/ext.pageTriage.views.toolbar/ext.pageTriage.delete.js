// view for display deletion wizard
$( function() {

	// date wrapper that generates a new Date() object
	var dateWrapper = function dateWrapper() {
		this.date = new Date();
		this.months = [
			'January', 'February', 'March', 'April', 'May', 'June', 'July',
			'August', 'September', 'October', 'November', 'December'
		];
	}

	// prototype for dateWrapper
	dateWrapper.prototype = {
		getMonth: function() {
			return this.months[this.date.getUTCMonth()];
		},

		getDate: function() {
			return this.date.getUTCDate();
		},

		getYear: function() {
			return this.date.getUTCFullYear();
		}
	};

	// Deletion taggging
	var specialDeletionTagging = {
		afd: {
			buildDiscussionRequest: function( reason, data ) {
				data.text = "{{subst:afd2|text=" + reason + " ~~~~|pg=" + mw.config.get('wgPageName') + "}}\n";
				data.summary = "Creating deletion discussion page for [[" + mw.config.get('wgPageName') + "]].";
				data.createonly = true;
			},

			buildLogRequest: function( oldText, reason, tagObj, data) {
				oldText += "\n";
				data.text = oldText.replace( /(<\!-- Add new entries to the TOP of the following list -->\n+)/, "$1{{subst:afd3|pg=" + mw.config.get('wgPageName') + "}}\n");
			},

			getLogPageTitle: function( prefix ) {
				var date = new dateWrapper();
				return prefix + '/Log/' + date.getYear() + ' ' + date.getMonth() + ' ' + date.getDate();
			}
		},

		rfd: {
			buildDiscussionRequest: function( reason, data ) {

			},

			buildLogRequest: function( oldText, reason, tagObj, data) {
				data.text = oldText.replace( /(<\!-- Add new entries directly below this line -->)/, "$1\n{{subst:rfd2|text=" + reason + "|redirect="+ mw.config.get('wgPageName') + "}} ~~~~\n" );
			},

			getLogPageTitle: function( prefix ) {
				var date = new dateWrapper();
				return prefix + '/Log/' + date.getYear() + ' ' + date.getMonth() + ' ' + date.getDate();
			}
		},

		ffd: {
			buildDiscussionRequest: function( reason, data ) {

			},

			buildLogRequest: function( oldText, reason, tagObj, data) {
				if ( !oldText ) {
					data.text = "{{subst:Ffd log}}";
				} else {
					data.text = '';
				}

				data.text += data.text + "\n{{subst:ffd2|Reason=" + reason + "|1=" + mw.config.get('wgTitle') + "}} ~~~~";
				data.summary = "Adding [[" + mw.config.get('wgPageName') + "]].";
				data.recreate = true;
			},

			getLogPageTitle: function( prefix ) {
				var date = new dateWrapper();
				return prefix + '/' + date.getYear() + ' ' + date.getMonth() + ' ' + date.getDate();
			}
		},

		mfd: {
			buildDiscussionRequest: function( reason, data ) {
				data.text = "{{subst:mfd2|text=" + reason + " ~~~~|pg=" + mw.config.get('wgPageName') + "}}\n";
				data.summary = "Creating deletion discussion page for [[" + mw.config.get('wgPageName') + "]].";
			},

			buildLogRequest: function( oldText, reason, tagObj, data) {
				var date = new dateWrapper();
				var dateHeader = "===" + date.getMonth() + ' ' + date.getDate() + ', ' + date.getUTCFullYear() + "===\n";
				var dateHeaderRegex = new RegExp( "(===\\s*" + month[date.getUTCMonth()] + '\\s+' + date.getDate() + ',\\s+' + date.getUTCFullYear() + "\\s*===)" );
				var newData = "{{subst:mfd3|pg=" + mw.config.get('wgPageName') + "}}";

				if( dateHeaderRegex.test( oldText ) ) { // we have a section already
					data.text = oldText.replace( dateHeaderRegex, "$1\n" + newData );
				} else { // we need to create a new section
					data.text = oldText.replace("===", dateHeader + newData + "\n\n===");
				}

				data.summary = "Adding [[" + tagObj.prefix + '/' + mw.config.get('wgPageName') + "]].";
				data.recreate = true;
			},

			getLogPageTitle: function( prefix ) {
				return prefix;
			}
		}
	};

	mw.pageTriage.DeleteView = mw.pageTriage.ToolView.extend( {
		id: 'mwe-pt-deletion-wizard',
		icon: 'icon_trash.png',
		title: 'Mark for Deletion',
		template: mw.pageTriage.viewUtil.template( { 'view': 'toolbar', 'template': 'delete.html' } ),
		deletionTagsOptions: {},
		selectedTag: {},
		selectedCat: '',

		/**
		 * Initialize data on startup
		 */
		initialize: function( options ) {
			this.eventBus = options.eventBus;
			this.reset();
		},

		/**
		 * Reset selected deletion tag data
		 */
		reset: function() {
			this.selectedTag = {};
			this.selectedCat = '';
		},

		/**
		 * Set up deletion tags based on namespace, for main namespace, show 'redirects
		 * for discussion' or 'articles for deletion' for xfd depending on whehter the
		 * article is a redirect
		 */
		setupDeletionTags: function() {
			// user namespace
			if ( wgCanonicalNamespace === 'User' ) {
				this.deletionTagsOptions = $.pageTriageDeletionTagsOptions['User'];
				this.deletionTagsOptions.mfd.label = this.deletionTagsOptions.mfd.tags.miscellanyfordeletion.label;
			// default to main namespace
			} else {
				this.deletionTagsOptions = $.pageTriageDeletionTagsOptions['Main'];
				var xfd = this.deletionTagsOptions.xfd;
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
		render: function() {
			var _this = this;

			this.setupDeletionTags();
			this.$tel.html( this.template( { 'tags': this.deletionTagsOptions } ) );

			// add click event for each category
			$( '#mwe-pt-delete-categories' ).find( 'div' ).each( function( index, value ) {
				var cat = $( $( this ).html() ).attr( 'cat' );
				$( this ).click(
					function() {
						$( this ).find( 'a' ).blur();
						_this.displayTags( cat );
						return false;
					}
				).end();
			} );

			// add click event for tag submission
			$( '#mwe-pt-delete-submit-button' ).button( { disabled: true } )
				.click(
					function () {
						_this.submit();
						return false;
					}
				).end();

			// show the first category as default
			for ( var key in this.deletionTagsOptions ) {
				this.displayTags( key );
				break;
			}
		},

		/**
		 * Build deletion tag check/raido and label
		 */
		buildTagHTML: function( key, tagSet, elementType ) {
			// build the checkbox or radio
			var checkbox = mw.html.element(
				'input',
				{
					'name': 'mwe-pt-delete',
					'type': elementType,
					'value': tagSet[key].tag,
					'class': 'mwe-pt-delete-checkbox',
					'id': 'mwe-pt-checkbox-delete-' + key,
					'checked': this.selectedTag[key] ? true : false
				}
			);
			return '<div class="mwe-pt-delete-row" id="mwe-pt-delete-row-' + key + '">' +
					'<table><tr>' +
					'<td class="mwe-delete-checkbox-cell">' + checkbox + '</td>' +
					'<td><div id="mwe-pt-delete-' + key + '" class="mwe-pt-delete-label">' +
					mw.html.escape( tagSet[key].label ) + '</div>' +
					'<div class="mwe-pt-delete-desc">' +
					mw.html.escape( tagSet[key].desc ) +
					'</div><div id="mwe-pt-delete-params-link-' + key + '" class="mwe-pt-delete-params-link"></div>' +
					'<div id="mwe-pt-delete-params-form-' + key + '" class="mwe-pt-delete-params-form">' +
					'</div></td>' +
					'</tr></table></div>';
		},

		/**
		 * Display deletion tags for selected category
		 */
		displayTags: function( cat ) {
			var _this = this, tagRow = '', tagSet = this.deletionTagsOptions[cat].tags;
			var elementType = this.deletionTagsOptions[cat].multiple ? 'checkbox' : 'radio';
			var $tagList = $( '<div id="mwe-pt-delete-list"></div>' );

			$( '#mwe-pt-delete' ).empty();
			// highlight the active category
			$( '.mwe-pt-delete-category' ).removeClass( 'mwe-pt-active' );
			$( '#mwe-pt-category-' + cat ).addClass( 'mwe-pt-active' );
			$( '.mwe-pt-delete-category .mwe-pt-category-pokey' ).hide();
			$( '#mwe-pt-category-' + cat + ' .mwe-pt-category-pokey' ).show();
			$( '#mwe-pt-delete' ).append( $tagList );

			for ( var key in tagSet ) {
				$tagList.append( this.buildTagHTML( key, tagSet, elementType ) );

				// insert the add/edit parameter link if the checkbox has been checked
				if ( $( '#mwe-pt-checkbox-delete-' + key ).prop( 'checked' ) ) {
					this.showParamsLink( key );
				}

				// add click events for checking/unchecking tags to both the
				// checkboxes and tag labels
				$( '#mwe-pt-delete-' + key + ', #mwe-pt-checkbox-delete-' + key ).click(
					function() {
						// Extract the tag key from the id of whatever was clicked on
						var tagKeyMatches = $( this ).attr( 'id' ).match( /.*-delete-(.*)/ );
						var tagKey = tagKeyMatches[1];

						$( '#mwe-pt-delete-params-form-' + tagKey ).hide();
						if ( !_this.selectedTag[tagKey] ) {
							$( '#mwe-pt-checkbox-delete-' + tagKey ).attr( 'checked', true );

							// different category from the selected one, refresh data
							if ( _this.selectedCat != cat ) {
								_this.multiHideParamsLink( _this.selectedTag );
								_this.selectedTag = {};
								_this.selectedCat = cat;
							// this category doesn't allow multiple selection
							} else if ( !_this.deletionTagsOptions[cat].multiple ) {
								_this.multiHideParamsLink( _this.selectedTag );
								_this.selectedTag = {};
							}

							_this.selectedTag[tagKey] = tagSet[tagKey];
							_this.showParamsLink( tagKey );
							// show the param form if there is required parameter
							for ( var param in tagSet[tagKey]['params'] ) {
								if ( tagSet[tagKey]['params'][param].input === 'required' ) {
									_this.showParamsForm( tagKey );
									break;
								}
							}
						} else {
							// deactivate checkbox
							$( '#mwe-pt-checkbox-delete-' + tagKey ).attr( 'checked', false );
							delete _this.selectedTag[tagKey];

							if ( !Object.keys( _this.selectedTag ).length ) {
								_this.selectedCat = '';
							}

							_this.hideParamsLink( tagKey );
							// If the param form is visible, hide it
							_this.hideParamsForm( tagKey );
						}
						_this.refreshSubmitButton();
					}
				).end();
			}
		},

		/**
		 * Refresh the submit button it has the latest number
		 */
		refreshSubmitButton: function() {
			var tagCount = Object.keys( this.selectedTag ).length;
			if ( tagCount ) {
				$( '#mwe-pt-delete-submit-button' ).button( 'enable' );
			} else {
				$( '#mwe-pt-delete-submit-button' ).button( 'disable' );
			}

			$( '#mwe-pt-delete-submit-button .ui-button-text' ).text( mw.msg( 'pagetriage-button-add-tag-number', tagCount ) );
		},

		/**
		 * Show 'Add/Edit parameter' link
		 */
		showParamsLink: function( key ) {
			var allParamsHidden = true, text = 'add', tag = this.selectedTag[key];

			// no params, don't show the link
			if ( !Object.keys( tag.params ).length ) {
				return;
			}

			// check if there is non-hidden param
			for ( var param in tag.params ) {
				if ( tag.params[param].type !== 'hidden' ) {
					allParamsHidden = false;
					// see if any of the parameters have been filled out
					if ( tag.params[param].value ) {
						text = 'edit';
					}
				}
			}
			// all params are hidden, don't show the link
			if ( allParamsHidden === true ) {
				return;
			}

			var _this = this;
			var link = mw.html.element(
						'a',
						{ 'href': '#', 'id': 'mwe-pt-delete-params-' + key },
						mw.msg( 'pagetriage-button-' + text + '-details' )
					);
			$( '#mwe-pt-delete-params-link-' + key ).html( '+&#160;' + link );
			// Add click event to the link that shows the param form
			$( '#mwe-pt-delete-params-' + key ).click( function() {
				_this.showParamsForm( key );
			} );
		},

		/**
		 * Hide 'Add/Edit parameter' link
		 */
		hideParamsLink: function( key ) {
			$( '#mwe-pt-delete-params-link-' + key ).empty();
		},

		/**
		 * Hide 'Add/Edit parameter' link for multiple deletion tags
		 */
		multiHideParamsLink: function ( obj ) {
			for ( var key in obj ) {
				this.hideParamsLink( key );
			}
		},

		/**
		 * Show the parameters form
		 */
		showParamsForm: function( key ) {
			var _this = this, html = '', tag = this.selectedTag[key];

			this.hideParamsLink( key );

			for ( var param in tag.params ) {
				var paramObj = tag.params[param];
				html += this.buildHTML( param, paramObj, key );
			}

			html += mw.html.element(
						'button',
						{ 'id': 'mwe-pt-delete-set-param-' + key, 'class': 'mwe-pt-delete-set-param-button ui-button-green' },
						mw.msg( 'pagetriage-button-add-details' )
					);
			html += mw.html.element(
						'button',
						{ 'id': 'mwe-pt-delete-cancel-param-' + key, 'class': 'ui-button-red' },
						mw.msg( 'cancel' )
					);

			html += '<div id="mwe-pt-delete-params-form-error"></div>';

			// Insert the form content into the flyout
			$( '#mwe-pt-delete-params-form-' + key ).html( html );
			$( '#mwe-pt-delete-params-form-' + key ).show();

			// Add click event for the Set Parameters button
			$( '#mwe-pt-delete-set-param-' + key ).button().click(
				function() {
					if ( _this.setParams( key ) ) {
						// Hide the form and show the link to reopen it
						_this.hideParamsForm( key );
						_this.showParamsLink( key );
					}
				}
			);

			// Add click event for the Cancel button
			$( '#mwe-pt-delete-cancel-param-' + key ).button().click(
				function() {
					var missingRequiredParam = false;
					for ( var param in tag.params ) {
						if ( tag.params[param].input === 'required' && !tag.params[param].value ) {
							delete _this.selectedTag[key];
							$( '#mwe-pt-checkbox-delete-' + key ).prop( 'checked', false );
							break;
						}
					}

					// Hide the form and show the link to reopen it
					_this.hideParamsForm( key );
					// Show the link if this tag is still selected
					if ( _this.selectedTag[key] ) {
						_this.showParamsLink( key );
					}
					_this.refreshSubmitButton();
				}
			);
		},

		/**
		 * Hide the parameters form
		 */
		hideParamsForm: function( key ) {
			$( '#mwe-pt-delete-params-form-' + key ).hide();
		},

		/**
		 * Set the parameter values
		 */
		setParams: function( key ) {
			var tag = this.selectedTag[key];
			for ( var param in tag.params ) {
				tag.params[param].value = $( '#mwe-pt-delete-params-' + key + '-' + param ).attr( 'value' );
				if ( tag.params[param].input === 'required' && !tag.params[param].value ) {
					$( '#mwe-pt-delete-params-form-error' ).text( mw.msg( 'pagetriage-tags-param-missing-required', param ) );
					return false;
				}
			}

			return true;
		},

		/**
		 * Build the parameter for request
		 */
		buildParams: function( obj ) {
			var paramVal = '';
			for ( var param in obj.params ) {
				if ( obj.params[param].value ) {
					paramVal += '|' + param + '=' + obj.params[param].value;
				}
			}
			return paramVal;
		},

		/**
		 * Submit the selected tags
		 */
		submit: function() {
			// check if the selected category allow multiple selection
			if ( !this.deletionTagsOptions[this.selectedCat].multiple ) {
				for ( var key in this.selectedTag ) {
					var tagObj = this.selectedTag[key];
					// check if the selected tag has a prefix like Wikipedia:Articles for deletion
					if ( tagObj.prefix ) {
						this.logPage( tagObj );
						return;
					}
				}
			}

			this.tagPage();
		},

		/**
		 * Add deletion tag template to the page
		 */
		tagPage: function( ) {
			var text = '', tagText = '', paramsText = '', _this = this, tempTag = '',
			count = Object.keys( this.selectedTag ).length;

			if ( count == 0 ) {
				return;
			}

			// for multiple tags, they must be in db-xxx format, when combining them in
			// db-multiple, remove 'db-' from each individual tags
			for ( var key in this.selectedTag ) {
				tempTag = this.selectedTag[key].tag;
				if ( count > 1 ) {
					tempTag = tempTag.replace( /^db-/gi, '' );
				}
				if ( tagText ) {
					tagText += '|';
				}
				tagText += tempTag;
				paramsText += this.buildParams( this.selectedTag[key] );
			}

			if ( count == 1 ) {
				text = '{{' + tagText + paramsText + '}}';
			} else {
				text = '{{' + $.pageTriageDeletionTagsMultiple.tag + '|' + tagText + paramsText + '}}';
			}

			$.ajax( {
				type: 'post',
				url: mw.util.wikiScript( 'api' ),
				data: {
					'action': 'pagetriagetagging',
					'pageid': mw.config.get( 'wgArticleId' ),
					'token': mw.user.tokens.get('editToken'),
					'format': 'json',
					'top': text
				},
				success: function( data ) {
					if ( data.pagetriagetagging.result === 'success' ) {
						_this.notifyUser( count, key );
					}
				},
				dataType: 'json'
			} );
		},

		/**
		 * Notify the user on talk page
		 */
		notifyUser: function( count, key ) {
			var _this = this;

			if ( count == 0 || !this.selectedTag[key] ) {
				return;
			}

			// use generic template for multiple deletion tag
			var template = ( count > 1 ) ? $.pageTriageDeletionTagsMultiple.talkpagenotiftpl : this.selectedTag[key].talkpagenotiftpl;
			template = '{{subst:' + template + '|' + mw.config.get( 'wgPageName' ) + '}}';

			if ( this.model.get( 'user_name' ) ) {
				var title = new mw.Title( this.model.get( 'user_name' ), mw.config.get( 'wgNamespaceIds' )['user_talk'] );

				$.ajax( {
					type: 'post',
					url: mw.util.wikiScript( 'api' ),
					data: {
						'action': 'edit',
						'title': title.getPrefixedText(),
						'appendtext': "\n" + template,
						'token': mw.user.tokens.get('editToken'),
						'format': 'json'
					},
					success: function( data ) {
						if ( data.edit && data.edit.result === 'Success' ) {
							_this.reset();
							window.location.reload( true );
						} else {
							alert( mw.msg( 'pagetriage-del-talk-page-notify-error' ) );
						}
					},
					dataType: 'json'
				} );
			}
		},

		/**
		 * Get the content of the current log page, then attempt to add this page
		 * to the log in another request
		 */
		logPage: function( tagObj ) {
			var _this = this, title = specialDeletionTagging[tagObj.tag].getLogPageTitle( tagObj.prefix );

			$.ajax( {
				type: 'post',
				url: mw.util.wikiScript( 'api' ),
				data: {
					'action': 'query',
					'prop': 'info|revisions',
					'intoken': 'edit',  // fetch an edit token
					'titles': title,
					'format': 'json',
					'rvprop': 'content'
				},
				success: function( data ) {
					if ( data && data.query && data.query.pages ) {
						for ( var i in data.query.pages ) {
							if ( i == '-1' ) {
								alert( mw.msg( 'pagetriage-del-log-page-missing-error' ) );
								return;
							}
							_this.addToLog( title, data.query.pages[i].revisions[0]['*'], tagObj );
							break;
						}
					} else {
						alert( mw.msg( 'pagetriage-del-log-page-missing-error' ) );
					}
				},
				dataType: 'json'
			} );
		},

		/**
		 * Add a page to the log
		 */
		addToLog: function( title, oldText, tagObj ) {
			var _this = this;
			var data = {
				'action': 'edit',
				'title': title,
				'token': mw.user.tokens.get('editToken'),
				'format': 'json'
			};

			specialDeletionTagging[tagObj.tag].buildLogRequest( oldText, tagObj.params['1'].value, tagObj, data );

			if( data.text === oldText ) {
				alert( mw.msg( 'pagetriage-del-log-page-adding-error' ) );
				return;
			}

			$.ajax( {
				type: 'post',
				url: mw.util.wikiScript( 'api' ),
				data: data,
				success: function( data ) {
					if ( data.edit && data.edit.result === 'Success' ) {
						if ( tagObj.discussion ) {
							_this.discussionPage( tagObj );
						} else {
							_this.tagPage();
						}
					}
				},
				dataType: 'json'
			} );
		},

		/**
		 * Generate the discussion page
		 */
		discussionPage: function( tagObj ) {
			var _this = this, title = tagObj.prefix + '/' + mw.config.get('wgPageName');
			var data = {
				'action': 'edit',
				'title': title,
				'token': mw.user.tokens.get('editToken'),
				'format': 'json'
			};

			if ( !specialDeletionTagging[tagObj.tag] ) {
				return;
			}

			specialDeletionTagging[tagObj.tag].buildDiscussionRequest( title, tagObj.params['1'].value, data  );

			$.ajax( {
				type: 'post',
				url: mw.util.wikiScript( 'api' ),
				data: data,
				success: function( data ) {
					_this.tagPage();
				},
				dataType: 'json'
			} );
		},

		/**
		 * Build the HTML for tag parameter
		 */
		buildHTML: function( name, obj, key ) {
			var html = '';

			switch ( obj.type ) {
				case 'hidden':
					html += mw.html.element(
							'input',
							{
								'type': 'hidden',
								'value': ( obj.value ) ? obj.value : '',
								'id': 'mwe-pt-delete-params-' + key + '-' + name
							}
						);
					break;
				case 'textarea':
					if ( obj.label ) {
						html += obj.label + ' ';
					}
					html += mw.html.element(
							'textarea',
							{ 'id': 'mwe-pt-delete-params-' + key + '-' + name },
							obj.value
						);
					html += "<br/>\n";
					break;
				case 'select':
					html += obj.label + ' ';
					for ( var i in obj.option ) {
						html += obj.option[i] + ' ' +
							mw.html.element(
								'input',
								{
									'type': 'radio',
									'value': i, 'id': 'mwe-pt-delete-params-' + key + '-' + name,
									'checked': ( i === obj.value ) ? true : false,
									'name': 'mwe-pt-delete-params-' + key + '-' + name
								}
						);
					}
					html += "<br/>\n";
					break;

				case 'text':
				default:
					html += obj.label + ' ';
					html += mw.html.element(
							'input',
							{
								'type': 'text',
								'value': ( obj.value ) ? obj.value : '',
								'id': 'mwe-pt-delete-params-' + key + '-' + name
							}
						);
					html += "<br/>\n";
					break;
			}

			return html;
		}

	} );

} );
