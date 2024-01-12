<template>
	<div id="mwe-pt-vue-flyout" class="mwe-pt-vue-tool-flyout mwe-pt-vue-tool-flyout-not-flipped">
		<flyout-header
			:help-link="helpLink"
			:title="title"
			@close-flyout="closeFlyout">
		</flyout-header>
		<flyout-content>
			<slot></slot>
		</flyout-content>
	</div>
</template>

<script>
const FlyoutHeader = require( './FlyoutHeader.vue' );
const FlyoutContent = require( './FlyoutContent.vue' );
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
		FlyoutHeader,
		FlyoutContent
	},
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
	methods: {
		closeFlyout: function () {
			const flyoutContainer = document.getElementById( 'mwe-pt-vue-flyout' );
			flyoutContainer.style.display = 'none';
		}
	}
};

</script>

<style>
.mwe-pt-vue-tool-flyout {
	position: absolute;
	top: 12px;
	width: 500px;
	padding: 5px;
	background-color: #cacaca;
	text-align: left;
	z-index: 2;
	border-radius: 4px;
	border: 1px solid #9f9f9f;
	box-shadow: 0 4px 8px rgba( 0, 0, 0, 0.4 );

	&-not-flipped {
		right: 46px;
	}

	&-flipped {
		left: 46px;
	}
}
</style>
