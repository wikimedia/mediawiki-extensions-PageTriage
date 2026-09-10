const mosOrderSectionData = require( './mosOrderSectionData.json' );

/**
 * Utilities to help user scripts properly follow English Wikipedia's MOS:ORDER.
 * Most of the methods need wikitext and a specific MOS:ORDER section as parameters,
 * then output something useful related to MOS:ORDER.
 *
 * Template names and other lookups live in mosOrderSectionData.json.
 */
class MOSOrderPositionFinder {
	/**
	 * Taken from https://github.com/NovemLinguae/UserScripts/blob/master/SpeciesHelper/modules/MOSOrderPositionFinder.js
	 * Determines whether the given wikitext has the specified MOS:ORDER section
	 *
	 * @author Novem Linguae
	 * @param {string} wikicode
	 * @param {string} section One of the keys in mosOrderSectionData.sectionOrder
	 * @return {boolean} whether the section is present
	 */
	hasSection( wikicode, section ) {
		this.wikitext = wikicode;
		this.calculate();
		return this.getSectionStartPosition( section ) !== -1;
	}

	/**
	 * Returns the numerical position of an MOS:ORDER section in wikitext
	 *
	 * @param {string} wikicode
	 * @param {string} section One of the keys in mosOrderSectionData.sectionOrder
	 * @return {number} sectionPosition: -1 if no section, integer if section. Counting starts at 0.
	 */
	getSectionPosition( wikicode, section ) {
		this.wikitext = wikicode;
		this.calculate();
		let position = this.getSectionStartPosition( section );
		if ( position === -1 ) {
			position = this.getPositionOfClosestSection( section );
		}
		return position;
	}

	/**
	 * Insert a string at the specified section in the wikitext. If section is absent, will guess
	 * where the section should go. Do not add whitespace, it will be computed for you.
	 *
	 * @param {string} wikicode
	 * @param {string} needle The string to insert
	 * @param {string} section One of the keys in mosOrderSectionData.sectionOrder
	 * @return {string} wikitext Transformed wikitext, containing the inserted string
	 */
	insertAtSection( wikicode, needle, section ) {
		this.wikitext = wikicode;

		// fix more than two enters in a row
		// this.wikitext = this.wikitext.replace(/\n{3,}/g, '\n\n');

		this.calculate();

		let position = this.getSectionStartPosition( section );
		if ( typeof position === 'undefined' ) {
			throw new Error( 'MOSOrderPositionFinder: invalid section supplied to function insertAtSection()' );
		}
		let hadToCreateNewSection = false;
		if ( position === -1 ) {
			position = this.getPositionOfClosestSection( section );
			hadToCreateNewSection = true;
		}

		let topHalf = this.wikitext.slice( 0, position );
		let bottomHalf = this.wikitext.slice( position );

		// TODO: these are band aid fixes, they need a rewrite. should probably add the ideal # of
		// blank lines beneath each section to the list of sections, and then do a foreach loop
		// through that
		// if too much whitespace, reduce amount of whitespace
		topHalf = topHalf.replace( /\n{3,}$/, '\n\n' );
		bottomHalf = bottomHalf.replace( /^\n{3,}/, '\n\n' );

		if ( topHalf.endsWith( '\n\n' ) ) {
			// intentionally left blank
		} else if ( topHalf.endsWith( '\n' ) ) {
			topHalf += '\n';
		} else {
			topHalf += '\n\n';
		}

		if ( !bottomHalf.startsWith( '\n' ) ) {
			bottomHalf = '\n' + bottomHalf;
		}

		if ( hadToCreateNewSection && !bottomHalf.startsWith( '\n\n' ) ) {
			bottomHalf = '\n' + bottomHalf;
		}

		this.wikitext = topHalf + needle + bottomHalf;

		if ( section === 'shortDescription' ) {
			// If a template is beneath the insertion point, don't put a blank line
			// between the short description and the other template.
			const shortDescriptionNames = this.joinTemplateNamesForRegExp(
				this.getNames( mosOrderSectionData.sections.shortDescription.names )
			);
			this.wikitext = this.wikitext.replace(
				new RegExp(
					'(\\{\\{(?:' + shortDescriptionNames + ')\\|[^}]+\\}\\}\\n)\\n(\\{\\{)',
					'i'
				),
				'$1$2'
			);
		}

		this.wikitext = this.wikitext.trim() + '\n';
		return this.wikitext;
	}

	/**
	 * Returns all section positions. Useful for testing.
	 *
	 * @param {string} wikicode
	 * @return {Object} An object with section names as keys and their positions in the wikitext as
	 * values. 0 is the very beginning of the string. -1 means the section is not present.
	 */
	getAllSectionPositions( wikicode ) {
		this.wikitext = wikicode;
		this.calculate();
		return this.sectionStartPositions;
	}

