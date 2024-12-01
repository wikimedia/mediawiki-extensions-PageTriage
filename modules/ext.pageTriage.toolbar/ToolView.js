// abstract class for individual tool views.  Basically just a set of defaults.
// extend this to make a new tool.

module.exports = Backbone.View.extend( {
	// These things will probably be overridden with static values.  You can use a function
	// if you want to, though.
	//
	id: 'mwe-pt-info-abstract',
	icon: 'icon_skip.png', // icon to display in the toolbar
	title: 'Abstract tool view', // the title for the flyout window
	scrollable: false, // should the output of render be in a scrollable div?

	// should the content be re-rendered every time the tool is opened, or just rendered the
	// first time?
	reRender: false,

	// These things will likely be overridden with functions.
	//
	// function that returns the number of items to display in an icon badge
	// if null, badge won't be displayed.
	badgeCount: function () {
		return null;
	},

	// function to bind to the icon's click handler
	// if not defined, runs render() and inserts the result into a flyout instead
	// useful for the "next" button, for example
	click: function () {
		if ( this.visible ) {
			this.hide();
		} else {
			this.show();
		}
	},

	// generate the stuff that goes in this tool's flyout.
	render: function () {
		this.$tel.html = 'this is some example html';
	},

	// if you override initialize, make sure you preserve eventBus and moduleConfig
	initialize: function ( options ) {
		this.eventBus = options.eventBus;
		this.moduleConfig = options.moduleConfig || {};
	},

	// ***********************************************************
	// from here down is stuff you probably won't want to override
	tagName: 'div',
	className: 'mwe-pt-tool',
	chromeTemplate: mw.template.get( 'ext.pageTriage.toolbar', 'ToolView.underscore' ),
	visible: false,
	rendered: false,

	show: function () {
		// trigger an event here saying which tool is being opened.
		this.eventBus.trigger( 'showTool', this );

		// close this tool if another tool is opened.
		this.eventBus.bind( 'showTool', function ( tool ) {
			if ( tool !== this ) {
				this.hide();
			}
		}, this );

		// swap the icon
		this.setIcon( 'active' );

		if ( this.reRender || !this.rendered ) {
			// render the content
			this.render();
			this.rendered = true;
		}

		// show the tool flyout
		this.$el.find( '.mwe-pt-tool-flyout' ).show();
		this.$el.find( '.mwe-pt-tool-pokey' ).show();
		this.visible = true;

		// If the toolbar has been dragged to the other side of the screen
		// make sure the flyout opens in the opposite direction.
		const flyoutOffset = this.$el.find( '.mwe-pt-tool-flyout' ).outerWidth() + 8;
		if (
			(
				// For LTR, calculate the value from the left
				$( 'body' ).css( 'direction' ) === 'ltr' &&
				$( '#mwe-pt-toolbar' ).offset().left < flyoutOffset
			) ||
			(
				// For RTL, calculate the value from the right (body width - left offset)
				$( 'body' ).css( 'direction' ) === 'rtl' &&
				( $( 'body' ).outerWidth() - $( '#mwe-pt-toolbar' ).offset().left ) < flyoutOffset
			)
		) {
			this.$el.find( '.mwe-pt-tool-flyout' ).removeClass( 'mwe-pt-tool-flyout-not-flipped' );
			this.$el.find( '.mwe-pt-tool-pokey' ).removeClass( 'mwe-pt-tool-pokey-not-flipped' );
			this.$el.find( '.mwe-pt-tool-flyout' ).addClass( 'mwe-pt-tool-flyout-flipped' );
			this.$el.find( '.mwe-pt-tool-pokey' ).addClass( 'mwe-pt-tool-pokey-flipped' );
		} else {
			this.$el.find( '.mwe-pt-tool-flyout' ).removeClass( 'mwe-pt-tool-flyout-flipped' );
			this.$el.find( '.mwe-pt-tool-pokey' ).removeClass( 'mwe-pt-tool-pokey-flipped' );
			this.$el.find( '.mwe-pt-tool-flyout' ).addClass( 'mwe-pt-tool-flyout-not-flipped' );
			this.$el.find( '.mwe-pt-tool-pokey' ).addClass( 'mwe-pt-tool-pokey-not-flipped' );
		}

		// remove the hover action
		// eslint-disable-next-line no-jquery/no-bind
		this.$icon.unbind( 'mouseenter mouseleave' );
	},

	hide: function () {
		// swap the icon
		this.setIcon( 'normal' );

		// hide the div
		this.$el.find( '.mwe-pt-tool-flyout' ).hide();
		this.$el.find( '.mwe-pt-tool-pokey' ).hide();
		this.visible = false;

		// this listener is only needed when the tool is open
		this.eventBus.unbind( 'showTool', null, this );

		// re-add the hover action
		const that = this;
		// eslint-disable-next-line no-jquery/no-event-shorthand
		this.$icon.hover(
			() => {
				that.setIcon( 'hover' );
			},
			() => {
				that.setIcon( 'normal' );
			}
		);
	},

	place: function () {

		if ( this.disabled ) {
			return null;
		}

		let iconPath;
		if ( this.specialIcon ) {
			iconPath = this.iconPath( 'special' );
		} else {
			iconPath = this.iconPath( 'normal' );
		}

		// return the HTML for the closed up version of this tool.
		this.$el.html( this.chromeTemplate( {
			id: this.id,
			title: this.title,
			iconPath: iconPath
		} ) );

		this.$icon = this.$el.find( '.mwe-pt-tool-icon' );

		// bind a click handler to open it.
		const that = this;
		this.$icon.on( 'click', () => {
			that.click();
		} );

		// and a hover action.
		this.$icon.on( {
			mouseenter: function () {
				that.setIcon( 'hover' );
			},
			mouseleave: function () {
				that.setIcon( 'normal' );
			}
		} );

		if ( this.tooltip ) {
			// The following messages are used here:
			// * pagetriage-toolbar-minimize
			// * pagetriage-info-tooltip
			// * pagetriage-wikilove-tooltip
			// * pagetriage-markunpatrolled
			// * pagetriage-markpatrolled
			// * pagetriage-tags-tooltip
			// * pagetriage-del-tooltip
			// * pagetriage-next-tooltip
			this.$icon.attr( 'title', mw.msg( this.tooltip ) );
		}

		// set up an event for the close button
		this.$el.find( '.mwe-pt-tool-close' ).on( 'click', () => {
			that.hide();
		} );

		// $tel is the "tool element".  put stuff that goes in the tool there.
		this.$tel = this.$el.find( '.mwe-pt-tool-content' );

		if ( this.model ) {
			// If this view works with a model, wait until
			// the model is loaded to set the badge.
			this.model.bind( 'change', this.setBadge, this );
		} else {
			// If no model, set the badge right away
			this.setBadge();
		}

		return this.$el;
	},

	disable: function () {
		// eslint-disable-next-line no-jquery/no-bind
		this.$icon.unbind( 'mouseenter mouseleave click' );
		this.setIcon( 'disabled' );
		this.$icon.css( 'cursor', 'default' );
	},

	iconPath: function ( dir ) {
		return mw.config.get( 'wgExtensionAssetsPath' ) +
			'/PageTriage/modules/ext.pageTriage.toolbar/images/icons/' +
			dir +
			'/' +
			this.icon;
	},

	setIcon: function ( dir ) {
		this.$icon.attr( 'src', this.iconPath( dir ) );
	},

	setBadge: function () {
		const badgeCount = this.badgeCount();
		if ( badgeCount ) {
			this.$el.find( '.mwe-pt-tool-icon-container' ).badge( badgeCount );
		}
	},

	/**
	 * Count the number of properties in an object
	 *
	 * @param {Object} obj
	 * @return {number}
	 */
	objectPropCount: function ( obj ) {
		let count = 0;
		for ( const key in obj ) {
			if ( Object.prototype.hasOwnProperty.call( obj, key ) ) {
				count++;
			}
		}
		return count;
	},

	/**
	 * Get standardized data to send back to callers of mw.pageTriage.actionQueue.
	 *
	 * @param {Object} [data] Additional data to give the hook handler.
	 * @return {Object}
	 */
	getDataForActionQueue: function ( data ) {
		data = data || {};

		// Allow for data.reviewed override, since the caller might
		// have just changed the reviewed status.
		const reviewed = !!( this.model.get( 'patrol_status' ) !== '0' || data.reviewed );

		if ( reviewed ) {
			data.reviewer = data.reviewer || this.model.get( 'reviewer' );
		}

		return Object.assign( {
			pageid: mw.config.get( 'wgArticleId' ),
			title: mw.config.get( 'wgPageName' ),
			creator: this.model.get( 'user_name' ),
			creatorHidden: this.model.get( 'creator_hidden' ),
			reviewed: reviewed
		}, data );
	},

	/**
	 * Creates a hidden quick-search textbox
	 *
	 * @param {string} [tagClassName] The css class which covers all relevant tags
	 * @return {jQuery} A div element containing the text box
	 */
	renderSearchTextBox: function ( tagClassName = 'mwe-pt-tag-row' ) {
		return $( '<div>' ).attr( 'id', this.id + '-search' )
			.addClass( 'mwe-pt-tag-quicksearch' )
			.append(
				$( '<label>' )
					.text( mw.msg( 'pagetriage-tags-quickfilter-label' ) )
					.attr( 'for', this.id + '-search-text' ),
				$( '<input>' )
					.attr( { id: this.id + '-search-text',
						type: 'text' } )
					.on( 'input paste', null, tagClassName, this.filterTags )
			).hide();
	},

	/**
	 * Makes the search textbox visible when there are many tags
	 *
	 * @param {number} [tagCount] Number of tags that can be filtered
	 */
	showSearchTextBox: function ( tagCount ) {
		if ( tagCount > 5 ) {
			$( '#' + this.id + '-search' ).show();
		}
	},

	/**
	 * Hides tags which don't match the input string in the textbox
	 *
	 * @param {Event} [event]
	 */
	filterTags: function ( event ) {
		const searchText = $( '#' + this.id ).val(),
			tagRows = document.getElementsByClassName( event.data );
		for ( let i = 0; i < tagRows.length; i++ ) {
			tagRows[ i ].classList.remove( 'mwe-pt-tag-row-hide' );
			if ( tagRows[ i ].outerHTML.toLowerCase().indexOf( searchText.toLowerCase() ) === -1 ) {
				tagRows[ i ].classList.add( 'mwe-pt-tag-row-hide' );
			}
		}
	}
} );
