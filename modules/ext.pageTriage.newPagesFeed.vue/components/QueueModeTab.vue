<template>
	<cdx-toggle-button-group
		v-model="settings.immediate.queueMode"
		:buttons="buttons"
		class="mwe-vue-pt-npp-afc-mode-toggle"
		@update:model-value="( newVal ) => { settings.updateImmediate( 'queueMode', newVal ) }">
	</cdx-toggle-button-group>
</template>

<script>
/**
 * Tab group for switching between new page patrol and articles for creation queues
 */

const { CdxToggleButtonGroup } = require( '@wikimedia/codex' );
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
	components: { CdxToggleButtonGroup },
	data() {
		const buttons = [
			{
				label: this.$i18n( 'pagetriage-new-page-patrol' ).text(),
				value: 'npp'
			},
			{
				label: this.$i18n( 'pagetriage-articles-for-creation' ).text(),
				value: 'afc'
			}
		];
		const settings = useSettingsStore();
		return {
			buttons,
			settings
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.mwe-vue-pt-npp-afc-mode-toggle {
	margin-bottom: @spacing-50;
}

</style>
