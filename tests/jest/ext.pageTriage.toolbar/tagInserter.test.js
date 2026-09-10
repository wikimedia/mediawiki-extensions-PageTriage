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

		test( 'adds a pipe when the wrapper has no parameters', () => {
			expect( tagInserter.mergeIntoWrapper(
				'{{Multiple issues}}\n\nLead.\n',
				'Multiple issues',
				'{{advert}}'
			) ).toBe(
				'{{Multiple issues|\n{{advert}}\n}}\n\nLead.\n'
			);
		} );

		test( 'returns the original wikitext when the wrapper is absent', () => {
			expect( tagInserter.mergeIntoWrapper( 'Txt', 'Multiple issues', '{{advert}}' ) )
				.toBe( 'Txt' );
		} );

		test( 'finds a wrapper regardless of template-name case', () => {
			expect( tagInserter.mergeIntoWrapper(
				'{{multiple issues|\n{{notability}}\n}}\n\nLead.\n',
				'Multiple issues',
				'{{advert}}'
			) ).toBe(
				'{{multiple issues|\n{{notability}}\n{{advert}}\n}}\n\nLead.\n'
			);
		} );

		test( 'merges into {{Issues}} as a Multiple issues alias', () => {
			expect( tagInserter.mergeIntoWrapper(
				'{{Issues|\n{{notability}}\n}}\n\nLead.\n',
				'Multiple issues',
				'{{advert}}'
			) ).toBe(
				'{{Issues|\n{{notability}}\n{{advert}}\n}}\n\nLead.\n'
			);
		} );

		test( 'merges into {{This is a redirect}} as a Redirect category shell alias', () => {
			expect( tagInserter.mergeIntoWrapper(
				'#REDIRECT [[Hello]]\n{{This is a redirect|\n{{R from move}}\n}}',
				'Redirect category shell',
				'\n{{R from initialism}}'
			) ).toBe(
				'#REDIRECT [[Hello]]\n{{This is a redirect|\n{{R from move}}\n{{R from initialism}}\n}}'
			);
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

		test( 'wraps a standalone R-tag together with newly added redirect tags', () => {
			expect( tagInserter.insertRedirectTags(
				'#REDIRECT [[Hello]]\n{{R from move}}',
				'Redirect category shell',
				'\n{{R from initialism}}'
			) ).toBe(
				'#REDIRECT [[Hello]]\n{{Redirect category shell|\n{{R from initialism}}\n{{R from move}}\n}}'
			);
		} );

		test( 'wraps an R-tag on the same line as #REDIRECT', () => {
			expect( tagInserter.insertRedirectTags(
				'#REDIRECT [[Hello]] {{R from move}}',
				'Redirect category shell',
				'\n{{R from initialism}}'
			) ).toBe(
				'#REDIRECT [[Hello]]\n{{Redirect category shell|\n{{R from initialism}}\n{{R from move}}\n}}'
			);
		} );

		test( 'does not escape a same-line non-absorbable template after ]]', () => {
			expect( tagInserter.insertRedirectTags(
				'#REDIRECT [[Foobar]] {{unknown template}}',
				'Redirect category shell',
				'\n{{R printworthy}}'
			) ).toBe(
				'#REDIRECT [[Foobar]]\n{{Redirect category shell|\n{{R printworthy}}\n}}\n{{unknown template}}'
			);
		} );

		test( 'does not prefix a same-line comment with a space after ]]', () => {
			expect( tagInserter.insertRedirectTags(
				'#REDIRECT [[Foobar]] <!-- comment -->',
				'Redirect category shell',
				'\n{{R printworthy}}'
			) ).toBe(
				'#REDIRECT [[Foobar]]\n{{Redirect category shell|\n{{R printworthy}}\n}}\n<!-- comment -->'
			);
		} );
	} );

	describe( 'insertMaintenanceTags', () => {
		test( 'T361988: wraps an existing standalone tag into a new Multiple issues', () => {
			expect( tagInserter.insertMaintenanceTags(
				'{{Disputed}}\ntest',
				'Multiple issues',
				'\n{{advert|date=today}}\n{{all plot|date=today}}',
				2
			) ).toBe(
				'{{Multiple issues|\n{{advert|date=today}}\n{{all plot|date=today}}\n{{Disputed}}\n}}\ntest\n'
			);
		} );

		test( 'merges into a parameterless {{Multiple issues}}', () => {
			expect( tagInserter.insertMaintenanceTags(
				'{{Multiple issues}}\ntest',
				'Multiple issues',
				'{{advert}}',
				1
			) ).toBe(
				'{{Multiple issues|\n{{advert}}\n}}\ntest'
			);
		} );

		test( 'T323883: appends a third tag inside an existing Multiple issues', () => {
			expect( tagInserter.insertMaintenanceTags(
				'{{Multiple issues|\n{{advert|date=today}}\n{{all plot|date=today}}\n}}\n\ntest\n',
				'Multiple issues',
				'{{disputed|date=today}}',
				1
			) ).toBe(
				'{{Multiple issues|\n{{advert|date=today}}\n{{all plot|date=today}}\n{{disputed|date=today}}\n}}\n\ntest\n'
			);
		} );

		test( 'wraps one existing standalone tag together with one newly added tag', () => {
			expect( tagInserter.insertMaintenanceTags(
				'{{Disputed}}\ntest',
				'Multiple issues',
				'{{advert|date=today}}',
				1
			) ).toBe(
				'{{Multiple issues|\n{{advert|date=today}}\n{{Disputed}}\n}}\ntest\n'
			);
		} );

		test( 'merges into {{Multiple issues|section=yes}} instead of nesting another wrapper', () => {
			expect( tagInserter.insertMaintenanceTags(
				'{{Multiple issues|section=yes|\n{{advert}}\n}}\n',
				'Multiple issues',
				'{{peacock}}',
				1
			) ).toBe(
				'{{Multiple issues|section=yes|\n{{advert}}\n{{peacock}}\n}}\n'
			);
		} );

		test( 'merges into {{ Multiple issues}} with a space after {{', () => {
			expect( tagInserter.insertMaintenanceTags(
				'{{ Multiple issues|\n{{advert}}\n}}\n',
				'Multiple issues',
				'{{peacock}}',
				1
			) ).toBe(
				'{{ Multiple issues|\n{{advert}}\n{{peacock}}\n}}\n'
			);
		} );

		test( 'does not pull a multiple:false tag into Multiple issues', () => {
			expect( tagInserter.insertMaintenanceTags(
				'{{Rough translation}}\ntest',
				'Multiple issues',
				'\n{{advert|date=today}}\n{{all plot|date=today}}',
				2
			) ).toBe(
				'{{Multiple issues|\n{{advert|date=today}}\n{{all plot|date=today}}\n}}\n{{Rough translation}}\ntest\n'
			);
		} );
	} );
} );
