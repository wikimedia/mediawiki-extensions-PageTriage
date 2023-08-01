<template>
	<list-filter-menu></list-filter-menu>
	<list-content></list-content>
</template>

<script>
/**
 * Interface for new page patrol and articles for creation queues
 */

// @vue/component
module.exports = {
	// Enable Vue 3 mode with configureCompat and compilerOptions
	configureCompat: {
		MODE: 3
	},
	compilerOptions: {
		whitespace: 'condense'
	},
	components: {
		ListFilterMenu: require( './components/ListFilterMenu.vue' ),
		ListContent: require( './components/ListContent.vue' )
	},
	mounted() {
		$( '#mwe-pt-list-warnings' ).show();
	}
};
</script>
