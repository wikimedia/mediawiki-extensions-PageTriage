<template>
	<list-filter-menu></list-filter-menu>
	<list-content :tb-version="tbVersion"></list-content>
</template>

<script>
/**
 * Interface for new page patrol and articles for creation queues
 */

// @vue/component
module.exports = {
	// Enable Vue 3 mode with configureCompat and compilerOptions
	compatConfig: {
		MODE: 3
	},
	compilerOptions: {
		whitespace: 'condense'
	},
	components: {
		ListFilterMenu: require( './components/ListFilterMenu.vue' ),
		ListContent: require( './components/ListContent.vue' )
	},
	props: {
		// optional toolbar feature flag
		tbVersion: {
			type: String,
			default: null
		}
	},
	mounted() {
		$( '#mwe-pt-list-warnings' ).show();
	}
};
</script>
