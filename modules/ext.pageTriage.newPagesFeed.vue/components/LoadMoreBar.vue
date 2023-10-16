<template>
	<div id="loadMoreBar" ref="barRef">
		<div id="mwe-vue-pt-feed-load-more" :class="!haveMore ? 'mwe-pt-hidden' : null">
			<cdx-progress-bar :aria-label="$i18n( 'pagetriage-please-wait' ).text()"></cdx-progress-bar>
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
	compatConfig: {
		MODE: 3
	},
	compilerOptions: {
		whitespace: 'condense'
	},
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

<style>
#mwe-vue-pt-feed-load-more {
	text-align: center;
	font-size: 17px;
	background-color: #e8f2f8;
	margin: 0;
	padding: 0.4em;
	border: 1px solid #ccc;
	border-top: 0;
}
#mwe-vue-pt-feed-load-more .cdx-progress-bar {
	/* Override codex styles to make a quieter version */
	background-color: inherit;
	border: unset;
	margin: 1.5em 0 1.5em 0;
}
</style>
