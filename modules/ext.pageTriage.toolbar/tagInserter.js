const MOSOrderPositionFinder = require( './MOSOrderPositionFinder.js' );

/**
 * Map PageTriage tag `position` values to MOS:ORDER sections.
 *
 * @type {Object.<string, string>}
 */
const POSITION_TO_SECTION = {
	top: 'maintenanceTags',
	categories: 'improveCategories',
	bottom: 'stubTemplates'
};

/**
 * Given a template name, return that template's wikitext from the article, including
 * nested templates.
 *
 * @param {string} wikitext
 * @param {string} name
 * @return {string}
 */
function extractTemplate( wikitext, name ) {
	const tagStart = '{{' + name;
	const startingIndex = wikitext.indexOf( tagStart );

	if ( !wikitext.includes( tagStart ) ) {
		return '';
	}

	let templateBraces = 0;

	for ( let i = startingIndex; i < wikitext.length; i++ ) {
		if ( wikitext[ i ] === '{' ) {
			templateBraces++;
		} else if ( wikitext[ i ] === '}' ) {
			templateBraces--;
		}

		if ( templateBraces === 0 ) {
			return wikitext.slice( startingIndex, i + 1 );
		}
	}

	return '';
}

/**
 * If `wrapper` is already on the page, append `innerWikitext` inside it.
 * Otherwise return the original wikitext unchanged.
 *
 * @param {string} wikitext
 * @param {string} wrapper
 * @param {string} innerWikitext
 * @return {string}
 */
function mergeIntoWrapper( wikitext, wrapper, innerWikitext ) {
	const existingWrapper = extractTemplate( wikitext, wrapper );
	if ( !existingWrapper ) {
		return wikitext;
	}

	return wikitext.replace(
		existingWrapper,
		existingWrapper.slice( 0, existingWrapper.length - 2 ).trim() + innerWikitext + '\n}}'
	);
}

/**
 * Insert `needle` at a MOS:ORDER section, creating the section if needed.
 *
 * @param {string} wikitext
 * @param {string} needle
 * @param {string} section
 * @return {string}
 */
function insertAtMosSection( wikitext, needle, section ) {
	if ( !needle ) {
		return wikitext;
	}
	const finder = new MOSOrderPositionFinder();
	return finder.insertAtSection( wikitext, needle, section );
}

/**
 * Insert `needle` using a PageTriage tag position (`top`, `categories`, `bottom`).
 *
 * @param {string} wikitext
 * @param {string} needle
 * @param {string} [position]
 * @return {string}
 */
function insertTags( wikitext, needle, position ) {
	const section = POSITION_TO_SECTION[ position ] || POSITION_TO_SECTION.top;
	return insertAtMosSection( wikitext, needle, section );
}

/**
 * Wrap redirect tags in {{Redirect category shell}}. Merge into an existing
 * wrapper when present; otherwise insert immediately after the #REDIRECT line.
 *
 * @param {string} wikitext
 * @param {string} wrapper
 * @param {string} innerWikitext
 * @return {string}
 */
function insertRedirectTags( wikitext, wrapper, innerWikitext ) {
	if ( !innerWikitext ) {
		return wikitext;
	}

	const merged = mergeIntoWrapper( wikitext, wrapper, innerWikitext );
	if ( merged !== wikitext ) {
		return merged;
	}

	const wrapped = '{{' + wrapper + '|' + innerWikitext + '\n}}';
	const match = wikitext.match( /^#REDIRECT[^\n]*/i );
	if ( !match ) {
		return insertAtMosSection( wikitext, wrapped, 'bottom' );
	}

	const idx = match[ 0 ].length;
	const after = wikitext.slice( idx );
	const separator = after === '' || after.startsWith( '\n' ) ? '' : '\n';
	return wikitext.slice( 0, idx ) + '\n' + wrapped + separator + after;
}

module.exports = {
	POSITION_TO_SECTION,
	extractTemplate,
	mergeIntoWrapper,
	insertAtMosSection,
	insertTags,
	insertRedirectTags
};
