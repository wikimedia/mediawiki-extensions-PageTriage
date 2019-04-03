// abstract class for individual tool views.  Basically just a set of defaults.
// extend this to make a new tool.

$( function () {
	mw.pageTriage.ToolView = Backbone.View.extend( {
		// These things will probably be overridden with static values.  You can use a function
		// if you want to, though.
		//
		id: 'mwe-pt-info-abstract',
		icon: 'icon_skip.png', // icon to display in the toolbar
		title: 'Abstract tool view', // the title for the flyout window
		scrollable: false, // should the output of render be in a scrollable div?

		// should the content be re-rendered every time the tool is opened, or just rendered the first time?
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

		// if you override initialize, make sure you preserve eventBus
		initialize: function ( options ) {
			this.eventBus = options.eventBus;
		},

		// ***********************************************************
		// from here down is stuff you probably won't want to override
		tagName: 'div',
		className: 'mwe-pt-tool',
		chromeTemplate: mw.template.get( 'ext.pageTriage.views.toolbar', 'toolView.underscore' ),
		visible: false,
		rendered: false,

		show: function () {
			var flyoutOffset;

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
			flyoutOffset = this.$el.find( '.mwe-pt-tool-flyout' ).outerWidth() + 8;
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
			// eslint-disable-next-line no-jquery/no-unbind
			this.$icon.unbind( 'mouseenter mouseleave' );
		},

		hide: function () {
			var that = this;

			// swap the icon
			this.setIcon( 'normal' );

			// hide the div
			this.$el.find( '.mwe-pt-tool-flyout' ).hide();
			this.$el.find( '.mwe-pt-tool-pokey' ).hide();
			this.visible = false;

			// this listener is only needed when the tool is open
			this.eventBus.unbind( 'showTool', null, this );

			// re-add the hover action
			// eslint-disable-next-line no-jquery/no-event-shorthand
			this.$icon.hover(
				function () {
					that.setIcon( 'hover' );
				},
				function () {
					that.setIcon( 'normal' );
				}
			);
		},

		place: function () {
			var iconPath,
				that = this;

			if ( this.disabled ) {
				return null;
			}

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
			this.$icon.on( 'click', function () {
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
				this.$icon.attr( 'title', mw.msg( this.tooltip ) );
			}

			// set up an event for the close button
			this.$el.find( '.mwe-pt-tool-close' ).on( 'click', function () {
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
			// eslint-disable-next-line no-jquery/no-unbind
			this.$icon.unbind( 'mouseenter mouseleave click' );
			this.setIcon( 'disabled' );
			this.$icon.css( 'cursor', 'default' );
		},

		iconPath: function ( dir ) {
			return mw.config.get( 'wgExtensionAssetsPath' ) +
				'/PageTriage/modules/ext.pageTriage.views.toolbar/images/icons/' +
				dir +
				'/' +
				this.icon;
		},

		setIcon: function ( dir ) {
			this.$icon.attr( 'src', this.iconPath( dir ) );
		},

		setBadge: function () {
			var badgeCount = this.badgeCount();
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
			var key,
				count = 0;
			for ( key in obj ) {
				if ( Object.prototype.hasOwnProperty.call( obj, key ) ) {
					count++;
				}
			}
			return count;
		}

	} );
} );
