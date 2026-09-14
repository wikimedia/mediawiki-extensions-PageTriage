/**
 * Username filter helpers.
 *
 * MAX_FILTER_USERNAMES must stay in sync with PageTriageUtil::MAX_USERNAME_FILTER.
 */

const MAX_FILTER_USERNAMES = 10;

/**
 * Normalize a username value into a de-duplicated list.
 *
 * @param {string[]|string|number|undefined} value
 * @return {string[]} Up to MAX_FILTER_USERNAMES distinct usernames
 */
function normalizeUsernames( value ) {
	let raw = [];
	if ( Array.isArray( value ) ) {
		raw = value;
	} else if ( typeof value === 'string' && value ) {
		raw = value.split( '|' );
	}

	const usernames = [];
	for ( const item of raw ) {
		if ( typeof item !== 'string' ) {
			continue;
		}
		const username = item.replace( /_/g, ' ' ).trim();
		if ( username && !usernames.includes( username ) ) {
			usernames.push( username );
		}
		if ( usernames.length === MAX_FILTER_USERNAMES ) {
			break;
		}
	}
	return usernames;
}

module.exports = {
	MAX_FILTER_USERNAMES,
	normalizeUsernames
};
