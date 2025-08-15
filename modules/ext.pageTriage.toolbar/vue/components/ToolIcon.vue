<template>
	<div class="mwe-pt-tool-icon-container" @click="click">
		<div :class="iconClass" :title></div>
	</div>
</template>

<script>
// @vue/component
module.exports = exports = {
	name: 'ToolIcon',
	props: {
		file: {
			type: String,
			required: true
		},
		title: {
			type: String,
			required: true
		},
		isOpen: {
			type: Boolean,
			default: false
		},
		disabled: {
			type: Boolean,
			default: false
		},
		useSpecialIcon: {
			type: Boolean,
			default: false
		}
	},
	emits: [ 'click' ],
	computed: {
		iconClass() {
			let classes = 'mwe-pt-tool-icon mwe-pt-tool-icon-' + this.file;

			if ( this.disabled ) {
				classes += '-disabled mwe-pt-tool-icon-disabled';
			} else if ( this.isOpen ) {
				classes += '-active';
			} else if ( this.useSpecialIcon ) {
				classes += '-special';
			}

			return classes;
		}
	},
	methods: {
		click() {
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
