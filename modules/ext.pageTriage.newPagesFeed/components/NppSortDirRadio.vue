<template>
	<cdx-field>
		<cdx-toggle-button-group
			v-model="settings.immediate.nppSortDir"
			:buttons="buttons"
			name="npp-sort-dir-radio-group"
			@update:model-value="( newVal ) => settings.updateImmediate( 'nppSortDir', newVal )"
		>
		</cdx-toggle-button-group>
	</cdx-field>
</template>

<script>
/*
 * Control for new page patrol queue sorting
 */

const { CdxToggleButtonGroup, CdxField } = require( '@wikimedia/codex' );
const { useSettingsStore } = require( '../stores/settings.js' );
// @vue/component
module.exports = {
	name: 'NppSortDirRadio',
	components: {
		CdxToggleButtonGroup,
		CdxField
	},
	data() {
		const buttons = [
			{
				label: this.$i18n( 'pagetriage-newest' ).text(),
				value: 'newestfirst'
			},
			{
				label: this.$i18n( 'pagetriage-oldest' ).text(),
				value: 'oldestfirst'
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
