<template>
	<div class="mwe-pt-vue-tool-content">
		<slot></slot>
		<flyout-footer></flyout-footer>
		<flyout-notes></flyout-notes>
	</div>
</template>

<script>
const FlyoutFooter = require( './FlyoutFooter.vue' );
const FlyoutNotes = require( './FlyoutNotes.vue' );
// @vue/component
module.exports = {
	components: {
		FlyoutFooter,
		FlyoutNotes
	}
};

</script>

<style>
.mwe-pt-vue-tool-content {
	background-color: #fff;
	font-size: 0.8em;
	border: 1px solid #9f9f9f;
	padding: 6px;
}
</style>
