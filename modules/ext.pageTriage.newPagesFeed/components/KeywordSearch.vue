<template>
	<cdx-field>
		<cdx-text-input
			:model-value="keyword"
			class="mwe-vue-pt-keyword-search"
			:placeholder="$i18n( 'pagetriage-filter-keyword' ).text()"
			@focus="$emit( 'focus' )"
			@update:model-value="( newVal ) => {
				$emit( 'update:keyword', newVal )
			}">
		</cdx-text-input>
	</cdx-field>
</template>

<script>
const {
	CdxField,
	CdxTextInput
} = require( '@wikimedia/codex' );
// @vue/component
module.exports = {
	name: 'KeywordSearch',
	components: {
		CdxField,
		CdxTextInput
	},
	props: {
		keyword: { type: String, default: '' }
	},
	emits: [ 'update:keyword', 'focus' ]

};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.mwe-vue-pt-keyword-search {
	display: inline-block;
}

.mwe-vue-pt-keyword-search .cdx-text-input {
	min-width: @size-1600;
}

.mwe-vue-pt-keyword-search .cdx-text-input__input {
	min-height: @size-75;
	line-height: @size-75;
}
</style>
