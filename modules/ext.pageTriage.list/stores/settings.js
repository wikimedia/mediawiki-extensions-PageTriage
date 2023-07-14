// @author DannyS712

const { defineStore } = require( 'pinia' );

/**
 * Convert afc submission state name to api value
 *
 * @param {number} stateName
 *
 * @return {string|boolean[]}
 */
const getAfcStateForApi = ( stateName ) => {
	const submissionNumbers = [ '~invalid~', 'unsubmitted', 'pending', 'reviewing', 'declined' ];
	const stateIndex = submissionNumbers.indexOf( stateName );
	return ( stateIndex <= 0 ? false : stateIndex.toString() );
};

// 'queueMode', 'nppSortDir', or 'afcSort'
const defaultImmediate = Object.freeze( {
	queueMode: 'npp',
	nppSortDir: 'newestfirst',
	afcSort: 'newestfirst'
} );
const defaultSettings = Object.freeze( {
	// watched settings
	afcSubmissionState: 'pending',
	// remaining settings
	nppNamespace: 0,
	nppIncludeUnreviewed: true,
	nppIncludeReviewed: false,
	nppIncludeNominated: false,
	nppIncludeRedirects: false,
	nppIncludeOthers: true,
	nppFilter: 'all',
	nppFilterUser: '',
	nppPredictedRating: {
		stub: false,
		start: false,
		c: false,
		b: false,
		good: false,
		featured: false
	},
	nppPossibleIssues: {
		vandalism: false,
		spam: false,
		attack: false,
		copyvio: false,
		none: false
	},
	nppDateFrom: '',
	nppDateTo: '',
	afcPredictedRating: {
		stub: false,
		start: false,
		c: false,
		b: false,
		good: false,
		featured: false
	},
	afcPossibleIssues: {
		vandalism: false,
		spam: false,
		attack: false,
		copyvio: false,
		none: false
	},
	afcDateFrom: '',
	afcDateTo: ''
} );

let params = {};

const offset = parseInt( mw.user.options.get( 'timecorrection' ).split( '|' )[ 1 ] );
module.exports = {
	useSettingsStore: defineStore( 'settings', {
		state: () => ( {
			immediate: JSON.parse( JSON.stringify( defaultImmediate ) ),
			controlMenuOpen: false,
			applied: JSON.parse( JSON.stringify( defaultSettings ) ),
			unsaved: JSON.parse( JSON.stringify( defaultSettings ) ),
			params: { mode: defaultSettings.queueMode },
			currentFilteredCount: -1
		} ),
		getters: {
			// returns a deep copy of applied settings
			cloneApplied: ( state ) => {
				return JSON.parse( JSON.stringify( state.applied ) );
			}
		},
		actions: {
			// When the sort dir or the view changes, we want to immediately
			// update the settings to use that, ignoring any other changes made.
			// Close the menu so that when it is reopened, the applied settings are
			// reused, cancelling out the changes in the local state
			// changeName should be 'queueMode', 'nppSortDir', or 'afcSort'
			updateImmediate: function ( changeName, changeVal ) {
				this.immediate[ changeName ] = changeVal;
				this.update( this.applied );
				this.controlMenuOpen = false;
			},
			update: function ( newVal ) {
				// deep copy
				this.applied = JSON.parse( JSON.stringify( newVal ) );
			},
			reset: function () {
				this.applied = JSON.parse( JSON.stringify( defaultSettings ) );
				this.unsaved = JSON.parse( JSON.stringify( defaultSettings ) );
				this.controlMenuOpen = false;
				return this.applied;
			},
			// start as -1 until fetched the first time; fetched with the rest of
			// the statistics in the nav bar within feed contents, and then
			// passed up via an event to all knowing it here to pass to the menu
			updateFilteredCount: function ( val ) {
				this.currentFilteredCount = val;
			},
			addIfToggled: function ( paramName, optionToggle ) {
				if ( optionToggle ) {
					params[ paramName ] = true;
				}
			},
			addOresFilters: function ( optionsObj, paramPrefix ) {
				for ( const optionName in optionsObj ) {
					this.addIfToggled( paramPrefix + optionName, optionsObj[ optionName ] );
				}
			},
			addDateParams: function ( fromVal, toVal ) {
				if ( fromVal ) {
					const fromDate = moment.utc( fromVal ).subtract( offset, 'minutes' );
					// eslint-disable-next-line camelcase
					params.date_range_from = fromDate.toISOString();
				}
				if ( toVal ) {
					const toDate = moment.utc( toVal ).subtract( offset, 'minutes' );
					// move to the end of the given day
					toDate.add( 1, 'day' ).subtract( 1, 'second' );
					// eslint-disable-next-line camelcase
					params.date_range_to = toDate.toISOString();
				}
			},
			addNppFilter: function ( chosenFilter, filterUser ) {
				const filtersToParams = {
					'no-categories': 'no_category',
					unreferenced: 'unreferenced',
					orphan: 'no_inbound_links',
					recreated: 'recreated',
					'non-autoconfirmed': 'non_autoconfirmed_users',
					learners: 'learners',
					blocked: 'blocked_users',
					'bot-edits': 'showbots'
				};
				if ( chosenFilter === 'username' && filterUser ) {
					params.username = filterUser;
					// if username is chosen with no filter, or 'all'
				} else if ( filtersToParams[ chosenFilter ] !== undefined ) {
					params[ filtersToParams[ chosenFilter ] ] = '1';
				}
			},
			apiOptions: function () {
				params = { mode: this.immediate.queueMode };
				// limit is added by ListContent Component
				if ( this.immediate.queueMode === 'npp' ) {
					this.addIfToggled( 'showreviewed', this.applied.nppIncludeReviewed );
					this.addIfToggled( 'showunreviewed', this.applied.nppIncludeUnreviewed );
					this.addIfToggled( 'showdeleted', this.applied.nppIncludeNominated );
					this.addIfToggled( 'showredirs', this.applied.nppIncludeRedirects );
					this.addIfToggled( 'showothers', this.applied.nppIncludeOthers );
					this.addNppFilter( this.applied.nppFilter, this.applied.nppFilterUser );
					this.addOresFilters( this.applied.nppPredictedRating, 'show_predicted_class_' );
					this.addOresFilters( this.applied.nppPossibleIssues, 'show_predicted_issues_' );
					params.namespace = this.applied.nppNamespace;
					params.dir = this.immediate.nppSortDir;
					this.addDateParams( this.applied.nppDateFrom, this.applied.nppDateTo );
				} else {
					this.addOresFilters( this.applied.afcPredictedRating, 'show_predicted_class_' );
					this.addOresFilters( this.applied.afcPossibleIssues, 'show_predicted_issues_' );
					params.showreviewed = '1';
					params.showunreviewed = '1';
					params.namespace = mw.config.get( 'wgNamespaceIds' ).draft || 118;
					params.dir = this.immediate.afcSort;
					const afcSubmissionStateApi = getAfcStateForApi( this.applied.afcSubmissionState );
					if ( afcSubmissionStateApi ) {
						// eslint-disable-next-line camelcase
						params.afc_state = afcSubmissionStateApi;
					}
					this.addDateParams( this.applied.afcDateFrom, this.applied.afcDateTo );
				}
				return params;
			}
		}
	} )
};
