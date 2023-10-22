<template>
	<cdx-tabs
		v-model:active="settings.immediate.queueMode"
		framed
		@update:active="( newVal ) => { settings.updateImmediate( 'queueMode', newVal ) }">
		<cdx-tab
			name="npp"
			:label="$i18n( 'pagetriage-new-page-patrol' ).text()">
		</cdx-tab>
		<cdx-tab
			name="afc"
			:label="$i18n( 'pagetriage-articles-for-creation' ).text()">
		</cdx-tab>
	</cdx-tabs>
</template>

<script>
/**
 * Tab group for switching between new page patrol and articles for creation queues
 */

const { CdxTab, CdxTabs } = require( '@wikimedia/codex' );
const { useSettingsStore } = require( '../stores/settings.js' );
// @vue/component
module.exports = {
	compatConfig: {
		MODE: 3
	},
	compilerOptions: {
		whitespace: 'condense'
	},
	name: 'QueueModeTab',
	components: { CdxTab, CdxTabs },
	data() {
		return { settings: useSettingsStore() };
	}
};
</script>
