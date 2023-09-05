<template>
	<span class="mwe-vue-pt-control-label">
		<b>{{ $i18n( 'pagetriage-showing' ).text() }}</b>
		{{ showingText }}
	</span>
</template>

<script>
/**
 * @author DannyS712
 * Displays this currently applied filters for the feed.
 */

const { useSettingsStore } = require( '../stores/settings.js' );
const { getNamespaceOptions } = require( '../namespaces.js' );
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
	configureCompat: {
		MODE: 3
	},
	compilerOptions: {
		whitespace: 'condense'
	},
	name: 'ShowingText',
	data: function () {
		return {
			msgObj: JSON.parse( JSON.stringify( defaultMsg ) )
		};
	},
	computed: {
		showingText: function () {
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
				this.addDate( settings.applied.nppDateFrom, settings.applied.nppDateTo );
			} else {
				this.addPredictedClass( settings.applied.afcPredictedRating );
				this.addPredictedIssues( settings.applied.afcPossibleIssues );
				this.addDate( settings.applied.afcDateFrom, settings.applied.afcDateTo );
				this.addState( settings.applied.afcSubmissionState );
			}
			const comma = this.$i18n( 'comma-separator' ).text();
			return Object.keys( this.msgObj )
				.map( ( group ) => {
					const groupShowing = this.msgObj[ group ];
					if ( !groupShowing || groupShowing.length === 0 ) {
						return '';
					}
					if ( group === 'top' || ( settings.immediate.queueMode === 'afc' && group === 'state' ) ) {
						return groupShowing[ 0 ];
					}
					let groupMsg = '';
					if ( group === 'type' ) {
						groupMsg = this.$i18n( 'pagetriage-filter-stat-type', 'blerb' ) + ' ' +
							this.$i18n( 'parentheses', groupShowing.join( comma ) ).text();
					} else {
						// Possible keys
						// 'pagetriage-filter-stat-namespace'
						// 'pagetriage-filter-stat-state'
						// 'pagetriage-filter-stat-predicted-class'
						// 'pagetriage-filter-stat-predicted-issues'
						// 'pagetriage-filter-stat-date_range'
						// eslint-disable-next-line mediawiki/msg-doc
						groupMsg = this.$i18n( `pagetriage-filter-stat-${group}` ) + ' ' +
							this.$i18n( 'parentheses', groupShowing.join( comma ) ).text();
					}
					return groupMsg;
				} )
				.filter( ( msg ) => msg !== '' )
				.join( comma );
		}
	},
	methods: {
		reset: function () {
			this.msgObj = JSON.parse( JSON.stringify( defaultMsg ) );
		},
		addNamespace: function ( namespace ) {
			this.msgObj.namespace = [ namespaceOptions[ namespace ] ];
		},
		addTop: function ( nppFilter, nppFilterUser ) {
			if ( !nppFilter || nppFilter === 'all' ) {
				return;
			}
			let localMsg = '';
			if ( nppFilter === 'username' && nppFilterUser ) {
				localMsg = this.$i18n( 'pagetriage-filter-stat-username', nppFilterUser ).text();
			} else if ( nppFilter === 'bot-edits' ) {
				// Need a different message key (not -bot-edits)
				localMsg = this.$i18n( 'pagetriage-filter-stat-bots' ).text();
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
				localMsg = this.$i18n( `pagetriage-filter-stat-${nppFilter}` ).text();
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
				this.$i18n( `pagetriage-filter-stat-${msgSuffix}` ).text()
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
					this.$i18n( `pagetriage-filter-stat-predicted-class-${settingsOption}` ).text()
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
					this.$i18n( `pagetriage-filter-stat-predicted-issues-${settingsOption}` ).text()
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
			this.msgObj.state.push( this.$i18n( `pagetriage-afc-state-${state}` ).text() );
		}
	}
};
</script>
