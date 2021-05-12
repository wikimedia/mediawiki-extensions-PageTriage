QUnit.module( 'ext.pageTriage.dateUtil' );

QUnit.test( 'Testing date parsing', function ( assert ) {
	var timestamp = '20200506134520';
	var date = mw.pageTriage.parseMwTimestamp( timestamp );
	assert.strictEqual( date.getUTCFullYear(), 2020 );
	assert.strictEqual( date.getUTCMonth(), 4 );
	assert.strictEqual( date.getUTCDate(), 6 );
	assert.strictEqual( date.getUTCHours(), 13 );
	assert.strictEqual( date.getUTCMinutes(), 45 );
	assert.strictEqual( date.getUTCSeconds(), 20 );
} );