	/**
	 * Returns all section positions that exist (that aren't -1). Useful for testing.
	 *
	 * @param {string} wikicode
	 * @return {Object} An object with section names as keys and their positions in the wikitext as
	 * values. 0 is the very beginning of the string.
	 */
	getAllExistingSectionPositions( wikicode ) {
		this.wikitext = wikicode;
		this.calculate();
		const sections = {};
		for ( const key in this.sectionStartPositions ) {
			if ( this.sectionStartPositions[ key ] !== -1 ) {
				sections[ key ] = this.sectionStartPositions[ key ];
			}
		}
		return sections;
	}

	calculate() {
		this.sectionOrder = mosOrderSectionData.sectionOrder;
		this.sectionStartPositions = {};

		for ( const section of this.sectionOrder ) {
			this.sectionStartPositions[ section ] = this.detectSection(
				mosOrderSectionData.sections[ section ]
			);
		}
		// One-off for body section
		if (
			this.sectionStartPositions.body > -1 &&
			this.wikitext.charAt( this.sectionStartPositions.body ) === '\n'
		) {
			this.sectionStartPositions.body += 1;
		}
		// If the body is the same position as any of the appendices, set body to -1, since there
		// isn't really a body, just appendices.
		const appendices = mosOrderSectionData.appendices.map(
			( section ) => this.sectionStartPositions[ section ]
		);
		if (
			this.sectionStartPositions.body !== -1 &&
			appendices.includes( this.sectionStartPositions.body )
		) {
			this.sectionStartPositions.body = -1;
		}

		if ( this.debug ) {
			for ( const section of this.sectionOrder ) {
				const position = this.getSectionStartPosition( section );
				const chunkPreview = this.wikitext.slice( position, position + 50 );
				// eslint-disable-next-line no-console
				console.log( `${ section }: ${ position }: ${ chunkPreview }` );
			}
		}
	}

	/**
	 * @param {Object} spec
	 * @return {number}
	 */
	detectSection( spec ) {
		if ( !spec ) {
			throw new Error( 'MOSOrderPositionFinder: missing section detection data' );
		}

		const names = this.getNames( spec.names );

		switch ( spec.type ) {
			case 'start':
				return 0;
			case 'end':
				return this.wikitext.length;
			case 'none':
				return -1;
			case 'templates':
				return this.lookForTemplates( this.wikitext, names );
			case 'headings':
				return this.lookForHeadings( this.wikitext, names );
			case 'strings':
				return this.lookForStrings( this.wikitext, names );
			case 'regex':
				return this.lookForRegEx(
					this.wikitext,
					new RegExp( spec.pattern, spec.flags || '' )
				);
			case 'templatesNotInsideTemplate':
				return this.findTemplateNotInsideTemplate( this.wikitext, names );
			case 'firstNonTemplateNonWhitespace':
				return this.getFirstNonTemplateNonWhitespace( this.wikitext );
			default:
				throw new Error( 'MOSOrderPositionFinder: unknown detection type: ' + spec.type );
		}
	}

	/**
	 * Find the lead: first non-template, non-whitespace, non-HTML-comment text.
	 * Skipping HTML comments handles an edge case involving AfC drafts.
	 *
	 * @param {string} wikicode
	 * @return {number}
	 */
	getFirstNonTemplateNonWhitespace( wikicode ) {
		const length = wikicode.length;
		let nesting = 0;
		for ( let i = 0; i < length; i++ ) {
			const chunk = wikicode.slice( i );
			if ( chunk.startsWith( '{{' ) || chunk.startsWith( '<!--' ) ) {
				nesting++;
			} else if ( chunk.startsWith( '}}' ) || chunk.startsWith( '->' ) ) {
				// Chunks in this conditional must only be 2 characters wide
				nesting--;
				i++; // skip 2nd }
			} else if ( nesting === 0 && !chunk.match( /^\s/ ) ) {
				return i;
			}
		}
		return -1;
	}

	findTemplateNotInsideTemplate( wikicode, arrayOfStrings ) {
		const length = wikicode.length;
		for ( const string of arrayOfStrings ) {
			let nesting = 0;
			for ( let i = 0; i < length; i++ ) {
				const chunk = wikicode.slice( i, i + 20 );
				const match = chunk.match( new RegExp( '^\\{\\{' + string, 'i' ) );
				if ( nesting === 0 && match ) {
					return i;
				} else if ( chunk.startsWith( '{{' ) ) {
					nesting++;
				} else if ( chunk.startsWith( '}}' ) ) {
					nesting--;
					i++; // skip 2nd }
				}
			}
		}
		return -1;
	}

