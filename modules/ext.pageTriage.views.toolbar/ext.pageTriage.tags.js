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
			$( '#mwe-pt-category' ).find( 'div' ).each( function( index, value ) {
				var cat = $( $( this ).html() ).attr( 'cat' );
				$( this ).click(
					function() {
						_this.displayTags( cat );
						return false;
					}
				).end();
			} );

			// add click event for tag submission
			$( '#mwe-pt-tag-submit' ).click(
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
			var _this = this, tagSet = this.tagsOptions[cat].tags;
			$( '#mwe-pt-tags' ).empty();
			for ( key in tagSet ) {
				// build the checkbox
				var checkbox = mw.html.element(
							'input',
							{
								'type': 'checkbox',
								'value': tagSet[key].tag,
								'checked': ( _this.selectedTag[cat][key] ) ? true : false
							}
						);

				// build the tag description following the checkbox
				$( '#mwe-pt-tags' ).append(
							'<div id="mwe-pt-tag-' + key + '">' +
							checkbox + ' ' +  mw.html.escape( tagSet[key].label ) + ' ' +
							 mw.html.escape( tagSet[key].desc ) +
							'</div><div id="mwe-pt-tag-params-link-' + key + '"></div>'
							);

				// build the edit parameter link if the checkbox has been checked
				if ( $( '#mwe-pt-tag-' + key + ' input:checkbox' ).prop( 'checked' ) ) {
					this.showParamsLink( key, cat, 'edit' );
				}

				// add click event to the checking/unchecking tags
				$( '#mwe-pt-tag-' + key ).click(
					function() {
						$( '#mwe-pt-tags-params-form' ).empty();
						var destCat, tagKey = $( this ).attr( 'id' ).replace( 'mwe-pt-tag-', '' );
						if ( tagSet[tagKey].dest ) {
							destCat	= tagSet[tagKey].dest;
						}

						if ( !_this.selectedTag[cat][tagKey] ) {
							$( this).find( ':input' ).attr( 'checked', true );
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
							$( this).find( ':input' ).attr( 'checked', false );
							_this.selectedTagCount--;
							delete _this.selectedTag[cat][tagKey];
							if ( destCat ) {
								delete _this.selectedTag[destCat][tagKey];
							}
							_this.hideParamsLink( tagKey );
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
				$( '#mwe-pt-tag-count-' + cat ).html( '(' + categoryTagCount + ')' );
			} else {
				$( '#mwe-pt-tag-count-' + cat ).empty();
			}

			if ( this.selectedTagCount > 0 ) {
				$( '#mwe-pt-tag-total-count' ).html(
					mw.msg( 'pagetriage-tag-count-total', this.selectedTagCount )
					);
				$( '#mwe-pt-tag-submit' ).show();
			} else {
				$( '#mwe-pt-tag-total-count' ).empty();
				$( '#mwe-pt-tag-submit' ).hide();
			}
		},

		/**
		 * Show 'Add/Edit parameter' link
		 */
		showParamsLink: function( key, cat, text ) {
			var automated = true, params = this.selectedTag[cat][key].params;
			// no params, don't show the link
			if ( !Object.keys( params ).length ) {
				return;
			}
			// check if there is non-automated param
			for ( param in params ) {
				if ( params[param].input !== 'automated' ) {
					automated = false;
					break;
				}
			}
			// all params are automated, don't show the link
			if ( automated === true ) {
				return;
			}

			if ( !text ) {
				text = 'add';
			}

			var _this = this;
			var link = mw.html.element(
						'a',
						{ 'href': '#', 'id': 'mwe-pt-tag-params-' + key },
						mw.msg( 'pagetriage-button-' + text + '-parameters' )
					);
			$( '#mwe-pt-tag-params-link-' + key ).html( link );
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
		 * Show the parameters
		 */
		showParamsForm: function( key, cat ) {
			var _this = this, html = '', tag = this.selectedTag[cat][key];

			$( '#mwe-pt-tags-params-form' ).empty();
			$( '#mwe-pt-tags' ).hide();
			for ( param in tag.params ) {
				var paramObj = tag.params[param];
				html += this.buildHTML( param, paramObj, key );
			}

			html += mw.html.element(
						'a',
						{ 'href':'#', 'id': 'mwe-pt-tag-set-param-' + key },
						mw.msg( 'pagetriage-button-set-parameters' )
					);
			html += ' ';
			html += mw.html.element(
						'a',
						{ 'href':'#', 'id': 'mwe-pt-tag-cancel-param-' + key },
						mw.msg( 'pagetriage-button-cancel-parameters' )
					);

			html += '<div id="mwe-pt-tags-params-form-error"></div>';

			$( '#mwe-pt-tags-params-form' ).html( html );

			$( '#mwe-pt-tag-set-param-' + key ).click(
				function() {
					if ( _this.setParams( key, cat ) ) {
						if ( tag.dest ) {
							_this.setParams( key, tag.dest );
						}
						_this.showParamsLink( key, cat, 'edit' );
						$( '#mwe-pt-tags-params-form' ).empty();
						$( '#mwe-pt-tags' ).show();
					}
				}
			);

			$( '#mwe-pt-tag-cancel-param-' + key ).click(
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

					$( '#mwe-pt-tags-params-form' ).empty();
					$( '#mwe-pt-tags' ).show();
				}
			);
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

			return $.ajax( {
				type: 'post',
				url: mw.util.wikiScript( 'api' ),
				data: {
					'action': 'pagetriagetagging',
					'pageid': wgArticleId,
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
			var html = obj.label + ' ';

			switch ( obj.type ) {
				case 'textarea':
					html += mw.html.element(
							'textarea',
							{ 'id': 'mwe-pt-tag-params-' + key + '-' + name },
							obj.value
						);
					break;
				case 'select':
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
					break;

				case 'text':
				default:
					html += mw.html.element(
							'input',
							{
								'type': 'text',
								'value': ( obj.value ) ? obj.value : '',
								'id': 'mwe-pt-tag-params-' + key + '-' + name
							}
						);
					break;
			}

			return html;
		}
	} );
} );
