// view for display deletion wizard
const { contentLanguageMessage } = require( 'ext.pageTriage.util' );
const { deletionTags: deletionTagOptions } = require( 'ext.pageTriage.tagData' );

// Used to keep track of what actions we want to invoke, and with what data.
const actionQueue = {};

// date wrapper that generates a new Date() object
const DateWrapper = function DateWrapper() {
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

const pageName = mw.config.get( 'wgPageName' ).replace( /_/g, ' ' );

// Deletion tagging
const specialDeletionTagging = {
	afd: {
		buildDeletionTag: function ( tagObj ) {
			if ( !tagObj.subpageNumber ) {
				return '{{subst:afd1}}';
			}
			return '{{subst:afdx|' + tagObj.subpageNumber + '}}';
		},

		buildDiscussionRequest: function ( reason, data ) {
			data.appendtext = '{{subst:afd2|text=' + reason + ' ~~~~|pg=' + pageName + '}}\n';
			data.summary = 'Creating deletion discussion page for [[' + pageName + ']].';
		},

		// eslint-disable-next-line no-unused-vars
		buildLogRequest: function ( oldText, _reason, tagObj, data, _redirectTarget ) {
			const page = tagObj.subpage || pageName;

			oldText += '\n';
			data.text = oldText.replace(
				/(<!-- Add new entries to the TOP of the following list -->\n+)/,
				'$1{{subst:afd3|pg=' + page + '}}\n'
			);
			data.summary = 'Adding [[' + tagObj.prefix + '/' + pageName + ']].';
		},

		getLogPageTitle: function ( prefix ) {
			const date = new DateWrapper();
			return prefix + '/Log/' + date.getYear() + ' ' + date.getMonth() + ' ' + date.getDate();
		}
	},

	'rfd-NPF': {
		buildDiscussionRequest: function () {
			// No-op
		},

		buildLogRequest: function ( oldText, reason, tagObj, data, redirectTarget ) {
			data.text = oldText.replace(
				// FIXME: This is pretty fragile (and English Wikipedia specific).
				/(<!-- Add new entries directly below this line\.? -->)/,
				'$1\n{{subst:rfd2|text=' + reason + '|redirect=' + pageName +
				'|target=' + redirectTarget + '}} ~~~~\n'
			);
		},

		getLogPageTitle: function ( prefix ) {
			const date = new DateWrapper();
			return prefix + '/Log/' + date.getYear() + ' ' + date.getMonth() + ' ' + date.getDate();
		}
	}
};

const ToolView = require( './ToolView.js' );
module.exports = ToolView.extend( {
	id: 'mwe-pt-deletion-wizard',
	icon: 'icon_trash.png',
	title: mw.msg( 'pagetriage-del-title' ),
	tooltip: 'pagetriage-del-tooltip',
	template: mw.template.get( 'ext.pageTriage.toolbar', 'delete.underscore' ),
	deletionTagsOptions: {},
	selectedTag: {},
	selectedCat: '',
	visibleParamsFormCount: 0,

	/**
	 * Initialize data on startup
	 *
	 * @param {Object} options
	 */
	initialize: function ( options ) {
		this.eventBus = options.eventBus;
		this.moduleConfig = options.moduleConfig || {};
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
		if ( typeof dir !== 'string' ) {
			dir = 'normal';
		}
		if ( dir === 'normal' && this.isPageNominatedForDeletion() ) {
			dir = 'special';
		}
		this.$icon.attr( 'src', this.iconPath( dir ) );
	},

	/**
	 * Set up deletion tags. Set 'redirects for discussion' or 'articles for
	 * deletion' depending on whether the article is a redirect.
	 */
	setupDeletionTags: function () {
		this.deletionTagsOptions = deletionTagOptions.main;
		const xfd = this.deletionTagsOptions.xfd;
		// redirect
		if ( Number( this.model.get( 'is_redirect' ) ) === 1 ) {
			xfd.label = xfd.tags.redirectsfordiscussion.label;
			delete xfd.tags.articlefordeletion;
		// non-redirect
		} else {
			xfd.label = xfd.tags.articlefordeletion.label;
			delete xfd.tags.redirectsfordiscussion;
		}
	},

	/**
	 * Render deletion tagging template
	 */
	render: function () {
		const that = this;

		this.setupDeletionTags();
		this.$tel.html( this.template( {
			tags: this.deletionTagsOptions,
			warningNotice: this.model.tagWarningNotice()
		} ) );

		// set the Learn More link URL
		if ( this.moduleConfig.helplink !== undefined ) {
			$( '#mwe-pt-deletion-wizard .mwe-pt-flyout-help-link' )
				.attr( 'href', this.moduleConfig.helplink );
		}

		// add click event for each category
		$( '#mwe-pt-delete-categories' ).find( 'div' ).each( function () {
			const cat = $( $( this ).html() ).attr( 'cat' );
			$( this ).on( 'click', function () {
				that.visibleParamsFormCount = 0;
				that.refreshSubmitButton();

				$( this ).find( 'a' ).trigger( 'blur' );
				that.displayTags( cat );
				return false;
			} ).end();
		} );

		// add click event for tag submission
		$( '#mwe-pt-delete-submit-button' ).button( { disabled: true } )
			.on( 'click', () => {
				$( '#mwe-pt-delete-submit-button' ).button( 'disable' );
				$( '#mwe-pt-delete-submit' ).append( $.createSpinner( 'delete-spinner' ) ); // show spinner
				that.submit();
				return false;
			} ).end();

		// show the first category as default
		// eslint-disable-next-line no-unreachable-loop
		for ( const key in this.deletionTagsOptions ) {
			this.displayTags( key );
			break;
		}
	},

	isPageNominatedForDeletion: function () {
		const deletion = [ 'csd_status', 'prod_status', 'blp_prod_status', 'afd_status' ];

		for ( let i = 0; i < deletion.length; i++ ) {
			if ( this.model.get( deletion[ i ] ) === '1' ) {
				return true;
			}
		}

		return false;
	},

	/**
	 * Checks if the page or talk page has any templates that should halt
	 * the deletion
	 *
	 * @param {Object[]} selectedTag An array with the config options of the
	 * currently selected tag
	 * @return {jQuery.Promise} A promise. Resolves template data, if any
	 * of the rejection templates are found. Resolves to false otherwise
	 */
	isAnyRejectionTemplatePresent: function ( selectedTag ) {
		for ( const key in selectedTag ) {
			if ( selectedTag[ key ].rejectionTemplates === undefined ) {
				continue;
			}

			const promises = [];

			// check for templates on the page
			if ( selectedTag[ key ].rejectionTemplates.article !== undefined ) {
				promises.push( this.areAnyOfTheseTemplatesPresentOnPage(
					selectedTag[ key ].rejectionTemplates.article,
					new mw.Title( mw.config.get( 'wgPageName' ) ).getPrefixedText() )
				);
			}

			// check for templates on the talk page
			if ( selectedTag[ key ].rejectionTemplates.talkPage !== undefined ) {
				promises.push( this.areAnyOfTheseTemplatesPresentOnPage(
					selectedTag[ key ].rejectionTemplates.talkPage,
					new mw.Title( mw.config.get( 'wgPageName' ) ).getTalkPage().getPrefixedText() )
				);
			}

			return $.when.apply( [], promises )
				.then( function () {
					// the return values of each of the functions called from the promises[] array
					// is stored in a "magic" array called arguments.This needs to be changed ASAP
					// when we move from using jQuery promises to ES6 Promise type of calls
					for ( let i = 0; i < arguments.length; i++ ) {
						if ( arguments[ i ].result !== false ) {
							return arguments[ i ].template;
						}
					}
					return false;
				} );
		}
		return $.Deferred().resolve( false );
	},

	/**
	 * Checks if any of a list of templates is present on a page
	 *
	 * @param {string[]} templateArray an array of template names
	 * @param {mw.Title} pageTitle the title of the page to be checked
	 * @return {jQuery.Promise} A promise. Resolves template data if
	 * any of the templates are found
	 */
	areAnyOfTheseTemplatesPresentOnPage: function ( templateArray, pageTitle ) {
		return new mw.Api().get( {
			action: 'query',
			titles: pageTitle,
			prop: 'templates',
			tltemplates: 'Template:' + templateArray.join( '|Template:' ),
			format: 'json'
		} )
			.then( ( data ) => {
				const key = Object.keys( data.query.pages )[ 0 ];
				const templates = data.query.pages[ key ].templates;
				const numTemplates = templates && templates.length;
				if ( numTemplates ) {
					return { result: true, template: templates[ 0 ].title };
				}
				return { result: false };
			} )
			.catch( () => ( { result: false } ) );
	},

	/**
	 * Build deletion tag check/radio and label
	 *
	 * @param {string} key
	 * @param {Object} tagSet
	 * @param {string} elementType
	 * @return {string}
	 */
	buildTagHTML: function ( key, tagSet, elementType ) {
		// build the checkbox or radio
		const checkbox = mw.html.element(
			'input',
			{
				name: 'mwe-pt-delete',
				type: elementType,
				value: tagSet[ key ].tag,
				class: 'mwe-pt-delete-checkbox',
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
		const that = this,
			tagSet = this.deletionTagsOptions[ cat ].tags,
			elementType = this.deletionTagsOptions[ cat ].multiple ? 'checkbox' : 'radio',
			$tagList = $( '<div>' ).attr( 'id', 'mwe-pt-delete-list' );
		let tagCount = 0;

		// unselect any previously selected tags and disable submit button
		this.selectedTag = {};
		$( '#mwe-pt-delete-submit-button' ).button( 'disable' );

		if ( this.deletionTagsOptions[ cat ].desc ) {
			const tagDesc = '<div id="mwe-pt-delete-category-desc">' + mw.html.escape( this.deletionTagsOptions[ cat ].desc ) + '</div>';
			$tagList.append( tagDesc );
		}

		$( '#mwe-pt-delete' ).empty();
		// highlight the active category
		$( '.mwe-pt-delete-category' ).removeClass( 'mwe-pt-active' );
		$( '#mwe-pt-category-' + cat ).addClass( 'mwe-pt-active' );
		$( '.mwe-pt-delete-category .mwe-pt-category-pokey' ).hide();
		$( '#mwe-pt-category-' + cat + ' .mwe-pt-category-pokey' ).show();
		$( '#mwe-pt-delete' ).append( this.renderSearchTextBox( 'mwe-pt-delete-row' ) );
		$( '#mwe-pt-delete' ).append( $tagList );

		for ( const key in tagSet ) {

			// Keep a running total of tags in the category
			if ( Object.prototype.hasOwnProperty.call( tagSet, key ) ) {
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

			$( '#mwe-pt-delete-' + key + ', #mwe-pt-checkbox-delete-' + key ).on( 'click', function () {
				// Extract the tag key from the id of whatever was clicked on
				const tagKeyMatches = $( this ).attr( 'id' ).match( /.*-delete-(.*)/ ),
					tagKey = tagKeyMatches[ 1 ];

				// if user unchecks a checkbox and there is an adjacent paramsForm
				// that will be closed by this action, decrease the counter
				if ( $( '#mwe-pt-delete-params-form-' + tagKey ).css( 'display' ) === 'block' ) {
					that.visibleParamsFormCount--;
					that.refreshSubmitButton();
				}

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
					for ( const param in tagSet[ tagKey ].params ) {
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
			} ).end();
		}

		// If there is only one tag in the category, go ahead and select it.
		if ( tagCount === 1 ) {
			$( '#mwe-pt-delete .mwe-pt-delete-checkbox' ).trigger( 'click' );
		}
		this.showSearchTextBox( tagCount );
	},

	/**
	 * Refresh the submit button
	 */
	refreshSubmitButton: function () {
		// Do not display the submit button until all visible paramsForms have
		// had their "Add details" buttons clicked. T238025, T313108
		if ( this.objectPropCount( this.selectedTag ) > 0 && this.visibleParamsFormCount === 0 ) {
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
		const tag = this.selectedTag[ key ];

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

		const link = mw.html.element(
			'a',
			{
				href: '#',
				id: 'mwe-pt-delete-params-' + key
			},
			// The following messages are used here:
			// * pagetriage-button-add-details
			// * pagetriage-button-edit-details
			mw.msg( 'pagetriage-button-' + text + '-details' )
		);
		$( '#mwe-pt-delete-params-link-' + key ).html( '+&#160;' + link );
		// Add click event to the link that shows the param form
		const that = this;
		$( '#mwe-pt-delete-params-' + key ).on( 'click', () => {
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
		for ( const key in obj ) {
			this.hideParamsLink( key );
		}
	},

	/**
	 * Show the parameters form. Typically contains a label, a text input or
	 * textarea, an "Add details" button, and a "Cancel" button. It collects
	 * additional input from the user regarding a tag.
	 *
	 * @param {string} key
	 */
	showParamsForm: function ( key ) {
		const that = this;
		const tag = this.selectedTag[ key ];
		let html = '',
			firstField = '';

		this.visibleParamsFormCount++;
		this.refreshSubmitButton();

		this.hideParamsLink( key );

		for ( const param in tag.params ) {
			const paramObj = tag.params[ param ];
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
				class: 'mwe-pt-delete-set-param-button ui-button-red'
			},
			mw.msg( 'pagetriage-button-add-details' )
		);
		html += mw.html.element(
			'button',
			{
				id: 'mwe-pt-delete-cancel-param-' + key,
				class: 'ui-button-red'
			},
			mw.msg( 'cancel' )
		);

		html += '<div id="mwe-pt-delete-params-form-error"></div>';

		// Insert the form content into the flyout
		$( '#mwe-pt-delete-params-form-' + key ).html( html );
		$( '#mwe-pt-delete-params-form-' + key ).show();

		// Add click event for the paramsForm "Add details" button
		$( '#mwe-pt-delete-set-param-' + key ).button().on( 'click', () => {
			if ( that.setParams( key ) ) {
				that.visibleParamsFormCount--;
				that.refreshSubmitButton();

				// Hide the form and show the link to reopen it
				that.hideParamsForm( key );
				that.showParamsLink( key );
			}
		} );

		// Add click event for the paramsForm "Cancel" button
		$( '#mwe-pt-delete-cancel-param-' + key ).button().on( 'click', () => {
			for ( const param in tag.params ) {
				that.visibleParamsFormCount--;
				that.refreshSubmitButton();

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
		} );

		// If there is an input field, focus the cursor on it
		if ( firstField ) {
			$( '#' + firstField ).trigger( 'focus' );
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
		const tag = this.selectedTag[ key ];
		for ( const param in tag.params ) {
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
		let paramVal = '';
		for ( const param in obj.params ) {
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
	 *
	 * @return {jQuery.Promise|void} A promise that resolves either when the page is
	 * tagged or if an error occurs.
	 */
	submit: function () {
		// no tag to submit
		if ( this.objectPropCount( this.selectedTag ) === 0 ) {
			return;
		}

		// check if page is already nominated for deletion
		if ( this.isPageNominatedForDeletion() ) {
			this.handleError( mw.msg( 'pagetriage-tag-deletion-error' ) );
			return;
		}

		const that = this,
			promises = [];

		// check for any missing parameters
		// eslint-disable-next-line no-jquery/no-each-util
		$.each( this.selectedTag, ( key, tagObj ) => {
			for ( const param in tagObj.params ) {
				if (
					tagObj.params[ param ].input === 'required' &&
					!tagObj.params[ param ].value
				) {
					that.handleError(
						mw.msg(
							'pagetriage-tags-param-missing-required',
							tagObj.tag
						) );
					return;
				}
			}

			if ( tagObj.usesSubpages ) {
				promises.push( that.pickDiscussionPageName( tagObj.prefix )
					.then( ( data ) => {
						tagObj.subpage = data.page;
						tagObj.subpageNumber = data.number;
					} )
				);
			}
		} );

		// reviewed value must be either '0' or '1'
		const markAsReviewed = this.deletionTagsOptions[ this.selectedCat ].reviewed || '0';
		// Wait until all discussion page names picked.
		return this.isAnyRejectionTemplatePresent( this.selectedTag )
			.then( ( rejectionTemplateFound ) => {
				if ( rejectionTemplateFound !== false ) {
					return $.Deferred().reject( 'previousdeletion', rejectionTemplateFound );
				}
			} )
			.then( $.when.apply( null, promises ) )
			// Applying deletion tags should mark the page as reviewed depending on the selected tag's
			// reviewed option. If it is not set then the page will be marked as not reviewed.
			.then( () => new mw.Api().postWithToken( 'csrf', {
				action: 'pagetriageaction',
				pageid: mw.config.get( 'wgArticleId' ),
				// reviewed value must be either '0' or '1'
				reviewed: markAsReviewed,
				skipnotif: '1'
			} ) )
			.then( () => {
				if ( markAsReviewed === '1' ) {
					// Page was also marked as reviewed, so we want to fire the action for that, too.
					// The 'reviewed' and 'reviewer' attributes on the model are not yet populated,
					// so we have to pass those in manually.
					actionQueue.mark = {
						reviewed: true,
						reviewer: mw.config.get( 'wgUserName' )
					};
				}
				actionQueue.delete = { tags: that.selectedTag };

				const rootPromise = $.Deferred();
				// End of the promise chain.
				let chainEnd = rootPromise;

				const isXFD = !that.deletionTagsOptions[ that.selectedCat ].multiple;
				if ( isXFD ) {
					for ( const key in that.selectedTag ) {
						const tagObj = that.selectedTag[ key ];
						if ( tagObj.prefix ) {
							// Handles writing to the XFD daily log and creating the XFD page. This
							// code path is only used for the XFD options (AFD for mainspace, RFD
							// for redirects)
							chainEnd = chainEnd
								.then( that.shouldLog.bind( that, tagObj ) )
								.then( that.addToLog.bind( that ) )
								.then( that.makeDiscussionPage.bind( that, tagObj ) );
							break;
						}
					}
				}
				// Handles tagging the article with a deletion tag and notifying the
				// creator on their user talk page. This code path is used by all
				// deletion options (CSD, PROD, XFD).
				// Functions using `this` must be bound to avoid losing context.
				chainEnd = chainEnd
					.then( that.tagPage.bind( that ) )
					.then( that.notifyUser.bind( that ) )
					.then( that.tagTalkPage.bind( that ) )
					.then( mw.pageTriage.actionQueue.runAndRefresh.bind(
						null, actionQueue, that.getDataForActionQueue()
					) )
					.catch( that.handleError );

				// Begin running the promise chain.
				rootPromise.resolve();
				return chainEnd;
			} )
			.catch( ( _errorCode, data ) => {
				if ( _errorCode === 'previousdeletion' ) {
					// isAnyRejectionTemplatePresent
					that.handleError( mw.msg( 'pagetriage-tag-previousdeletion-error', data ) );
				} else {
					that.handleError( mw.msg( 'pagetriage-mark-as-reviewed-error', data.error.info ) );
				}
			} );
	},

	/**
	 * Handle an error occurring after submit
	 *
	 * @param {string|Error} msg The message to display
	 */
	handleError: function ( msg ) {
		if ( msg instanceof Error ) {
			msg = msg.message;
		}

		// Log error to WikimediaEvents
		const skin = mw.config.get( 'skin' );
		const dumpOfTag = JSON.stringify( this.selectedTag );
		let errorMessage = 'PageTriage error type: ' + msg + '\n' +
			'File: delete.js\n' +
			'Page name: ' + pageName + '\n' +
			'Skin: ' + skin + '\n' +
			'Dump of this.selectedTag: ' + dumpOfTag;
		errorMessage = errorMessage.slice( 0, 1000 );
		mw.log.error( errorMessage );
		const err = new Error( errorMessage );
		err.name = 'pageTriageHandleError';
		const sitename = mw.config.get( 'wgDBname' );
		mw.track( 'counter.MediaWiki.extension.PageTriage.' + sitename + '.viewsToolbar.delete.error' );

		$.removeSpinner( 'delete-spinner' );
		// Re-enable the submit button (in case it is disabled)
		$( '#mwe-pt-delete-submit-button' ).button( 'enable' );
		// Show error message to the user
		// eslint-disable-next-line no-alert
		alert( msg );
	},

	/**
	 * Add tag template (if relevant) to the article's talk page
	 *
	 * @return {jQuery.Promise} A promise. Resolves if successful, rejects with
	 * an `Error` if not.
	 */
	tagTalkPage: function () {
		// eslint-disable-next-line no-unreachable-loop
		for ( const key in this.selectedTag ) {
			const tagObj = this.selectedTag[ key ];
			if ( !( 'articletalkpagenotiftpl' in tagObj ) ||
				tagObj.articletalkpagenotiftpl === '' ) {
				break;
			}

			const template = tagObj.articletalkpagenotiftpl;
			const paramsText = '|nom=' + mw.config.get( 'wgUserName' ) + '|nomdate={{subst:#time: Y-m-d}}';
			const text = '{{' + template + paramsText + '}}\n';
			const talkTitle = ( new mw.Title( mw.config.get( 'wgPageName' ) ) ).getTalkPage().toText();

			return new mw.Api().postWithToken( 'csrf', {
				action: 'edit',
				title: talkTitle,
				prependtext: text,
				section: 0,
				summary: 'Adding {{' + template + '}}',
				tags: 'pagetriage'
			} )
				.catch( ( errorCode ) => {
					throw new Error( errorCode + mw.msg( 'pagetriage-tagging-error' ) );
				} );
		}
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
	 * Add deletion tag template to the page
	 *
	 * @return {jQuery.Promise|void} A promise. Resolves if successful, rejects with
	 * an `Error` if not. The resolved promise is an Object with the key `tagCount`
	 * (the number of tags added to the page) and the key `tagKey` (the key
	 * of the tag added to the page).
	 */
	tagPage: function () {
		const that = this,
			tagList = [],
			count = this.objectPropCount( this.selectedTag );
		let text = '',
			tagText = '',
			paramsText = '';

		if ( count === 0 ) {
			return;
		}

		let key;
		// for multiple tags, they must be in db-xxx format, when combining them in
		// db-multiple, remove 'db-' from each individual tags
		for ( key in this.selectedTag ) {
			const tagObj = this.selectedTag[ key ];
			let tempTag = tagObj.tag;
			const tagging = specialDeletionTagging[ tagObj.tag ];

			if ( !( 'discussionPage' in tagObj ) ||
				tagObj.discussionPage === ''
			) {
				tagList.push( tagObj.tag.toLowerCase() );
			} else {
				tagList.push( '[[' + tagObj.discussionPage + ']]' );
			}

			if ( count > 1 ) {
				if ( tagObj.code !== undefined ) {
					tempTag = tagObj.code;
				} else {
					tempTag = tempTag.replace( /^db-/gi, '' );
				}
			} else {
				// Some deletion types have their custom building routine
				if ( tagging && tagging.buildDeletionTag ) {
					text = tagging.buildDeletionTag( tagObj );
					continue;
				}
				// this template must be substituted
				if ( tagObj.subst ) {
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
			paramsText += that.buildParams( tagObj );
		}

		if ( count === 1 ) {
			if ( text === '' ) {
				text = '{{' + tagText + paramsText + '}}';
			}
		} else {
			text = '{{' + deletionTagOptions.multiple.tag + '|' + tagText + paramsText + '}}';
		}

		return this.fetchArticleContent().then( ( wikitext ) => new mw.Api().postWithToken( 'csrf', {
			action: 'pagetriagetagging',
			pageid: mw.config.get( 'wgArticleId' ),
			wikitext: text + wikitext,
			deletion: 1,
			taglist: tagList.join( '|' )
		} )
			// To be passed into `addToLog`.
			.then( () => ( { tagCount: count, tagKey: key } )
			)
			.catch( ( errorCode ) => {
				if ( errorCode === 'pagetriage-tag-deletion-error' ) {
					throw new Error( mw.msg( 'pagetriage-tag-deletion-error' ) );
				} else {
					throw new Error( mw.msg( 'pagetriage-tagging-error' ) );
				}
			} ) );
	},

	/**
	 * Notify the user on talk page
	 *
	 * @param {Object} data The data returned by `tagPage`
	 * @param {number} data.count The number of deletion tags added
	 * @param {string} data.key The key of the added deletion tag (if only one tag was added)
	 * @return {jQuery.Promise|void} A promise. Resolves if successful, rejects with
	 * an `Error` if not.
	 */
	notifyUser: function ( data ) {
		const count = data.tagCount,
			key = data.tagKey;

		if ( count === 0 || !this.selectedTag[ key ] ) {
			return;
		}

		let selected,
			paramsText = '';
		// use generic template for multiple deletion tag
		if ( count > 1 ) {
			selected = deletionTagOptions.multiple;
		} else {
			selected = this.selectedTag[ key ];
			paramsText = this.buildParams( this.selectedTag[ key ] );
			if ( selected.usesSubpages && selected.subpage ) {
				paramsText += '|' + selected.subpage;
			}
		}

		const topicTitleKey = selected.talkpagenotiftopictitle;
		const templateName = selected.talkpagenotiftpl;
		// If a talkpagenotiftopictitle and a talkpagenotiftpl is not associated
		// with a deletion tag we should not be sending a talk page notification for
		// that specific tag. Instead return a blank promise and continue with execution.
		if ( !topicTitleKey && !templateName ) {
			return $.Deferred().resolve();
		}
		const topicTitle = ( !topicTitleKey && templateName ) ? '' : contentLanguageMessage( topicTitleKey, pageName ).text();
		const template = '{{subst:' + templateName + '|' + pageName + paramsText + '}}';
		if ( this.model.get( 'user_name' ) ) {
			const messagePosterPromise = mw.messagePoster.factory.create(
				new mw.Title(
					this.model.get( 'creator_user_talk_page' )
				)
			);
			return messagePosterPromise.then( ( messagePoster ) => messagePoster.post( topicTitle, template, { tags: 'pagetriage' } ) ).catch( () => {
				throw new Error( mw.msg( 'pagetriage-del-talk-page-notify-error' ) );
			} );
		}
		// Return a blank resolved promise to proceed with execution.
		return $.Deferred().resolve();
	},

	/**
	 * Check if a log entry should be saved, and return the data for saving the entry
	 * if so.
	 *
	 * @param {Object} tagObj
	 * @return {jQuery.Promise} A promise. Resolves if successful, rejects with
	 * an `Error` if not. Resolves log append data if the page will be appended to
	 * the log, `undefined` otherwise.
	 */
	shouldLog: function ( tagObj ) {
		const title = specialDeletionTagging[ tagObj.tag ].getLogPageTitle( tagObj.prefix );

		return new mw.Api().get( {
			action: 'query',
			prop: 'revisions',
			titles: title,
			rvprop: 'content'
		} )
			.then( ( data ) => {
				if ( data && data.query && data.query.pages ) {
					for ( const i in data.query.pages ) {
						// If log page is missing, skip ahead to making the AFD page
						if ( i !== '-1' ) {
							return {
								title: title,
								oldText: data.query.pages[ i ].revisions[ 0 ][ '*' ],
								tagObj: tagObj
							};
						}
					}
				}
			} )
			.catch( () => {
				// Don't log.
			} );
	},

	/**
	 * Add a page to the log
	 *
	 * @param {Object} data The data returned by `shouldLog`
	 * @param {string} data.title The title of the log page
	 * @param {string} data.oldText The current content of the log page
	 * @param {Object} data.tagObj
	 * @return {jQuery.Promise|void} A promise. Resolves if successful, rejects with
	 * an `Error` if not.
	 */
	addToLog: function ( data ) {
		if ( !data ) {
			// No data returned or false, skip adding to log.
			return;
		}

		const title = data.title,
			oldText = data.oldText,
			tagObj = data.tagObj,
			request = {
				action: 'edit',
				title: title,
				tags: 'pagetriage'
			};

		tagObj.discussionPage = title;
		specialDeletionTagging[ tagObj.tag ].buildLogRequest(
			oldText,
			tagObj.params[ '1' ].value,
			tagObj,
			request,
			this.model.get( 'redirect_target' )
		);

		if ( request.text === oldText ) {
			throw new Error( mw.msg( 'pagetriage-del-log-page-adding-error' ) );
		}

		return new mw.Api().postWithToken( 'csrf', request )
			.then( ( editData ) => {
				if ( !editData.edit || editData.edit.result !== 'Success' ) {
					throw new Error( mw.msg( 'pagetriage-del-log-page-adding-error' ) );
				}
			} )
			.catch( () => {
				throw new Error( mw.msg( 'pagetriage-del-log-page-adding-error' ) );
			} );
	},

	/**
	 * Generate an AFD discussion page
	 *
	 * @param {Object} tagObj
	 * @return {jQuery.Promise|void} A promise. Resolves if successful, rejects with
	 * an `Error` if not.
	 */
	makeDiscussionPage: function ( tagObj ) {
		if ( !tagObj.discussion ) {
			// Does not warrant a discussion page.
			return;
		}

		const title = tagObj.prefix + '/' + ( tagObj.subpage || pageName ),
			request = {
				action: 'edit',
				title: title,
				watchlist: 'watch',
				tags: 'pagetriage'
			};

		tagObj.discussionPage = title;
		if ( !specialDeletionTagging[ tagObj.tag ] ) {
			// T313303
			throw new Error( 'tagObj.tag is not an allowed value ~ ' + tagObj.tag );
		}

		specialDeletionTagging[ tagObj.tag ].buildDiscussionRequest( tagObj.params[ '1' ].value, request );

		return new mw.Api().postWithToken( 'csrf', request )
			.catch( () => {
				throw new Error( mw.msg( 'pagetriage-del-discussion-page-adding-error' ) );
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
						id: 'mwe-pt-delete-params-' + key + '-' + name
					}
				);
				break;
			case 'textarea':
				if ( obj.label ) {
					html += mw.html.element(
						'div',
						{ class: 'mwe-pt-delete-params-question' },
						obj.label
					);
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
				html += mw.html.escape( obj.label ) + ' ';
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
	},

	pickDiscussionPageName: function ( prefix ) {
		const that = this,
			baseTitle = prefix + '/' + pageName,
			baseTitleNoNS = new mw.Title( baseTitle ).getMainText();

		return new mw.Api().get( {
			action: 'query',
			// Check whether first nomination exists
			titles: baseTitle,
			// And subsequent ones, too
			list: 'allpages',
			apnamespace: 4, // NS_PROJECT
			apprefix: baseTitleNoNS + ' (',
			aplimit: 'max',
			formatversion: 2
		} )
			.then( ( data ) => {
				const pages = {};

				if ( !data || !data.query ) {
					throw new Error( 'API error' );
				}
				if ( data.query.pages ) {
					const page = data.query.pages[ 0 ];
					if ( page && page.title && !page.missing ) {
						pages[ page.title ] = true;
					}
				}
				if ( data.query.allpages ) {
					for ( const page in data.query.allpages ) {
						pages[ data.query.allpages[ page ].title ] = true;
					}
				}

				if ( !pages[ baseTitle ] ) {
					return { page: pageName, number: undefined };
				}
				for ( let i = 2; i < 50; i++ ) {
					const suffix = ' (' + that.getNumeral( i ) + ' nomination)';
					if ( !pages[ baseTitle + suffix ] ) {
						return { page: pageName + suffix, number: that.getNumeral( i ) };
					}
				}
				// If a page was nominated over 50 times (API limit for unprivileged users),
				// there's something suspicious going on
				throw new Error( 'Couldn\'t pick nomination page name' );
			} );
	},

	/**
	 * Convert an integer into its ordinal form e.g. 2 => 2nd
	 *
	 * @param {number} num the integer that needs to be converted
	 * @return {string} ordinal form of the input integer
	 */
	getNumeral: function ( num ) {
		if ( typeof num !== 'number' ) {
			num = parseInt( num );
		}
		switch ( num ) {
			case 1:
				return '1st';
			case 2:
				return '2nd';
			case 3:
				return '3rd';
			default:
				if ( num <= 20 ) {
					return num + 'th';
				}
		}

		return Math.floor( num / 10 ) + this.getNumeral( num % 10 );
	}
} );
