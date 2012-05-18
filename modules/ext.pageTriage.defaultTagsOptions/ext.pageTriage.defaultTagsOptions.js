//See http://www.mediawiki.org/wiki/Extension:PageTriage for basic documentation on configuration.
//<nowiki>
( function( $ ) {
$.pageTriageTagsOptions = {

	'common': {
		label: mw.msg( 'pagetriage-tags-cat-common-label' ),
		tags: {
			'linkrot': {
				label: mw.msg( 'pagetriage-tags-linkrot-label' ),
				tag: 'linkrot',
				desc: mw.msg( 'pagetriage-tags-linkrot-desc' ),
				params: [ 'date' ],
				position: 'reference'
			},

			'copyedit': {
				label: mw.msg( 'pagetriage-tags-copyedit-label' ),
				tag: 'copy edit',
				desc: mw.msg( 'pagetriage-tags-copyedit-desc' ),
				params: [ 'for', 'date', 'categories', 1 ],
				position: 'top'
			},

			'morefootnotes': {
				label: mw.msg( 'pagetriage-tags-morefootnotes-label' ),
				tag: 'more footnotes',
				desc: mw.msg( 'pagetriage-tags-morefootnotes-desc' ),
				params: [ 'date', 'BLP', 1 ],
				position: 'top'
			},

			'refimprove': {
				label: mw.msg( 'pagetriage-tags-refimprove-label' ),
				tag: 'ref improve',
				desc: mw.msg( 'pagetriage-tags-refimprove-desc' ),
				params: [ 'date', 'talk', 1 ],
				position: 'top'
			},

			'uncategorised': {
				label: mw.msg( 'pagetriage-tags-uncategorised-label' ),
				tag: 'uncategorised',
				desc: mw.msg( 'pagetriage-tags-uncategorised-desc' ),
				params: [ ],
				position: 'bottom'
			},

			'unreferenced': {
				label: mw.msg( 'pagetriage-tags-unreferenced-label' ),
				tag: 'unreferenced',
				desc: mw.msg( 'pagetriage-tags-unreferenced-desc' ),
				params: [ ],
				position: 'bottom'
			}
		}
	},

	'metadata': {
		label: mw.msg( 'pagetriage-tags-cat-metadata-label' ),
		tags: {
			'deadend': {
				label: mw.msg( 'pagetriage-tags-deadend-label' ),
				tag: 'dead end',
				desc: mw.msg( 'pagetriage-tags-deadend-desc' ),
				params: [ 'date', 1 ],
				position: 'top'
			},

			'externallinks': {
				label: mw.msg( 'pagetriage-tags-externallinks-label' ),
				tag: 'external links',
				desc: mw.msg( 'pagetriage-tags-externallinks-desc' ),
				params: [ 'date' ],
				position: 'external-link'
			}
		}
	},

	'cleanup': {
		label: mw.msg( 'pagetriage-tags-cat-cleanup-label' ),
		tags: {

		}
	},

	'neutrality': {
		label: mw.msg( 'pagetriage-tags-cat-neutrality-label' ),
		tags: {

		}
	},

	'sources': {
		label: mw.msg( 'pagetriage-tags-cat-sources-label' ),
		tags: {

		}
	},

	'structure': {
		label: mw.msg( 'pagetriage-tags-cat-structure-label' ),
		tags: {

		}
	},

	'unwantedcontent': {
		label: mw.msg( 'pagetriage-tags-cat-unwantedcontent-label' ),
		tags: {

		}
	},

	'verifiability': {
		label: mw.msg( 'pagetriage-tags-cat-verifiability-label' ),
		tags: {

		}
	},

	'writingstyle': {
		label: mw.msg( 'pagetriage-tags-cat-writingstyle-label' ),
		tags: {

		}
	},

	'moretags': {
		label: mw.msg( 'pagetriage-tags-cat-moretags-label' ),
		tags: {

		}
	}

};

} ) ( jQuery );
//</nowiki>