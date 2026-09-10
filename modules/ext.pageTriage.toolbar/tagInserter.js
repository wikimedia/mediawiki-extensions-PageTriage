const MOSOrderPositionFinder = require( './MOSOrderPositionFinder.js' );
const mosOrderSectionData = require( './mosOrderSectionData.json' );
const { maintenanceTags } = require( 'ext.pageTriage.tagData' );

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
 * Normalize a template name for comparison (case, `_` vs space).
 *
 * @param {string} name
 * @return {string}
 */
function normalizeTemplateName( name ) {
	return name.replace( /_/g, ' ' ).replace( /\s+/g, ' ' ).trim().toLowerCase();
}

/**
 * Canonical name plus aliases from mosOrderSectionData.json.
 *
 * @param {string} wrapper Canonical wrapper name
 * @return {string[]}
 */
function getWrapperAliases( wrapper ) {
	const target = normalizeTemplateName( wrapper );
	for ( const spec of Object.values( mosOrderSectionData.sections ) ) {
		if ( !spec || !Array.isArray( spec.names ) ) {
			continue;
		}
		for ( const item of spec.names ) {
			if ( !item || typeof item !== 'object' || typeof item.name !== 'string' ) {
				continue;
			}
			const group = [ item.name ].concat( item.aliases || [] );
			if ( group.some( ( name ) => normalizeTemplateName( name ) === target ) ) {
				return group;
			}
		}
	}
	return [ wrapper ];
}

/**
 * @param {string} name
 * @param {string} wrapper
 * @return {boolean}
 */
function isWrapperName( name, wrapper ) {
	const normalized = normalizeTemplateName( name );
	return getWrapperAliases( wrapper ).some(
		( alias ) => normalizeTemplateName( alias ) === normalized
	);
}

/**
 * @param {string} name
 * @return {string}
 */
function escapeTemplateNameForRegExp( name ) {
	return name
		.replace( /_/g, ' ' )
		.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' )
		.replace( / /g, '[ _]' );
}

/**
 * Index of `{{Name` in wikitext, case-insensitive, `_` as space.
 *
 * @param {string} wikitext
 * @param {string} name
 * @return {number} -1 if not found
 */
function findTemplateStart( wikitext, name ) {
	const pattern = '\\{\\{\\s*' +
		escapeTemplateNameForRegExp( name ) +
		'(?=[\\s_]*[|}])';
	const match = wikitext.match( new RegExp( pattern, 'i' ) );
	return match ? match.index : -1;
}

/**
 * Extract a template that begins at `startingIndex`.
 *
 * @param {string} wikitext
 * @param {number} startingIndex
 * @return {string}
 */
