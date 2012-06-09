// view for displaying tags
$( function() {
	mw.pageTriage.TagsView = mw.pageTriage.ToolView.extend( {
		id: 'mwe-pt-tag',
		icon: 'icon_tag.png',
		title: 'Add Tags',
		template: mw.pageTriage.viewUtil.template( { 'view': 'toolbar', 'template': 'tags.html' } ),
		tagsOptions: $.pageTriageTagsOptions,
		selectedTag: {},
		selectedTagCount: 0,

		/**
		 * Initialize data on startup
		 */
		initialize: function( options ) {
			this.eventBus = options.eventBus;
			this.reset();
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
			this.$tel.html( this.template( { 'tags': this.tagsOptions, 'title': this.title } ) );
			
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
						_this.submit();
					}
				).end();

			// show tags under common by default
			this.displayTags( 'common' );
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
			
			/*
			$( '#mwe-pt-tags' ).append(
				'<div class="mwe-pt-tags-cat-desc">' +
				mw.msg( 'pagetriage-tags-cat-' + cat + '-desc' ) +
				'</div>'
			);
			*/
			$( '#mwe-pt-tags' ).append( $tagList );
			
			for ( var key in tagSet ) {
				
				var checked = false;
				/*
				if ( tagSet[key].dest ) {
					var destCat	= tagSet[key].dest;
					if ( this.selectedTag[destCat][key] ) {
						checked = true;
					}
				}
				*/
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
						var destCat;
						
						// Extract the tag key from the id of whatever was clicked on
						var tagKeyMatches = $( this ).attr( 'id' ).match( /.*-tag-(.*)/ );
						var tagKey = tagKeyMatches[1];
						
						$( '#mwe-pt-tag-params-form-' + tagKey ).hide();

						// Tags in the 'Common' group actually belong to other categories.
						// In those cases we need to interact with the real parent
						// category which is indicated in the 'dest' attribute.
						if ( tagSet[tagKey].dest ) {
							destCat	= tagSet[tagKey].dest;
						}
						if ( !_this.selectedTag[cat][tagKey] ) {
							// activate checkbox
							$( '#mwe-pt-checkbox-tag-' + tagKey ).attr( 'checked', true );
							_this.selectedTagCount++;
							_this.selectedTag[cat][tagKey] = tagSet[tagKey];
							if ( destCat ) {
								_this.selectedTag[destCat][tagKey] = tagSet[tagKey];
							}
							_this.showParamsLink( tagKey, cat );
							// show the param form if there is required parameter
							for ( param in tagSet[tagKey]['params'] ) {
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
			var categoryTagCount = Object.keys( this.selectedTag[cat] ).length;
			if ( categoryTagCount > 0 ) {
				$( '#mwe-pt-category-' + cat + ' .mwe-pt-tag-count' ).html( '(' + categoryTagCount + ')' );
			} else {
				$( '#mwe-pt-category-' + cat + ' .mwe-pt-tag-count' ).empty();
			}
			
			// update the number in the submit button
			$( '#mwe-pt-tag-submit-button .ui-button-text' ).html( mw.msg( 'pagetriage-button-add-tag-number', this.selectedTagCount ) );
			
			// activate or deactivate the submit button and associated parts
			if ( this.selectedTagCount > 0 ) {
				$( '#mwe-pt-tag-submit-button' ).button( 'enable' );
				$( '#mwe-pt-checkbox-mark-reviewed' ).removeAttr( 'disabled' );
				$( '#mwe-pt-checkbox-mark-reviewed-label' ).css( 'opacity', 1.0 );
			} else {
				$( '#mwe-pt-tag-total-count' ).empty();
				$( '#mwe-pt-tag-submit-button' ).button( 'disable' );
				$( '#mwe-pt-checkbox-mark-reviewed' ).attr( 'disabled', true );
				$( '#mwe-pt-checkbox-mark-reviewed-label' ).css( 'opacity', 0.35 );
			}
		},

		/**
		 * Show 'Add/Edit parameter' link
		 */
		showParamsLink: function( key, cat ) {
			var allParamsHidden = true;
			var text = 'add';
			var tag = this.selectedTag[cat][key];
			
			// no params, don't show the link
			if ( !Object.keys( tag.params ).length ) {
				return;
			}
			
			// check if there is non-hidden param
			for ( param in tag.params ) {
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
						{ 'href': '#', 'id': 'mwe-pt-tag-params-' + key },
						mw.msg( 'pagetriage-button-' + text + '-details' )
					);
			$( '#mwe-pt-tag-params-link-' + key ).html( '+&#160;' + link );
			// Add click even to the link that shows the param form
			$( '#mwe-pt-tag-params-' + key ).click( function() {
				_this.showParamsForm( key, cat );
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
			var _this = this, html = '', tag = this.selectedTag[cat][key];

			this.hideParamsLink( key );
			
			for ( param in tag.params ) {
				var paramObj = tag.params[param];
				html += this.buildHTML( param, paramObj, key );
			}

			html += mw.html.element(
						'button',
						{ 'id': 'mwe-pt-tag-set-param-' + key, 'class': 'mwe-pt-tag-set-param-button ui-button-green' },
						mw.msg( 'pagetriage-button-add-details' )
					);
			html += mw.html.element(
						'button',
						{ 'id': 'mwe-pt-tag-cancel-param-' + key, 'class': 'ui-button-red' },
						mw.msg( 'cancel' )
					);

			html += '<div id="mwe-pt-tags-params-form-error"></div>';

			// Insert the form content into the flyout
			$( '#mwe-pt-tag-params-form-' + key ).html( html );
			$( '#mwe-pt-tag-params-form-' + key ).show();
			
			$( '.mwe-pt-tag-row' ).not( '#mwe-pt-tag-row-' + key ).hide( 'squish', {}, 800 );

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
					for ( param in tag.params ) {
						if ( tag.params[param].input === 'required' && !tag.params[param].value ) {
							if ( tag.dest ) {
								destCat = tag.dest;
								delete _this.selectedTag[destCat][key];
							}
							delete _this.selectedTag[cat][key];
							_this.selectedTagCount--;
							_this.refreshTagCountDisplay( key, destCat ? destCat : cat );
							$( '#mwe-pt-tag-' + key + ' input:checkbox' ).attr( 'checked', false );
							break;
						}
					}
					// Hide the form and show the link to reopen it
					_this.hideParamsForm( key );
					_this.showParamsLink( key, cat );
				}
			);
		},

		/**
		 * Hide the parameters form
		 */
		hideParamsForm: function( key ) {
			$( '#mwe-pt-tag-params-form-' + key ).hide();
			// If the other tags are squished, unsquish them
			if ( $( '.mwe-pt-tag-row' ).not( '#mwe-pt-tag-row-' + key ).first().css( 'display' ) == 'none' ) {
				$( '.mwe-pt-tag-row' ).not( '#mwe-pt-tag-row-' + key ).show( 'squish', {}, 600 );
			}
		},

		/**
		 * Set the parameter values
		 */
		setParams: function( key, cat ) {
			var tag = this.selectedTag[cat][key];
			for ( param in tag.params ) {
				tag.params[param].value = $( '#mwe-pt-tag-params-' + key + '-' + param ).attr( 'value' );
				if ( tag.params[param].input === 'required' && !tag.params[param].value ) {
					$( '#mwe-pt-tags-params-form-error' ).html( mw.msg( 'pagetriage-tags-param-missing-required', param ) );
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
			for ( param in obj.params ) {
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
			var topText = '', bottomText = '', processed = {}, _this = this;

			for ( cat in this.selectedTag ) {
				for ( tagKey in this.selectedTag[cat] ) {
					if ( processed[tagKey] ) {
						continue;
					}
					tagObj = this.selectedTag[cat][tagKey];
					switch ( tagObj.position ) {
						case 'bottom':
							bottomText += '{{' + tagObj.tag + this.buildParams( tagObj ) + '}}';
							break;
						case 'top':
						default:
							topText += '{{' + tagObj.tag + this.buildParams( tagObj ) + '}}';
							break;
					}
					processed[tagKey] = true;
				}
			}

			if ( topText == '' && bottomText == '') {
				return;
			}
			
			// If review checkbox is checked, mark as reviewed, then submit tags
			if ( $( '#mwe-pt-checkbox-mark-reviewed' ).is( ':checked' ) ) {
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
							alert( mw.msg( 'pagetriage-mark-as-reviewed-error' ) );
						} else {
							_this.applyTags( topText, bottomText );
						}
					},
					dataType: 'json'
				} );
			} else {
				this.applyTags( topText, bottomText );
			}
		},
		
		applyTags: function( topText, bottomText ) {
			var _this = this;
			$.ajax( {
				type: 'post',
				url: mw.util.wikiScript( 'api' ),
				data: {
					'action': 'pagetriagetagging',
					'pageid': mw.config.get( 'wgArticleId' ),
					'token': mw.user.tokens.get('editToken'),
					'format': 'json',
					'top': topText,
					'bottom': bottomText
				},
				success: function( data ) {
					if ( data.pagetriagetagging.result === 'success' ) {
						_this.reset();
						window.location.reload( true );
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
				case 'select':
					html += obj.label + ' ';
					for ( i in obj.option ) {
						html += obj.option[i] + ' ' +
							mw.html.element(
								'input',
								{
									'type': 'radio',
									'value': i, 'id': 'mwe-pt-tag-params-' + key + '-' + name,
									'checked': ( i === obj.value ) ? true : false,
									'name': 'mwe-pt-tag-params-' + key + '-' + name
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
