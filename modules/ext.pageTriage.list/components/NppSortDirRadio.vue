<template>
	<b class="mwe-vue-pt-sort-label">
		{{ $i18n( 'pagetriage-sort-by' ).text() }}
	</b>
	<cdx-radio
		v-for="radio in radios"
		:key="`mwe-vue-pt-radio-${radio.value}`"
		v-model="settings.immediate.nppSortDir"
		name="npp-sort-dir-radio-group"
		:input-value="radio.value"
		:inline="true"
		@change="settings.updateImmediate( 'nppSortDir', radio.value )"
	>
		<span>
			{{ radio.label }}
		</span>
	</cdx-radio>
</template>

<script>
/*
 * Control for new page patrol queue sorting
 */

const { CdxRadio } = require( '@wikimedia/codex' );
const { useSettingsStore } = require( '../stores/settings.js' );
// @vue/component
module.exports = {
	compatConfig: {
		MODE: 3
	},
	compilerOptions: {
		whitespace: 'condense'
	},
	name: 'NppSortDirRadio',
	components: { CdxRadio },
	data() {
		const radios = [
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
			radios,
			settings
		};
	}
};
</script>

<style>
.mwe-vue-pt-sort-label {
	padding-right: 5px;
	white-space: nowrap;
}
</style>