function extractTemplateAt( wikitext, startingIndex ) {
	if ( wikitext.slice( startingIndex, startingIndex + 2 ) !== '{{' ) {
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
 * Given a template name, return that template's wikitext from the article, including
 * nested templates.
 *
 * @param {string} wikitext
 * @param {string} name
 * @return {string}
 */
function extractTemplate( wikitext, name ) {
	const startingIndex = findTemplateStart( wikitext, name );
	if ( startingIndex === -1 ) {
		return '';
	}
	return extractTemplateAt( wikitext, startingIndex );
}

/**
 * @param {string} templateWikitext
 * @return {string}
 */
function getTemplateName( templateWikitext ) {
	const match = templateWikitext.match( /^\{\{\s*([^|{}]+)/ );
	return match ? match[ 1 ].trim() : '';
}

/**
 * Inner wikitext of a wrapper template (everything after the first `|`).
 *
 * @param {string} wrapperWikitext
 * @return {string}
 */
function getWrapperInner( wrapperWikitext ) {
	const withoutBraces = wrapperWikitext.slice( 2, -2 );
	const pipe = withoutBraces.indexOf( '|' );
	if ( pipe === -1 ) {
		return '';
	}
	return withoutBraces.slice( pipe + 1 ).trim();
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
		for ( const alias of getWrapperAliases( wrapper ) ) {
			if ( normalizeTemplateName( alias ) === normalizeTemplateName( wrapper ) ) {
				continue;
			}
			const aliased = extractTemplate( wikitext, alias );
			if ( aliased ) {
				return mergeIntoWrapper( wikitext, alias, innerWikitext );
			}
		}
		return wikitext;
	}

	let head = existingWrapper.slice( 0, existingWrapper.length - 2 ).trim();
	if ( !head.includes( '|' ) ) {
		head += '|';
	}
	const inner = innerWikitext.replace( /^\n+/, '' );
	const replacement = head + '\n' + inner + '\n}}';
	const idx = wikitext.indexOf( existingWrapper );
	return wikitext.slice( 0, idx ) + replacement +
		wikitext.slice( idx + existingWrapper.length );
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
	const positionFinder = new MOSOrderPositionFinder();
	return positionFinder.insertAtSection( wikitext, needle, section );
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
 * @param {string} section
 * @return {string[]}
 */
function listSectionTemplateNames( section ) {
	const spec = mosOrderSectionData.sections[ section ];
	if ( !spec ) {
		return [];
	}
	return new MOSOrderPositionFinder().getNames( spec.names );
}

/**
 * Names absorbAdjacentTags may pull into {{Multiple issues}}: MOS:ORDER
 * maintenance templates plus PageTriage `multiple: true` tags, minus
 * `multiple: false` (those stay outside the wrapper).
 *
 * @return {string[]}
 */
function listGroupableMaintenanceTagNames() {
	const names = listSectionTemplateNames( 'maintenanceTags' );
	const tagOptions = maintenanceTags.tagOptions;
	if ( !tagOptions ) {
		return names;
	}

	const excluded = new Set();
	for ( const catName in tagOptions ) {
		if ( catName === 'redirects' ) {
			continue;
		}
		const cat = tagOptions[ catName ];
		if ( !cat || !cat.tags ) {
			continue;
		}
		for ( const tag of Object.values( cat.tags ) ) {
			if ( !tag || !tag.tag ) {
				continue;
			}
			if ( tag.multiple === false ) {
				excluded.add( normalizeTemplateName( tag.tag ) );
			} else if ( tag.multiple && ( !tag.position || tag.position === 'top' ) ) {
				names.push( tag.tag );
			}
		}
	}

	return names.filter(
		( name ) => !excluded.has( normalizeTemplateName( name ) )
	);
}

/**
 * @return {string[]}
 */
function listRedirectTemplateNames() {
	const names = getWrapperAliases( maintenanceTags.redirectCategoryShell );
	const redirects = maintenanceTags.tagOptions && maintenanceTags.tagOptions.redirects;
	if ( redirects && redirects.tags ) {
		for ( const tag of Object.values( redirects.tags ) ) {
			if ( tag.tag ) {
				names.push( tag.tag );
			}
		}
	}
	return names;
}

/**
 * End of `#REDIRECT [[target]]`, not including same-line templates.
 *
 * @param {string} wikitext
 * @return {number} -1 if the page is not a redirect
 */
function getRedirectTargetEnd( wikitext ) {
	const match = wikitext.match( /^#REDIRECT\s*:?\s*\[\[[^\]]*\]\]/i );
	return match ? match[ 0 ].length : -1;
}

/**
 * Start of the outermost template that contains `pos`, or -1 if `pos` is not
 * inside a template.
 *
 * @param {string} wikitext
 * @param {number} pos
 * @return {number}
 */
function findEnclosingTemplateStart( wikitext, pos ) {
	let depth = 0;
	let start = -1;
	for ( let i = 0; i < pos; i++ ) {
		if ( wikitext.slice( i, i + 2 ) === '{{' ) {
			if ( depth === 0 ) {
				start = i;
			}
			depth++;
			i++;
		} else if ( wikitext.slice( i, i + 2 ) === '}}' ) {
			depth--;
			if ( depth <= 0 ) {
				depth = 0;
				start = -1;
			}
			i++;
		}
	}
	return depth > 0 ? start : -1;
}

/**
 * @param {string} wikitext
 * @param {Object} options
 * @param {string} [options.section] a section name
 * @param {boolean} [options.afterRedirect]
 * @return {number}
 */
function getScanStart( wikitext, options ) {
	let pos = -1;
	if ( options.section ) {
		const positionFinder = new MOSOrderPositionFinder();
		const sectionPos = positionFinder.getAllSectionPositions( wikitext )[ options.section ];
		pos = typeof sectionPos === 'number' ? sectionPos : -1;
	} else if ( options.afterRedirect ) {
		const idx = getRedirectTargetEnd( wikitext );
		if ( idx < 0 ) {
			return -1;
		}
		let i = idx;
		while ( i < wikitext.length && /\s/.test( wikitext[ i ] ) ) {
			i++;
		}
		pos = i < wikitext.length ? i : -1;
	}
	if ( pos < 0 ) {
		return -1;
	}
	const enclosing = findEnclosingTemplateStart( wikitext, pos );
	return enclosing >= 0 ? enclosing : pos;
}

/**
 * Consecutive templates at `start` whose names are in `nameSet`.
 *
 * @param {string} wikitext
 * @param {number} start
 * @param {Set<string>} nameSet
 * @return {Object[]}
 */
function extractConsecutiveNamedTemplates( wikitext, start, nameSet ) {
	const templates = [];
	if ( start < 0 ) {
		return templates;
	}

	let i = start;
	while ( i < wikitext.length ) {
		while ( i < wikitext.length && /\s/.test( wikitext[ i ] ) ) {
			i++;
		}
		if ( wikitext.slice( i, i + 2 ) !== '{{' ) {
			break;
		}
		const text = extractTemplateAt( wikitext, i );
		if ( !text ) {
			break;
		}
		const name = getTemplateName( text );
		if ( !nameSet.has( normalizeTemplateName( name ) ) ) {
			break;
		}
		templates.push( {
			text: text,
			start: i,
			end: i + text.length,
			name: name
		} );
		i += text.length;
	}

	return templates;
}

/**
 * After inserting tags, pull leftover standalone tags (or a mix of wrapper +
 * standalones) into a single wrapper. Fixes T361988 / T323883 and the same
 * hole for {{Redirect category shell}}.
 *
 * @param {string} wikitext
 * @param {string} wrapper
 * @param {Object} options
 * @param {boolean} [options.afterRedirect]
 * @return {string}
 */
function absorbAdjacentTags( wikitext, wrapper, options ) {
	const start = getScanStart( wikitext, options );
	if ( start < 0 ) {
		return wikitext;
	}

	const names = (
		options.afterRedirect ?
			listRedirectTemplateNames() :
			listGroupableMaintenanceTagNames()
	);
	const nameSet = new Set(
		names.concat( getWrapperAliases( wrapper ) ).map( normalizeTemplateName )
	);
	const templates = extractConsecutiveNamedTemplates( wikitext, start, nameSet );
	if ( !templates.length ) {
		return wikitext;
	}

	const wrapperInners = [];
	const standalones = [];
	let wrapperName = wrapper;
	for ( const template of templates ) {
		if ( isWrapperName( template.name, wrapper ) ) {
			wrapperName = template.name.replace( /_/g, ' ' );
			wrapperInners.push( getWrapperInner( template.text ) );
		} else {
			standalones.push( template.text );
		}
	}

	if ( wrapperInners.length === 1 && standalones.length === 0 ) {
		return wikitext;
	}
	if ( wrapperInners.length === 0 && standalones.length <= 1 ) {
		return wikitext;
	}

	const allInner = wrapperInners.concat( standalones )
		.map( ( part ) => part.trim() )
		.filter( Boolean )
		.join( '\n' );
	const wrapped = '{{' + wrapperName + '|\n' + allInner + '\n}}';
	return wikitext.slice( 0, templates[ 0 ].start ) +
		wrapped +
		wikitext.slice( templates[ templates.length - 1 ].end );
}

/**
 * Insert maintenance tags, merging into {{Multiple issues}} when present and
 * wrapping adjacent standalone tags (T361988, T323883).
 *
 * @param {string} wikitext
 * @param {string} wrapper
 * @param {string} innerWikitext
 * @param {number} newCount
 * @return {string}
 */
function insertMaintenanceTags( wikitext, wrapper, innerWikitext, newCount ) {
	if ( !innerWikitext ) {
		return wikitext;
	}

	let result = mergeIntoWrapper( wikitext, wrapper, innerWikitext );
	if ( result === wikitext ) {
		const needle = newCount > 1 ?
			'{{' + wrapper + '|' + innerWikitext + '\n}}' :
			innerWikitext;
		result = insertAtMosSection( wikitext, needle, 'maintenanceTags' );
	}

	return absorbAdjacentTags( result, wrapper, { section: 'maintenanceTags' } );
}

/**
 * Wrap redirect tags in {{Redirect category shell}}. Merge into an existing
 * wrapper when present; otherwise insert immediately after the #REDIRECT line.
 * Standalone R-tags already on the page are pulled into the same wrapper.
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

	let result = mergeIntoWrapper( wikitext, wrapper, innerWikitext );
	if ( result === wikitext ) {
		const wrapped = '{{' + wrapper + '|' + innerWikitext + '\n}}';
		const idx = getRedirectTargetEnd( wikitext );
		if ( idx < 0 ) {
			result = insertAtMosSection( wikitext, wrapped, 'bottom' );
		} else {
			let after = wikitext.slice( idx );
			const separator = after === '' || after.startsWith( '\n' ) ? '' : '\n';
			if ( separator === '\n' ) {
				after = after.replace( /^[^\S\n]+/, '' );
			}
			result = wikitext.slice( 0, idx ) + '\n' + wrapped + separator + after;
		}
	}

	return absorbAdjacentTags( result, wrapper, { afterRedirect: true } );
}

module.exports = {
	POSITION_TO_SECTION,
	extractTemplate,
	mergeIntoWrapper,
	insertAtMosSection,
	insertTags,
	insertMaintenanceTags,
	insertRedirectTags
};
