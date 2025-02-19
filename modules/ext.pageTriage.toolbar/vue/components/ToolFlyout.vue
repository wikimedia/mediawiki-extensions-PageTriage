<template>
	<div id="mwe-pt-vue-flyout" class="mwe-pt-vue-tool-flyout mwe-pt-vue-tool-flyout-not-flipped">
		<flyout-header
			:help-link="helpLink"
			:title="title"
			@close-flyout="closeFlyout">
		</flyout-header>
		<div class="mwe-pt-vue-tool-content">
			<slot name="content"></slot>
			<slot name="notes"></slot>
			<slot name="footer"></slot>
		</div>
	</div>
</template>

<script>
const { inject } = require( 'vue' );
const FlyoutHeader = require( './FlyoutHeader.vue' );
// @vue/component
module.exports = {
	components: {
		FlyoutHeader
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
	setup: function () {
		const { showFlyout, updateShowFlyout } = inject( 'showFlyout' );
		return {
			showFlyout,
			updateShowFlyout
		};
	},
	methods: {
		closeFlyout: function () {
			this.updateShowFlyout( !this.showFlyout );
		}
	}
};

</script>

<style>
.mwe-pt-vue-tool-flyout {
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

.mwe-pt-vue-tool-content {
	background-color: #fff;
	font-size: 0.8em;
	border: 1px solid #9f9f9f;
	padding: 6px;
}
</style>
