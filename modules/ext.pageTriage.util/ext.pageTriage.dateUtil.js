( function () {

	/**
	 * Parses a timestamp in the yyyyMMddHHmmss format to a Date object.
	 * The date parts are extracted from the string using regex.
	 *
	 * @param {string} str
	 * @return {Date|null}
	 */
	mw.pageTriage.parseMwTimestamp = function ( str ) {
		var regex = /(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/g,
			match = regex.exec( str );
		if ( !match ) {
			return null;
		}
		return new Date(
			Date.UTC(
				parseInt( match[ 1 ] ), // year
				parseInt( match[ 2 ] ) - 1, // month
				parseInt( match[ 3 ] ), // date
				parseInt( match[ 4 ] ), // hours
				parseInt( match[ 5 ] ), // minutes
				parseInt( match[ 6 ] ) // seconds
			)
		);
	};
}() );