	/**
	 * Pull string names out of a section `names` list, skipping `"*"` comments.
	 *
	 * @param {Array} names
	 * @return {string[]}
	 */
	getNames( names ) {
		if ( !Array.isArray( names ) ) {
			return [];
		}
		const result = [];
		for ( const item of names ) {
			if ( typeof item === 'string' ) {
				result.push( item );
			} else if ( item && typeof item.name === 'string' ) {
				result.push( item.name );
				if ( Array.isArray( item.aliases ) ) {
					for ( const alias of item.aliases ) {
						if ( typeof alias === 'string' ) {
							result.push( alias );
						}
					}
				}
			}
		}
		return result;
	}

	/**
	 * Convert template names to a RegExp alternation. Underscores are treated as
	 * spaces, every space can be a space or underscore, and regex metacharacters
	 * are escaped.
	 *
	 * @param {string[]} templateNames
	 * @return {string}
	 */
	joinTemplateNamesForRegExp( templateNames ) {
		return templateNames.map( ( name ) => name
			.replace( /_/g, ' ' )
			.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' )
			.replace( / /g, '[ _]' )
		).join( '|' );
	}

	/**
	 * Whether this name should match any template that starts with it.
	 *
	 * @param {string} name
	 * @return {boolean}
	 */
	isPrefixTemplateName( name ) {
		const PREFIX_TEMPLATE_NAMES = new Set( [
			'Infobox',
			'Pp',
			'S-'
		] );
		return PREFIX_TEMPLATE_NAMES.has( name ) || name.endsWith( '-' );
	}

	/**
	 * Find the first exact template match, unless the name is a known prefix
	 * (Infobox, Pp, S-). Exact names must be followed by optional whitespace and
	 * then `|` or `}`, so {{Prod}} does not match {{Production}}.
	 *
	 * @param {string} haystack
	 * @param {string[]} arrayOfTemplateNames
	 * @return {number}
	 */
	lookForTemplates( haystack, arrayOfTemplateNames ) {
		const prefixNames = [];
		const exactNames = [];
		for ( const name of arrayOfTemplateNames ) {
			if ( this.isPrefixTemplateName( name ) ) {
				prefixNames.push( name );
			} else {
				exactNames.push( name );
			}
		}

		const alternatives = [];
		if ( exactNames.length ) {
			alternatives.push(
				'(?:' + this.joinTemplateNamesForRegExp( exactNames ) + ')(?=[\\s_]*[|}])'
			);
		}
		if ( prefixNames.length ) {
			alternatives.push(
				'(?:' + this.joinTemplateNamesForRegExp( prefixNames ) + ')'
			);
		}

		const regExString = '\\{\\{\\s*(?:' +
			alternatives.join( '|' ) +
			')(?![ -\\|]section)';
		// Don't match section maintenance tags, e.g. {{More citations needed section}}
		// and {{More citations needed|section}}
		const matches = haystack.match( new RegExp( regExString, 'i' ) );
		return matches ? matches.index : -1;
	}

	/**
	 * Heading names are not RegEx escaped.
	 *
	 * @param {string} haystack
	 * @param {string[]} arrayOfHeadingNames
	 * @return {number}
	 */
	lookForHeadings( haystack, arrayOfHeadingNames ) {
		let regExString = '={2,}\\s*(?:';
		for ( const name of arrayOfHeadingNames ) {
			regExString += name + '|';
		}
		regExString = regExString.slice( 0, -1 ); // delete last character |
		regExString += ')';
		const matches = haystack.match( new RegExp( regExString, 'i' ) );
		return matches ? matches.index : -1;
	}

	lookForStrings( haystack, arrayOfRegExStrings ) {
		let regExString = '(?:';
		for ( const name of arrayOfRegExStrings ) {
			regExString += name + '|';
		}
		regExString = regExString.slice( 0, -1 ); // delete last character |
		regExString += ')';
		const matches = haystack.match( new RegExp( regExString, 'i' ) );
		return matches ? matches.index : -1;
	}

	lookForRegEx( haystack, regEx ) {
		const matches = haystack.match( regEx );
		return matches ? matches.index : -1;
	}

	getSectionStartPosition( section ) {
		const validSection = section in this.sectionStartPositions;
		if ( !validSection ) {
			throw new Error( 'MOSOrderPositionFinder: Invalid section name.' );
		}
		return this.sectionStartPositions[ section ];
	}

	// https://stackoverflow.com/a/13109786/3480193
	arraySearch( arr, val ) {
		for ( let i = 0; i < arr.length; i++ ) {
			if ( arr[ i ] === val ) {
				return i;
			}
		}
		return false;
	}

	getPositionOfClosestSection( section ) {
		const sectionKey = this.arraySearch( this.sectionOrder, section );

		// scan until you find a section that is not -1
		// can scan in either direction. I chose to scan down.
		for ( let i = sectionKey; i < this.sectionOrder.length; i++ ) {
			const sectionKey2 = this.sectionOrder[ i ];
			const sectionPosition = this.sectionStartPositions[ sectionKey2 ];
			if ( sectionPosition !== -1 ) {
				return sectionPosition;
			}
		}
	}
}

module.exports = MOSOrderPositionFinder;
