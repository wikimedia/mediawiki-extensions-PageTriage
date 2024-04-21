<template>
	<div id="loadMoreBar" ref="barRef">
		<div id="mwe-vue-pt-feed-load-more" :class="!haveMore ? 'mwe-pt-hidden' : null">
			<cdx-progress-bar :aria-label="$i18n( 'pagetriage-please-wait' ).text()" :inline="true">
			</cdx-progress-bar>
		</div>
	</div>
</template>

<script>
/**
 * @author DannyS712
 * Bar after the last entry that allows loading more when scrolled into view.
 * Whether to show or not is based on a prop instead of being controlled in the calling code
 * so that the intersection observer does not need to be recreated each time.
 */

const { CdxProgressBar } = require( '@wikimedia/codex' );
const { onMounted, ref } = require( 'vue' );
// @vue/component
module.exports = {
	components: {
		CdxProgressBar
	},
	props: {
		haveMore: { type: Boolean, required: true }
	},
	emits: [ 'trigger-load' ],
	setup( props, { emit } ) {
		const emitLoadMore = function () {
			// check that we should try to load
			if ( props.haveMore ) {
				emit( 'trigger-load' );
			}
		};
		const barRef = ref();
		const observerCallback = function ( entries ) {
			const observerEntry = entries[ 0 ];
			// whether we scrolled to see it or away from it
			const nowSeen = observerEntry.isIntersecting;
			if ( !nowSeen ) {
				return;
			}
			emitLoadMore();
		};
		const observer = new IntersectionObserver( observerCallback );
		onMounted( () => {
			observer.observe( barRef.value );
		} );
		return {
			barRef
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

#mwe-vue-pt-feed-load-more {
	text-align: center;
	background-color: @background-color-base;
	border-left: @border-subtle;
	border-right: @border-subtle;
	margin: 0;
	border-top: 0;
}
</style>
