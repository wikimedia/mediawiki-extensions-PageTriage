<template>
	<cdx-radio
		v-model="selected"
		:name="type + '-exclude-radio-group'"
		input-value="own"
		@click="$emit( 'update:filter', 'own' )"
	>
		<span>{{ $i18n( 'pagetriage-filter-hide-own-pages' ).text() }}</span>
	</cdx-radio>
	<cdx-radio
		v-model="selected"
		:name="type + '-exclude-radio-group'"
		input-value="none"
		@click="$emit( 'update:filter', 'none' )"
	>
		<span>{{ $i18n( 'pagetriage-filter-exclude-none' ).text() }}</span>
	</cdx-radio>
</template>

<script>
/**
 * @author MSK
 */
const { CdxRadio } = require( '@wikimedia/codex' );
const { ref } = require( 'vue' );
// @vue/component
module.exports = {
	name: 'ExcludeRadios',
	components: { CdxRadio },
	props: {
		filter: { type: String, default: 'none' },
		type: { type: String, default: 'npp' }
	},
	emits: [
		'update:filter'
	],
	setup( props ) {
		return {
			selected: ref( props.filter )
		};
	}
};
</script>
