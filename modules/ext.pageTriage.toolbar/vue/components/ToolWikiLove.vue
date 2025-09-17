<template>
	<div id="mwe-pt-wikilove" class="mwe-pt-tool">
		<tool-icon
			:title="$i18n( 'pagetriage-wikilove-tooltip' ).text()"
			file="wikilove"
			:is-open="active"
			@click="eventBus.trigger( 'showTool', this )"
		></tool-icon>

		<tool-flyout
			:active
			:title="$i18n( 'wikilove' ).text()"
			:help-link
			@closed="active = false">
			<template #content>
				<div>{{ $i18n( 'pagetriage-wikilove-helptext' ).text() }}</div>

				<div class="mwe-pt-article-contributor-list">
					<cdx-checkbox
						v-for="editor in editors"
						:key="editor.username"
						v-model="selected"
						:input-value="editor.username">
						<a :href="getEditorUrl( editor )">{{ editor.username }}</a>
						<span class="mwe-pt-info-text">{{ getEditorInfo( editor ) }}</span>
					</cdx-checkbox>
				</div>

				<cdx-button
					action="progressive"
					:disabled="selected.length < 1"
					@click="send">
					{{ $i18n( 'wikilove-button-send' ).text() }}
				</cdx-button>
			</template>
		</tool-flyout>
	</div>
</template>

<script>
const { ref } = require( 'vue' );
const { CdxButton, CdxCheckbox } = require( '../../../codex.js' );
const ToolFlyout = require( './ToolFlyout.vue' );
const ToolIcon = require( './ToolIcon.vue' );
const { PageTriageCurationModules } = require( '../../config.json' );

// @vue/component
module.exports = exports = {
	name: 'ToolWikiLove',
	components: { ToolFlyout, ToolIcon, CdxButton, CdxCheckbox },
	props: {
		article: {
			type: Object,
			required: true
		},
		eventBus: {
			type: Object,
			required: true
		}
	},
	setup( { article } ) {
		const editors = [];

		article.on( 'ready', () => {
			const users = {};
			const creator = article.get( 'user_name' );

			// Insert page creator first, unless they're hidden
			if ( !article.get( 'creator_hidden' ) ) {
				users[ creator ] = 1;
			}

			// Cache the current username
			const currentUsername = mw.user.getName();

			// Aggregate revisions by unique usernames
			article.revisions.each( ( revision ) => {
				const editor = revision.get( 'user' );

				if ( editor !== currentUsername && !revision.has( 'userhidden' ) ) {
					users[ editor ] = ( users[ editor ] || 0 ) + 1;
				}
			} );

			// Sort by page creator first, then descend by revision count
			const sorted = Object.entries( users )
				.sort( ( a, b ) => a[ 0 ] === creator ? -1 : a[ 1 ] < b[ 1 ] );

			// Finally let's push the sorted values to the Codex table
			for ( const entry of sorted ) {
				const username = entry[ 0 ];

				editors.push( {
					username,
					count: entry[ 1 ],
					isCreator: username === creator
				} );
			}
		} );

		return {
			active: ref( false ),
			helpLink: ref( PageTriageCurationModules.wikiLove.helplink ),
			editors: ref( editors ),
			selected: ref( [] )
		};
	},
	methods: {
		getEditorUrl( editor ) {
			return ( new mw.Title(
				editor.username,
				mw.config.get( 'wgNamespaceIds' ).user
			) ).getUrl();
		},
		getEditorInfo( editor ) {
			const text = '– ' + mw.msg( 'pagetriage-wikilove-edit-count', editor.count );

			if ( !editor.isCreator ) {
				return text;
			}

			return text + mw.msg( 'comma-separator' ) + mw.msg( 'pagetriage-wikilove-page-creator' );
		},
		send() {
			this.active = false;
			$.wikiLove.openDialog( this.selected, [ 'pagetriage' ] );
		}
	}
};
</script>

<style lang="less">
#mwe-pt-wikilove {
	.mwe-pt-article-contributor-list {
		margin: 6px 0;

		.cdx-checkbox {
			margin-bottom: 0;

			&__label {
				font-size: 1em;
			}
		}
	}

	.cdx-button {
		margin: 0;
	}
}
</style>
