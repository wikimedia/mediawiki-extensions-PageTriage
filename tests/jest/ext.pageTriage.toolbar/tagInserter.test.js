const tagInserter = require( '../../../modules/ext.pageTriage.toolbar/tagInserter.js' );

describe( 'tagInserter', () => {
	describe( 'extractTemplate', () => {
		test( 'extracts a simple template', () => {
			expect( tagInserter.extractTemplate( '{{abc}}', 'abc' ) ).toBe( '{{abc}}' );
		} );

		test( 'extracts the requested template among several', () => {
			expect( tagInserter.extractTemplate( '{{abc}} {{bcd}}', 'abc' ) ).toBe( '{{abc}}' );
		} );

		test( 'includes nested templates', () => {
			expect( tagInserter.extractTemplate( '{{abc|{{bcd}}}}', 'abc' ) )
				.toBe( '{{abc|{{bcd}}}}' );
		} );

		test( 'finds a nested template by name', () => {
			expect( tagInserter.extractTemplate(
				'{{abc|{{target}}{{subst:REVISIONUSER}}}}',
				'target'
			) ).toBe( '{{target}}' );
		} );

		test( 'returns an empty string when the template is absent', () => {
			expect( tagInserter.extractTemplate( '{{abc}}', 'missing' ) ).toBe( '' );
		} );
	} );

	describe( 'mergeIntoWrapper', () => {
		test( 'appends tags inside an existing Multiple issues wrapper', () => {
			const wikitext = `
{{Multiple issues|
{{notability}}
{{should be deleted}}
}}

PageTriage is the best.
			`;
			const inner = `
{{advert}}
{{peacock}}`;
			expect( tagInserter.mergeIntoWrapper( wikitext, 'Multiple issues', inner ) ).toBe(
				`
{{Multiple issues|
{{notability}}
{{should be deleted}}
{{advert}}
{{peacock}}
}}

PageTriage is the best.
			` );
		} );

		test( 'returns the original wikitext when the wrapper is absent', () => {
			expect( tagInserter.mergeIntoWrapper( 'Txt', 'Multiple issues', '{{advert}}' ) )
				.toBe( 'Txt' );
		} );
	} );

	describe( 'insertAtMosSection', () => {
		test( 'places a deletion tag after a short description', () => {
			expect( tagInserter.insertAtMosSection(
				'{{Short description|Foo}}\n\nLead text.',
				'{{subst:afd1}}',
				'deletionAndProtection'
			) ).toBe( '{{Short description|Foo}}\n\n{{subst:afd1}}\n\nLead text.\n' );
		} );

		test( 'places a deletion banner at deletionAndProtection', () => {
			expect( tagInserter.insertAtMosSection(
				'{{Short description|Foo}}\n{{Infobox person}}\n\nLead text.',
				'{{Db-g11}}',
				'deletionAndProtection'
			) ).toBe(
				'{{Short description|Foo}}\n\n{{Db-g11}}\n\n{{Infobox person}}\n\nLead text.\n'
			);
		} );

		test( 'places a stub tag at stubTemplates, after categories', () => {
			expect( tagInserter.insertAtMosSection(
				'Lead text.\n\n[[Category:Foo]]',
				'{{stub}}',
				'stubTemplates'
			) ).toBe( 'Lead text.\n\n[[Category:Foo]]\n\n{{stub}}\n' );
		} );

		test( 'returns the original wikitext when the needle is empty', () => {
			expect( tagInserter.insertAtMosSection( 'Lead.', '', 'maintenanceTags' ) )
				.toBe( 'Lead.' );
		} );

		test( 'does not insert a deletion tag at {{Production}}', () => {
			expect( tagInserter.insertAtMosSection(
				'Lead intro.\n\n== History ==\n{{Production}}\nMore text.',
				'{{db-g11}}',
				'deletionAndProtection'
			) ).toBe(
				'{{db-g11}}\n\nLead intro.\n\n== History ==\n{{Production}}\nMore text.\n'
			);
		} );
	} );

	describe( 'insertTags', () => {
		test( 'maps top to maintenanceTags, after hatnotes', () => {
			expect( tagInserter.insertTags(
				'{{Short description|Foo}}\n{{About|x}}\n\nLead text.',
				'{{notability}}',
				'top'
			) ).toBe(
				'{{Short description|Foo}}\n{{About|x}}\n\n{{notability}}\n\nLead text.\n'
			);
		} );

		test( 'maps categories to improveCategories', () => {
			expect( tagInserter.insertTags(
				'Lead text.\n\n[[Category:Foo]]',
				'{{improve categories}}',
				'categories'
			) ).toBe( 'Lead text.\n\n[[Category:Foo]]\n\n{{improve categories}}\n' );
		} );
	} );

	describe( 'insertRedirectTags', () => {
		test( 'wraps tags after the #REDIRECT line', () => {
			expect( tagInserter.insertRedirectTags(
				'#REDIRECT [[Hello]]',
				'Redirect category shell',
				'\n{{R from initialism}}'
			) ).toBe(
				'#REDIRECT [[Hello]]\n{{Redirect category shell|\n{{R from initialism}}\n}}'
			);
		} );

		test( 'merges into an existing Redirect category shell', () => {
			const wikitext = '#REDIRECT [[Hello]]\n{{Redirect category shell|\n{{R from move}}\n}}';
			expect( tagInserter.insertRedirectTags(
				wikitext,
				'Redirect category shell',
				'\n{{R from initialism}}'
			) ).toBe(
				'#REDIRECT [[Hello]]\n{{Redirect category shell|\n{{R from move}}\n{{R from initialism}}\n}}'
			);
		} );
	} );
} );
