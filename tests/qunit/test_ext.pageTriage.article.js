( function ( mw ) {
	QUnit.module( 'ext.pageTriage.article' );

	QUnit.test( 'generateCopyPatrolURL() should generate a valid URL for enwiki', function ( assert ) {
		const wikiLanguageCodeForCopyPatrolURL = 'en';
		const filter = 'all';
		const filterPage = '18th+Game+Developers+Choice+Awards';
		const drafts = 0;
		const revision = 1146389450;

		const article = new mw.pageTriage.Article( { pageId: 123 } );
		const actual = article.generateCopyPatrolURL( wikiLanguageCodeForCopyPatrolURL, filter, filterPage, drafts, revision );

		const expected = 'https://copypatrol.toolforge.org/en?filter=all&filterPage=18th%2BGame%2BDevelopers%2BChoice%2BAwards&drafts=0&revision=1146389450';

		assert.equal( actual, expected );
	} );
}( mediaWiki ) );
