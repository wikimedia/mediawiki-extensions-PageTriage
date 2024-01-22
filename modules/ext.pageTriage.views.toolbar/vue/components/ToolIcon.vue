<template>
	<div
		class="mwe-pt-tool-icon-container"
		@click="click"
		@mouseover="mouseover"
		@mouseleave="mouseleave"
	>
		<img
			class="mwe-pt-tool-icon"
			:src="src"
			:title="title"
			alt=""
		>
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
		}
	},
	emits: [ 'click' ],
	data() {
		return {
			dir: 'normal'
		};
	},
	computed: {
		src: function () {
			const path = `${mw.config.get( 'wgExtensionAssetsPath' )}/PageTriage/modules/ext.pageTriage.views.toolbar/images/icons`;
			const dir = this.disabled ? 'disabled' : this.dir;
			return `${path}/${dir}/${this.file}`;
		}
	},
	methods: {
		click: function () {
			if ( !this.disabled ) {
				this.$emit( 'click' );
			}
		},
		mouseover: function () {
			this.dir = 'hover';
		},
		mouseleave: function () {
			this.dir = 'normal';
		}
	}
};
</script>

<style>
.mwe-pt-tool-icon {
	width: 35px;
	height: 35px;
}
</style>
