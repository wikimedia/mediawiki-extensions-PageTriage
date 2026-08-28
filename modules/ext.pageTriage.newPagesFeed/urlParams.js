/**
 * Session-only URL query parameters for Special:NewPagesFeed.
 *
 * Add a new entry to urlParamHandlers to support another query parameter.
 * Handlers run in order after saved prefs are loaded and must not persist.
 */

/**
 * @typedef {Object} UrlParamHandler
 * @property {string} name Query parameter name
 * @property {Function} [validate] Return true if the raw value should be applied
 * @property {Function} apply Mutate the settings store with the validated value
 */

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
		if ( handler.validate && !handler.validate( value ) ) {
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
