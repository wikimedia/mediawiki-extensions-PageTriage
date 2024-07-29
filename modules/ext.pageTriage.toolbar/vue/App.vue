<template>
	<!-- used for the drag event listener and prevents page interaction while dragging toolbar -->
	<div
		v-show="isDragging === true"
		id="mwe-pt-toolbar-drag-area"
		ref="dragArea"
	></div>
	<div
		v-show="display !== 'hidden'"
		id="mwe-pt-toolbar"
		ref="toolbar"
		:class="displayClass"
		:style="style"
		@pointerdown="dragEnable"
	>
		<div v-show="display === 'maximized'" id="mwe-pt-toolbar-active">
			<div id="mwe-pt-toolbar-main">
				<tool-minimize
					@minimize="minimize"
				></tool-minimize>
				<!-- Individual toolbar icons go here. You can place Vue components in bewteen them. -->
				<span ref="articleInfoTool" class="mwe-pt-hidden"></span>
				<span ref="wikiLoveTool" class="mwe-pt-hidden"></span>
				<span ref="markTool" class="mwe-pt-hidden"></span>
				<span ref="tagsTool" class="mwe-pt-hidden"></span>
				<span ref="deleteTool" class="mwe-pt-hidden"></span>
				<tool-next
					:page="article.attributes"
					:page-triage-ui="pageTriageUi"
				></tool-next>
			</div>
		</div>
		<div v-show="display === 'minimized'" id="mwe-pt-toolbar-inactive">
			<div class="mwe-pt-toolbar-close">
				<div
					class="mwe-pt-toolbar-close-button"
					:title="$i18n( 'pagetriage-toolbar-close' ).text()"
					@click="close"
				></div>
			</div>
			<p
				id="mwe-pt-toolbar-vertical"
				@click="maximize"
			>
				{{ $i18n( 'pagetriage-toolbar-collapsed' ).text() }}
			</p>
		</div>
	</div>
</template>

<script>
/**
 * Curation Toolbar container that supports Vue components and backbone views to enable incremental migration.
 * Designed to completely replace ToolbarView.js upon promotion to the default toolbar experience.
 */

const { Article, contentLanguageMessages } = require( 'ext.pageTriage.util' );
// Add content language messages
contentLanguageMessages.set( require( '../contentLanguageMessages.json' ) );

const { ref } = require( 'vue' ),
	// create an event aggregator
	eventBus = _.extend( {}, Backbone.Events ),
	config = require( '../config.json' ),
	modules = config.PageTriageCurationModules,
	openCurationToolbarLinkId = 't-opencurationtoolbar',
	patrolLinkSelector = '#mw-content-text .patrollink',
	/**
	 * Check if the flyout is enabled
	 *
	 * @param {string} flyout
	 * @return {boolean}
	 */
	isFlyoutEnabled = ( flyout ) => {
		// this flyout is disabled for curation toolbar
		if ( typeof modules[ flyout ] === 'undefined' ) {
			return false;
		}

		// this flyout is disabled for current namespace
		return ( modules[ flyout ].namespace.indexOf( mw.config.get( 'wgNamespaceNumber' ) ) !== -1 );
	};

const ToolMinimize = require( './components/ToolMinimize.vue' );
const ToolNext = require( './components/ToolNext.vue' );

// Tracks position of toolbar during drag.
const pos = {
	start: { x: 0, y: 0 },
	new: { x: 0, y: 0 }
};

