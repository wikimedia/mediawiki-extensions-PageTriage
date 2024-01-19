<template>
	<div class="mwe-vue-pt-control-section-wrapper">
		<cdx-field class="mwe-vue-pt-control-section" :is-fieldset="true">
			<template #label>
				{{ labelText }}
			</template>
			<div class="mwe-vue-pt-control-options">
				<slot></slot>
			</div>
		</cdx-field>
	</div>
</template>

<script>
/**
 * @author DannyS712
 * Helper for controls form, contains a specific section with a message label
 * and slot content
 */

const { CdxField } = require( '@wikimedia/codex' );

// @vue/component
module.exports = {
	name: 'ControlSection',
	components: {
		CdxField
	},
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

<style lang="less">
@import 'mediawiki.skin.variables.less';

.mwe-vue-pt-control-options {
	margin-left: @spacing-100;
	margin-right: @spacing-50;
	white-space: nowrap;
}

.mwe-vue-pt-control-section-wrapper {
	margin: @spacing-25;
}
</style>
