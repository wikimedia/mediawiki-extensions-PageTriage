// view for displaying tags

var ToolView = require( './ToolView.js' ),
	config = require( './config.json' );
module.exports = ToolView.extend( {
	id: 'mwe-pt-tag',
	icon: 'icon_tag.png',
	title: mw.msg( 'pagetriage-tags-title' ),
	tooltip: 'pagetriage-tags-tooltip',
	template: mw.template.get( 'ext.pageTriage.views.toolbar', 'tags.underscore' ),
	tagsOptions: $.pageTriageTagsOptions,
	selectedTag: {},
	selectedTagCount: 0,
	noteChanged: false,

	/**
	 * Initialize data on startup
	 *
	 * @param {Object} options
	 */
	initialize: function ( options ) {
		this.eventBus = options.eventBus;
		this.moduleConfig = options.moduleConfig || {};
		this.buildAllCategory();
		this.reset();
	},

	/**
	 * Construct the 'All' category on the fly
	 */
	buildAllCategory: function () {
		var cat, key, tag, tagKey, len, i,
			list = [];
		// first, loop through all tags and store them in the array list
		for ( cat in this.tagsOptions ) {
			if ( this.tagsOptions[ cat ].alias ) {
				continue;
			}
			for ( key in this.tagsOptions[ cat ].tags ) {
				tag = $.extend( true, {}, this.tagsOptions[ cat ].tags[ key ] );
				tag.dest = cat;
				list.push( tag );
			}
		}
		// then, sort the array in ascending order
		list.sort( function ( a, b ) {
			if ( a.label < b.label ) {
				return -1;
			}
			if ( a.label > b.label ) {
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
		for ( i = 0; i < len; i++ ) {
			tagKey = list[ i ].tag.replace( /-/g, '' ).replace( / /g, '' ).toLowerCase();
			this.tagsOptions.all.tags[ tagKey ] = list[ i ];
		}
	},

	/**
	 * Reset selected tag data
	 */
	reset: function () {
		var cat;
		this.selectedTagCount = 0;
		for ( cat in this.tagsOptions ) {
			this.selectedTag[ cat ] = {};
		}
	},

	/**
	 * Display the tag flyout, everything should be reset
	 */
	render: function () {
		var that = this;

		function handleFocus() {
			$( this ).val( '' );
			$( this ).css( 'color', 'black' );
			$( this ).off( 'focus', handleFocus );
		}

		this.reset();
		this.$tel.html( this.template(
			{
				tags: this.tagsOptions,
				warningNotice: this.model.tagWarningNotice(),
				title: this.title,
				creator: this.model.get( 'user_name' )
			} ) );

		// set the Learn More link URL
		$( '#mwe-pt-tag .mwe-pt-flyout-help-link' ).attr( 'href', this.moduleConfig.helplink );
		$( '#mwe-pt-tag-note-input' )
			.on( 'keyup', function () {
				if ( that.selectedTagCount > 0 ) {
					$( '#mwe-pt-tag-submit-button' ).button( 'enable' );
				} else {
					$( '#mwe-pt-tag-submit-button' ).button( 'disable' );
				}
			} )
			.on( 'focus', handleFocus )
			.on( 'change', function () {
				that.noteChanged = true;
			} );

		// add click event for each category
		$( '#mwe-pt-categories' ).find( 'div' ).each( function () {
			var cat = $( $( this ).html() ).attr( 'cat' );
			$( this ).on( 'click',
				function () {
					$( this ).find( 'a' ).trigger( 'blur' );
					that.displayTags( cat );
					return false;
				}
			).end();
		} );

		// add click event for tag submission
		$( '#mwe-pt-tag-submit-button' )
			.button( { disabled: true } )
			.on( 'click', function () {
				$( '#mwe-pt-tag-submit-button' ).button( 'disable' );
				$( '#mwe-pt-tag-submit' ).append( $.createSpinner( 'tag-spinner' ) ); // show spinner
				that.submit();
				return false;
			} )
			.end();

		// show tags under common by default
		this.displayTags( 'common' );
	},

	/**
	 * Display the tags for the selected category
	 *
	 * @param {string} cat
	 */
	displayTags: function ( cat ) {
		var $tagList, key, checked, checkbox,
			that = this,
			tagSet = this.tagsOptions[ cat ].tags,
			tagRow = '';

		$( '#mwe-pt-tags' ).empty();

		$tagList = $( '<div>' ).attr( 'id', 'mwe-pt-tag-list' );

		// highlight the active category
		$( '.mwe-pt-category' ).removeClass( 'mwe-pt-active' );
		$( '#mwe-pt-category-' + cat ).addClass( 'mwe-pt-active' );
		$( '.mwe-pt-category .mwe-pt-category-pokey' ).hide();
		$( '#mwe-pt-category-' + cat + ' .mwe-pt-category-pokey' ).show();

		$( '#mwe-pt-tags' ).append( $tagList );

		for ( key in tagSet ) {

			checked = false;

			// If the tag has been selected, show it as checked
			if ( this.selectedTag[ cat ][ key ] ) {
				checked = true;
			}

			// build the checkbox
			checkbox = mw.html.element(
				'input',
				{
					type: 'checkbox',
					value: tagSet[ key ].tag,
					class: 'mwe-pt-tag-checkbox',
					id: 'mwe-pt-checkbox-tag-' + key,
					checked: checked
				}
			);
			tagRow = '<div class="mwe-pt-tag-row" id="mwe-pt-tag-row-' + key + '"><table><tr>';
			tagRow += '<td class="mwe-pt-tag-checkbox-cell">' + checkbox + '</td>';
			tagRow += '<td><div id="mwe-pt-tag-' + key + '" class="mwe-pt-tag-label">' +
				mw.html.escape( tagSet[ key ].label ) + '</div>';
			tagRow += '<div class="mwe-pt-tag-desc">' +
				mw.html.escape( tagSet[ key ].desc ) +
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
			$( '#mwe-pt-tag-' + key + ', #mwe-pt-checkbox-tag-' + key ).on( 'click', function () {
				var destCat, alsoCommon, param,
					// Extract the tag key from the id of whatever was clicked on
					tagKeyMatches = $( this ).attr( 'id' ).match( /.*-tag-(.*)/ ),
					tagKey = tagKeyMatches[ 1 ];

				// Tags in the 'common' group actually belong to other categories.
				// In those cases we need to interact with the real parent
				// category which is indicated in the 'dest' attribute.
				if ( ( cat === 'common' || cat === 'all' ) && tagSet[ tagKey ].dest ) {
					destCat = tagSet[ tagKey ].dest;
				}

				// Tags in other groups may also belong to the 'common' group.
				// In these cases, we need to update the corresponding tag
				// in the 'common' group as well.
				if ( cat !== 'common' && that.tagsOptions.common.tags[ tagKey ] !== undefined ) {
					alsoCommon = true;
				}

				if ( !that.selectedTag[ cat ][ tagKey ] ) {
					// activate checkbox
					$( '#mwe-pt-checkbox-tag-' + tagKey ).prop( 'checked', true );
					that.selectedTagCount++;
					that.selectedTag[ cat ][ tagKey ] = tagSet[ tagKey ];
					if ( destCat ) {
						that.selectedTag[ destCat ][ tagKey ] = tagSet[ tagKey ];
					}
					if ( alsoCommon ) {
						that.selectedTag.common[ tagKey ] = tagSet[ tagKey ];
					}
					that.selectedTag.all[ tagKey ] = tagSet[ tagKey ];
					that.showParamsLink( tagKey, cat );
					// show the param form if there is required parameter
					for ( param in tagSet[ tagKey ].params ) {
						if ( tagSet[ tagKey ].params[ param ].input === 'required' ) {
							that.showParamsForm( tagKey, cat );
							break;
						}
					}
				} else {
					// deactivate checkbox
					$( '#mwe-pt-checkbox-tag-' + tagKey ).prop( 'checked', false );
					that.selectedTagCount--;
					delete that.selectedTag[ cat ][ tagKey ];
					if ( destCat ) {
						delete that.selectedTag[ destCat ][ tagKey ];
					}
					if ( alsoCommon ) {
						delete that.selectedTag.common[ tagKey ];
					}
					delete that.selectedTag.all[ tagKey ];
					that.hideParamsLink( tagKey );
					// If the param form is visible, hide it
					that.hideParamsForm( tagKey );
				}

				that.refreshTagCountDisplay( tagKey, destCat || cat );
			} ).end();
		}
	},

	/**
	 * Refresh the display of tag count
	 *
	 * @param {string} key
	 * @param {string} cat
	 */
	refreshTagCountDisplay: function ( key, cat ) {
		var categoryTagCount = this.objectPropCount( this.selectedTag[ cat ] );

		if ( categoryTagCount > 0 ) {
			$( '#mwe-pt-category-' + cat + ' .mwe-pt-tag-count' ).html( '(' + categoryTagCount + ')' );
		} else {
			$( '#mwe-pt-category-' + cat + ' .mwe-pt-tag-count' ).empty();
		}

		if ( this.selectedTagCount > 0 ) {
			$( '#mwe-pt-tag-note' ).show();
		} else {
			$( '#mwe-pt-tag-note' ).hide();
		}

		// update the number in the submit button
		$( '#mwe-pt-tag-submit-button .ui-button-text' ).text( mw.msg( 'pagetriage-button-add-tag-number', this.selectedTagCount ) );

		// activate or deactivate the submit button and associated parts
		if ( this.selectedTagCount > 0 ) {
			$( '#mwe-pt-tag-submit-button' ).button( 'enable' );
		} else {
			$( '#mwe-pt-tag-total-count' ).empty();
			$( '#mwe-pt-tag-submit-button' ).button( 'disable' );
		}
	},

	/**
	 * Show 'Add/Edit parameter' link
	 *
	 * @param {string} key
	 * @param {string} cat
	 */
	showParamsLink: function ( key, cat ) {
		var param, link,
			allParamsHidden = true,
			text = 'add',
			tag = this.selectedTag[ cat ][ key ],
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

		// Construct the link that activates the params form
		// Give grep a chance to find the usages:
		// pagetriage-button-add-details, pagetriage-button-edit-details
		link = mw.html.element(
			'a',
			{ href: '#', id: 'mwe-pt-tag-params-' + key },
			mw.msg( 'pagetriage-button-' + text + '-details' )
		);
		$( '#mwe-pt-tag-params-link-' + key ).html( '+&#160;' + link );

		// Add click event to the link that shows the param form
		$( '#mwe-pt-tag-params-' + key ).on( 'click', function () {
			that.showParamsForm( key, cat );
			return false;
		} );
	},

	/**
	 * Hide 'Add/Edit parameter' link
	 *
	 * @param {string} key
	 */
	hideParamsLink: function ( key ) {
		$( '#mwe-pt-tag-params-link-' + key ).empty();
	},

	/**
	 * Show the parameters form
	 *
	 * @param {string} key
	 * @param {string} cat
	 */
	showParamsForm: function ( key, cat ) {
		var param, paramObj,
			that = this,
			html = '',
			buttons = '',
			tag = this.selectedTag[ cat ][ key ];

		this.hideParamsLink( key );

		for ( param in tag.params ) {
			paramObj = tag.params[ param ];
			html += this.buildHTML( param, paramObj, key );
		}

		buttons += mw.html.element(
			'button',
			{ id: 'mwe-pt-tag-set-param-' + key, class: 'mwe-pt-tag-set-param-button ui-button-green' },
			mw.msg( 'pagetriage-button-add-details' )
		);
		buttons += mw.html.element(
			'button',
			{ id: 'mwe-pt-tag-cancel-param-' + key, class: 'ui-button-red' },
			mw.msg( 'cancel' )
		);
		html += '<div class="mwe-pt-tag-params-form-buttons">' + buttons + '</div>';

		html += '<div id="mwe-pt-tags-params-form-error"></div>';

		// Insert the form content into the flyout
		$( '#mwe-pt-tag-params-form-' + key ).html( html );
		$( '#mwe-pt-tag-params-form-' + key ).show();

		// Add click even for the Set Parameters button
		$( '#mwe-pt-tag-set-param-' + key ).button().on( 'click', function () {
			if ( that.setParams( key, cat ) ) {
				if ( tag.dest ) {
					that.setParams( key, tag.dest );
				}
				// Hide the form and show the link to reopen it
				that.hideParamsForm( key );
				that.showParamsLink( key, cat );
			}
		} );

		// Add click even for the Cancel button
		$( '#mwe-pt-tag-cancel-param-' + key ).button().on( 'click', function () {
			var destCat, param;

			// Hide the form and show the link to reopen it
			that.hideParamsForm( key );
			that.showParamsLink( key, cat );

			// If there were any unset required params, uncheck the tag
			// and hide the form link (basically, reset it)
			for ( param in tag.params ) {
				if ( tag.params[ param ].input === 'required' && !tag.params[ param ].value ) {
					if ( tag.dest ) {
						destCat = tag.dest;
						delete that.selectedTag[ destCat ][ key ];
					}
					delete that.selectedTag[ cat ][ key ];
					that.selectedTagCount--;
					that.refreshTagCountDisplay( key, destCat || cat );
					$( '#mwe-pt-checkbox-tag-' + key ).prop( 'checked', false );
					that.hideParamsLink( key );
					break;
				}
			}
		} );
	},

	/**
	 * Hide the parameters form
	 *
	 * @param {string} key
	 */
	hideParamsForm: function ( key ) {
		$( '#mwe-pt-tag-params-form-' + key ).hide();
	},

	/**
	 * Set the parameter values
	 *
	 * @param {string} key
	 * @param {string} cat
	 * @return {boolean}
	 */
	setParams: function ( key, cat ) {
		var param,
			tag = this.selectedTag[ cat ][ key ];
		for ( param in tag.params ) {
			if ( tag.params[ param ].type === 'checkbox' ) {
				// See if it's checked or not
				if ( $( '#mwe-pt-tag-params-' + key + '-' + param ).is( ':checked' ) ) {
					tag.params[ param ].value = $( '#mwe-pt-tag-params-' + key + '-' + param ).val();
				} else {
					tag.params[ param ].value = '';
				}
			} else if ( tag.params[ param ].type === 'select' ) {
				tag.params[ param ].value = $( 'input[name = mwe-pt-tag-params-' + key + '-' + param + ']:checked' ).val();
			} else {
				tag.params[ param ].value = $( '#mwe-pt-tag-params-' + key + '-' + param ).val();
			}
			// If a parameter is required but not filled in, show an error and keep the form open
			if ( tag.params[ param ].input === 'required' && !tag.params[ param ].value ) {
				$( '#mwe-pt-tags-params-form-error' ).text( mw.msg( 'pagetriage-tags-param-missing-required', tag.tag ) );
				return false;
			}
		}

		return true;
	},

	/**
	 * Build the parameter for request
	 *
	 * @param {Object} tagObj
	 * @return {string}
	 */
	buildParams: function ( tagObj ) {
		var param,
			paramVal = '';
		for ( param in tagObj.params ) {
			if ( tagObj.params[ param ].value ) {
				paramVal += '|' + param + '=' + tagObj.params[ param ].value;
			}
		}
		return paramVal;
	},

	/**
	 * Submit the selected tags
	 */
	submit: function () {
		var cat, tagKey, tagObj, param,
			topText = '',
			bottomText = '',
			processed = {},
			that = this,
			multipleTags = {},
			tagList = [],
			openText = '',
			closeText = '',
			multipleTagsText = '';

		if ( this.model.get( 'page_len' ) < 1000 && this.selectedTagCount > 4 ) {
			// eslint-disable-next-line no-alert
			if ( !confirm( mw.msg( 'pagetriage-add-tag-confirmation', this.selectedTagCount ) ) ) {
				$.removeSpinner( 'tag-spinner' );
				$( '#mwe-pt-tag-submit-button' ).button( 'enable' );
				return;
			}
		}

		for ( cat in this.selectedTag ) {
			for ( tagKey in this.selectedTag[ cat ] ) {
				if ( processed[ tagKey ] ) {
					continue;
				}
				tagObj = this.selectedTag[ cat ][ tagKey ];

				// Final check on required params
				for ( param in tagObj.params ) {
					if ( tagObj.params[ param ].input === 'required' && !tagObj.params[ param ].value ) {
						that.handleError( mw.msg( 'pagetriage-tags-param-missing-required', tagObj.tag ) );
						return;
					}
				}

				switch ( tagObj.position ) {
					case 'bottom':
						bottomText += '{{' + tagObj.tag + this.buildParams( tagObj ) + '}}';
						break;
					case 'categories':
						bottomText = '{{' + tagObj.tag + this.buildParams( tagObj ) + '}}' + bottomText;
						break;
					case 'top':
						/* falls through */
					default:
						if ( tagObj.multiple ) {
							multipleTags[ tagKey ] = tagObj;
						} else {
							topText += '{{' + tagObj.tag + this.buildParams( tagObj ) + '}}';
						}
						break;
				}
				processed[ tagKey ] = true;
				tagList.push( tagObj.tag.toLowerCase() );
			}
		}

		if ( this.objectPropCount( multipleTags ) > 1 ) {
			openText = '{{' + $.pageTriageTagsMultiple + '|';
			closeText = '}}';
		}

		for ( tagKey in multipleTags ) {
			multipleTagsText += '{{' + multipleTags[ tagKey ].tag +
				this.buildParams( multipleTags[ tagKey ] ) + '}}';
		}

		topText += openText + multipleTagsText + closeText;

		if ( topText === '' && bottomText === '' ) {
			return;
		}

		// Applying maintenance tags should automatically mark the page as reviewed
		new mw.Api().postWithToken( 'csrf', {
			action: 'pagetriageaction',
			pageid: mw.config.get( 'wgArticleId' ),
			reviewed: '1',
			skipnotif: '1'
		} )
			.done( function () {
				that.applyTags( topText, bottomText, tagList );
			} )
			.fail( function ( errorCode, data ) {
				that.handleError( mw.msg( 'pagetriage-mark-as-reviewed-error', data.error.info ) );
			} );
	},

	/**
	 * Handle an error occuring after submit
	 *
	 * @param {string} msg The error message to display
	 */
	handleError: function ( msg ) {
		$.removeSpinner( 'tag-spinner' );
		// Re-enable the submit button (in case it is disabled)
		$( '#mwe-pt-tag-submit-button' ).button( 'enable' );
		// Show error message to the user
		// eslint-disable-next-line no-alert
		alert( msg );
	},

	applyTags: function ( topText, bottomText, tagList ) {
		var that = this,
			note = $( '#mwe-pt-tag-note-input' ).val().trim();
		if ( !this.noteChanged || !note.length ) {
			note = '';
		}

		new mw.Api().postWithToken( 'csrf', {
			action: 'pagetriagetagging',
			pageid: mw.config.get( 'wgArticleId' ),
			top: topText,
			bottom: bottomText,
			note: note,
			taglist: tagList.join( '|' )
		} )
			.done( function () {
				if ( note ) {
					that.talkPageNote( note );
				} else {
					// update the article model, since it's now changed.
					that.reset();
					window.location.reload( true );
				}
			} )
			.fail( function ( errorCode, data ) {
				that.handleError( mw.msg( 'pagetriage-mark-as-reviewed-error', data.error.info ) );
			} );
	},

	talkPageNote: function ( note ) {
		var topicTitle, messagePosterPromise,
			that = this,
			pageName = mw.config.get( 'wgPageName' ).replace( /_/g, ' ' );

		messagePosterPromise = mw.messagePoster.factory.create(
			new mw.Title( this.model.get( 'creator_user_talk_page' ) )
		);

		topicTitle = mw.pageTriage.contentLanguageMessage(
			'pagetriage-tags-talk-page-notify-topic-title',
			pageName
		).text();

		note = '{{subst:' + config.TalkPageNoteTemplate.Tags +
			'|1=' + pageName +
			'|2=' + mw.config.get( 'wgUserName' ) +
			'|3=' + note + '}}';

		messagePosterPromise.then( function ( messagePoster ) {
			return messagePoster.post( topicTitle, note, { tags: 'pagetriage' } );
		} ).then( function () {
			// update the article model, since it's now changed.
			that.reset();
			window.location.reload( true );
		}, function () {
			that.handleError( mw.msg( 'pagetriage-mark-as-reviewed-error' ) );
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
		var i,
			html = '';

		switch ( obj.type ) {
			case 'hidden':
				html += mw.html.element(
					'input',
					{
						type: 'hidden',
						value: ( obj.value ) ? obj.value : '',
						id: 'mwe-pt-tag-params-' + key + '-' + name
					}
				);
				break;
			case 'textarea':
				html += mw.html.escape( obj.label ) + ' ';
				html += mw.html.element(
					'textarea',
					{ id: 'mwe-pt-tag-params-' + key + '-' + name },
					obj.value
				);
				html += '<br/>\n';
				break;
			case 'checkbox':
				html += mw.html.element(
					'input',
					{
						type: 'checkbox',
						value: 'yes',
						checked: obj.value === 'yes',
						name: 'mwe-pt-tag-params-' + key + '-' + name,
						id: 'mwe-pt-tag-params-' + key + '-' + name
					}
				);
				html += mw.html.escape( obj.label );
				html += '<br/>\n';
				break;
			case 'select':
				html += mw.html.escape( obj.label ) + ' ';
				for ( i in obj.option ) {
					html += mw.html.element(
						'input',
						{
							type: 'radio',
							value: i.toLowerCase(),
							checked: i === obj.value,
							name: 'mwe-pt-tag-params-' + key + '-' + name
						}
					);
					html += obj.option[ i ];
				}
				html += '<br/>\n';
				break;
			case 'text':
				/* falls through */
			default:
				html += mw.html.escape( obj.label ) + ' ';
				html += mw.html.element(
					'input',
					{
						type: 'text',
						value: obj.value || '',
						id: 'mwe-pt-tag-params-' + key + '-' + name
					}
				);
				html += '<br/>\n';
				break;
		}

		return html;
	}

} );
