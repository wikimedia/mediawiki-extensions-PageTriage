// abstract class for individual tool views.  Basically just a set of defaults.
// extend this to make a new tool.

$( function() {
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
		badgeCount: function() {
			return null;
		},

		// function to bind to the icon's click handler
		// if not defined, runs render() and inserts the result into a flyout instead
		// useful for the "next" button, for example
		click: function () {
			if( this.visible ) {
				this.hide();
			} else {
				this.show();
			}
		},

		// generate the stuff that goes in this tool's flyout.
		render: function() {
			this.$tel.html = 'this is some example html';
		},

		// if you override initialize, make sure you preserve eventBus
		initialize: function( options ) {
			this.eventBus = options.eventBus;
		},

		// ***********************************************************
		// from here down is stuff you probably won't want to override
		tagName: "div",
		className: "mwe-pt-tool",
		chromeTemplate: mw.pageTriage.viewUtil.template( { 'view': 'toolbar', 'template': 'toolView.html' } ),
		visible: false,
		rendered: false,
		
		show: function() {
			_this = this;
			// trigger an event here saying which tool is being opened.
			this.eventBus.trigger( 'showTool', this );

			// close this tool if another tool is opened.
			this.eventBus.bind( 'showTool', function( tool ) {
				if( tool !== this ) {
					this.hide();
				}
			}, this );

			// swap the icon
			this.setIcon( 'active' );

			if( this.reRender || ! this.rendered ) {
				// render the content
				this.render();
				this.rendered = true;
			}

			// show the tool flyout
			this.$el.find( '.mwe-pt-tool-flyout' ).show();
			this.$el.find( '.mwe-pt-tool-pokey' ).show();
			this.visible = true;
			
		},

		hide: function() {
			// swap the icon
			this.setIcon( 'normal' );

			// hide the div
			this.$el.find( '.mwe-pt-tool-flyout' ).hide();
			this.$el.find( '.mwe-pt-tool-pokey' ).hide();
			this.visible = false;

			// this listener is only needed when the tool is open
			this.eventBus.unbind( 'showTool', null, this );
		},

		place: function() {
			var _this = this;

			// return the HTML for the closed up version of this tool.
			this.$el.html( this.chromeTemplate( {
				id: this.id,
				title: this.title,
				iconPath: this.iconPath( 'normal' ),
			} ) );

			// bind a click handler to open it.
			this.$el.find( '.mwe-pt-tool-icon' ).click( function() {
				_this.click();
			} );

			// set up an event for the close button
			this.$el.find( '.mwe-pt-tool-close' ).click( function() {
				_this.hide();
			} );
			
			// $tel is the "tool element".  put stuff that goes in the tool there.
			this.$tel = this.$el.find( '.mwe-pt-tool-content' );

			if( this.model ) {
				// If this view works with a model, wait until
				// the model is loaded to set the badge.
				this.model.bind('change', this.setBadge, this);
			} else {
				// If no model, set the badge right away
				this.setBadge();
			}

			return this.$el;
		},

		iconPath: function( dir ) {
			return mw.config.get( 'wgExtensionAssetsPath' ) +
				'/PageTriage/modules/ext.pageTriage.views.toolbar/images/icons/' +
				dir +
				'/' +
				this.icon;
		},

		setIcon: function( dir ) {
			this.$el.find( 'img.mwe-pt-tool-icon' ).attr('src', this.iconPath( dir ) );
		},
		
		setBadge: function() {
			var badgeCount = this.badgeCount();
			if( badgeCount ) {
				this.$el.find( '.mwe-pt-tool-icon-container' ).badger( String( badgeCount ) );
			}
		}

	} );
} );
