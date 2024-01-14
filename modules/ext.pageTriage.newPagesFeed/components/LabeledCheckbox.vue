<template>
	<cdx-checkbox
		:id="inputId"
		v-model="value"
		inline>
		{{ labelText }}
	</cdx-checkbox>
	<br v-if="!noBreak">
</template>

<script>
/**
 * @author DannyS712
 * Labeled checkox for mulitple filter controls
 */

const { CdxCheckbox, useModelWrapper } = require( '@wikimedia/codex' );
const { toRef } = require( 'vue' );

let lastGeneratedIdNum = 0;
// @vue/component
module.exports = {
	compatConfig: {
		MODE: 3
	},
	compilerOptions: {
		whitespace: 'condense'
	},
	name: 'LabeledCheckbox',
	components: {
		CdxCheckbox
	},
	props: {
		// id is used to associated input with the <label> via `for`, if not
		// provided auto generate one
		inputId: {
			type: String,
			default: ( props ) => `mwe-vue-pt-checkbox-${props.labelMsg}-${++lastGeneratedIdNum}`
		},
		labelMsg: {
			type: String,
			required: true,
			validator( value ) {
				return [
					'pagetriage-auto-refresh-list',
					'pagetriage-filter-nominated-for-deletion',
					'pagetriage-filter-others',
					'pagetriage-filter-predicted-class-stub',
					'pagetriage-filter-predicted-class-start',
					'pagetriage-filter-predicted-class-c',
					'pagetriage-filter-predicted-class-b',
					'pagetriage-filter-predicted-class-good',
					'pagetriage-filter-predicted-class-featured',
					'pagetriage-filter-predicted-issues-vandalism',
					'pagetriage-filter-predicted-issues-spam',
					'pagetriage-filter-predicted-issues-attack',
					'pagetriage-filter-predicted-issues-copyvio',
					'pagetriage-filter-predicted-issues-none',
					'pagetriage-filter-redirects',
					'pagetriage-filter-reviewed-edits',
					'pagetriage-filter-unreviewed-edits',
					'pagetriage-refresh-list'
				].indexOf( value ) !== -1;
			}
		},
		// eslint-disable-next-line vue/no-unused-properties
		checked: { type: Boolean, default: false },
		noBreak: { type: Boolean, default: false }
	},
	emits: [ 'update:checked' ],
	setup( props, { emit } ) {
		const value = useModelWrapper( toRef( props, 'checked' ), emit, 'update:checked' );

		return {
			value
		};
	},
	computed: {
		labelText: function () {
			// See labelMsg validator for possible keys
			// eslint-disable-next-line mediawiki/msg-doc
			return this.$i18n( this.labelMsg ).text();
		}
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.cdx-checkbox--inline {
	margin-bottom: @spacing-50;
}
</style>
