// @author DannyS712

const { defineStore } = require( 'pinia' );

const submissionNumbers = [ '~invalid~', 'unsubmitted', 'pending', 'reviewing', 'declined' ];

// Default API parameters; mode, showunreviewed, and showothers map to settings
const defaultParams = {
	mode: 'npp',
	namespace: 0,
	showunreviewed: 1,
	showothers: 1,
	format: 'json',
	formatversion: 2,
	version: 2
};

// 'queueMode', 'nppSortDir', or 'afcSort'
const defaultImmediate = Object.freeze( {
	queueMode: defaultParams.mode,
	nppSortDir: 'newestfirst',
	afcSort: 'newestfirst'
} );
// Default filter form settings; these get translated to API parameters
const defaultSettings = Object.freeze( {
	afcSubmissionState: 'pending',
	nppNamespace: 0,
	nppIncludeUnreviewed: true,
	nppIncludeReviewed: false,
	nppIncludeNominated: false,
	nppIncludeRedirects: false,
	nppIncludeOthers: true,
	nppFilter: 'all',
	afcFilter: 'all',
	afcFilterUser: '',
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
	nppDate: {
		from: '',
		to: ''
	},
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
	afcDate: {
		from: '',
		to: ''
	}
} );

const filtersToParams = {
	'no-categories': 'no_category',
	unreferenced: 'unreferenced',
	orphan: 'no_inbound_links',
	recreated: 'recreated',
	'non-autoconfirmed': 'non_autoconfirmed_users',
	learners: 'learners',
	blocked: 'blocked_users',
	'bot-edits': 'showbots',
	'autopatrolled-edits': 'showautopatrolled',
	username: 'username'
};

const initState = () => {
	// Named users store params ( user-centric ) in a user option, other users store params
	// in localStorage ( client-centric )
	const stored = mw.user.options.get( 'userjs-NewPagesFeedFilterOptions', localStorage.getItem( 'userjs-NewPagesFeedFilterOptions' ) );
	return {
		immediate: JSON.parse( JSON.stringify( defaultImmediate ) ),
		controlMenuOpen: false,
		applied: JSON.parse( JSON.stringify( defaultSettings ) ),
		unsaved: JSON.parse( JSON.stringify( defaultSettings ) ),
		// Load stored API parameters if possible, else defaults
		params: JSON.parse( stored ) || defaultParams,
		currentFilteredCount: -1
	};
};

