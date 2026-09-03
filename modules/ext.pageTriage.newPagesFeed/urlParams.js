/**
 * Session-only URL query parameters for Special:NewPagesFeed.
 *
 * Add a new entry to urlParamHandlers to support another query parameter.
 * Handlers run in order after saved prefs are loaded and must not persist.
 */

/**
 * @typedef {Object} UrlParamHandler
 * @property {string} name Query parameter name
 * @property {Function} [validate] Return true if the raw value should be applied.
 *   Called as validate( value, settings ).
 * @property {Function} apply Mutate the settings store with the validated value
 */

const STATUS_VALUES = [ 'reviewed', 'unreviewed' ];

/**
 * Split a comma-separated query value into trimmed lowercase tokens.
 *
 * @param {string} value
 * @return {string[]}
 */
function parseList( value ) {
	return value.split( ',' ).map( ( part ) => part.trim().toLowerCase() ).filter( Boolean );
}

/**
 * Parse a calendar date in YYYY-MM-DD form.
 * Trims whitespace and rejects impossible dates such as 2026-02-30.
 *
 * @param {string|null} value
 * @return {string|null} Normalized YYYY-MM-DD, or null if invalid
 */
function parseIsoDate( value ) {
	if ( !value ) {
		return null;
	}
	const trimmed = value.trim();
	const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec( trimmed );
	if ( !match ) {
		return null;
	}
	const year = Number( match[ 1 ] );
	const month = Number( match[ 2 ] );
	const day = Number( match[ 3 ] );
	const date = new Date( Date.UTC( year, month - 1, day ) );
	if (
		date.getUTCFullYear() !== year ||
		date.getUTCMonth() !== month - 1 ||
		date.getUTCDate() !== day
	) {
		return null;
	}
	return trimmed;
}

/**
 * Overlay from/to as a complete URL range. An omitted or invalid bound is
 * cleared so it does not merge with a saved preference.
 *
 * @param {Object} settings
 */
function applyDateRange( settings ) {
	const from = parseIsoDate( mw.util.getParamValue( 'from' ) );
	const to = parseIsoDate( mw.util.getParamValue( 'to' ) );
	settings.unsaved.nppDate.from = from || '';
	settings.unsaved.nppDate.to = to || '';
	settings.unsaved.afcDate.from = from || '';
	settings.unsaved.afcDate.to = to || '';
}

/**
 * Class keys currently present on the filter form.
 *
 * @param {Object} settings
 * @return {string[]}
 */
function getOresClasses( settings ) {
	const npp = settings.unsaved.nppPredictedRating || {};
	const afc = settings.unsaved.afcPredictedRating || {};
	return [ ...new Set( [ ...Object.keys( npp ), ...Object.keys( afc ) ] ) ];
}

/**
 * Ordered list of URL parameter handlers.
 *
 * @type {UrlParamHandler[]}
 */
const urlParamHandlers = [
	{
		name: 'feed',
		validate: ( value ) => {
			if ( value === 'npp' ) {
				return true;
			}
			// AFC is only available when a draft namespace is configured
			return value === 'afc' && !!mw.config.get( 'wgPageTriageDraftNamespaceId' );
		},
		apply: ( value, settings ) => {
			settings.immediate.queueMode = value;
		}
	},
	{
		name: 'username',
		apply: ( value, settings ) => {
			const username = value.replace( /_/g, ' ' );

			settings.unsaved.nppFilter = 'username';
			settings.unsaved.nppFilterUser = username;
			settings.unsaved.afcFilter = 'username';
			settings.unsaved.afcFilterUser = username;
		}
	},
	{
		name: 'status',
		validate: ( value ) => parseList( value ).some(
			( token ) => STATUS_VALUES.includes( token )
		),
		apply: ( value, settings ) => {
			const tokens = parseList( value );
			settings.unsaved.nppIncludeReviewed = tokens.includes( 'reviewed' );
			settings.unsaved.nppIncludeUnreviewed = tokens.includes( 'unreviewed' );
		}
	},
	{
		name: 'from',
		validate: ( value ) => parseIsoDate( value ) !== null,
		apply: ( _value, settings ) => {
			applyDateRange( settings );
		}
	},
	{
		name: 'to',
		validate: ( value ) => parseIsoDate( value ) !== null,
		apply: ( _value, settings ) => {
			applyDateRange( settings );
		}
	},
	{
		name: 'ores',
		validate: ( value, settings ) => {
			// Hidden when ORES is unavailable or filters are disabled for this wiki
			if ( !mw.config.get( 'wgShowOresFilters' ) ) {
				return false;
			}
			const classes = getOresClasses( settings );
			return parseList( value ).some( ( token ) => classes.includes( token ) );
		},
		apply: ( value, settings ) => {
			const tokens = parseList( value );
			const ratings = [
				settings.unsaved.nppPredictedRating,
				settings.unsaved.afcPredictedRating
			];
			for ( const rating of ratings ) {
				if ( !rating ) {
					continue;
				}
				for ( const name of Object.keys( rating ) ) {
					rating[ name ] = tokens.includes( name );
				}
			}
		}
	}
];

/**
 * Apply supported URL query parameters onto the settings store.
 *
 * @param {Object} settings Pinia settings store instance
 * @return {boolean} Whether any parameter was applied
 */
function applyUrlParams( settings ) {
	let applied = false;
	for ( const handler of urlParamHandlers ) {
		const value = mw.util.getParamValue( handler.name );
		if ( !value ) {
			continue;
		}
		if ( handler.validate && !handler.validate( value, settings ) ) {
			continue;
		}
		handler.apply( value, settings );
		applied = true;
	}
	return applied;
}

module.exports = {
	applyUrlParams
};
