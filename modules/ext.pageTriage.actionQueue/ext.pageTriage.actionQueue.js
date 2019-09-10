/**
 * Handles the queue for asynchronous calls after actions
 * are made and before the page refreshes
 *
 * When the queue is available, gadgets and external code
 * can interact with it and add methods that will be invoked
 * for specific actions:
 * - `mw.pageTriage.actionQueue.add( action, function )`
 *    Adds a function to the queue for the action.
 * - `mw.pageTriage.actionQueue.peek( action )`
 *   See the current queue and the functions that are attached
 *   to the given action. This is meant mostly for testing,
 *   debugging, and validation.
 *
 * Internally inside PageTriage, the queue is invoked, per action:
 * - `mw.pageTriage.actionQueue.run( action )`
 *   Run all the functions attached to this action.
 * - `mw.pageTriage.actionQueue.runAndRefresh( action )`
 *   Run all the functions attached to this action and when all of
 *   them are done (resolved or rejected) continue to refresh
 *   the page.
 * Both of the above methods return a promise that resolves when
 * all functions have finished running.
 */
mw.pageTriage.actionQueue = ( function () {
	var queue = {},
		/**
		 * Add to the queue under a certain action
		 *
		 * @param  {string} action An action that triggers this
		 *  queue.
		 * @param  {jQuery.Promise|Function} func Function to run when the action
		 *  is triggered.
		 */
		addToAction = function ( action, func ) {
			var err = [];
			if ( !action ) {
				err.push( 'Missing action type parameter.' );
			}
			if ( !func ) {
				err.push( 'Missing function.' );
			}
			if ( typeof func !== 'function' ) {
				err.push( 'Given function parameter is not a runnable function' );
			}
			if ( err.length ) {
				mw.log.warn(
					'mw.pageTriage.actionQueue skipped adding function:' +
					err.join( ', ' )
				);
				return;
			}
			// Initialize the array if needed
			queue[ action ] = queue[ action ] || [];
			// Add to the action array
			queue[ action ].push( func );
		},
		/**
		 * Run all added functions that relate to the specific actin
		 *
		 * @param {string} action Action name
		 * @param {Mixed} data Data pushed as a parameter into the
		 *  queued functions
		 * @return {jQuery.Promise} A Promise that is resolved after
		 *  all stored functions finished running
		 */
		runAllInAction = function ( action, data ) {
			var promises = [];

			if ( !action ) {
				throw new Error( 'mw.pageTriage.actionQueue could not invoke for a missing (empty) action' );
			}

			queue[ action ].forEach( function ( func ) {
				promises.push(
					// $.when returns a resolved promise if all of the given promises
					// were resolved. If even one was rejected, it immediately rejects
					// its own promise and stops running the others.
					// This is not a good case for this queue, where we mihght have
					// different promises from different tools that are unaware of one
					// another.
					// To fix this, we'll promisify all given functions and convert
					// their failures to a resolved promise.
					// Since all this queue cares about is waiting untill everything
					// runs and not about returned values, this will ensure that we
					// provide a consistent experience to all clients.
					$.when( func( data ) )
						.then( null, function () { return $.Deferred().resolve(); } ) );
			} );

			return $.when.apply( null, promises );
		};

	// Exposed functions
	return {
		/**
		 * Peek into the queue to see what functions are
		 * queued up per action.
		 * Mostly used for testing and debugging.
		 *
		 * @param  {string} [action] Specific action in the queue.
		 *  If not given, the entire queue object will be returned.
		 * @return {Object|Array} A cloned object or array for the queue
		 */
		peek: function ( action ) {
			return action ?
				// Clone the array
				( queue[ action ] || [] ).slice( 0 ) :
				// Clone the object
				$.extend( true, {}, queue );
		},
		/**
		 * Add a function to run as response to a specific action
		 */
		add: addToAction,
		/**
		 * Run the functions in a specific action
		 *
		 * @param {string} action Action name
		 * @param {Mixed} data Data pushed as a parameter into the
		 *  queued functions
		 * @return {jQuery.Promise} A Promise that is resolved after
		 *  all stored functions finished running
		 */
		run: runAllInAction,
		/**
		 * Run the functions in a specific action and refresh the page
		 * when all functions finished running successfully.
		 *
		 * @param {string} action Action name
		 * @param {Mixed} data Data pushed as a parameter into the
		 *  queued functions
		 * @param  {boolean} forcedReload Force a page reload
		 * @return {jQuery.Promise} A Promise that is resolved after
		 *  all stored functions finished running
		 */
		runAndRefresh: function ( action, data, forcedReload ) {
			return runAllInAction( action, data ).always( function () {
				window.location.reload( forcedReload );
			} );
		}
	};
}() );
