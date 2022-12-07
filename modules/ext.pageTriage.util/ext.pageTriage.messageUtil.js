( function () {
	mw.pageTriage.contentLanguageMessages = new mw.Map();

	/**
	 * Get a message object, in the content language.  The message must have been exported
	 * by a special module and added to mw.pageTriage.contentLanguageMessages.
	 *
	 * Other than that, it is exactly the same as mw.message, and jQueryMsg is supported.
	 *
	 * @see mw.message
	 * @param {string} key Key of message to get
	 * @param {...Mixed} parameters Values for $N replacements
	 * @return {mw.Message}
	 */
	mw.pageTriage.contentLanguageMessage = function ( key ) {
		const parameters = Array.prototype.slice.call( arguments, 1 );
		return new mw.Message( mw.pageTriage.contentLanguageMessages, key, parameters );
	};
}() );
