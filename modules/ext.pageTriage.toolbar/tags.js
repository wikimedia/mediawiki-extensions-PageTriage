// view for displaying tags

const { contentLanguageMessage } = require( 'ext.pageTriage.util' );
const ToolView = require( './ToolView.js' );
const config = require( './config.json' );
const { maintenanceTags: tagOptions } = require( 'ext.pageTriage.tagData' );

// Used to keep track of what actions we want to invoke, and with what data.
const actionQueue = {};
module.exports = ToolView.extend( {
	id: 'mwe-pt-tag',
	icon: 'icon_tag.png',
	title: mw.msg( 'pagetriage-tags-title' ),
	tooltip: 'pagetriage-tags-tooltip',
	template: mw.template.get( 'ext.pageTriage.toolbar', 'tags.underscore' ),
	selectedTag: {},
	selectedTagCount: 0,
	noteChanged: false,
	isRedirect: false,

	/**
	 * Initialize data on startup
	 *
	 * @param {Object} options
	 */
	initialize: function ( options ) {
		this.tagOptions = options.tagOptions ? options.tagOptions : tagOptions.tagOptions;
		this.eventBus = options.eventBus;
		this.moduleConfig = options.moduleConfig || {};
		this.reset();
	},

	/**
	 * Exclude redirect templates if the page is not a redirect
	 * if the page is a redirect, delete all tags except the redirect tags
	 */
	handleRedirectsTemplates: function () {
		if ( this.model.attributes.is_redirect === '0' ) {
			delete this.tagOptions.redirects;
			this.buildAllAndCommonCategories();
		} else {
			this.isRedirect = true;
			Object
				.getOwnPropertyNames( this.tagOptions )
				.forEach( ( prop ) => {
					if ( prop !== 'redirects' ) {
						delete this.tagOptions[ prop ];
					}
				} );
		}
	},

	/**
	 * Construct the 'All' and 'Common' categories on the fly
	 */
	buildAllAndCommonCategories: function () {
		const list = [];
		const commonList = [];

		// first, loop through all tags in other categories and store them in the "list" variable
		for ( const cat in this.tagOptions ) {
			if ( this.tagOptions[ cat ].alias ) {
				continue;
			}
			for ( const key in this.tagOptions[ cat ].tags ) {
				const tag = $.extend( true, {}, this.tagOptions[ cat ].tags[ key ] );
				tag.dest = cat;
				tag.destKey = key;
				list.push( tag );
				if ( tag.common ) {
					commonList.push( tag );
				}
			}
		}

		// then, sort the "list" variable by tag name, in ascending order
		list.sort( ( a, b ) => {
			if ( a.label < b.label ) {
				return -1;
			}
			if ( a.label > b.label ) {
				return 1;
			}
			return 0;
		} );

		// push the sorted array of common tags into the existing tag json object
		let len = commonList.length;
		for ( let i = 0; i < len; i++ ) {
			// T332105: Combine the tag (template name) and the label to
			// produce a unique key
			let tagKey = commonList[ i ].tag + commonList[ i ].label;
			tagKey = tagKey.replace( /[- (){}]/g, '' ).toLowerCase();
			this.tagOptions.common.tags[ tagKey ] = commonList[ i ];
		}

		// finally, push the sorted array into the existing tag json object
		this.tagOptions.all = {
			label: mw.msg( 'pagetriage-tags-cat-all-label' ),
			alias: true,
			tags: {}
		};
		len = list.length;
		for ( let i = 0; i < len; i++ ) {
			// T332105: Combine the tag (template name) and the label to
			// produce a unique key
			let tagKey = list[ i ].tag + list[ i ].label;
			tagKey = tagKey.replace( /[- (){}]/g, '' ).toLowerCase();
			this.tagOptions.all.tags[ tagKey ] = list[ i ];
		}
	},

	/**
	 * Reset selected tag data
	 */
	reset: function () {
		this.selectedTagCount = 0;
		for ( const cat in this.tagOptions ) {
			this.selectedTag[ cat ] = {};
		}
	},

	/**
	 * Display the tag flyout, everything should be reset
	 */
	render: function () {
		const that = this;
		this.handleRedirectsTemplates();

		this.reset();
		this.$tel.html( this.template(
			{
				tags: this.tagOptions,
				warningNotice: this.model.tagWarningNotice(),
				title: this.title,
				creator: this.model.get( 'user_name' ),
				patrolStatus: this.model.get( 'patrol_status' )
			} ) );

		// set the Learn More link URL
		$( '#mwe-pt-tag .mwe-pt-flyout-help-link' ).attr( 'href', this.moduleConfig.helplink );

		// add click event for each category
		$( '#mwe-pt-categories' ).find( 'div' ).each( ( i, el ) => {
			const cat = $( $( el ).html() ).attr( 'cat' );
			$( el ).on( 'click',
				() => {
					$( el ).find( 'a' ).trigger( 'blur' );
					that.displayTags( cat );
					return false;
				}
			).end();
		} );

		// add click event for tag submission
		$( '#mwe-pt-tag-submit-button' ).attr( 'disabled', true )
			.on( 'click', () => {
				$( '#mwe-pt-tag-submit-button' ).attr( 'disabled', true );
				$( '#mwe-pt-tag-submit' ).append( $.createSpinner( 'tag-spinner' ) ); // show spinner
				that.submit();
				return false;
			} )
			.end();

		// when the maintenance tag menu is first opened, show the 'All tags' menu by default
		// if the article is a redirect, we don't have any tabs other than 'redirects'
		// so we can show that as a fullscreen
		if ( this.isRedirect ) {
			this.displayTags( 'redirects' );
			$( '#mwe-pt-categories' ).hide();
			$( '#mwe-pt-tags' ).addClass( 'mwe-pt-tags-redirect-only' );
		} else {
			this.displayTags( 'all' );
		}
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
			tagSet = this.tagOptions[ cat ].tags;
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
			$( '#mwe-pt-tag-' + key + ', #mwe-pt-checkbox-tag-' + key ).on( 'click', ( e ) => {
				let destCat, destKey;

				// Extract the tag key from the id of whatever was clicked on
				const tagKeyMatches = $( e.delegateTarget ).attr( 'id' ).match( /.*-tag-(.*)/ );
				const tagKey = tagKeyMatches[ 1 ];

				let allTagKey = tagSet[ tagKey ].tag + tagSet[ tagKey ].label;
				allTagKey = allTagKey.replace( /[- (){}]/g, '' ).toLowerCase();

				// If the tag is in the 'all' category, we already have a destinationCategory
				// which we can use to sync the tag data across category. However, for non 'all'
				// categories we can't identify the corresponding tag in the 'all' category
				// to fix this, we generate the allTagKey (which should be the key for the tag
				// in the 'all category') above and use it to sync the tag data across categories.
				if ( cat !== 'all' ) {
					tagSet[ tagKey ].allTagKey = allTagKey;
				}

				// Tags in the 'common' and 'all' groups actually belong to other categories.
				// In those cases we need to interact with the real parent
				// category which is indicated in the 'dest' attribute.
				if ( ( cat === 'common' || cat === 'all' ) && tagSet[ tagKey ].dest ) {
					destCat = tagSet[ tagKey ].dest;
					destKey = tagSet[ tagKey ].destKey;
				}

				if ( !that.selectedTag[ cat ][ tagKey ] ) {
					// activate checkbox
					$( '#mwe-pt-checkbox-tag-' + tagKey ).prop( 'checked', true );
					that.selectedTagCount++;
					that.selectedTag[ cat ][ tagKey ] = tagSet[ tagKey ];
					if ( destCat ) {
						that.selectedTag[ destCat ][ destKey ] = tagSet[ tagKey ];
					}
					if ( tagSet[ tagKey ].common ) {
						that.selectedTag.common[ tagKey ] = tagSet[ tagKey ];
					}
					if ( that.tagOptions.all ) {
						that.selectedTag.all[ allTagKey ] = that.tagOptions.all.tags[ allTagKey ];
					}
					that.showParamsLink( tagKey, cat );
					// show the param form if there is required parameter
					for ( const param in tagSet[ tagKey ].params ) {
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
						delete that.selectedTag[ destCat ][ destKey ];
					}
					if ( tagSet[ tagKey ].common ) {
						delete that.selectedTag.common[ tagKey ];
					}
					if ( that.tagOptions.all ) {
						delete that.selectedTag.all[ allTagKey ];
					}
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
			$( '#mwe-pt-tag-submit-button' ).attr( 'disabled', false );
			$( '#mwe-pt-checkbox-mark-reviewed-wrapper' ).show();
			if ( mw.config.get( 'wgUserName' ) !== this.model.get( 'user_name' ) && !this.model.get( 'creator_hidden' ) ) {
				$( '#mwe-pt-tag-note' ).show();
			}
		} else {
			$( '#mwe-pt-tag-total-count' ).empty();
			$( '#mwe-pt-tag-submit-button' ).attr( 'disabled', true );
			$( '#mwe-pt-checkbox-mark-reviewed-wrapper' ).hide();
			$( '#mwe-pt-tag-note' ).hide();
		}

		// update the number in the submit button
		$( '#mwe-pt-tag-submit-button' ).text( mw.msg( 'pagetriage-button-add-tag-number', this.selectedTagCount ) );
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
		$( '#mwe-pt-tag-params-' + key ).on( 'click', () => {
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
			{ id: 'mwe-pt-tag-set-param-' + key, class: 'mwe-pt-tag-set-param-button cdx-button' },
			mw.msg( 'pagetriage-button-add-details' )
		);
		buttons += mw.html.element(
			'button',
			{ id: 'mwe-pt-tag-cancel-param-' + key, class: 'cdx-button cdx-button--action-destructive' },
			mw.msg( 'cancel' )
		);
		html += '<div class="mwe-pt-tag-params-form-buttons">' + buttons + '</div>';

		html += '<div id="mwe-pt-tags-params-form-error"></div>';

		// Insert the form content into the flyout
		$( '#mwe-pt-tag-params-form-' + key ).html( html );
		$( '#mwe-pt-tag-params-form-' + key ).show();

		const that = this;
		// Add click even for the Set Parameters button
		$( '#mwe-pt-tag-set-param-' + key ).on( 'click', () => {
			// When setting parameters, we need to make sure that all tags that are duplicated
			// across categories are updated to reflect the param changes made to the tag in
			// the current category. For most tags that are not in the all category, we can find
			// the dupicate tag in the all category by using the previously set allTagKey.
			// However, for tags in the all category, we need to use the destKey and dest
			// (Category) to locate the duplicated tag.
			if ( that.setParams( key, key, cat ) ) {
				if ( tag.dest ) {
					that.setParams( key, tag.destKey, tag.dest );
				} else {
					that.setParams( key, tag.allTagKey, 'all' );
				}
				// Hide the form and show the link to reopen it
				that.hideParamsForm( key );
				that.showParamsLink( key, cat );
			}
		} );

		// Add click even for the Cancel button
		$( '#mwe-pt-tag-cancel-param-' + key ).on( 'click', () => {
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
	 * @param {string} destKey
	 * @param {string} destCat
	 * @return {boolean}
	 */
	setParams: function ( key, destKey, destCat ) {
		const tag = this.selectedTag[ destCat ][ destKey ];
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
	 * Fetch the current article content so that
	 * the tagging module can apply transformations
	 * based on the tags being added.
	 *
	 * @return {Promise<string>} A promise that resolves when the article content has been fetched.
	 */
	fetchArticleContent: function () {
		return new mw.Api().get( {
			action: 'query',
			prop: 'revisions',
			rvprop: 'content',
			rvlimit: 1,
			titles: mw.config.get( 'wgPageName' )
		} ).then( ( data ) => {
			const page = data.query.pages[ Object.keys( data.query.pages )[ 0 ] ];
			return page.revisions[ 0 ][ '*' ];
		} );
	},

	/**
	 * Given a tag, return the wikitext corresponding to the tag based on the
	 * current article wikitext
	 *
	 * @param {string} wikitext
	 * @param {string} tag
	 * @return {string|void} The wikitext corresponding to the tag
	 */
	extractTagFromWikitext: function ( wikitext, tag ) {
		const tagStart = '{{' + tag;
		const startingIndex = wikitext.indexOf( tagStart );

		if ( wikitext.indexOf( tagStart ) === -1 ) {
			return '';
		}

		let templateBraces = 0;

		for ( let i = startingIndex; i < wikitext.length; i++ ) {
			if ( wikitext[ i ] === '{' ) {
				templateBraces++;
			} else if ( wikitext[ i ] === '}' ) {
				templateBraces--;
			}

			if ( templateBraces === 0 ) {
				return wikitext.slice( startingIndex, i + 1 );
			}
		}
	},

	/**
	 * Given a template wrapper and some tag wikitext, extract the wrapper from the article wikitext
	 * (if it is present), add the tags to the wrapper and replace the wrapper in the article
	 * else construct a new wrapper and add the tags to it and place it in the article wikitext.
	 *
	 * For example, if the wrapper is "Multiple issues", the tag wikitext is {{advert}}\n{{peacock}}
	 * and the article wikitext is the following:
	 * ```
	 * {{Multiple issues|
	 * {{notability}}
	 * {{should be deleted}}
	 * }}
	 *
	 * PageTriage is the best.
	 * ```
	 *
	 * the function will find the existing Multiple issues wrapper tag and try to append the
	 * tagWikitext as part of the Multiple issues tag block, so the returned output would be:
	 *
	 * ```
	 * {{Multiple issues|
	 * {{notability}}
	 * {{should be deleted}}
	 * {{advert}}
	 * {{peacock}}
	 * }}
	 *
	 * PageTriage is the best.
	 * ```
	 *
	 * @param {string} articleWikitext
	 * @param {string} wrapper
	 * @param {string} tagWikitext
	 * @param {"top"|"bottom"} position
	 * @param {boolean} shouldWrap
	 * @return {string} Article text with the tag wrapped in the wrapper placed in the appropriate
	 * position
	 */
	addToExistingTags: function ( articleWikitext, wrapper, tagWikitext, position, shouldWrap ) {
		const existingWrapper = this.extractTagFromWikitext( articleWikitext, wrapper );
		if ( existingWrapper ) {
			return articleWikitext.replace(
				existingWrapper,
				existingWrapper.slice( 0, existingWrapper.length - 2 ).trim() + tagWikitext + '\n}}'
			);
		}

		let wrappedWikitext = tagWikitext;

		if ( shouldWrap ) {
			wrappedWikitext = '{{' + wrapper + '|' + tagWikitext + '\n}}';
		}
		if ( position === 'top' ) {
			if ( !this.isRedirect || ( this.isRedirect && tagWikitext !== 'redirectTag' ) ) {
				return wrappedWikitext + '\n' + articleWikitext.replace( /^\s+/, '' );
			}
			return wrappedWikitext + articleWikitext.replace( /^\s+/, '' );
		}
		return articleWikitext.replace( /^\s+/, '' ) + '\n' + wrappedWikitext;
	},

	/**
	 * Submit the selected tags
	 *
	 * @return {jQuery.Promise<void>}
	 */
	submit: function () {
		return this.fetchArticleContent().then( ( wikitext ) => {
			if ( this.model.get( 'page_len' ) < 1000 && this.selectedTagCount > 4 ) {
				// eslint-disable-next-line no-alert
				if ( !confirm( mw.msg( 'pagetriage-add-tag-confirmation', this.selectedTagCount ) ) ) {
					$.removeSpinner( 'tag-spinner' );
					$( '#mwe-pt-tag-submit-button' ).attr( 'disabled', false );
					return;
				}
			}

			let bottomText = '';
			const processed = {};
			const that = this;
			const multipleTags = {};
			const redirectTags = {};
			const tagList = [];
			let multipleTagsText = '',
				multipleRedirectTagsText = '';
			for ( const cat in this.selectedTag ) {
				for ( const tagKey in this.selectedTag[ cat ] ) {
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
								wikitext = '{{' + tagObj.tag + this.buildParams( tagObj ) + '}}\n' + wikitext;
							}
							break;
					}
					processed[ tagKey ] = true;
					if ( cat === 'all' ) {
						processed[ tagObj.destKey ] = true;
					}
					tagList.push( tagObj.tag.toLowerCase() );
				}
			}

			wikitext = wikitext + bottomText;

			// Generate a string of line breaks and templates. For example,
			// \n{{No references}}\n{{Notability}}
			const tagsArray = [];
			for ( const tagKey in multipleTags ) {
				tagsArray.push( '{{' + multipleTags[ tagKey ].tag + this.buildParams( multipleTags[ tagKey ] ) + '}}' );
			}
			multipleTagsText = tagsArray.join( '\n' );
			if ( tagsArray.length > 1 ) {
				multipleTagsText = '\n' + multipleTagsText;
			}

			wikitext = this.addToExistingTags(
				wikitext,
				tagOptions.multiple,
				multipleTagsText,
				'top',
				this.objectPropCount( multipleTags ) > 1
			);

			for ( const tagKey in redirectTags ) {
				multipleRedirectTagsText += '\n{{' + redirectTags[ tagKey ].tag +
				this.buildParams( redirectTags[ tagKey ] ) + '}}';
			}

			if ( this.objectPropCount( redirectTags ) > 0 ) {
				wikitext = this.addToExistingTags(
					wikitext,
					tagOptions.redirectCategoryShell,
					multipleRedirectTagsText,
					'bottom',
					true
				);
			}

			// When applying maintenance tags, reviewer can choose if the page is reviewed or not
			if ( $( '#mwe-pt-checkbox-mark-reviewed' ).is( ':checked' ) ) {
				return new mw.Api().postWithToken( 'csrf', {
					action: 'pagetriageaction',
					pageid: mw.config.get( 'wgArticleId' ),
					// NOTE: if the logic for whether to mark as reviewed is changed,
					//   be sure to also conditionally register actionQueue.mark below
					reviewed: '1',
					skipnotif: '1'
				} )
					.then( () => {
						// Register action for marking the page as reviewed.
						actionQueue.mark = { reviewed: true };

						that.applyTags( wikitext, tagList );
					} )
					.catch( ( _errorCode, data ) => {
						that.handleError( mw.msg( 'pagetriage-mark-as-reviewed-error', data.error.info ) );
					} );
			} else {
				return new mw.Api().postWithToken( 'csrf', {
					action: 'pagetriageaction',
					pageid: mw.config.get( 'wgArticleId' ),
					// NOTE: if the logic for whether to mark as reviewed is changed,
					//   be sure to also conditionally register actionQueue.mark above
					reviewed: '0',
					skipnotif: '1'
				} )
					.then( () => {
						// Register action for marking the page as unreviewed.
						actionQueue.mark = { reviewed: false };

						that.applyTags( wikitext, tagList );
					} )
					.catch( ( _errorCode, data ) => {
						that.handleError( mw.msg( 'pagetriage-mark-as-unreviewed-error', data.error.info ) );
					} );
			}
		} );
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
		const dbname = mw.config.get( 'wgDBname' );
		mw.track( 'counter.MediaWiki.extension.PageTriage.' + dbname + '.viewsToolbar.tags.error' );
		mw.track( 'stats.mediawiki_pagetriage_viewstoolbar_tags_error_total', { wiki: dbname } );

		$.removeSpinner( 'tag-spinner' );
		// Re-enable the submit button (in case it is disabled)
		$( '#mwe-pt-tag-submit-button' ).attr( 'disabled', false );
		// Show error message to the user
		// eslint-disable-next-line no-alert
		alert( msg );
	},

	applyTags: function ( wikitext, tagList ) {
		const that = this;
		const note = $( '#mwe-pt-tag-note-input' ).val().trim();

		new mw.Api().postWithToken( 'csrf', {
			action: 'pagetriagetagging',
			pageid: mw.config.get( 'wgArticleId' ),
			wikitext: wikitext,
			note: note,
			taglist: tagList.join( '|' )
		} )
			.then( () => {
				actionQueue.tags = { tags: tagList };

				if ( note ) {
					actionQueue.tags.note = note;
					that.talkPageNote( note );
				} else {
					mw.pageTriage.actionQueue.runAndRefresh(
						actionQueue,
						that.getDataForActionQueue()
					);
				}
			} )
			.catch( ( _errorCode, data ) => {
				that.handleError( mw.msg( 'pagetriage-mark-as-reviewed-error', data.error.info ) );
			} );
	},

	talkPageNote: function ( note ) {
		const messagePosterPromise = mw.messagePoster.factory.create(
			new mw.Title( this.model.get( 'creator_user_talk_page' ) )
		);

		const pageName = mw.config.get( 'wgPageName' ).replace( /_/g, ' ' );
		const topicTitle = contentLanguageMessage(
			'pagetriage-tags-talk-page-notify-topic-title',
			pageName
		).text();

		note = '{{subst:' + config.TalkPageNoteTemplate.Tags +
			'|1=' + pageName +
			'|2=' + mw.config.get( 'wgUserName' ) +
			'|3=' + note + '}}';

		const that = this;
		return messagePosterPromise.then( ( messagePoster ) => messagePoster.post( topicTitle, note, { tags: 'pagetriage' } ) ).then( () => {
			mw.pageTriage.actionQueue.runAndRefresh( actionQueue, that.getDataForActionQueue() );
		}, () => {
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
