<template>
	<control-section label-msg="pagetriage-filter-date-range-heading">
		<div class="mwe-vue-date-container">
			<label class="mwe-vue-date-label" :for="`mwe-vue-pt-filter-${type}-date-range-from`">{{ $i18n( 'pagetriage-filter-date-range-from' ).text() }}</label>
			<input
				:id="`mwe-vue-pt-filter-${type}-date-range-from`"
				type="date"
				:value="from"
				:placeholder="$i18n( 'pagetriage-filter-date-range-format-placeholder' ).text()"
				@input="$emit( 'update:from', $event.target.value )"
			>
		</div>
		<div class="mwe-vue-date-container">
			<label class="mwe-vue-date-label" :for="`mwe-vue-pt-filter-${type}-date-range-to`">{{ $i18n( 'pagetriage-filter-date-range-to' ).text() }}</label>
			<input
				:id="`mwe-vue-pt-filter-${type}-date-range-to`"
				type="date"
				:value="to"
				:placeholder="$i18n( 'pagetriage-filter-date-range-format-placeholder' ).text()"
				@input="$emit( 'update:to', $event.target.value )"
			>
		</div>
	</control-section>
</template>

<script>
/**
 * @author DannyS712
 * Controls for date ranges
 */
const ControlSection = require( './ControlSection.vue' );
// @vue/component
module.exports = {
	compatConfig: {
		MODE: 3
	},
	compilerOptions: {
		whitespace: 'condense'
	},
	name: 'DateControlSection',
	components: { ControlSection },
	props: {
		type: { type: String, required: true },
		from: { type: String, default: '' },
		to: { type: String, default: '' }
	},
	emits: [ 'update:from', 'update:to' ]
};
</script>

<style>
#mwe-vue-pt-filter-npp-date-range-from,
#mwe-vue-pt-filter-npp-date-range-to {
	display: table-cell;
	margin: 5px 0px 5px 15px;
}
.mwe-vue-date-container {
	display: table-row;
}
.mwe-vue-date-label {
	display: table-cell;
}
</style>
