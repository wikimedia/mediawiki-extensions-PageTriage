// view for displaying tags
$( function() {
	mw.pageTriage.TagsView = mw.pageTriage.ToolView.extend( {
		id: 'mwe-pt-tag',
		icon: 'icon_tag.png',
		title: gM( 'pagetriage-tags-title' ),
		tooltip: 'pagetriage-tags-tooltip',
		template: mw.pageTriage.viewUtil.template( { 'view': 'toolbar', 'template': 'tags.html' } ),
		tagsOptions: $.pageTriageTagsOptions,
		selectedTag: {},
		selectedTagCount: 0,
		noteMaxLength: 250,
		noteChanged: false,

		/**
		 * Initialize data on startup
		 */
		initialize: function( options ) {
			this.eventBus = options.eventBus;
			this.buildAllCategory();
			this.reset();
		},

		/**
		 * Construct the 'All' category on the fly
		 */
		buildAllCategory: function() {
			var list = [], len;
			// first, loop through all tags and store them in the array list
			for ( var cat in this.tagsOptions ) {
				if ( this.tagsOptions[cat].alias ) {
					continue;
				}
				for ( var key in this.tagsOptions[cat].tags ) {
					var tag = $.extend( true, {}, this.tagsOptions[cat].tags[key] );
					tag.dest = cat;
					list.push( tag );
				}
			}
			// then, sort the array in ascending order
			list.sort( function( a, b ) {
				if ( a.label < b.label ) {
					return -1;
				}
				if ( a.label > b.label) {
					return 1;
				}
				return 0;
			} );
			// finally, push the sorted array into the existing tag json object
			this.tagsOptions.all = {
				label: mw.msg( 'pagetriage-tags-cat-all-label' ),
				alias: true,
				tags: {}
			};
			len = list.length;
			for ( var i = 0; i < len; i++ ) {
				var tagKey = list[i].tag.replace( /-/g, '' ).replace( / /g, '' ).toLowerCase();
				this.tagsOptions.all.tags[tagKey] = list[i];
			}
		 },

		/**
		 * Reset selected tag data
		 */
		reset: function() {
			this.selectedTagCount = 0;
			for ( var cat in this.tagsOptions ) {
				this.selectedTag[cat] = {};
			}
		},

		/**
		 * Display the tag flyout, everything should be reset
		 */
		render: function() {
			var _this = this;
			this.reset();
			this.$tel.html( this.template( { 'tags': this.tagsOptions, 'title': this.title, 'maxLength': this.noteMaxLength, 'creator': this.model.get( 'user_name' ) } ) );

			// set the Learn More link URL
			var modules = mw.config.get( 'wgPageTriageCurationModules' );
			$( '#mwe-pt-tag .mwe-pt-flyout-help-link' ).attr( 'href', modules.tags.helplink );
			$( '#mwe-pt-tag-note-input' ).keyup( function() {
				var charLeft = _this.noteCharLeft();

				$( '#mwe-pt-tag-note-char-count' ).text( mw.msg( 'pagetriage-characters-left', charLeft ) );

				if ( charLeft > 0 && _this.selectedTagCount > 0 ) {
					$( '#mwe-pt-tag-submit-button' ).button( 'enable' );
				} else {
					$( '#mwe-pt-tag-submit-button' ).button( 'disable' );
				}
			} ).live( 'focus', function(e) {
				$( this ).val( '' );
				$( this ).css( 'color', 'black' );
				$( this ).unbind( e );
			} ).change( function() {
				_this.noteChanged = true;
			} );

			// add click event for each category
			$( '#mwe-pt-categories' ).find( 'div' ).each( function( index, value ) {
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
			$( '#mwe-pt-tag-submit-button' )
				.button( { disabled: true } )
				.click(
					function () {
						$( '#mwe-pt-tag-submit-button' ).button( 'disable' );
						$( '#mwe-pt-tag-submit' ).append( $.createSpinner( 'tag-spinner' ) ); // show spinner
						_this.submit();
						return false;
					}
				).end();

			// show tags under common by default
			this.displayTags( 'common' );
		},

		noteCharLeft: function() {
			return this.noteMaxLength - $.trim( $('#mwe-pt-tag-note-input').val() ).length;
		},

		/**
		 * Display the tags for the selected category
		 */
		displayTags: function( cat ) {
			var _this = this, tagSet = this.tagsOptions[cat].tags, tagRow = '';

			$( '#mwe-pt-tags' ).empty();

			var $tagList = $( '<div id="mwe-pt-tag-list"></div>' );
			
			// highlight the active category
			$( '.mwe-pt-category' ).removeClass( 'mwe-pt-active' );
			$( '#mwe-pt-category-' + cat ).addClass( 'mwe-pt-active' );
			$( '.mwe-pt-category .mwe-pt-category-pokey' ).hide();
			$( '#mwe-pt-category-' + cat + ' .mwe-pt-category-pokey' ).show();
			
			$( '#mwe-pt-tags' ).append( $tagList );
			
			for ( var key in tagSet ) {

				var checked = false;
				
				// If the tag has been selected, show it as checked
				if ( this.selectedTag[cat][key] ) {
					checked = true;
				}

				// build the checkbox
				var checkbox = mw.html.element(
							'input',
							{
								'type': 'checkbox',
								'value': tagSet[key].tag,
								'class': 'mwe-pt-tag-checkbox',
								'id': 'mwe-pt-checkbox-tag-' + key,
								'checked': ( checked ) ? true : false
							}
						);
				tagRow = '<div class="mwe-pt-tag-row" id="mwe-pt-tag-row-' + key + '"><table><tr>';
				tagRow += '<td class="mwe-pt-tag-checkbox-cell">' + checkbox + '</td>';
				tagRow += '<td><div id="mwe-pt-tag-' + key + '" class="mwe-pt-tag-label">' +
					mw.html.escape( tagSet[key].label ) + '</div>';
				tagRow += '<div class="mwe-pt-tag-desc">' +
					mw.html.escape( tagSet[key].desc ) +
					'</div><div id="mwe-pt-tag-params-link-' + key + '" class="mwe-pt-tag-params-link"></div>' +
					'<div id="mwe-pt-tag-params-form-' + key + '" class="mwe-pt-tag-params-form">' +
					'</div></td>';
				tagRow += '</tr></table></div>';

				$tagList.append( tagRow );

				// insert the add/edit parameter link if the checkbox has been checked
				if ( $( '#mwe-pt-checkbox-tag-' + key ).prop( 'checked' ) ) {
					this.showParamsLink( key, cat );
				}

				// add click events for checking/unchecking tags to both the 
				// checkboxes and tag labels
				$( '#mwe-pt-tag-' + key + ', #mwe-pt-checkbox-tag-' + key ).click(
					function() {
						var destCat, alsoCommon;
						
						// Extract the tag key from the id of whatever was clicked on
						var tagKeyMatches = $( this ).attr( 'id' ).match( /.*-tag-(.*)/ );
						var tagKey = tagKeyMatches[1];

						// Tags in the 'common' group actually belong to other categories.
						// In those cases we need to interact with the real parent
						// category which is indicated in the 'dest' attribute.
						if ( ( cat === 'common' || cat == 'all' ) && tagSet[tagKey].dest ) {
							destCat	= tagSet[tagKey].dest;
						}
						
						// Tags in other groups may also belong to the 'common' group.
						// In these cases, we need to update the corresponding tag
						// in the 'common' group as well.
						if ( cat !== 'common' && _this.tagsOptions['common'].tags[tagKey] !== undefined ) {
							alsoCommon = true;
						}

						if ( !_this.selectedTag[cat][tagKey] ) {
							// activate checkbox
							$( '#mwe-pt-checkbox-tag-' + tagKey ).attr( 'checked', true );
							_this.selectedTagCount++;
							_this.selectedTag[cat][tagKey] = tagSet[tagKey];
							if ( destCat ) {
								_this.selectedTag[destCat][tagKey] = tagSet[tagKey];
							}
							if ( alsoCommon ) {
								_this.selectedTag['common'][tagKey] = tagSet[tagKey];
							}
							_this.selectedTag['all'][tagKey] = tagSet[tagKey];
							_this.showParamsLink( tagKey, cat );
							// show the param form if there is required parameter
							for ( var param in tagSet[tagKey]['params'] ) {
								if ( tagSet[tagKey]['params'][param].input === 'required' ) {
									_this.showParamsForm( tagKey, cat );
									break;
								}
							}
						} else {
							// deactivate checkbox
							$( '#mwe-pt-checkbox-tag-' + tagKey ).attr( 'checked', false );
							_this.selectedTagCount--;
							delete _this.selectedTag[cat][tagKey];
							if ( destCat ) {
								delete _this.selectedTag[destCat][tagKey];
							}
							if ( alsoCommon ) {
								delete _this.selectedTag['common'][tagKey];
							}
							delete _this.selectedTag['all'][tagKey];
							_this.hideParamsLink( tagKey );
							// If the param form is visible, hide it
							_this.hideParamsForm( tagKey );
						}

						_this.refreshTagCountDisplay( tagKey, destCat ? destCat : cat );
					}
				).end();
			}
		},

		/**
		 * Refresh the display of tag count
		 */
		refreshTagCountDisplay: function( key, cat ) {
			var categoryTagCount = this.objectPropCount( this.selectedTag[cat] );

			if ( categoryTagCount > 0 ) {
				$( '#mwe-pt-category-' + cat + ' .mwe-pt-tag-count' ).html( '(' + categoryTagCount + ')' );
			} else {
				$( '#mwe-pt-category-' + cat + ' .mwe-pt-tag-count' ).empty();
			}

			this.selectedTagCount > 0 ? $( '#mwe-pt-tag-note' ).show() : $( '#mwe-pt-tag-note' ).hide();

			// update the number in the submit button
			$( '#mwe-pt-tag-submit-button .ui-button-text' ).html( mw.msg( 'pagetriage-button-add-tag-number', this.selectedTagCount ) );
			
			// activate or deactivate the submit button and associated parts
			if ( this.selectedTagCount > 0 && this.noteCharLeft() > 0 ) {
				$( '#mwe-pt-tag-submit-button' ).button( 'enable' );
			} else {
				$( '#mwe-pt-tag-total-count' ).empty();
				$( '#mwe-pt-tag-submit-button' ).button( 'disable' );
			}
		},

		/**
		 * Show 'Add/Edit parameter' link
		 */
		showParamsLink: function( key, cat ) {
			var allParamsHidden = true;
			var text = 'add';
			var tag = this.selectedTag[cat][key];
			var _this = this;

			// no params, don't show the link
			if ( $.isEmptyObject( tag.params ) ) {
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

			// Construct the link that activates the params form
			var link = mw.html.element(
						'a',
						{ 'href': '#', 'id': 'mwe-pt-tag-params-' + key },
						mw.msg( 'pagetriage-button-' + text + '-details' )
					);
			$( '#mwe-pt-tag-params-link-' + key ).html( '+&#160;' + link );
			
			// Add click event to the link that shows the param form
			$( '#mwe-pt-tag-params-' + key ).click( function() {
				_this.showParamsForm( key, cat );
				return false;
			} );
		},

		/**
		 * Hide 'Add/Edit parameter' link
		 */
		hideParamsLink: function( key ) {
			$( '#mwe-pt-tag-params-link-' + key ).empty();
		},

		/**
		 * Show the parameters form
		 */
		showParamsForm: function( key, cat ) {
			var _this = this, html = '', buttons = '', tag = this.selectedTag[cat][key];

			this.hideParamsLink( key );

			for ( var param in tag.params ) {
				var paramObj = tag.params[param];
				html += this.buildHTML( param, paramObj, key );
			}

			buttons += mw.html.element(
						'button',
						{ 'id': 'mwe-pt-tag-set-param-' + key, 'class': 'mwe-pt-tag-set-param-button ui-button-green' },
						mw.msg( 'pagetriage-button-add-details' )
					);
			buttons += mw.html.element(
						'button',
						{ 'id': 'mwe-pt-tag-cancel-param-' + key, 'class': 'ui-button-red' },
						mw.msg( 'cancel' )
					);
			html += '<div class="mwe-pt-tag-params-form-buttons">' + buttons + '</div>';

			html += '<div id="mwe-pt-tags-params-form-error"></div>';

			// Insert the form content into the flyout
			$( '#mwe-pt-tag-params-form-' + key ).html( html );
			$( '#mwe-pt-tag-params-form-' + key ).show();

			// Add click even for the Set Parameters button
			$( '#mwe-pt-tag-set-param-' + key ).button().click(
				function() {
					if ( _this.setParams( key, cat ) ) {
						if ( tag.dest ) {
							_this.setParams( key, tag.dest );
						}
						// Hide the form and show the link to reopen it
						_this.hideParamsForm( key );
						_this.showParamsLink( key, cat );
					}
				}
			);

			// Add click even for the Cancel button
			$( '#mwe-pt-tag-cancel-param-' + key ).button().click(
				function() {
					var missingRequiredParam = false, destCat;
					
					// Hide the form and show the link to reopen it
					_this.hideParamsForm( key );
					_this.showParamsLink( key, cat );
					
					// If there were any unset required params, uncheck the tag
					// and hide the form link (basically, reset it)
					for ( var param in tag.params ) {
						if ( tag.params[param].input === 'required' && !tag.params[param].value ) {
							if ( tag.dest ) {
								destCat = tag.dest;
								delete _this.selectedTag[destCat][key];
							}
							delete _this.selectedTag[cat][key];
							_this.selectedTagCount--;
							_this.refreshTagCountDisplay( key, destCat ? destCat : cat );
							$( '#mwe-pt-checkbox-tag-' + key ).attr( 'checked', false );
							_this.hideParamsLink( key );
							break;
						}
					}
				}
			);
		},

		/**
		 * Hide the parameters form
		 */
		hideParamsForm: function( key ) {
			$( '#mwe-pt-tag-params-form-' + key ).hide();
		},

		/**
		 * Set the parameter values
		 */
		setParams: function( key, cat ) {
			var tag = this.selectedTag[cat][key];
			for ( var param in tag.params ) {
				if ( tag.params[param].type === 'checkbox' ) {
					// See if it's checked or not
					if ( $( '#mwe-pt-tag-params-' + key + '-' + param ).is(':checked') ) {
						tag.params[param].value = $( '#mwe-pt-tag-params-' + key + '-' + param ).val();
					} else {
						tag.params[param].value = '';
					}
				} else if ( tag.params[param].type === 'select' ) {
					tag.params[param].value = $( 'input[name = mwe-pt-tag-params-' + key+ '-' + param + ']:checked' ).val();
				} else {
					tag.params[param].value = $( '#mwe-pt-tag-params-' + key + '-' + param ).val();
				}
				// If a parameter is required but not filled in, show an error and keep the form open
				if ( tag.params[param].input === 'required' && !tag.params[param].value ) {
					$( '#mwe-pt-tags-params-form-error' ).html( mw.msg( 'pagetriage-tags-param-missing-required', tag.tag ) );
					return false;
				}
			}

			return true;
		},

		/**
		 * Build the parameter for request
		 */
		buildParams: function( tagObj ) {
			var paramVal = '';
			for ( var param in tagObj.params ) {
				if ( tagObj.params[param].value ) {
					paramVal += '|' + param + '=' + tagObj.params[param].value;
				}
			}
			return paramVal;
		},

		/**
		 * Submit the selected tags
		 */
		submit: function() {
			if ( this.model.get( 'page_len' ) < 1000 && this.selectedTagCount > 4 ) {
				if ( !confirm( mw.msg( 'pagetriage-add-tag-confirmation', this.selectedTagCount ) ) ) {
					$.removeSpinner( 'tag-spinner' );
					$( '#mwe-pt-tag-submit-button' ).button( 'enable' );
					return;
				}
			}

			var topText = '', bottomText = '', processed = {}, _this = this, multipleTags = {}, tagList = [];

			for ( var cat in this.selectedTag ) {
				for ( var tagKey in this.selectedTag[cat] ) {
					if ( processed[tagKey] ) {
						continue;
					}
					var tagObj = this.selectedTag[cat][tagKey];

					// Final check on required params
					for ( var param in tagObj.params ) {
						if ( tagObj.params[param].input === 'required' && !tagObj.params[param].value ) {
							_this.handleError( mw.msg( 'pagetriage-tags-param-missing-required', tagObj.tag ) );
							return;
						}
					}

					switch ( tagObj.position ) {
						case 'bottom':
							bottomText += '{{' + tagObj.tag + this.buildParams( tagObj ) + '}}';
							break;
						case 'top':
						default:
							if ( tagObj.multiple ) {
								multipleTags[tagKey] = tagObj;
							} else {
								topText += '{{' + tagObj.tag + this.buildParams( tagObj ) + '}}';
							}
							break;
					}
					processed[tagKey] = true;
					tagList.push( tagObj.tag.toLowerCase() );
				}
			}

			var openText = '', closeText = '', multipleTagsText = '';
			if ( this.objectPropCount( multipleTags ) > 1 ) {
				openText = '{{' + $.pageTriageTagsMultiple + '|';
				closeText = '}}';
			}

			for ( var tagKey in multipleTags ) {
				multipleTagsText += '{{' + multipleTags[tagKey].tag
					+ this.buildParams( multipleTags[tagKey] ) + '}}';
			}

			topText += openText + multipleTagsText + closeText;

			if ( topText == '' && bottomText == '') {
				return;
			}

			// Applying maintenance tags should automatically mark the page as reviewed
			apiRequest = {
				'action': 'pagetriageaction',
				'pageid': mw.config.get( 'wgArticleId' ),
				'reviewed': '1',
				'token': mw.user.tokens.get('editToken'),
				'format': 'json'
			};
			$.ajax( {
				type: 'post',
				url: mw.util.wikiScript( 'api' ),
				data: apiRequest,
				success: function( data ) {
					if ( data.error ) {
						_this.handleError( mw.msg( 'pagetriage-mark-as-reviewed-error', data.error.info ) );
					} else {
						_this.applyTags( topText, bottomText, tagList );
					}
				},
				dataType: 'json'
			} );
		},
		
		/**
		 * Handle an error occuring after submit
		 * @param {String} msg The error message to display
		 */
		handleError: function( msg ) {
			$.removeSpinner( 'tag-spinner' );
			// Re-enable the submit button (in case it is disabled)
			$( '#mwe-pt-tag-submit-button' ).button( 'enable' );
			// Show error message to the user
			alert( msg );
		},

		applyTags: function( topText, bottomText, tagList ) {
			var _this = this, note = $.trim( $( '#mwe-pt-tag-note-input' ).val() );
			if ( !this.noteChanged || !note.length ) {
				note = '';
			}

			$.ajax( {
				type: 'post',
				url: mw.util.wikiScript( 'api' ),
				data: {
					'action': 'pagetriagetagging',
					'pageid': mw.config.get( 'wgArticleId' ),
					'token': mw.user.tokens.get('editToken'),
					'format': 'json',
					'top': topText,
					'bottom': bottomText,
					'note': note,
					'taglist': tagList.join( '|' )
				},
				success: function( data ) {
					if ( data.pagetriagetagging && data.pagetriagetagging.result === 'success' ) {
						if ( note ) {
							_this.talkPageNote( note );
						} else {
							// update the article model, since it's now changed.
							_this.reset();
							window.location.reload( true );
						}
					} else {
						_this.handleError( mw.msg( 'pagetriage-mark-as-reviewed-error' ) );
					}
				},
				dataType: 'json'
			} );
		},

		talkPageNote: function( note ) {
			var _this = this, title = new mw.Title( this.model.get( 'user_name' ), mw.config.get( 'wgNamespaceIds' )['user_talk'] ),
			pageName = new mw.Title(  mw.config.get( 'wgPageName' ) ).getPrefixedText();

			note = '{{subst:' + mw.config.get( 'wgTalkPageNoteTemplate' )['Tags']
				+ '|' + pageName
				+ '|' + mw.config.get( 'wgUserName' )
				+ '|' + note + '}}';

			$.ajax( {
				type: 'post',
				url: mw.util.wikiScript( 'api' ),
				data: {
					'action': 'edit',
					'title': title.getPrefixedText(),
					'appendtext': "\n" + note,
					'token': mw.user.tokens.get('editToken'),
					'summary': mw.msg( 'pagetriage-tags-note-edit-summary', pageName ),
					'format': 'json'
				},
				success: function( data ) {
					if ( data.edit && data.edit.result === 'Success' ) {
						// update the article model, since it's now changed.
						_this.reset();
						window.location.reload( true );
					} else {
						_this.handleError( mw.msg( 'pagetriage-mark-as-reviewed-error' ) );
					}
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
								'id': 'mwe-pt-tag-params-' + key + '-' + name
							}
						);
					break;
				case 'textarea':
					html += obj.label + ' ';
					html += mw.html.element(
							'textarea',
							{ 'id': 'mwe-pt-tag-params-' + key + '-' + name },
							obj.value
						);
					html += "<br/>\n";
					break;
				case 'checkbox':
					html += mw.html.element(
						'input',
						{
							'type': 'checkbox',
							'value': 'yes',
							'checked': ( obj.value === 'yes' ) ? true : false,
							'name': 'mwe-pt-tag-params-' + key + '-' + name,
							'id': 'mwe-pt-tag-params-' + key + '-' + name
						}
					);
					html += obj.label;
					html += "<br/>\n";
					break;
				case 'select':
					html += obj.label + ' ';
					for ( var i in obj.option ) {
						html += mw.html.element(
							'input',
							{
								'type': 'radio',
								'value': i.toLowerCase(),
								'checked': ( i === obj.value ) ? true : false,
								'name': 'mwe-pt-tag-params-' + key + '-' + name
							}
						);
						html += obj.option[i];
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
								'id': 'mwe-pt-tag-params-' + key + '-' + name
							}
						);
					html += "<br/>\n";
					break;
			}

			return html;
		}

	} );
} );
