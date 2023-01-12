// view for displaying tags

const ToolView = require( './ToolView.js' );
const config = require( './config.json' );
// Used to keep track of what actions we want to invoke, and with what data.
const actionQueue = {};
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
		const list = [];

		// first, loop through all tags in other categories and store them in the "list" variable
		for ( const cat in this.tagsOptions ) {
			if ( this.tagsOptions[ cat ].alias ) {
				continue;
			}
			for ( const key in this.tagsOptions[ cat ].tags ) {
				const tag = $.extend( true, {}, this.tagsOptions[ cat ].tags[ key ] );
				tag.dest = cat;
				list.push( tag );
			}
		}

		// then, sort the "list" variable by tag name, in ascending order
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
		const len = list.length;
		for ( let i = 0; i < len; i++ ) {
			const tagKey = list[ i ].tag.replace( /-/g, '' ).replace( / /g, '' ).toLowerCase();
			this.tagsOptions.all.tags[ tagKey ] = list[ i ];
		}
	},

	/**
	 * Reset selected tag data
	 */
	reset: function () {
		this.selectedTagCount = 0;
		for ( const cat in this.tagsOptions ) {
			this.selectedTag[ cat ] = {};
		}
	},

	/**
	 * Display the tag flyout, everything should be reset
	 */
	render: function () {
		const that = this;

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
				creator: this.model.get( 'user_name' ),
				patrolStatus: this.model.get( 'patrol_status' )
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
			const cat = $( $( this ).html() ).attr( 'cat' );
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

		// when the maintenance tag menu is first opened, show the 'All tags' menu by default
		this.displayTags( 'all' );
	},

	/**
	 * Display the tags for the selected category
	 *
	 * @param {string} cat
	 */
	displayTags: function ( cat ) {
		const $tagList = $( '<div>' ).attr( 'id', 'mwe-pt-tag-list' );
		let tagCount = 0;

		$( '#mwe-pt-tags' ).empty();
		$( '#mwe-pt-tags' ).append( this.renderSearchTextBox() );

		// highlight the active category
		$( '.mwe-pt-category' ).removeClass( 'mwe-pt-active' );
		$( '#mwe-pt-category-' + cat ).addClass( 'mwe-pt-active' );
		$( '.mwe-pt-category .mwe-pt-category-pokey' ).hide();
		$( '#mwe-pt-category-' + cat + ' .mwe-pt-category-pokey' ).show();

		$( '#mwe-pt-tags' ).append( $tagList );

		const that = this,
			tagSet = this.tagsOptions[ cat ].tags;
		for ( const key in tagSet ) {

			// Keep a running total of tags in the category
			if ( Object.prototype.hasOwnProperty.call( tagSet, key ) ) {
				tagCount++;
			}

			let checked = false;

			// If the tag has been selected, show it as checked
			if ( this.selectedTag[ cat ][ key ] ) {
				checked = true;
			}

			// build the checkbox
			const checkbox = mw.html.element(
				'input',
				{
					type: 'checkbox',
					value: tagSet[ key ].tag,
					class: 'mwe-pt-tag-checkbox',
					id: 'mwe-pt-checkbox-tag-' + key,
					checked: checked
				}
			);
			let tagRow = '<div class="mwe-pt-tag-row" id="mwe-pt-tag-row-' + key + '"><table><tr>';
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
				let destCat, alsoCommon, param;
				// Extract the tag key from the id of whatever was clicked on
				const tagKeyMatches = $( this ).attr( 'id' ).match( /.*-tag-(.*)/ );
				const tagKey = tagKeyMatches[ 1 ];

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

		this.showSearchTextBox( tagCount );
	},

	/**
	 * Refresh the display of tag count
	 *
	 * @param {string} key
	 * @param {string} cat
	 */
	refreshTagCountDisplay: function ( key, cat ) {
		const categoryTagCount = this.objectPropCount( this.selectedTag[ cat ] );

		if ( categoryTagCount > 0 ) {
			$( '#mwe-pt-category-' + cat + ' .mwe-pt-tag-count' ).html( '(' + categoryTagCount + ')' );
		} else {
			$( '#mwe-pt-category-' + cat + ' .mwe-pt-tag-count' ).empty();
		}

		// activate or deactivate the submit button and associated parts
		if ( this.selectedTagCount > 0 ) {
			$( '#mwe-pt-tag-submit-button' ).button( 'enable' );
			$( '#mwe-pt-checkbox-mark-reviewed-wrapper' ).show();
			if ( mw.config.get( 'wgUserName' ) !== this.model.get( 'user_name' ) ) {
				$( '#mwe-pt-tag-note' ).show();
			}
		} else {
			$( '#mwe-pt-tag-total-count' ).empty();
			$( '#mwe-pt-tag-submit-button' ).button( 'disable' );
			$( '#mwe-pt-checkbox-mark-reviewed-wrapper' ).hide();
			$( '#mwe-pt-tag-note' ).hide();
		}

		// update the number in the submit button
		$( '#mwe-pt-tag-submit-button .ui-button-text' ).text( mw.msg( 'pagetriage-button-add-tag-number', this.selectedTagCount ) );
	},

	/**
	 * Show 'Add/Edit parameter' link
	 *
	 * @param {string} key
	 * @param {string} cat
	 */
	showParamsLink: function ( key, cat ) {

		const tag = this.selectedTag[ cat ][ key ];
		// no params, don't show the link
		if ( $.isEmptyObject( tag.params ) ) {
			return;
		}

		let allParamsHidden = true,
			text = 'add';
		// check if there is non-hidden param
		for ( const param in tag.params ) {
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
		const link = mw.html.element(
			'a',
			{ href: '#', id: 'mwe-pt-tag-params-' + key },
			// The following messages are used here:
			// * pagetriage-button-add-details
			// * pagetriage-button-edit-details
			mw.msg( 'pagetriage-button-' + text + '-details' )
		);
		$( '#mwe-pt-tag-params-link-' + key ).html( '+&#160;' + link );

		const that = this;
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
		let html = '';
		const tag = this.selectedTag[ cat ][ key ];

		this.hideParamsLink( key );

		for ( const param in tag.params ) {
			const paramObj = tag.params[ param ];
			html += this.buildHTML( param, paramObj, key );
		}

		let buttons = mw.html.element(
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

		const that = this;
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
			let destCat;

			// Hide the form and show the link to reopen it
			that.hideParamsForm( key );
			that.showParamsLink( key, cat );

			// If there were any unset required params, uncheck the tag
			// and hide the form link (basically, reset it)
			for ( const param in tag.params ) {
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
		const tag = this.selectedTag[ cat ][ key ];
		for ( const param in tag.params ) {
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
		let paramVal = '';
		for ( const param in tagObj.params ) {
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
		if ( this.model.get( 'page_len' ) < 1000 && this.selectedTagCount > 4 ) {
			// eslint-disable-next-line no-alert
			if ( !confirm( mw.msg( 'pagetriage-add-tag-confirmation', this.selectedTagCount ) ) ) {
				$.removeSpinner( 'tag-spinner' );
				$( '#mwe-pt-tag-submit-button' ).button( 'enable' );
				return;
			}
		}

		let tagKey,
			topText = '',
			bottomText = '';
		const processed = {};
		const that = this;
		const multipleTags = {};
		const redirectTags = {};
		const tagList = [];
		let openText = '',
			closeText = '',
			multipleTagsText = '',
			multipleRedirectTagsText = '';
		for ( const cat in this.selectedTag ) {
			for ( tagKey in this.selectedTag[ cat ] ) {
				if ( processed[ tagKey ] ) {
					continue;
				}
				const tagObj = this.selectedTag[ cat ][ tagKey ];

				// Final check on required params
				for ( const param in tagObj.params ) {
					if ( tagObj.params[ param ].input === 'required' && !tagObj.params[ param ].value ) {
						that.handleError( mw.msg( 'pagetriage-tags-param-missing-required', tagObj.tag ) );
						return;
					}
				}

				switch ( tagObj.position ) {
					case 'redirectTag':
						redirectTags[ tagKey ] = tagObj;
						break;
					case 'bottom':
					case 'stub':
						bottomText += '\n\n{{' + tagObj.tag + this.buildParams( tagObj ) + '}}';
						break;
					case 'categories':
						bottomText = '\n{{' + tagObj.tag + this.buildParams( tagObj ) + '}}' + bottomText;
						break;
					case 'top':
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

		// Generate a string of line breaks and templates. For example,
		// \n{{No references}}\n{{Notability}}
		for ( tagKey in multipleTags ) {
			multipleTagsText += '\n{{' + multipleTags[ tagKey ].tag +
				this.buildParams( multipleTags[ tagKey ] ) + '}}';
		}

		// If multiple templates, wrap string in {{Multiple issues}}. If just one template, trim()
		// it to get rid of extra \n
		if ( this.objectPropCount( multipleTags ) > 1 ) {
			openText = '{{' + $.pageTriageTagsMultiple + '|';
			closeText = '\n}}';
		} else {
			multipleTagsText = multipleTagsText.trim();
		}

		topText += openText + multipleTagsText + closeText;

		for ( tagKey in redirectTags ) {
			multipleRedirectTagsText += '\n{{' + redirectTags[ tagKey ].tag +
				this.buildParams( redirectTags[ tagKey ] ) + '}}';
		}

		if ( this.objectPropCount( redirectTags ) > 0 ) {
			bottomText = '{{' + $.pageTriageTagsRedirectCategoryShell +
				'|' + multipleRedirectTagsText + '\n}}' + bottomText;
		}

		if ( topText === '' && bottomText === '' ) {
			return;
		}

		// When applying maintenance tags, reviewer can choose if the page is reviewed or not
		if ( $( '#mwe-pt-checkbox-mark-reviewed' ).is( ':checked' ) ) {
			new mw.Api().postWithToken( 'csrf', {
				action: 'pagetriageaction',
				pageid: mw.config.get( 'wgArticleId' ),
				// NOTE: if the logic for whether to mark as reviewed is changed,
				//   be sure to also conditionally register actionQueue.mark below
				reviewed: '1',
				skipnotif: '1'
			} )
				.then( function () {
					// Register action for marking the page as reviewed.
					actionQueue.mark = { reviewed: true };

					that.applyTags( topText, bottomText, tagList );
				} )
				.catch( function ( _errorCode, data ) {
					that.handleError( mw.msg( 'pagetriage-mark-as-reviewed-error', data.error.info ) );
				} );
		} else {
			new mw.Api().postWithToken( 'csrf', {
				action: 'pagetriageaction',
				pageid: mw.config.get( 'wgArticleId' ),
				// NOTE: if the logic for whether to mark as reviewed is changed,
				//   be sure to also conditionally register actionQueue.mark above
				reviewed: '0',
				skipnotif: '1'
			} )
				.then( function () {
					// Register action for marking the page as unreviewed.
					actionQueue.mark = { reviewed: false };

					that.applyTags( topText, bottomText, tagList );
				} )
				.catch( function ( _errorCode, data ) {
					that.handleError( mw.msg( 'pagetriage-mark-as-unreviewed-error', data.error.info ) );
				} );
		}
	},

	/**
	 * Handle an error occurring after submit
	 *
	 * @param {string} msg The error message to display
	 */
	handleError: function ( msg ) {
		// Log error to WikimediaEvents
		const pageName = mw.config.get( 'wgPageName' );
		const skin = mw.config.get( 'skin' );
		const dumpOfTag = JSON.stringify( this.selectedTag );
		let errorMessage = 'PageTriage error type: ' + msg + '\n' +
			'File: tags.js\n' +
			'Page name: ' + pageName + '\n' +
			'Skin: ' + skin + '\n' +
			'Dump of this.selectedTag: ' + dumpOfTag;
		errorMessage = errorMessage.slice( 0, 1000 );
		mw.log.error( errorMessage );
		const err = new Error( errorMessage );
		err.name = 'pageTriageHandleError';
		mw.errorLogger.logError( err, 'error.pagetraige' );
		const sitename = mw.config.get( 'wgDBname' );
		mw.track( 'counter.MediaWiki.extension.PageTriage.' + sitename + '.viewsToolbar.tags.error' );

		$.removeSpinner( 'tag-spinner' );
		// Re-enable the submit button (in case it is disabled)
		$( '#mwe-pt-tag-submit-button' ).button( 'enable' );
		// Show error message to the user
		// eslint-disable-next-line no-alert
		alert( msg );
	},

	applyTags: function ( topText, bottomText, tagList ) {
		const that = this;
		let note = $( '#mwe-pt-tag-note-input' ).val().trim();
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
			.then( function () {
				actionQueue.tags = { tags: tagList };

				if ( note ) {
					actionQueue.tags.note = note;
					that.talkPageNote( note );
				} else {
					mw.pageTriage.actionQueue.runAndRefresh( actionQueue, that.getDataForActionQueue() );
				}
			} )
			.catch( function ( _errorCode, data ) {
				that.handleError( mw.msg( 'pagetriage-mark-as-reviewed-error', data.error.info ) );
			} );
	},

	talkPageNote: function ( note ) {
		const messagePosterPromise = mw.messagePoster.factory.create(
			new mw.Title( this.model.get( 'creator_user_talk_page' ) )
		);

		const pageName = mw.config.get( 'wgPageName' ).replace( /_/g, ' ' );
		const topicTitle = mw.pageTriage.contentLanguageMessage(
			'pagetriage-tags-talk-page-notify-topic-title',
			pageName
		).text();

		note = '{{subst:' + config.TalkPageNoteTemplate.Tags +
			'|1=' + pageName +
			'|2=' + mw.config.get( 'wgUserName' ) +
			'|3=' + note + '}}';

		const that = this;
		messagePosterPromise.then( function ( messagePoster ) {
			return messagePoster.post( topicTitle, note, { tags: 'pagetriage' } );
		} ).then( function () {
			mw.pageTriage.actionQueue.runAndRefresh( actionQueue, that.getDataForActionQueue() );
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
		let html = '';

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
				for ( const i in obj.option ) {
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
