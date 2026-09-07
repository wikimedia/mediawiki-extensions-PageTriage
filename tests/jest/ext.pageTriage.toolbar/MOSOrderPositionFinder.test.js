const MOSOrderPositionFinder = require( '../../../modules/ext.pageTriage.toolbar/MOSOrderPositionFinder.js' );

describe( 'MOSOrderPositionFinder', () => {
	const finder = new MOSOrderPositionFinder();

	describe( 'hasSection', () => {
		test( 'returns true when the section exists', () => {
			const wikicode =
				'{{Short description|test}}\n\nLead\n\n== First heading ==\nBody\n\n{{Navbox}}';
			expect( finder.hasSection( wikicode, 'shortDescription' ) ).toBe( true );
		} );

		test( 'returns false when the section does not exist', () => {
			const wikicode = 'Lead\n\n== First heading ==\nBody';
			expect( finder.hasSection( wikicode, 'shortDescription' ) ).toBe( false );
		} );

		test( 'throws for an invalid section name', () => {
			expect( () => {
				finder.hasSection( 'test', 'invalidSection' );
			} ).toThrow( 'MOSOrderPositionFinder: Invalid section name.' );
		} );
	} );

	describe( 'getSectionPosition', () => {
		const wikicode =
			'{{Short description|test}}\n\nLead\n\n== First heading ==\nBody\n\n{{Navbox}}';

		test( 'returns the start position when the section exists', () => {
			expect( finder.getSectionPosition( wikicode, 'shortDescription' ) ).toBe( 0 );
		} );

		test( 'returns the next existing section when the requested section is absent', () => {
			expect( finder.getSectionPosition( wikicode, 'infoboxes' ) ).toBe(
				wikicode.indexOf( 'Lead' )
			);
		} );

		test( 'throws for an invalid section name', () => {
			expect( () => {
				finder.getSectionPosition( wikicode, 'invalidSection' );
			} ).toThrow( 'MOSOrderPositionFinder: Invalid section name.' );
		} );
	} );

	describe( 'template name matching', () => {
		test( 'does not treat templates that are prefixed with Prod, but are not Prod, as Prod', () => {
			const wikitext = 'Lead intro.\n\n== History ==\n{{Prod and It\'s Completely Different but Also Still Not Prod}}\nMore text.';
			expect( finder.getAllExistingSectionPositions( wikitext ).deletionAndProtection )
				.toBeUndefined();
		} );

		test( 'matches an exact PROD template', () => {
			expect( finder.getAllExistingSectionPositions( '{{Prod|reason=spam}}\n\nLead.' )
				.deletionAndProtection ).toBe( 0 );
		} );

		test( 'matches Infobox as a prefix', () => {
			expect( finder.getAllExistingSectionPositions(
				'{{Infobox person|name=Ada}}\n\nLead.'
			).infoboxes ).toBe( 0 );
		} );

		test( 'matches Pp-* protection templates as a prefix', () => {
			expect( finder.getAllExistingSectionPositions( '{{Pp-move}}\n\nLead.' )
				.deletionAndProtection ).toBe( 0 );
		} );

		test( 'matches S-* succession boxes as a prefix', () => {
			const wikitext = 'Lead.\n\n{{S-start}}\n{{S-end}}';
			expect( finder.getAllExistingSectionPositions( wikitext )
				.successionAndGeographyBoxes ).toBeDefined();
		} );

		test( 'treats underscores as spaces in multi-word names', () => {
			expect( finder.getAllExistingSectionPositions(
				'{{Use_New_Zealand_English}}\n\nLead.'
			).engvar ).toBe( 0 );
		} );

		test( 'does not match section variants of maintenance tags', () => {
			const wikitext = 'Lead.\n\n== History ==\n{{More citations needed|section}}\nText.';
			expect( finder.getAllExistingSectionPositions( wikitext ).maintenanceTags )
				.toBeUndefined();
		} );

		test( 'matches an exact maintenance tag', () => {
			expect( finder.getAllExistingSectionPositions(
				'{{More citations needed|date=January 2024}}\n\nLead.'
			).maintenanceTags ).toBe( 0 );
		} );
	} );

	describe( 'insertAtSection', () => {
		test( 'inserts deletion tags at MOS position, not at {{Production}}', () => {
			expect( finder.insertAtSection(
				'Lead intro.\n\n== History ==\n{{Production}}\nMore text.',
				'{{db-g11}}',
				'deletionAndProtection'
			) ).toBe(
				'{{db-g11}}\n\nLead intro.\n\n== History ==\n{{Production}}\nMore text.\n'
			);
		} );

		test( 'inserts maintenance tags before an engvar template that uses underscores', () => {
			expect( finder.insertAtSection(
				'{{Use_New_Zealand_English}}\n\nLead.',
				'{{notability}}',
				'maintenanceTags'
			) ).toBe( '{{notability}}\n\n{{Use_New_Zealand_English}}\n\nLead.\n' );
		} );

		test( 'inserts infobox after AfC HTML comments, not inside them', () => {
			const wikicode = '{{AfC Comment}}<!-- do not remove this line-->\n\nLead\n';
			expect( finder.insertAtSection( wikicode, '{{Speciesbox}}', 'infoboxes' ) ).toBe(
				'{{AfC Comment}}<!-- do not remove this line-->\n\n{{Speciesbox}}\n\nLead\n'
			);
		} );
	} );
} );
