const actionQueue = require( '../../../modules/ext.pageTriage.toolbarStartup/ext.pageTriage.actionQueue.js' );
describe( 'ext.pageTriage.actionQueue', () => {

	test( 'Testing the queue: synchronous and asynchronous methods', function () {
		const test = {},
			pushToTest = function ( action, text, data ) {
				test[ action ] = test[ action ] || [];
				test[ action ].push( {
					action: action,
					text: text,
					data: data
				} );
			};

		actionQueue.reset();
		actionQueue.add( 'action1', function ( data ) {
			pushToTest( 'action1', 'synchronous', data );
			return true;
		} );
		actionQueue.add( 'action2', function ( data ) {
			pushToTest( 'action2', 'synchronous without returning true', data );
		} );

		actionQueue.add( 'action1', function ( data ) {
			const $deferred = $.Deferred();

			setTimeout( function () {
				pushToTest( 'action1', 'slow asynchronous success', data );
				$deferred.resolve();
			}, 500 );

			return $deferred.promise();
		} );
		actionQueue.add( 'action1', function ( data ) {
			const $deferred = $.Deferred();

			setTimeout( function () {
				pushToTest( 'action1', 'quick asynchronous failure', data );
				$deferred.reject();
			}, 100 );

			return $deferred.promise();
		} );

		// Check that on running the actions, the correct functions were added
		expect(
			actionQueue.peek( 'action1' ).length
		).toBe(
			3,
			'Added three methods to "action1"'
		);

		expect(
			actionQueue.peek( 'action2' ).length
		).toBe(
			1,
			'Added one method to "action2"'
		);

		// Run the queue
		const test1 = actionQueue.run( 'action1', [ 'testparam1', 'testparam2' ] )
			.always( function () {
				expect(
					test.action1
				).toBe(
					[
						{ action: 'action1', text: 'synchronous', data: [ 'testparam1', 'testparam2' ] },
						{ action: 'action1', text: 'quick asynchronous failure', data: [ 'testparam1', 'testparam2' ] },
						// Making sure that the slow success ran even thought there's a rejected promise before it
						{ action: 'action1', text: 'slow asynchronous success', data: [ 'testparam1', 'testparam2' ] }
					],
					'Methods in "action1" ran successfully.'
				);
				return Promise.resolve();
			} );
		const test2 = actionQueue.run( 'action2', { test1: 'param3', test2: 'param4' } )
			.always( function () {
				expect(
					test.action2
				).toBe(
					[
						{ action: 'action2', text: 'synchronous without returning true', data: { test1: 'param3', test2: 'param4' } }
					],
					'Methods in "action2" ran successfully.'
				);
				return Promise.resolve();
			} );
		return Promise.all( [ test1, test2 ] );
	} );

	test( 'Testing the queue: run() with multiple actions', function () {
		const test = [],
			pushToTest = function ( action, text, data ) {
				test.push( {
					action: action,
					text: text,
					data: data
				} );
			};

		actionQueue.reset();
		// Add multiple methods to action1
		actionQueue.add( 'action1', function ( data ) {
			pushToTest( 'action1', 'foo', data );
		} );
		actionQueue.add( 'action1', function ( data ) {
			pushToTest( 'action1', 'bar', data );
		} );
		actionQueue.add( 'action1', function ( data ) {
			pushToTest( 'action1', 'baz', data );
		} );
		actionQueue.add( 'action1', function ( data ) {
			const $deferred = $.Deferred();

			setTimeout( function () {
				pushToTest( 'action1', 'slow asynchronous success', data );
				$deferred.resolve();
			}, 500 );

			return $deferred.promise();
		} );
		// Add multiple methods to action2
		actionQueue.add( 'action2', function ( data ) {
			pushToTest( 'action2', 'foo2', data );
		} );
		actionQueue.add( 'action2', function ( data ) {
			pushToTest( 'action2', 'bar2', data );
		} );
		actionQueue.add( 'action2', function ( data ) {
			pushToTest( 'action2', 'baz2', data );
		} );
		actionQueue.add( 'action2', function ( data ) {
			const $deferred = $.Deferred();

			setTimeout( function () {
				pushToTest( 'action2', 'quick asynchronous failure', data );
				$deferred.reject();
			}, 100 );

			return $deferred.promise();
		} );

		// Run both actions at once
		return actionQueue.run( [ 'action1', 'action2' ], { param1: 'something', param2: 'else' } )
			.then( function () {
				// Check that all functions from both action queues ran
				expect(
					test
				).toStrictEqual(
					[
						{ action: 'action1', text: 'foo', data: { param1: 'something', param2: 'else' } },
						{ action: 'action1', text: 'bar', data: { param1: 'something', param2: 'else' } },
						{ action: 'action1', text: 'baz', data: { param1: 'something', param2: 'else' } },

						{ action: 'action2', text: 'foo2', data: { param1: 'something', param2: 'else' } },
						{ action: 'action2', text: 'bar2', data: { param1: 'something', param2: 'else' } },
						{ action: 'action2', text: 'baz2', data: { param1: 'something', param2: 'else' } },
						{ action: 'action2', text: 'quick asynchronous failure', data: { param1: 'something', param2: 'else' } },

						{ action: 'action1', text: 'slow asynchronous success', data: { param1: 'something', param2: 'else' } }
					],
					'Methods from both queues ran successfully.'
				);
				return Promise.resolve();
			} );
	} );

	test( 'Testing the queue: run() with actions with action-specific data', function () {
		const test = [],
			pushToTest = function ( action, text, data ) {
				test.push( {
					action: action,
					text: text,
					data: data
				} );
			};

		actionQueue.reset();
		// Add multiple methods to action1
		actionQueue.add( 'action1', function ( data ) {
			pushToTest( 'action1', 'foo', data );
		} );
		actionQueue.add( 'action1', function ( data ) {
			pushToTest( 'action1', 'bar', data );
		} );
		actionQueue.add( 'action1', function ( data ) {
			pushToTest( 'action1', 'baz', data );
		} );
		actionQueue.add( 'action1', function ( data ) {
			const $deferred = $.Deferred();

			setTimeout( function () {
				pushToTest( 'action1', 'slow asynchronous success', data );
				$deferred.resolve();
			}, 500 );

			return $deferred.promise();
		} );
		// Add multiple methods to action2
		actionQueue.add( 'action2', function ( data ) {
			pushToTest( 'action2', 'foo2', data );
		} );
		actionQueue.add( 'action2', function ( data ) {
			pushToTest( 'action2', 'bar2', data );
		} );
		actionQueue.add( 'action2', function ( data ) {
			pushToTest( 'action2', 'baz2', data );
		} );
		actionQueue.add( 'action2', function ( data ) {
			const $deferred = $.Deferred();

			setTimeout( function () {
				pushToTest( 'action2', 'quick asynchronous failure', data );
				$deferred.reject();
			}, 100 );

			return $deferred.promise();
		} );

		// Run both actions at once
		return actionQueue.run(
			{
				action1: {
					actionSpecific: 'onlyAction1GetsThis'
				},
				action2: {
					somethingElse: true,
					andAnother: 'somethingAction2Does'
				}
			},
			// General data sent to all
			{ param1: 'allActions', param2: 'getThese' }
		)
			.then( function () {
				// Check that all functions from both action queues ran
				expect(
					test
				).toStrictEqual(
					[
						{ action: 'action1', text: 'foo', data: { param1: 'allActions', param2: 'getThese', actionSpecific: 'onlyAction1GetsThis' } },
						{ action: 'action1', text: 'bar', data: { param1: 'allActions', param2: 'getThese', actionSpecific: 'onlyAction1GetsThis' } },
						{ action: 'action1', text: 'baz', data: { param1: 'allActions', param2: 'getThese', actionSpecific: 'onlyAction1GetsThis' } },

						{ action: 'action2', text: 'foo2', data: { param1: 'allActions', param2: 'getThese', somethingElse: true, andAnother: 'somethingAction2Does' } },
						{ action: 'action2', text: 'bar2', data: { param1: 'allActions', param2: 'getThese', somethingElse: true, andAnother: 'somethingAction2Does' } },
						{ action: 'action2', text: 'baz2', data: { param1: 'allActions', param2: 'getThese', somethingElse: true, andAnother: 'somethingAction2Does' } },
						{ action: 'action2', text: 'quick asynchronous failure', data: { param1: 'allActions', param2: 'getThese', somethingElse: true, andAnother: 'somethingAction2Does' } },

						{ action: 'action1', text: 'slow asynchronous success', data: { param1: 'allActions', param2: 'getThese', actionSpecific: 'onlyAction1GetsThis' } }
					],
					'Methods from both queues ran successfully.'
				);
				return Promise.resolve();
			} );
	} );
} );
