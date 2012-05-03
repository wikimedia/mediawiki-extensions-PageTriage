mw.loader.using( ['mediawiki.jqueryMsg'], function() {
	// this is the start of a connector between Mediawiki's localization and date.js.
	// currently it adds messages necessary for proper date display, but not for date parsing.
	//
	// note that shortestDayNames, firstLetterDayNames, AMDesignator and PMDesignator aren't included,
	// since Mediawiki doesn't currently use those.

	Date.CultureInfo.dayNames = [
		gM('sunday'),
		gM('monday'),
		gM('tuesday'),
		gM('wednesday'),
		gM('thursday'),
		gM('friday'),
		gM('saturday')
	];

	Date.CultureInfo.abbreviatedDayNames = [
		gM('sun'),
		gM('mon'),
		gM('tue'),
		gM('wed'),
		gM('thu'),
		gM('fri'),
		gM('sat')		
	];

	Date.CultureInfo.monthNames = [
		gM('january'),
		gM('february'),
		gM('march'),
		gM('april'),
		gM('may_long'),
		gM('june'),
		gM('july'),
		gM('august'),
		gM('september'),
		gM('october'),
		gM('november'),
		gM('december')
	];
	
	Date.CultureInfo.abbreviatedMonthNames = [
		gM('jan'),
		gM('feb'),
		gM('mar'),
		gM('apr'),
		gM('may'),
		gM('jun'),
		gM('jul'),
		gM('aug'),
		gM('sep'),
		gM('oct'),
		gM('nov'),
		gM('dec')
	];
} );