let tagData;

describe( 'tagData', () => {
	beforeEach( () => {
		// needs to be loaded after mw.config.get has been defined to avoid fatal.
		tagData = require( '../../../modules/ext.pageTriage.tagData/main.js' );
	} );

	test( 'tagData should exist', () => {
		expect( tagData.maintenanceTags.redirectCategoryShell ).not.toBe( undefined );
		expect( tagData.maintenanceTags.multiple ).not.toBe( undefined );
		expect( tagData.maintenanceTags.tagOptions ).not.toBe( undefined );
	} );

	test( 'defaultDeletionTagsOptions should exist', () => {
		expect( tagData.deletionTags.multiple ).not.toBe( undefined );
		expect( tagData.deletionTags.main ).not.toBe( undefined );
	} );
} );
