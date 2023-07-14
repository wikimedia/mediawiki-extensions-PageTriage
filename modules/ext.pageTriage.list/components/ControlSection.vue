<template>
	<fieldset class="mwe-vue-pt-control-section">
		<legend class="mwe-vue-pt-control-label">
			<b>{{ labelText }}</b>
		</legend>
		<div class="mwe-vue-pt-control-options">
			<slot></slot>
		</div>
	</fieldset>
</template>

<script>
/**
 * @author DannyS712
 * Helper for controls form, contains a specific section with a message label
 * and slot content
 */

// @vue/component
module.exports = {
	configureCompat: {
		MODE: 3
	},
	compilerOptions: {
		whitespace: 'condense'
	},
	name: 'ControlSection',
	props: {
		labelMsg: {
			type: String,
			required: true,
			validator( value ) {
				return [
					'pagetriage-filter-date-range-heading',
					'pagetriage-filter-namespace-heading',
					'pagetriage-filter-show-heading',
					'pagetriage-filter-show-heading',
					'pagetriage-filter-show-heading',
					'pagetriage-filter-second-show-heading',
					'pagetriage-filter-type-show-heading',
					'pagetriage-filter-predicted-class-heading',
					'pagetriage-filter-predicted-issues-heading'
				].indexOf( value ) !== -1;
			}
		}
	},
	computed: {
		labelText() {
			// See labelMsg validator for possible keys
			// eslint-disable-next-line mediawiki/msg-doc
			return this.$i18n( this.labelMsg ).text();
		}
	}
};
</script>

<style>
.mwe-vue-pt-control-options {
	margin-left: 1em;
	margin-right: 0.5em;
	white-space: nowrap;
}
</style>
