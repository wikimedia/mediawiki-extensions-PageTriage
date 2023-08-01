let Article;

describe( 'ext.pageTriage.article', () => {
	beforeEach( () => {
		mw.user.options.get = jest.fn( ( key ) => {
			switch ( key ) {
				case 'timecorrection':
					return 'ZoneInfo|-480|America/Los_Angeles';
				default:
					return null;
			}
		} );
		// needs to be loaded after mw.config.get has been defined to avoid fatal.
		Article = require( 'ext.pageTriage.util' ).Article;
	} );

	test( 'generateCopyPatrolURL() should generate a valid URL for enwiki', function () {
		const wikiLanguageCodeForCopyPatrolURL = 'en';
		const filter = 'all';
		const filterPage = '18th+Game+Developers+Choice+Awards';
		const drafts = 0;
		const revision = 1146389450;

		const article = new Article( { pageId: 123 } );
		const actual = article.generateCopyPatrolURL( wikiLanguageCodeForCopyPatrolURL, filter, filterPage, drafts, revision );

		const expected = 'https://copypatrol.toolforge.org/en?filter=all&filterPage=18th%2BGame%2BDevelopers%2BChoice%2BAwards&drafts=0&revision=1146389450';

		expect( actual ).toBe( expected );
	} );
} );
