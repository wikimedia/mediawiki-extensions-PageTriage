const { Article, ArticleList } = require( './models/ext.pageTriage.article.js' );
const { Revision, RevisionList } = require( './models/ext.pageTriage.stats.js' );
const Stats = require( './models/ext.pageTriage.stats.js' );
const { useSettingsStore } = require( './stores/settings.js' );

const contentLanguageMessages = new mw.Map();

/**
 * Get a message object, in the content language.  The message must have been exported
 * by a special module and added to contentLanguageMessages.
 *
 * Other than that, it is exactly the same as mw.message, and jQueryMsg is supported.
 *
 * @see mw.message
 * @param {string} key Key of message to get
 * @param {...Mixed} parameters Values for $N replacements
 * @return {mw.Message}
 */
const contentLanguageMessage = function ( key ) {
	const parameters = Array.prototype.slice.call( arguments, 1 );
	// eslint-disable-next-line mediawiki/msg-doc
	return new mw.Message( contentLanguageMessages, key, parameters );
};

module.exports = { ArticleList, Article, RevisionList, Revision, Stats,
	contentLanguageMessages, contentLanguageMessage, useSettingsStore
};
