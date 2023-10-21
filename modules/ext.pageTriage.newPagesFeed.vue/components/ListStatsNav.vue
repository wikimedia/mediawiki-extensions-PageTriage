<template>
	<div
		id="mwe-vue-pt-stats-navigation"
		class="mwe-vue-pt-navigation-bar mwe-vue-pt-control-gradient"
		:class="{ 'mwe-vue-pt-navigation-bar-sticky-footer': stickyFooter }">
		<div id="mwe-vue-pt-stats-navigation-content">
			<span class="mwe-vue-pt-refresh-controls">
				<labeled-checkbox
					v-model:checked="doAutoRefresh"
					input-id="mwe-vue-pt-autorefresh-checkbox"
					:no-break="true"
					label-msg="pagetriage-auto-refresh-list"
				>
				</labeled-checkbox>
				<cdx-button
					id="mwe-vue-pt-refresh-button"
					@click="$emit( 'refresh-feed' )"
				>
					<span>
						{{ $i18n( 'pagetriage-refresh-list' ).text() }}
					</span>
				</cdx-button>
			</span>
			<div v-show="showStats">
				<div>{{ $i18n( 'pagetriage-unreviewed-article-count', unreviewedArticleCount, unreviewedRedirectCount, unreviewedOldest ).text() }}</div>

				<div>{{ $i18n( 'pagetriage-reviewed-article-count-past-week', reviewedArticleCount, reviewedRedirectCount ).text() }}</div>
			</div>
		</div>
	</div>
</template>

<script>
/**
 * @author DannyS712
 * Footer providing queue statistics and refresh controls
 */

const { ref, watch } = require( 'vue' );
const { CdxButton } = require( '@wikimedia/codex' );
const LabeledCheckbox = require( './LabeledCheckbox.vue' );
// @vue/component
module.exports = {
	compatConfig: {
		MODE: 3
	},
	compilerOptions: {
		whitespace: 'condense'
	},
	name: 'ListStatsNav',
	components: {
		CdxButton,
		LabeledCheckbox
	},
	props: {
		queueMode: { type: String, default: 'npp' },
		apiResult: {
			type: Object,
			default: () => ( {} )
		}
	},
	emits: [ 'refresh-feed' ],
	setup( _props, { emit } ) {
		const doAutoRefresh = ref( false );
		let intervalID;
		watch( doAutoRefresh, ( autoRefresh ) => {
			if ( autoRefresh === true ) {
				intervalID = setInterval( function () {
					emit( 'refresh-feed' );
				}, 30000 );
			} else {
				clearInterval( intervalID );
			}
		} );
		return {
			doAutoRefresh
		};
	},
	data() {
		return {
			stickyFooter: false
		};
	},
	computed: {
		unreviewedArticleCount() {
			if ( this.apiResult.result === 'success' &&
				this.apiResult.stats &&
				this.apiResult.stats.unreviewedarticle
			) {
				return this.apiResult.stats.unreviewedarticle.count;
			}
			// Should not be shown
			return -1;
		},
		unreviewedRedirectCount() {
			if ( this.apiResult.result === 'success' &&
				this.apiResult.stats &&
				this.apiResult.stats.unreviewedredirect
			) {
				return this.apiResult.stats.unreviewedredirect.count;
			}
			// Should not be shown
			return -1;
		},
		unreviewedOldest() {
			if ( this.apiResult.result === 'success' &&
				this.apiResult.stats &&
				this.apiResult.stats.unreviewedarticle
			) {
				const rawOldest = this.apiResult.stats.unreviewedarticle.oldest;
				// convert to number of days based on formatDaysFromNow in
				// pagetriage
				if ( !rawOldest ) {
					return '';
				}
				const diff = this.calculateDiff( rawOldest );
				if ( diff ) {
					return this.$i18n( 'days', diff ).text();
				}
				return this.$i18n( 'pagetriage-stats-less-than-a-day', diff ).text();
			}
			// Should not be shown
			return '?';
		},
		reviewedArticleCount() {
			if ( this.apiResult.result === 'success' &&
				this.apiResult.stats &&
				this.apiResult.stats.reviewedarticle
			) {
				return this.apiResult.stats.reviewedarticle.reviewed_count;
			}
			// Should not be shown
			return -1;
		},
		reviewedRedirectCount() {
			if ( this.apiResult.result === 'success' &&
				this.apiResult.stats &&
				this.apiResult.stats.reviewedredirect
			) {
				return this.apiResult.stats.reviewedredirect.reviewed_count;
			}
			// Should not be shown
			return -1;
		},
		showStats() {
			// make sure all the values were computed
			return this.queueMode === 'npp' &&
				this.unreviewedArticleCount !== -1 &&
				this.unreviewedRedirectCount !== -1 &&
				this.unreviewedOldest !== '?' &&
				this.reviewedArticleCount !== -1 &&
				this.reviewedRedirectCount !== -1;
		}
	},
	methods: {
		calculateDiff( rawOldest ) {
			let now = new Date();
			now = new Date(
				now.getUTCFullYear(),
				now.getUTCMonth(),
				now.getUTCDate(),
				now.getUTCHours(),
				now.getUTCMinutes(),
				now.getUTCSeconds()
			);
			const begin = moment.utc( rawOldest, 'YYYYMMDDHHmmss' );

			return Math.round( ( now.getTime() - begin.valueOf() ) / ( 1000 * 60 * 60 * 24 ) );
		}
	},
	mounted() {
		const options = {
			// Offset the height occupied by this element + the approximate bottom margin of the last list item
			rootMargin: `-${this.$el.offsetHeight + 10}px`
		};
		const observerCallback = ( function ( entries ) {
			const observerEntry = entries[ 0 ];
			// not sticky if the previous sibling is visible
			// sticky if the previous sibling is not visible
			this.stickyFooter = !observerEntry.isIntersecting;
		} ).bind( this );
		const observer = new IntersectionObserver( observerCallback, options );
		// Observe the visibility of the element before this one;
		observer.observe( this.$el.previousElementSibling );
	}
};
</script>

<style>
.mwe-vue-pt-navigation-bar {
	border: 1px solid #ccc;
}
.mwe-vue-pt-control-gradient {
	background: #c9c9c9;
}
.mwe-vue-pt-refresh-controls {
	float: right;
}
#mwe-vue-pt-stats-navigation {
	min-height: 50px;
	border-top: 1px solid #ccc;
	position: sticky;
	bottom: 0;
	z-index: 1;
}
.mwe-vue-pt-navigation-bar-sticky-footer {
	box-shadow: 0 -7px 10px rgba( 0, 0, 0, 0.4 );
}
#mwe-vue-pt-stats-navigation-content {
	padding: 0.5em 1em;
}
</style>
