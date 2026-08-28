<template>
	<span v-if="creatorUserId > 0 && !creatorAutoConfirmed">
		{{ $i18n( 'pagetriage-byline-new-editor-heading', creatorName ).text() }}
	</span>
	<span v-else>
		{{ $i18n( 'pagetriage-byline-heading', creatorName ).text() }}
	</span>
	<user-info-button
		v-if="showUserInfoCard"
		class="ext-page-triage-userinfocard-button"
		:username="creatorName"
	></user-info-button>
	<a
		v-tooltip="userPageTooltip"
		:href="userPageUrl"
		class="cdx-link"
		:class="userPageClass">
		{{ creatorName }}
	</a>
	<a v-if="creatorIsTempAccount" class="ext-page-triage-tempaccount-show-ip-link cdx-link"></a>
	(<a
		:href="talkPageUrl"
		class="cdx-link"
		:class="talkPageClass">
		{{ $i18n( 'sp-contributions-talk' ).text() }}
	</a>
	{{ $i18n( 'pipe-separator' ).text() }}
	<a :href="contribsUrl">
		{{ $i18n( 'contribslink' ).text() }}
	</a>)
</template>

<script>
/**
 * Byline for list item creator
 */
const { defineAsyncComponent } = require( 'vue' );
const { CdxTooltip } = require( '@wikimedia/codex' );

// see: https://doc.wikimedia.org/codex/latest/components/mixins/link.html
const skin = mw.config.get( 'skin' );
const redLink = skin === 'vector' ? 'new' : 'is-red-link';
const params = { action: 'edit', redlink: 1 };
// @vue/component
module.exports = {
	name: 'CreatorByline',
	components: {
		// CheckUser is an optional dependency, so the module which holds the button is
		// requested only when the byline shows the button.
		UserInfoButton: defineAsyncComponent( {
			loader: () => new Promise( ( resolve, reject ) => {
				mw.loader.using(
					'ext.checkUser.userInfoCard',
					( require ) => {
						resolve( require( 'ext.checkUser.userInfoCard' ).UserCardButton );
					},
					reject
				);
			} ),
			onError() {}
		} )
	},
	directives: {
		tooltip: CdxTooltip
	},
	props: {
		creatorName: { type: String, required: true },
		creatorUserId: { type: Number, required: true },
		creatorAutoConfirmed: { type: Boolean, required: true },
		creatorUserPageExists: { type: Boolean, required: true },
		creatorTalkPageExists: { type: Boolean, required: true },
		creatorIsTempAccount: { type: Boolean, required: false },
		creatorIsExpiredTempAccount: { type: Boolean, required: false }
	},
	computed: {
		showUserInfoCard: function () {
			// The card only supports registered users.
			return this.creatorUserId > 0 &&
				!!mw.user.options.get( 'checkuser-userinfocard-enable' );
		},
		userPageClass: function () {
			if ( this.creatorIsExpiredTempAccount ) {
				return 'mw-tempuserlink mw-tempuserlink-expired';
			}
			if ( this.creatorIsTempAccount ) {
				return 'mw-tempuserlink';
			}
			return this.creatorUserPageExists ? '' : redLink;
		},
		userPageTooltip: function () {
			return this.creatorIsExpiredTempAccount ? mw.msg( 'tempuser-expired-link-tooltip' ) : '';
		},
		userPageUrl: function () {
			if ( this.creatorUserPageExists ) {
				return mw.util.getUrl( `User:${ this.creatorName }` );
			}
			return mw.util.getUrl( `User:${ this.creatorName }`, params );
		},
		talkPageClass: function () {
			return this.creatorTalkPageExists ? '' : redLink;
		},
		talkPageUrl: function () {
			if ( this.creatorTalkPageExists ) {
				return mw.util.getUrl( `User talk:${ this.creatorName }` );
			}
			return mw.util.getUrl( `User talk:${ this.creatorName }`, params );
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

	&.mw-tempuserlink-expired {
		text-decoration: line-through;
	}
}

// The byline is a line of text, but the button is taller than the text. Keep the
// button aligned with the text and prevent it from making the line higher.
.ext-page-triage-userinfocard-button.cdx-button {
	vertical-align: text-bottom;
	margin-top: -@spacing-25;
	margin-bottom: -@spacing-25;
}
</style>