// @vue/component
module.exports = {
	components: {
		ToolMinimize,
		ToolNext
	},
	props: {
		pageTriageUi: {
			type: String,
			default: null
		}
	},
	setup: function () {
		return {
			// placeholder references for inserting backbone tools
			articleInfoTool: ref( null ),
			wikiLoveTool: ref( null ),
			markTool: ref( null ),
			tagsTool: ref( null ),
			deleteTool: ref( null ),
			// placeholder reference for drag area
			dragArea: ref( null ),
			// placeholder reference for toolbar
			toolbar: ref( null ),
			// placeholder reference for text dir
			dir: ref( 'ltr' )
		};
	},
	data: function () {
		// setup info to populate backbone model for current article
		const article = new Article( {
			eventBus: eventBus,
			pageId: mw.config.get( 'wgArticleId' ),
			includeHistory: true
		} );
		// Set initial toolbar display state from preference.
		const display = mw.user.options.get( 'userjs-curationtoolbar', 'maximized' );
		const style = {};
		const isDragging = false;
		return {
			article,
			display,
			style,
			isDragging
		};
	},
	computed: {
		displayClass: function () {
			// Possible values:
			// mwe-pt-toolbar-hidden
			// mwe-pt-toolbar-maximized
			// mwe-pt-toolbar-minimized
			return `mwe-pt-toolbar-${ this.display }`;
		}
	},
	methods: {
		// removes inline style and updates display property and user preference
		setToolbarDisplay: function ( display ) {
			this.style = {};
			this.display = display;
			mw.user.options.set( 'userjs-curationtoolbar', display );
			new mw.Api().saveOption( 'userjs-curationtoolbar', display );
		},
		// records the starting position and enables dragging when the pointer is down on the toolbar
		dragEnable: function ( event ) {
			if ( event.which && event.which === 1 ) {
				// starting pointer position
				pos.start.x = event.clientX;
				pos.start.y = event.clientY;
				this.isDragging = true;
				// add move handler; set directly for immediate effect
				this.dragArea.onpointermove = this.doDrag;
			}
		},
		// disables dragging when the pointer is released from the toolbar
		dragDisable: function () {
			this.isDragging = false;
			// remove move handler; unset directly for immediate effect
			this.dragArea.onpointermove = null;
		},
		// tracks the pointer while dragging
		doDrag: function ( event ) {
			event.preventDefault();
			// track pointer position
			pos.new.x = pos.start.x - event.clientX;
			pos.new.y = pos.start.y - event.clientY;
			pos.start.x = event.clientX;
			pos.start.y = event.clientY;
			this.updatePosition();
		},
		resize: function () {
			pos.start = { x: 0, y: 0 };
			pos.new = { x: 0, y: 0 };
			this.updatePosition();
		},
		// actually calculates and updates the position of the toolbar
		updatePosition: function () {
			// calculate toolbar y position, constrained to window
			const maxY = $( window ).height() - this.toolbar.offsetHeight;
			let top = this.toolbar.offsetTop - pos.new.y;
			top = Math.max( top, 0 );
			top = Math.min( top, maxY );
			this.style.top = `${ top }px`;

			// calculate toolbar x position, constrained to window
			const maxX = $( window ).width() - this.toolbar.offsetWidth;
			// ltr sets position relative to right side
			if ( this.dir === 'ltr' ) {
				const offsetRight = maxX - this.toolbar.offsetLeft;
				let right = offsetRight + pos.new.x;
				right = Math.max( right, 0 );
				right = Math.min( right, maxX );
				this.style.right = `${ right }px`;
			// rtl sets position relative to left side
			} else if ( this.dir === 'rtl' ) {
				let left = this.toolbar.offsetLeft - pos.new.x;
				left = Math.max( left, 0 );
				left = Math.min( left, maxX );
				this.style.left = `${ left }px`;
			}
		},
		close: function () {
			this.setToolbarDisplay( 'hidden' );
			// Show the Open Page Curation link in the left menu
			mw.util.showPortlet( openCurationToolbarLinkId );
			// Show closed patrol link in case they want to use that instead
			$( patrolLinkSelector ).show();
		},
		maximize: function () {
			// hide the Open Page Curation link in the left menu
			mw.util.hidePortlet( openCurationToolbarLinkId );
			// hide the "Mark this page as patrolled" link
			$( patrolLinkSelector ).hide();
			// dock to the side of the screen
			this.setToolbarDisplay( 'maximized' );
		},
		minimize: function () {
			// hide the Open Page Curation link in the left menu
			mw.util.hidePortlet( openCurationToolbarLinkId );
			// close any open tools by triggering showTool with empty tool param
			eventBus.trigger( 'showTool', '' );
			// hide the "Mark this page as patrolled" link
			$( patrolLinkSelector ).hide();
			this.setToolbarDisplay( 'minimized' );
		},
		// Create toolbox link to reopen the toolbar; initially hidden.
		insertLink: function () {
			const pageCurationLink = mw.util.addPortletLink(
				'p-tb',
				'#',
				mw.msg( 'pagetriage-toolbar-linktext' ),
				openCurationToolbarLinkId
			);
			if ( !pageCurationLink ) {
				return;
			}
			mw.util.hidePortlet( openCurationToolbarLinkId );
			const maximize = this.maximize;
			pageCurationLink.addEventListener( 'click', () => {
				pageCurationLink.blur();
				maximize();
			} );
		}
	},
	/*
	 * Recreates much of the Toolbarview.js render method for compatibility with backbone tool views
	 */
	mounted: function () {
		// RTL check for drag behavior
		if ( document.querySelector( 'body.rtl' ) ) {
			this.dir = 'rtl';
		}
		// Stop dragging any time the pointer is released, regardless of its location
		document.addEventListener( 'pointerup', this.dragDisable );
		// Update position on resize to keep the toolbar within the viewport
		window.addEventListener( 'resize', this.resize );
		// More verbose than the original loop, but it allows for individual migrations and allows for static module loading.
		if ( isFlyoutEnabled( 'articleInfo' ) ) {
			require( '../../external/jquery.badge.js' );
			const ArticleInfo = require( '../articleInfo.js' );
			const articleInfo = new ArticleInfo( { eventBus: eventBus, model: this.article, moduleConfig: modules.articleInfo } );
			$( this.articleInfoTool ).before( articleInfo.place() );
		}
		if ( isFlyoutEnabled( 'wikiLove' ) ) {
			const WikiLove = require( '../wikiLove.js' );
			const wikiLove = new WikiLove( { eventBus: eventBus, model: this.article, moduleConfig: modules.wikiLove } );
			$( this.wikiLoveTool ).before( wikiLove.place() );
		}
		if ( isFlyoutEnabled( 'mark' ) ) {
			const Mark = require( '../mark.js' );
			const mark = new Mark( { eventBus: eventBus, model: this.article, moduleConfig: modules.mark } );
			$( this.markTool ).before( mark.place() );
		}
		// tags and deletion only available when enwiki features are enabled
		if ( config.PageTriageEnableExtendedFeatures ) {
			if ( isFlyoutEnabled( 'tags' ) ) {
				const Tags = require( '../tags.js' );
				const tags = new Tags( { eventBus: eventBus, model: this.article, moduleConfig: modules.tags } );
				$( this.tagsTool ).before( tags.place() );
			}
			if ( isFlyoutEnabled( 'delete' ) ) {
				const Delete = require( '../delete.js' );
				const deletion = new Delete( { eventBus: eventBus, model: this.article, moduleConfig: modules.delete } );
				$( this.deleteTool ).before( deletion.place() );
			}
		}

		// Auto-resize textareas within tools while typing.
		$( this.toolbar ).off( 'input.mwe-pt-tool-flyout' )
			.on( 'input.mwe-pt-tool-flyout', 'textarea', function () {
				// eslint-disable-next-line vue/no-undef-properties
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

		this.insertLink();

		// Show the toolbar based on stored item.
		switch ( this.display ) {
			case 'hidden':
				this.close();
				break;
			case 'minimized':
				$( patrolLinkSelector ).hide();
				this.minimize();
				break;
			case 'maximized':
				$( patrolLinkSelector ).hide();
				// falls through
			default:
				break;
		}
		this.article.fetch( {
			success: ( function () {
				this.article.set( 'successfulModelLoading', 1 );
			} ).bind( this )
		} );
	}
};
</script>

<style>
#mwe-pt-toolbar-drag-area {
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
}

#mwe-pt-toolbar {
	position: fixed;
	top: 140px;
	z-index: 50;
	padding: 3px 3px 5px;
	background-color: #cacaca;
	border-radius: 4px;
	border: 1px solid #9f9f9f;
	box-shadow: 0 4px 8px rgba( 0, 0, 0, 0.4 );
}

