// Badger v1.0 by Daniel Raftery
// http://thrivingkings.com/badger
// http://twitter.com/ThrivingKings
// Modified by Ryan Kaldari <rkaldari@wikimedia.org>

/**
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * This program is distributed WITHOUT ANY WARRANTY.
 */

(function( $ ) {
	$.fn.badger = function( badge, callback ) {
		var badgerExists = this.find( '#mwe-pt-badger' ).html();

		if ( !badge ) {
			// Clear any existing badge
			if ( badgerExists ) {
				this.find( '#mwe-pt-badger' ).remove();
			}
		} else {
			// Figure out what number to display in the badge
			var oldBadge = this.find( '#mwe-pt-badge' ).text();
			if ( badge.charAt(0) === '+' ) {
				badge = Math.round( Number( oldBadge ) + Number( badge.substr(1) ) );
			} else if ( badge.charAt(0) === '-' ) {
				badge = Math.round( Number( oldBadge ) - Number( badge.substr(1) ) );
			}

			// Don't add duplicates
			if ( badgerExists ) {
				this.find( '#mwe-pt-badge' ).html( badge );
			} else {
				this.append( '<div class="mwe-pt-badger-outer" id="mwe-pt-badger"><span class="mwe-pt-badger-badge" id="mwe-pt-badge">'+badge+'</span></div>' );
			}

			// If a callback was specified, call it with the badge number
			if ( callback ) {
				callback( badge );
			}
		}
	};
} ) ( jQuery );
