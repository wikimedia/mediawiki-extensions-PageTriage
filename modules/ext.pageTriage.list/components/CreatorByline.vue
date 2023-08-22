<template>
	<span v-if="creatorUserId > 0 && !creatorAutoConfirmed">
		{{ $i18n( 'pagetriage-byline-new-editor-heading', creatorName ).text() }}
	</span>
	<span v-else>
		{{ $i18n( 'pagetriage-byline-heading', creatorName ).text() }}
	</span>
	<a :href="userPageUrl" class="cdx-link" :class="userPageClass">
		{{ creatorName }}
	</a>
	(
	<a :href="talkPageUrl" class="cdx-link" :class="talkPageClass">
		{{ $i18n( 'sp-contributions-talk' ).text() }}
	</a>
	{{ $i18n( 'pipe-separator' ).text() }}
	<a :href="contribsUrl">
		{{ $i18n( 'contribslink' ).text() }}
	</a>
	)
</template>

<script>
/**
 * Byline for list item creator
 */

// see: https://doc.wikimedia.org/codex/latest/components/mixins/link.html
const skin = mw.config.get( 'skin' );
const redLink = skin === 'vector' ? 'new' : 'is-red-link';
const params = { action: 'edit', redlink: 1 };
// @vue/component
module.exports = {
	configureCompat: {
		MODE: 3
	},
	compilerOptions: {
		whitespace: 'condense'
	},
	name: 'CreatorByline',
	props: {
		creatorName: { type: String, required: true },
		creatorUserId: { type: Number, required: true },
		creatorAutoConfirmed: { type: Boolean, required: true },
		creatorUserPageExists: { type: Boolean, required: true },
		creatorTalkPageExists: { type: Boolean, required: true }
	},
	computed: {
		userPageClass: function () {
			return this.creatorUserPageExists ? '' : redLink;
		},
		userPageUrl: function () {
			if ( this.creatorUserPageExists ) {
				return mw.util.getUrl( `User:${this.creatorName}` );
			}
			return mw.util.getUrl( `User:${this.creatorName}`, params );
		},
		talkPageClass: function () {
			return this.creatorTalkPageExists ? '' : redLink;
		},
		talkPageUrl: function () {
			if ( this.creatorTalkPageExists ) {
				return mw.util.getUrl( `User talk:${this.creatorName}` );
			}
			return mw.util.getUrl( `User talk:${this.creatorName}`, params );
		},
		contribsUrl: function () {
			return mw.util.getUrl( 'Special:Contributions/' + this.creatorName );
		}
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
.cdx-link {
	.cdx-mixin-link();
}
</style>
