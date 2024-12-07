<template>
	<div
		class="mwe-pt-tool-icon-container"
		@click="click"
	>
		<div
			:class="disabled ? `mwe-pt-tool-icon-${file}-disabled mwe-pt-tool-icon-disabled` :
				useSpecialIcon ? `mwe-pt-tool-icon-${file}-special` :
				`mwe-pt-tool-icon-${file}`"
			class="mwe-pt-tool-icon"
			:title="title"
		>
		</div>
	</div>
</template>

<script>
/**
 *  Tool Icon
 */

// @vue/component
module.exports = {
	name: 'ToolIcon',
	props: {
		title: {
			type: String,
			required: true
		},
		disabled: {
			type: Boolean,
			required: true
		},
		file: {
			type: String,
			required: true
		},
		useSpecialIcon: {
			type: Boolean,
			required: false
		}
	},
	emits: [ 'click' ],
	methods: {
		click: function () {
			if ( !this.disabled ) {
				this.$emit( 'click' );
			}
		}
	}
};
</script>

<style>
.mwe-pt-tool-icon {
	width: 35px;
	height: 35px;
}

.mwe-pt-tool-icon-disabled {
	cursor: default;
}
</style>
