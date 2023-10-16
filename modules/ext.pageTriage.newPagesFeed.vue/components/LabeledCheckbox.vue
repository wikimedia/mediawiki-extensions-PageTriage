<template>
	<input
		:id="inputId"
		:checked="checked"
		type="checkbox"
		@change="$emit( 'update:checked', $event.target.checked )"
	>
	<label :for="inputId">
		{{ labelText }}
	</label>
	<br v-if="!noBreak">
</template>

<script>
/**
 * @author DannyS712
 * Labeled checkox for mulitple filter controls
 */

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
		checked: { type: Boolean, default: false },
		noBreak: { type: Boolean, default: false }
	},
	emits: [ 'update:checked' ],
	computed: {
		labelText: function () {
			// See labelMsg validator for possible keys
			// eslint-disable-next-line mediawiki/msg-doc
			return this.$i18n( this.labelMsg ).text();
		}
	}
};
</script>
