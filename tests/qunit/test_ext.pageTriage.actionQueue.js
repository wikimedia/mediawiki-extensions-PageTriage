( function ( mw ) {
	QUnit.module( 'ext.pageTriage.actionQueue' );

	QUnit.test( 'Testing the queue', function ( assert ) {
		var test = {},
			pushToTest = function ( action, text, data ) {
				test[ action ] = test[ action ] || [];
				test[ action ].push( {
					action: action,
					text: text,
					data: data
				} );
			},
			done1 = assert.async(),
			done2 = assert.async();

		mw.pageTriage.actionQueue.add( 'action1', function ( data ) {
			pushToTest( 'action1', 'synchronous', data );
			return true;
		} );
		mw.pageTriage.actionQueue.add( 'action2', function ( data ) {
			pushToTest( 'action2', 'synchronous without returning true', data );
		} );

		mw.pageTriage.actionQueue.add( 'action1', function ( data ) {
			var $deferred = $.Deferred();

			setTimeout( function () {
				pushToTest( 'action1', 'slow asynchronous success', data );
				$deferred.resolve();
			}, 500 );

			return $deferred.promise();
		} );
		mw.pageTriage.actionQueue.add( 'action1', function ( data ) {
			var $deferred = $.Deferred();

			setTimeout( function () {
				pushToTest( 'action1', 'quick asynchronous failure', data );
				$deferred.reject();
			}, 100 );

			return $deferred.promise();
		} );

		// Check that on running the actions, the correct functions were added
		assert.deepEqual(
			mw.pageTriage.actionQueue.peek( 'action1' ).length,
			3,
			'Added three methods to "action1"'
		);

		assert.deepEqual(
			mw.pageTriage.actionQueue.peek( 'action2' ).length,
			1,
			'Added one method to "action2"'
		);

		// Run the queue
		mw.pageTriage.actionQueue.run( 'action1', [ 'testparam1', 'testparam2' ] )
			.always( function () {
				assert.deepEqual(
					test.action1,
					[
						{ action: 'action1', text: 'synchronous', data: [ 'testparam1', 'testparam2' ] },
						{ action: 'action1', text: 'quick asynchronous failure', data: [ 'testparam1', 'testparam2' ] },
						// Making sure that the slow success ran even thought there's a rejected promise before it
						{ action: 'action1', text: 'slow asynchronous success', data: [ 'testparam1', 'testparam2' ] }
					],
					'Methods in "action1" ran successfully.'
				);
				done1();
			} );
		mw.pageTriage.actionQueue.run( 'action2', { test1: 'param3', test2: 'param4' } )
			.always( function () {
				assert.deepEqual(
					test.action2,
					[
						{ action: 'action2', text: 'synchronous without returning true', data: { test1: 'param3', test2: 'param4' } }
					],
					'Methods in "action2" ran successfully.'
				);
				done2();
			} );
	} );
}( mediaWiki ) );
