<template>
	<div v-show="active" class="mwe-pt-tool-pokey mwe-pt-tool-pokey-not-flipped"></div>

	<div v-show="active" class="mwe-pt-tool-flyout mwe-pt-tool-flyout-not-flipped">
		<flyout-header
			:title
			:help-link
			@closed="$emit( 'closed' )">
		</flyout-header>

		<div class="mwe-pt-tool-content">
			<slot name="content"></slot>
			<slot name="notes"></slot>
			<slot name="footer"></slot>
		</div>
	</div>
</template>

<script>
const FlyoutHeader = require( './FlyoutHeader.vue' );

// @vue/component
module.exports = exports = {
	components: { FlyoutHeader },
	props: {
		active: {
			type: Boolean,
			default: false
		},
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

<style lang="less">
.mwe-pt-tool-flyout {
	position: absolute;
	top: -12px;
	width: 500px;
	padding: 5px;
	background-color: #cacaca;
	text-align: left;
	z-index: 2;
	border-radius: 4px;
	border: 1px solid #9f9f9f;
	box-shadow: 0 4px 8px rgba( 0, 0, 0, 0.4 );

	&-flipped[ dir='ltr' ],
	&-not-flipped[ dir='rtl' ] {
		left: 46px;
	}

	&-not-flipped[ dir='ltr' ],
	&-flipped[ dir='rtl' ] {
		right: 46px;
	}
}

.mwe-pt-tool-content {
	background-color: #fff;
	font-size: 0.8em;
	border: 1px solid #9f9f9f;
	padding: 6px;
}
</style>
