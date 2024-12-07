<template>
	<div id="mwe-pt-minimize" class="mwe-pt-tool">
		<tool-icon
			:title="$i18n( 'pagetriage-toolbar-minimize' ).text()"
			:disabled="false"
			file="minimize"
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
	name: 'ToolMinimize',
	components: {
		ToolIcon
	},
	emits: [ 'minimize' ]
};
</script>
