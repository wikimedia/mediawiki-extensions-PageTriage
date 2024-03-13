<template>
	<div class="mwe-vue-pt-showing-section">
		<b>{{ $i18n( 'pagetriage-active-filters' ).text() }}</b>
		<template v-for="group in Object.keys( showingObj )" :key="group">
			<cdx-info-chip
				v-for="groupShowing in showingObj[ group ]"
				:key="groupShowing"
				class="mwe-vue-pt-showing-filter-chip">
				{{ groupShowing }}
			</cdx-info-chip>
		</template>
		<div
			v-show="settings.currentFilteredCount !== -1"
			class="mwe-vue-pt-control-label-right mwe-vue-pt-filter-count"
		>
			{{ $i18n( 'pagetriage-stats-filter-page-count', settings.currentFilteredCount )
				.text() }}
		</div>
	</div>
</template>

<script>
/**
 * @author DannyS712
 * Displays this currently applied filters for the feed.
 */

const { useSettingsStore } = require( '../stores/settings.js' );
const { getNamespaceOptions } = require( '../namespaces.js' );
const { CdxInfoChip } = require( '@wikimedia/codex' );
const namespaceOptions = getNamespaceOptions();
const offset = parseInt( mw.user.options.get( 'timecorrection' ).split( '|' )[ 1 ] );
const defaultMsg = {
	namespace: [],
	state: [],
	type: [],
	'predicted-class': [],
	'predicted-issues': [],
	top: [],
	// eslint-disable-next-line camelcase
	date_range: []
};
// @vue/component
module.exports = {
	name: 'ShowingText',
	components: {
		CdxInfoChip
	},
	setup() {
		const settings = useSettingsStore();
		settings.loadApiParams();
		return {
			settings
		};
	},
	data: function () {
		return {
			msgObj: JSON.parse( JSON.stringify( defaultMsg ) )
		};
	},
	computed: {
		showingObj: function () {
			this.reset();
			const settings = useSettingsStore();
			if ( settings.immediate.queueMode === 'npp' ) {
				this.addNamespace( settings.applied.nppNamespace );
				this.addTop( settings.applied.nppFilter, settings.applied.nppFilterUser );
				this.addIf( settings.applied.nppIncludeReviewed, 'reviewed', this.msgObj.state );
				this.addIf( settings.applied.nppIncludeUnreviewed, 'unreviewed', this.msgObj.state );
				this.addIf( settings.applied.nppIncludeNominated, 'nominated-for-deletion', this.msgObj.type );
				this.addIf( settings.applied.nppIncludeRedirects, 'redirects', this.msgObj.type );
				this.addIf( settings.applied.nppIncludeOthers, 'others', this.msgObj.type );
				this.addPredictedClass( settings.applied.nppPredictedRating );
				this.addPredictedIssues( settings.applied.nppPossibleIssues );
				this.addDate( settings.applied.nppDate.from, settings.applied.nppDate.to );
			} else {
				this.addTop( settings.applied.afcFilter, settings.applied.afcFilterUser );
				this.addPredictedClass( settings.applied.afcPredictedRating );
				this.addPredictedIssues( settings.applied.afcPossibleIssues );
				this.addDate( settings.applied.afcDate.from, settings.applied.afcDate.to );
				this.addState( settings.applied.afcSubmissionState );
			}

			const msgParts = JSON.parse( JSON.stringify( this.msgObj ) );
			if ( getNamespaceOptions().length <= 1 ) {
				msgParts.namespace = [];
			}

			return msgParts;
		}
	},
	methods: {
		reset: function () {
			this.msgObj = JSON.parse( JSON.stringify( defaultMsg ) );
		},
		addNamespace: function ( namespace ) {
			this.msgObj.namespace = [ namespaceOptions[ namespace ] ];
		},
		addTop: function ( filter, filterUser ) {
			if ( !filter || filter === 'all' ) {
				return;
			}
			let localMsg = '';
			if ( filter === 'username' && filterUser ) {
				localMsg = this.$i18n( 'pagetriage-filter-stat-username', filterUser ).text();
			} else if ( filter === 'bot-edits' ) {
				// Need a different message key (not -bot-edits)
				localMsg = this.$i18n( 'pagetriage-filter-stat-bots' ).text();
			} else if ( filter === 'autopatrolled-edits' ) {
				// same as above, needs a different message key
				localMsg = this.$i18n( 'pagetriage-filter-stat-autopatrolled' ).text();
			} else {
				// Possible keys
				// 'pagetriage-filter-stat-no-categories':
				// 'pagetriage-filter-stat-unreferenced':
				// 'pagetriage-filter-stat-orphan':
				// 'pagetriage-filter-stat-recreated':
				// 'pagetriage-filter-stat-non-autoconfirmed':
				// 'pagetriage-filter-stat-learners':
				// 'pagetriage-filter-stat-blocked':
				// 'pagetriage-filter-stat-autopatrolled-edits':
				// 'pagetriage-filter-stat-user-heading':
				// eslint-disable-next-line mediawiki/msg-doc
				localMsg = this.$i18n( `pagetriage-filter-stat-${ filter }` ).text();
			}
			this.msgObj.top = [ localMsg ];
		},
		addIf: function ( isApplicable, msgSuffix, msgGroup ) {
			if ( !isApplicable || !msgGroup ) {
				return;
			}
			msgGroup.push(
				// Possible keys
				// 'pagetriage-filter-stat-unreviewed'
				// 'pagetriage-filter-stat-reviewed'
				// 'pagetriage-filter-stat-nominated-for-deletion'
				// 'pagetriage-filter-stat-redirects'
				// 'pagetriage-filter-stat-others'
				// eslint-disable-next-line mediawiki/msg-doc
				this.$i18n( `pagetriage-filter-stat-${ msgSuffix }` ).text()
			);
		},
		addPredictedClass: function ( settingsObj ) {
			for ( const settingsOption in settingsObj ) {
				if ( !settingsObj[ settingsOption ] ) {
					continue;
				}
				this.msgObj[ 'predicted-class' ].push(
					// Possible keys
					// 'pagetriage-filter-stat-predicted-class-stub'
					// 'pagetriage-filter-stat-predicted-class-start'
					// 'pagetriage-filter-stat-predicted-class-c'
					// 'pagetriage-filter-stat-predicted-class-b'
					// 'pagetriage-filter-stat-predicted-class-good'
					// 'pagetriage-filter-stat-predicted-class-featured'
					// eslint-disable-next-line mediawiki/msg-doc
					this.$i18n( `pagetriage-filter-stat-predicted-class-${ settingsOption }` ).text()
				);
			}
		},
		addPredictedIssues: function ( settingsObj ) {
			for ( const settingsOption in settingsObj ) {
				if ( !settingsObj[ settingsOption ] ) {
					continue;
				}
				this.msgObj[ 'predicted-issues' ].push(
					// Possible keys
					// 'pagetriage-filter-stat-predicted-issues-vandalism'
					// 'pagetriage-filter-stat-predicted-issues-spam'
					// 'pagetriage-filter-stat-predicted-issues-attack'
					// 'pagetriage-filter-stat-predicted-issues-copyvio'
					// 'pagetriage-filter-stat-predicted-issues-none'
					// eslint-disable-next-line mediawiki/msg-doc
					this.$i18n( `pagetriage-filter-stat-predicted-issues-${ settingsOption }` ).text()
				);
			}
		},
		addDate: function ( dateFrom, dateTo ) {
			if ( dateFrom ) {
				const forFormattingFrom = moment( dateFrom );
				const formattedFrom = forFormattingFrom.utcOffset( offset )
					.format( this.$i18n( 'pagetriage-filter-date-range-format-showing' ).text() );
				this.msgObj.date_range.push(
					this.$i18n( 'pagetriage-filter-stat-date_range_from', formattedFrom ).text()
				);
			}
			if ( dateTo ) {
				const forFormattingTo = moment( dateTo );
				const formattedTo = forFormattingTo.utcOffset( offset )
					.format( this.$i18n( 'pagetriage-filter-date-range-format-showing' ).text() );
				this.msgObj.date_range.push(
					this.$i18n( 'pagetriage-filter-stat-date_range_to', formattedTo ).text()
				);
			}
		},
		addState: function ( state ) {
			if ( !state ) {
				return;
			}
			// Possible keys
			// 'pagetriage-afc-state-all'
			// 'pagetriage-afc-state-unsubmitted'
			// 'pagetriage-afc-state-pending'
			// 'pagetriage-afc-state-reviewing'
			// 'pagetriage-afc-state-declined'
			// eslint-disable-next-line mediawiki/msg-doc
			this.msgObj.state.push( this.$i18n( `pagetriage-afc-state-${ state }` ).text() );
		}
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.mwe-vue-pt-showing-section {
	margin-bottom: @spacing-25;
}

.mwe-vue-pt-showing-filter-chip {
	background-color: @background-color-base;
	margin: @spacing-25 0 0 @spacing-25;
}

.mwe-vue-pt-filter-count {
	margin-left: @spacing-50;
}
</style>
