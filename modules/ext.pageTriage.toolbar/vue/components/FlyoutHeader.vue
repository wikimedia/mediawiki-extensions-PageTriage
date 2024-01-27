<template>
	<span class="mwe-pt-vue-tool-title">
		<!-- TODO: title name will be dynamic, will be added in first Toolbar migration task -->
		{{ title }}
	</span>
	<!-- TODO: href will be dynamic, will be added in first Toolbar migration task -->
	<a
		class="mwe-pt-vue-flyout-help-link"
		:href="helpLink">
		{{ $i18n( 'pagetriage-toolbar-learn-more' ).text() }}
	</a>
	<span class="mwe-pt-vue-tool-close" @click="$emit( 'closeFlyout' )">
		{{ $i18n( 'pagetriage-toolbar-close' ).text() }}
	</span>
</template>

<script>
// @vue/component
module.exports = {
	props: {
		helpLink: {
			type: String,
			required: true
		},
		title: {
			type: String,
			required: true
		}
	},
	emits: [ 'closeFlyout' ]
};
</script>

<style>
.mwe-pt-vue-tool-title {
	font-weight: bold;
	display: inline-block;
	padding: 2px 0 5px 0;
}

.mwe-pt-vue-flyout-help-link {
	display: inline-block;
	margin-left: 0.5em;
	font-size: 0.7em;
}

.mwe-pt-vue-tool-close {
	color: #808080;
	float: right;
	font-size: 0.6em;
	cursor: pointer;
}
</style>
