// view for the floating toolbar
const { Article, contentLanguageMessages } = require( 'ext.pageTriage.util' );

const config = require( './config.json' );
// create an event aggregator
const eventBus = _.extend( {}, Backbone.Events );
// the current article
const article = new Article( {
	eventBus: eventBus,
	pageId: mw.config.get( 'wgArticleId' ),
	includeHistory: true
} );
// array of tool instances
let tools;

// Used later via articleInfo.js
require( '../external/jquery.badge.js' );

// Add content language messages
contentLanguageMessages.set( require( './contentLanguageMessages.json' ) );

// overall toolbar view
// currently, this is the main application view.
const ToolbarView = Backbone.View.extend( {
	template: mw.template.get( 'ext.pageTriage.toolbar', 'ToolbarView.underscore' ),
	openCurationToolbarLinkId: 't-opencurationtoolbar',

	initialize: function ( options ) {
		this.pageTriageUi = options.pageTriageUi;

		this.openCurationToolbarSelector = '#' + this.openCurationToolbarLinkId;

		const modules = config.PageTriageCurationModules;
		// An array of tool instances to put on the bar, ordered top-to-bottom
		tools = [];

		const MinimizeView = require( './minimize.js' );
		tools.push( new MinimizeView( { eventBus: eventBus, model: article, toolbar: this } ) );

		// article information, wikilove, mark as reviewed
		const potentialTools = [ 'articleInfo', 'wikiLove', 'mark' ];
		// tags and deletion only available when enwiki features are enabled
		if ( config.PageTriageEnableExtendedFeatures ) {
			potentialTools.push( 'tags', 'delete' );
		}
		for ( const index in potentialTools ) {
			const potentialToolName = potentialTools[ index ];
			// potential tool names should correspond to the name for isFlyoutEnabled,
			// the file name (without the .js) for the view's code, and the key to
			// modules with the configuration to use
			if ( this.isFlyoutEnabled( potentialToolName ) ) {
				// FIXME or describe why it is okay
				// eslint-disable-next-line security/detect-non-literal-require
				const ToolViewClass = require( './' + potentialToolName + '.js' );
				tools.push( new ToolViewClass( {
					eventBus: eventBus,
					model: article,
					moduleConfig: modules[ potentialToolName ]
				} ) );
			}
		}

		// next article, should be always on
		const NextView = require( './next.js' );
		tools.push( new NextView( {
			eventBus: eventBus,
			model: article,
			pageTriageUi: this.pageTriageUi
		} ) );
	},

	/**
	 * Check if the flyout is enabled
	 *
	 * @param {string} flyout
	 * @return {boolean}
	 */
	isFlyoutEnabled: function ( flyout ) {
		const modules = config.PageTriageCurationModules;

		// this flyout is disabled for curation toolbar
		if ( typeof modules[ flyout ] === 'undefined' ) {
			return false;
		}

		// this flyout is disabled for current namespace
		return ( modules[ flyout ].namespace.indexOf( mw.config.get( 'wgNamespaceNumber' ) ) !== -1 );
	},

	render: function () {
		// build the empty toolbar.
		this.$el.html( this.template() );

		_.each( tools, ( tool ) => {
			// append the individual tool template to the toolbar's big tool div part
			// this is the icon and hidden div. (the actual tool content)
			$( '#mwe-pt-toolbar-main' ).append( tool.place() );
		} );

		function calculatePosition( element, position ) {
			const toolbarWidth = element.outerWidth( true );
			const toolbarHeight = element.outerHeight( true );
			const windowWidth = $( window ).width();
			const windowHeight = $( window ).height();
			const maxLeft = windowWidth - toolbarWidth;
			const maxTop = windowHeight - toolbarHeight;
			const left = Math.max( 0, Math.min( position.left, maxLeft ) );
			const top = Math.max( 0, Math.min( position.top, maxTop ) );
			return { left: left, top: top };
		}
		// make it draggable
		$( '#mwe-pt-toolbar' ).draggable( {
			start: function ( _event, ui ) {
				$( this ).data( 'startPosition', ui.position );
			},
			drag: function ( _event, ui ) {
				const newPosition = calculatePosition( $( this ), ui.position );
				ui.position.left = newPosition.left;
				ui.position.top = newPosition.top;
			},
			delay: 200, // these options prevent unwanted drags when attempting to click buttons
			distance: 10,
			cancel: '.mwe-pt-tool-content'
		} );

		$( window ).on( 'resize', () => {
			const $toolbar = $( '#mwe-pt-toolbar' );
			const position = { left: parseInt( $toolbar.css( 'left' ), 10 ), top: parseInt( $toolbar.css( 'top' ), 10 ) };
			const newPosition = calculatePosition( $toolbar, position );
			$toolbar.css( newPosition );
		} );

		const that = this;
		// make clicking on the minimized toolbar expand to normal size
		$( '#mwe-pt-toolbar-vertical' ).on( 'click', () => {
			that.maximize( true );
		} );

		// since transform only works in IE 9 and higher, use writing-mode
		// to rotate the minimized toolbar content in older versions
		if ( $.client.test( { msie: [ [ '<', 9 ] ] }, null, true ) ) {
			$( '#mwe-pt-toolbar-vertical' ).css( 'writing-mode', 'tb-rl' );
		}

		// make the close button do something
		$( '.mwe-pt-toolbar-close-button' ).on( 'click', () => {
			that.hide( true );
		} );

		// Auto-resize all textareas as they type.
		$( '#mwe-pt-toolbar' ).off( 'input.mwe-pt-tool-flyout' )
			.on( 'input.mwe-pt-tool-flyout', 'textarea', function () {
				const newHeight = this.scrollHeight + 2, // +2 to account for line-height.
					maxHeight = 152; // Arbitrary, roughly 12 lines of text.

				if ( newHeight > maxHeight ) {
					this.style.height = maxHeight + 'px';
					this.style.overflowY = 'scroll';
				} else {
					this.style.height = 'auto';
					this.style.height = newHeight + 'px';
					this.style.overflowY = 'hidden';
				}
			} );

		// Create left menu link to reopen the toolbar. Hide it initially to avoid a
		// flash of content, we can show it later.
		if ( $( this.openCurationToolbarSelector ).length === 0 ) {
			this.insertLink();
		}

		// Show the toolbar based on saved prefs.
		switch ( mw.user.options.get( 'userjs-curationtoolbar' ) ) {
			case 'hidden':
				this.hide();
				break;
			case 'minimized':
				this.minimize();
				$( '#mw-content-text .patrollink' ).hide();
				break;
			case 'maximized':
			/* falls through */
			default:
				this.maximize();
				$( '#mw-content-text .patrollink' ).hide();
				break;
		}
	},

	hide: function ( savePref ) {
		// hide everything
		$( '#mwe-pt-toolbar' ).hide();
		// reset the curation toolbar to original state
		$( '#mwe-pt-toolbar-inactive' ).css( 'display', 'none' );
		$( '#mwe-pt-toolbar-active' ).css( 'display', 'block' );
		$( '#mwe-pt-toolbar' ).removeClass( 'mwe-pt-toolbar-small' ).addClass( 'mwe-pt-toolbar-big' );
		if ( typeof savePref !== 'undefined' && savePref === true ) {
			this.setToolbarPreference( 'hidden' );
		}
		// Show the Open Page Curation link in the left menu
		$( this.openCurationToolbarSelector ).show();
		// Show hidden patrol link in case they want to use that instead
		$( '#mw-content-text .patrollink' ).show();
	},

	minimize: function ( savePref ) {
		const dir = $( 'body' ).css( 'direction' ),
			toolbarPosCss = dir === 'ltr' ?
				{
					left: 'auto',
					right: 0
				} :
				// For RTL, flip
				{
					left: 0,
					right: 'auto'
				};

		// hide the Open Page Curation link in the left menu
		$( this.openCurationToolbarSelector ).hide();
		// close any open tools by triggering showTool with empty tool param
		eventBus.trigger( 'showTool', '' );
		// hide the regular toolbar content
		$( '#mwe-pt-toolbar-active' ).hide();
		// show the minimized toolbar content
		$( '#mwe-pt-toolbar-inactive' ).show();
		// switch to smaller size
		$( '#mwe-pt-toolbar' ).removeClass( 'mwe-pt-toolbar-big' ).addClass( 'mwe-pt-toolbar-small' )
			// dock to the side of the screen
			.css( toolbarPosCss );
		// set a pref for the user so the minimize state is remembered
		if ( typeof savePref !== 'undefined' && savePref === true ) {
			this.setToolbarPreference( 'minimized' );
		}
	},

	maximize: function ( savePref ) {
		const dir = $( 'body' ).css( 'direction' ),
			toolbarPosCss = dir === 'ltr' ?
				{
					left: 'auto',
					right: 0
				} :
				// For RTL, flip
				{
					left: 0,
					right: 'auto'
				};

		// hide the Open Page Curation link in the left menu
		$( this.openCurationToolbarSelector ).hide();
		// show the entire toolbar, in case it is hidden
		$( '#mwe-pt-toolbar' ).show();
		// hide the "Mark this page as patrolled" link
		$( '#mw-content-text .patrollink' ).hide();
		// hide the minimized toolbar content
		$( '#mwe-pt-toolbar-inactive' ).hide();
		// show the regular toolbar content
		$( '#mwe-pt-toolbar-active' ).show();
		// switch to larger size
		$( '#mwe-pt-toolbar' ).removeClass( 'mwe-pt-toolbar-small' ).addClass( 'mwe-pt-toolbar-big' )
			// reset alignment to the side of the screen (since the toolbar is wider now)
			.css( toolbarPosCss );
		// set a pref for the user so the minimize state is remembered
		if ( typeof savePref !== 'undefined' && savePref === true ) {
			this.setToolbarPreference( 'maximized' );
		}
	},
	setToolbarPreference: function ( state ) {
		return new mw.Api().saveOption( 'userjs-curationtoolbar', state );
	},
	insertLink: function () {
		const that = this,
			pageCurationLink = mw.util.addPortletLink(
				'p-tb',
				'#',
				mw.msg( 'pagetriage-toolbar-linktext' ),
				this.openCurationToolbarLinkId
			);

		if ( !pageCurationLink ) {
			return;
		}
		$( pageCurationLink )
			.hide()
			.on( 'click', function () {
				that.maximize( true );
				this.blur();
				return false;
			} );
	}
} );

module.exports = {
	oldToolbar: function ( options ) {
		const create = function () {
			article.fetch(
				{
					success: function () {
						// create an instance of the toolbar view
						const el = document.getElementById( 'mw-pagetriage-toolbar' );
						const toolbar = new ToolbarView(
							Object.assign( options, { el, eventBus } )
						);
						toolbar.render();
						article.set( 'successfulModelLoading', 1 );
					}
				}
			);
		};
		mw.loader.using( config.PageTriageCurationDependencies ).then( create );
	}
};
