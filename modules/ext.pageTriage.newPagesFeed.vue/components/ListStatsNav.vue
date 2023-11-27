<template>
	<div
		id="mwe-vue-pt-stats-navigation"
		class="mwe-vue-pt-navigation-bar mwe-vue-pt-control-gradient">
		<div id="mwe-vue-pt-stats-navigation-content">
			<div class="mwe-vue-pt-refresh-controls">
				<cdx-toggle-button
					v-model="doAutoRefresh"
					input-id="mwe-vue-pt-autorefresh-toggle"
				>
					<cdx-icon v-if="doAutoRefresh" :icon="cdxIconStop"></cdx-icon>
					<cdx-icon v-else :icon="cdxIconPlay"></cdx-icon>
					{{ $i18n( 'pagetriage-auto-refresh-list' ).text() }}
				</cdx-toggle-button>
				<cdx-button
					id="mwe-vue-pt-refresh-button"
					@click="$emit( 'refresh-feed' )"
				>
					<cdx-icon :icon="cdxIconReload"></cdx-icon>
					<span>
						{{ $i18n( 'pagetriage-refresh-list' ).text() }}
					</span>
				</cdx-button>
			</div>
			<div v-if="showStats">
				<div>{{ $i18n( 'pagetriage-unreviewed-article-count', unreviewedArticleCount, unreviewedRedirectCount, unreviewedOldest ).text() }}</div>

				<div>{{ $i18n( 'pagetriage-reviewed-article-count-past-week', reviewedArticleCount, reviewedRedirectCount ).text() }}</div>
			</div>
			<div v-else-if="showDraftStats">
				<div>{{ $i18n( 'pagetriage-unreviewed-draft-count', unreviewedDraftCount, unreviewedOldestDraft ).text() }}</div>
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
const { CdxButton, CdxToggleButton, CdxIcon } = require( '@wikimedia/codex' );
const { cdxIconPlay, cdxIconStop, cdxIconReload } = require( './icons.json' );
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
		CdxToggleButton,
		CdxIcon
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
			cdxIconPlay,
			cdxIconReload,
			cdxIconStop,
			doAutoRefresh
		};
	},
	computed: {
		unreviewedArticleCount() {
			if ( this.apiResult.result === 'success' &&
				this.apiResult.stats &&
				this.apiResult.stats.unreviewedarticle
			) {
				return this.apiResult.stats.unreviewedarticle.count || 0;
			}
			// Should not be shown
			return -1;
		},
		unreviewedRedirectCount() {
			if ( this.apiResult.result === 'success' &&
				this.apiResult.stats &&
				this.apiResult.stats.unreviewedredirect
			) {
				return this.apiResult.stats.unreviewedredirect.count || 0;
			}
			// Should not be shown
			return -1;
		},
		unreviewedDraftCount() {
			if ( this.apiResult.result === 'success' &&
				this.apiResult.stats &&
				this.apiResult.stats.unrevieweddraft
			) {
				return this.apiResult.stats.unrevieweddraft.count || 0;
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
			return -1;
		},
		unreviewedOldestDraft() {
			if ( this.apiResult.result === 'success' &&
				this.apiResult.stats &&
				this.apiResult.stats.unrevieweddraft &&
				this.apiResult.stats.unrevieweddraft.count
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
			return -1;
		},
		reviewedArticleCount() {
			if ( this.apiResult.result === 'success' &&
				this.apiResult.stats &&
				this.apiResult.stats.reviewedarticle
			) {
				return this.apiResult.stats.reviewedarticle.reviewed_count || 0;
			}
			// Should not be shown
			return -1;
		},
		reviewedRedirectCount() {
			if ( this.apiResult.result === 'success' &&
				this.apiResult.stats &&
				this.apiResult.stats.reviewedredirect
			) {
				return this.apiResult.stats.reviewedredirect.reviewed_count || 0;
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
		},
		showDraftStats() {
			// make sure all the values were computed
			return this.queueMode === 'afc' &&
				this.unreviewedDraftCount !== -1 &&
				this.unreviewedOldestDraft !== -1;
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
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.mwe-vue-pt-navigation-bar {
	border: @border-subtle;
}

.mwe-vue-pt-control-gradient {
	background: @background-color-interactive;
}

.mwe-vue-pt-refresh-controls {
	float: right;
}

#mwe-vue-pt-stats-navigation {
	min-height: 50px;
	border-top: @border-subtle;
	position: sticky;
	bottom: 0;
	z-index: @z-index-above-content;
}

.skin-minerva #mwe-vue-pt-refresh-button {
	display: inline-block;
}

#mwe-vue-pt-stats-navigation-content {
	padding: @spacing-50 @spacing-100;
}
</style>