.mw-content-ltr #mwe-pt-toolbar {
	left: auto;
	right: 0;
}

.mw-content-rtl #mwe-pt-toolbar {
	left: 0;
	right: auto;
}

.skin-monobook #mwe-pt-toolbar {
	font-size: 1.5em;
}

.ve-activated #mwe-pt-toolbar {
	display: none;
}

.mwe-pt-toolbar-maximized {
	width: 35px;
}

.mwe-pt-toolbar-minimized {
	width: 15px;
}

#mwe-pt-toolbar-inactive {
	width: 15px;
	min-height: 90px;
	cursor: pointer;
	/* overrides backbone style; not needed once backbone toolbar is removed. */
	display: block;
}

.mwe-pt-toolbar-close {
	width: 12px;
}

.mwe-pt-toolbar-minimized .mwe-pt-toolbar-hidden {
	width: auto;
	text-align: center;
}

.mwe-pt-toolbar-close-button {
	background-image: url( ../images/close.png );
	height: 12px;
	width: 12px;
	margin: 0 auto;
	cursor: pointer;
}

#mwe-pt-toolbar-vertical {
	color: #333;
	border: 0 solid #f00;
	writing-mode: vertical-rl;
	white-space: nowrap;
	display: block;
	bottom: 0;
	width: 20px;
	font-size: 1em;
	font-weight: normal;
	line-height: 1.5em;
}
/* Prevent notification badges from getting in the way of clicking on the toolbar icons. */
.mwe-pt-toolbar-maximized .notification-badge {
	pointer-events: none;
}

</style>
