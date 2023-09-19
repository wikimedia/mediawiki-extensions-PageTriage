<template>
	<div id="mwe-pt-minimize" class="mwe-pt-tool">
		<tool-icon
			:title="$i18n( 'pagetriage-toolbar-minimize' ).text()"
			:disabled="false"
			file="icon_minimize.png"
			@click="$emit( 'minimize' )"
		></tool-icon>
	</div>
</template>

<script>
/**
 * Minimize the toolbar
 */
const ToolIcon = require( './ToolIcon.vue' );
// @vue/component
module.exports = {
	configureCompat: {
		MODE: 3
	},
	compilerOptions: {
		whitespace: 'condense'
	},
	name: 'ToolMinimize',
	components: {
		ToolIcon
	},
	emits: [ 'minimize' ]
};
</script>
