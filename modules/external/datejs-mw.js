mw.loader.using( ['mediawiki.jqueryMsg'], function() {
	// this is the start of a connector between Mediawiki's localization and date.js.
	// currently it adds messages necessary for proper date display, but not for date parsing.
	//
	// note that shortestDayNames, firstLetterDayNames, AMDesignator and PMDesignator aren't included,
	// since Mediawiki doesn't currently use those.

	Date.CultureInfo.dayNames = [
		mw.msg('sunday'),
		mw.msg('monday'),
		mw.msg('tuesday'),
		mw.msg('wednesday'),
		mw.msg('thursday'),
		mw.msg('friday'),
		mw.msg('saturday')
	];

	Date.CultureInfo.abbreviatedDayNames = [
		mw.msg('sun'),
		mw.msg('mon'),
		mw.msg('tue'),
		mw.msg('wed'),
		mw.msg('thu'),
		mw.msg('fri'),
		mw.msg('sat')
	];

	Date.CultureInfo.monthNames = [
		mw.msg('january'),
		mw.msg('february'),
		mw.msg('march'),
		mw.msg('april'),
		mw.msg('may_long'),
		mw.msg('june'),
		mw.msg('july'),
		mw.msg('august'),
		mw.msg('september'),
		mw.msg('october'),
		mw.msg('november'),
		mw.msg('december')
	];

	Date.CultureInfo.abbreviatedMonthNames = [
		mw.msg('jan'),
		mw.msg('feb'),
		mw.msg('mar'),
		mw.msg('apr'),
		mw.msg('may'),
		mw.msg('jun'),
		mw.msg('jul'),
		mw.msg('aug'),
		mw.msg('sep'),
		mw.msg('oct'),
		mw.msg('nov'),
		mw.msg('dec')
	];
} );