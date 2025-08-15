<template>
	<span class="mwe-pt-tool-title">{{ title }}</span>

	<a class="mwe-pt-flyout-help-link" :href="helpLink">
		{{ $i18n( 'pagetriage-toolbar-learn-more' ).text() }}
	</a>

	<span class="mwe-pt-tool-close" @click="$emit( 'closed' )">
		{{ $i18n( 'pagetriage-toolbar-close' ).text() }}
	</span>
</template>

<script>
// @vue/component
module.exports = exports = {
	props: {
		title: {
			type: String,
			required: true
		},
		helpLink: {
			type: String,
			required: true
		}
	},
	emits: [ 'closed' ]
};
</script>

<style>
.mwe-pt-tool-title {
	font-weight: bold;
	display: inline-block;
	padding: 2px 0 5px 0;
}

.mwe-pt-flyout-help-link {
	display: inline-block;
	margin-left: 0.5em;
	font-size: 0.7em;
}

.mwe-pt-tool-close {
	color: #808080;
	float: right;
	font-size: 0.6em;
	cursor: pointer;
}
</style>
