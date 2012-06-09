/*!
 * jQuery UI Squish 1.0
 * Based on jQuery UI Effects Blind 1.8.21
 *
 * Copyright 2012, AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * http://docs.jquery.com/UI/Effects/Blind
 *
 * Depends:
 *	jquery.effects.core.js
 */
(function( $ ) {

$.effects.squish = function(o) {

	return this.queue(function() {

		// Create element
		var el = $(this), props = ['position','top','bottom'];

		// Set options
		var mode = $.effects.setMode(el, o.options.mode || 'hide'); // Set Mode

		// Adjust
		$.effects.save(el, props); el.show(); // Save & Show
		var wrapper = $.effects.createWrapper(el).css({overflow:'hidden'}); // Create Wrapper
		var distance = wrapper.height();
		if(mode == 'show') wrapper.css('height', 0); // Shift

		// Animation
		var animation = {};
		animation['height'] = mode == 'show' ? distance : 0;

		// Animate
		wrapper.animate(animation, o.duration, o.options.easing, function() {
			if(mode == 'hide') el.hide(); // Hide
			$.effects.restore(el, props); $.effects.removeWrapper(el); // Restore
			el.dequeue();
		});

	});

};

})(jQuery);
