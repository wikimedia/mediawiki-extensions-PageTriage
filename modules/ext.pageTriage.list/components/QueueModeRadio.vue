<template>
	<cdx-radio
		key="'mwe-vue-pt-radio-npp'"
		v-model="settings.immediate.queueMode"
		name="queue-mode-radio-group"
		input-value="npp"
		:inline="true"
		@change="settings.updateImmediate( 'queueMode', 'npp' )"
	>
		{{ $i18n( 'pagetriage-new-page-patrol' ).text() }}
	</cdx-radio>
	<cdx-radio
		key="'mwe-vue-pt-radio-afc'"
		v-model="settings.immediate.queueMode"
		name="queue-mode-radio-group"
		input-value="afc"
		:inline="true"
		@change="settings.updateImmediate( 'queueMode', 'afc' )"
	>
		{{ $i18n( 'pagetriage-articles-for-creation' ).text() }}
	</cdx-radio>
</template>

<script>
/**
 * Radio button group for switching between new page patrol and articles for creation queues
 */

const { CdxRadio } = require( '@wikimedia/codex' );
const { useSettingsStore } = require( '../stores/settings.js' );
// @vue/component
module.exports = {
	configureCompat: {
		MODE: 3
	},
	compilerOptions: {
		whitespace: 'condense'
	},
	name: 'QueueModeRadio',
	components: { CdxRadio },
	data() {
		return { settings: useSettingsStore() };
	}
};
</script>
