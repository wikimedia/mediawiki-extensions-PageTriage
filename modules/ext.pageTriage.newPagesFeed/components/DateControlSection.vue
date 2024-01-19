<template>
	<control-section label-msg="pagetriage-filter-date-range-heading">
		<div class="mwe-vue-date-container">
			<label class="mwe-vue-date-label" :for="`mwe-vue-pt-filter-${type}-date-range-from`">{{ $i18n( 'pagetriage-filter-date-range-from' ).text() }}</label>
			<cdx-text-input
				:id="`mwe-vue-pt-filter-${type}-date-range-from`"
				input-type="date"
				:model-value="from"
				@update:model-value="( newVal ) => $emit( 'update:from', newVal )"
			></cdx-text-input>
		</div>
		<div class="mwe-vue-date-container">
			<label class="mwe-vue-date-label" :for="`mwe-vue-pt-filter-${type}-date-range-to`">{{ $i18n( 'pagetriage-filter-date-range-to' ).text() }}</label>
			<cdx-text-input
				:id="`mwe-vue-pt-filter-${type}-date-range-to`"
				input-type="date"
				:model-value="to"
				@update:model-value="( newVal ) => $emit( 'update:to', newVal )"
			></cdx-text-input>
		</div>
	</control-section>
</template>

<script>
/**
 * @author DannyS712
 * Controls for date ranges
 */
const ControlSection = require( './ControlSection.vue' );
const { CdxTextInput } = require( '@wikimedia/codex' );
// @vue/component
module.exports = {
	name: 'DateControlSection',
	components: {
		ControlSection,
		CdxTextInput
	},
	props: {
		type: { type: String, required: true },
		from: { type: String, default: '' },
		to: { type: String, default: '' }
	},
	emits: [ 'update:from', 'update:to' ]
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

#mwe-vue-pt-filter-npp-date-range-from,
#mwe-vue-pt-filter-npp-date-range-to {
	display: table-cell;
	margin: @spacing-30 0 @spacing-30 @spacing-50;
}

.mwe-vue-date-container {
	display: table-row;

	.cdx-text-input {
		min-width: unset;
	}
}

.mwe-vue-date-label {
	display: table-cell;
}
</style>