const offset = parseInt( mw.user.options.get( 'timecorrection' ).split( '|' )[ 1 ] );
module.exports = {
	useSettingsStore: defineStore( 'settings', {
		state: initState,
		getters: {
			// returns a deep copy of applied settings
			cloneApplied: ( state ) => JSON.parse( JSON.stringify( state.applied ) )
		},
		actions: {
			// Map AFC submission state form values to API parameters
			afcStateFilterToParam: function ( stateName ) {
				const stateIndex = submissionNumbers.indexOf( stateName );
				if ( stateName === 'all' ) {
					delete this.params.afc_state;
				} else if ( stateIndex !== -1 ) {
					// eslint-disable-next-line camelcase
					this.params.afc_state = stateIndex;
				}
			},
			// Map AFC submission state API parameters to form values
			afcStateParamToFilter: function () {
				const stateName = submissionNumbers[ this.params.afc_state ];
				return ( stateName || 'all' );
			},
			/*
			 * Map ORES API parameters to form values
			 *  @param {Object} issues NPP or AFC ORES predicted class parameters
			 *  @param {Object} rating NPP or AFC ORES predicted issues parameters
			 */
			oresParamsToFilters: function ( issues, rating ) {
				const issuesParamsToFilters = {
					/* eslint-disable camelcase */
					show_predicted_issues_vandalism: 'vandalism',
					show_predicted_issues_spam: 'spam',
					show_predicted_issues_attack: 'attack',
					show_predicted_issues_copyvio: 'copyvio',
					show_predicted_issues_none: 'none'
					/* eslint-enable camelcase */
				};
				const ratingParamsToFilters = {
					/* eslint-disable camelcase */
					show_predicted_class_stub: 'stub',
					show_predicted_class_start: 'start',
					show_predicted_class_c: 'c',
					show_predicted_class_b: 'b',
					show_predicted_class_good: 'good',
					show_predicted_class_featured: 'featured'
					/* eslint-enable camelcase */
				};
				for ( const param in issuesParamsToFilters ) {
					issues[ issuesParamsToFilters[ param ] ] = !!this.params[ param ];

				}
				for ( const param in ratingParamsToFilters ) {
					rating[ ratingParamsToFilters[ param ] ] = !!this.params[ param ];
				}
			},
			paramsToFilter: function ( params ) {
				const settings = {
					/* eslint-disable camelcase */
					no_category: 'no-categories',
					unreferenced: 'unreferenced',
					no_inbound_links: 'orphan',
					recreated: 'recreated',
					non_autoconfirmed_users: 'non-autoconfirmed',
					learners: 'learners',
					blocked_users: 'blocked',
					/* eslint-enable camelcase */
					showbots: 'bot-edits',
					showautopatrolled: 'autopatrolled-edits',
					username: 'username'
				};
				for ( const param in settings ) {
					if ( params[ param ] ) {
						return settings[ param ];
					}
				}
			},
			// Map NPP API parameters to form values
			nppParamsToFilters: function () {
				this.unsaved.nppFilterUser = this.params.username || '';
				this.unsaved.nppFilter = this.paramsToFilter( this.params ) || 'all';
			},
			// Map AFC API parameters to form values
			afcParamsToFilters: function () {
				this.unsaved.afcFilterUser = this.params.username || '';
				this.unsaved.afcFilter = this.paramsToFilter( this.params ) || 'all';
			},
			/* Map date API parameters to form values
			 *  @param {Object} NPP or AFC date forms setting object
			 */
			dateParamsToFilters: function ( dateObj ) {
				if ( this.params.date_range_from ) {
					const fromDate = moment.utc( this.params.date_range_from ).add( offset, 'minutes' );
					dateObj.from = fromDate.format( 'YYYY-MM-DD' );
				}
				if ( this.params.date_range_to ) {
					const toDate = moment.utc( this.params.date_range_to ).add( offset, 'minutes' );
					// move to the end of the given day
					toDate.add( 1, 'day' ).subtract( 1, 'second' );
					dateObj.to = toDate.format( 'YYYY-MM-DD' );
				}
			},
			// Set form values from API parameters
			loadApiParams: function () {
				// mode-independent settings
				this.immediate.queueMode = this.params.mode;
				this.unsaved.namespace = this.params.namespace;

				// NPP-specific settings
				if ( this.params.mode === 'npp' ) {
					this.immediate.nppSortDir = this.params.dir;
					this.unsaved.nppIncludeReviewed = !!this.params.showreviewed;
					this.unsaved.nppIncludeUnreviewed = !!this.params.showunreviewed;
					this.unsaved.nppIncludeNominated = !!this.params.showdeleted;
					this.unsaved.nppIncludeRedirects = !!this.params.showredirs;
					this.unsaved.nppIncludeOthers = !!this.params.showothers;
					this.oresParamsToFilters( this.unsaved.nppPossibleIssues,
						this.unsaved.nppPredictedRating );
					this.nppParamsToFilters();
					this.dateParamsToFilters( this.unsaved.nppDate );
				// AFC-specific settings
				} else {
					this.immediate.afcSortDir = this.params.dir;
					this.unsaved.afcSubmissionState = this.afcStateParamToFilter();
					this.afcParamsToFilters();
					this.oresParamsToFilters( this.unsaved.afcPossibleIssues,
						this.unsaved.afcPredictedRating );
					this.dateParamsToFilters( this.unsaved.afcDate );
				}
				// Apply new form settings
				this.update( this.unsaved );
			},
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
				this.setApiParams();
			},
			reset: function () {
				this.unsaved = JSON.parse( JSON.stringify( defaultSettings ) );
				this.update( this.unsaved );
				this.controlMenuOpen = false;
				return this.applied;
			},
			// start as -1 until fetched the first time; fetched with the rest of
			// the statistics in the nav bar within feed contents, and then
			// passed up via an event to all knowing it here to pass to the menu
			updateFilteredCount: function ( val ) {
				this.currentFilteredCount = val;
			},
			// Add boolean form value as numeric API parameter if set, otherwise delete
			// from parameters
			addIfToggled: function ( paramName, optionToggle ) {
				if ( optionToggle ) {
					this.params[ paramName ] = 1;
				} else {
					delete this.params[ paramName ];
				}
			},
			// Map ORES form values to API parameters
			addOresFilters: function ( optionsObj, paramPrefix ) {
				for ( const optionName in optionsObj ) {
					this.addIfToggled( paramPrefix + optionName, optionsObj[ optionName ] );
				}
			},
			// Map date form values to API parameters
			addDateFilters: function ( fromVal, toVal ) {
				if ( fromVal ) {
					const fromDate = moment.utc( fromVal ).subtract( offset, 'minutes' );
					// eslint-disable-next-line camelcase
					this.params.date_range_from = fromDate.toISOString();
				} else {
					delete this.params.date_range_from;
				}
				if ( toVal ) {
					const toDate = moment.utc( toVal ).subtract( offset, 'minutes' );
					// move to the end of the given day
					toDate.add( 1, 'day' ).subtract( 1, 'second' );
					// eslint-disable-next-line camelcase
					this.params.date_range_to = toDate.toISOString();
				} else {
					delete this.params.date_range_to;
				}
			},
			// clear existing params for the 'that' filter
			clearAllThatFilterParams: function () {
				for ( const filter in filtersToParams ) {
					delete this.params[ filtersToParams[ filter ] ];
				}
			},
			// Map NPP form values to API parameters and unset user form value if needed
			addNppFilter: function () {
				// username requires text input
				if ( this.applied.nppFilter === 'username' && this.applied.nppFilterUser ) {
					this.params.username = this.applied.nppFilterUser;
				} else {
					// unset username when another filter is selected
					this.unsaved.nppFilterUser = '';
					// everything else is logically boolean and should set a numeric API
					// parameter if defined
					if ( filtersToParams[ this.applied.nppFilter ] !== undefined ) {
						this.params[ filtersToParams[ this.applied.nppFilter ] ] = 1;
					}
				}
			},
			addAfcFilter: function () {
				// username requires text input
				if ( this.applied.afcFilter === 'username' && this.applied.afcFilterUser ) {
					this.params.username = this.applied.afcFilterUser;
				} else {
					// unset username when another filter is selected
					this.unsaved.afcFilterUser = '';
					// everything else is logically boolean and should set a numeric API
					// parameter if defined
					if ( filtersToParams[ this.applied.afcFilter ] !== undefined ) {
						this.params[ filtersToParams[ this.applied.afcFilter ] ] = 1;
					}
				}
			},
			// Set API parameters from form values
			setApiParams: function () {
				this.params.mode = this.immediate.queueMode;
				this.clearAllThatFilterParams();
				if ( this.params.mode === 'npp' ) {
					delete this.params.afc_state;
					this.addIfToggled( 'showreviewed', this.applied.nppIncludeReviewed );
					this.addIfToggled( 'showunreviewed', this.applied.nppIncludeUnreviewed );
					this.addIfToggled( 'showdeleted', this.applied.nppIncludeNominated );
					this.addIfToggled( 'showredirs', this.applied.nppIncludeRedirects );
					this.addIfToggled( 'showothers', this.applied.nppIncludeOthers );
					this.addNppFilter();
					this.addOresFilters( this.applied.nppPredictedRating, 'show_predicted_class_' );
					this.addOresFilters( this.applied.nppPossibleIssues, 'show_predicted_issues_' );
					this.params.namespace = this.applied.nppNamespace;
					this.params.dir = this.immediate.nppSortDir;
					this.addDateFilters( this.applied.nppDate.from, this.applied.nppDate.to );
				} else {
					delete this.params.showdeleted;
					delete this.params.showredirs;
					delete this.params.showothers;
					this.addOresFilters( this.applied.afcPredictedRating, 'show_predicted_class_' );
					this.addOresFilters( this.applied.afcPossibleIssues, 'show_predicted_issues_' );
					this.addAfcFilter();
					this.params.showreviewed = 1;
					this.params.showunreviewed = 1;
					this.params.namespace = mw.config.get( 'wgNamespaceIds' ).draft || 118;
					this.params.dir = this.immediate.afcSort;
					this.afcStateFilterToParam( this.applied.afcSubmissionState );
					this.addDateFilters( this.applied.afcDate.from, this.applied.afcDate.to );
				}
				// Set the filter parameters to a users option
				mw.user.options.set( 'userjs-NewPagesFeedFilterOptions', JSON.stringify( this.params ) );
				// Store the option to a user preference if possible
				if ( mw.user.isNamed() ) {
					new mw.Api().saveOption( 'userjs-NewPagesFeedFilterOptions', JSON.stringify( this.params ) );
				// Otherwise use local storage
				} else {
					localStorage.setItem( 'userjs-NewPagesFeedFilterOptions', JSON.stringify( this.params ) );
				}
			}
		}
	} )
};
