//See http://www.mediawiki.org/wiki/Extension:PageTriage for basic documentation on configuration.
//<nowiki>
( function( $ ) {

var param = {
	'url': {
		label: mw.msg( 'pagetriage-tags-param-url-label' ),
		input: 'required',
		type: 'text',
		value: ''
	},

	'article': {
		label: mw.msg( 'pagetriage-tags-param-article-label' ),
		input: 'required',
		type: 'text',
		value: ''
	},

	'source': {
		label: mw.msg( 'pagetriage-tags-param-source-label' ),
		input: 'required',
		type: 'text',
		value: ''
	}
};

var tags = {
	'blpprod': {
		label: mw.msg( 'pagetriage-del-tags-blpprod-label' ),
		tag: 'blp-prod',
		desc: mw.msg( 'pagetriage-del-tags-blpprod-desc' ),
		params: {},
		anchor: ''
	},

	'dba1': {
		label: mw.msg( 'pagetriage-del-tags-dba1-label' ),
		tag: 'db-a1',
		desc: mw.msg( 'pagetriage-del-tags-dba1-desc' ),
		params: {},
		anchor: 'nocontext'
	},

	'dba2': {
		label: mw.msg( 'pagetriage-del-tags-dba2-label' ),
		tag: 'db-a2',
		desc: mw.msg( 'pagetriage-del-tags-dba2-desc' ),
		params: {
			'source': $.extend( true, {}, param.source )
		},
		anchor: 'notenglish'
	},

	'dba3': {
		label: mw.msg( 'pagetriage-del-tags-dba3-label' ),
		tag: 'db-a3',
		desc: mw.msg( 'pagetriage-del-tags-dba3-desc' ),
		params: {},
		anchor: 'nocontent'
	},

	'dba7': {
		label: mw.msg( 'pagetriage-del-tags-dba7-label' ),
		tag: 'db-a7',
		desc: mw.msg( 'pagetriage-del-tags-dba7-desc' ),
		params: {},
		anchor: 'importance'
	},

	'dba9': {
		label: mw.msg( 'pagetriage-del-tags-dba9-label' ),
		tag: 'db-a9',
		desc: mw.msg( 'pagetriage-del-tags-dba9-desc' ),
		params: {},
		anchor: 'music'
	},

	'dba10': {
		label: mw.msg( 'pagetriage-del-tags-dba10-label' ),
		tag: 'db-a10',
		desc: mw.msg( 'pagetriage-del-tags-dba10-desc' ),
		params: {
			'article': $.extend( true, {}, param.article )
		},
		anchor: 'duplicate'
	},

	'dbg1': {
		label: mw.msg( 'pagetriage-del-tags-dbg1-label' ),
		tag: 'db-g1',
		desc: mw.msg( 'pagetriage-del-tags-dbg1-desc' ),
		params: {},
		anchor: 'nonsense'
	},

	'dbg2': {
		label: mw.msg( 'pagetriage-del-tags-dbg2-label' ),
		tag: 'db-g2',
		desc: mw.msg( 'pagetriage-del-tags-dbg2-desc' ),
		params: {},
		anchor: 'test'
	},

	'dbg3': {
		label: mw.msg( 'pagetriage-del-tags-dbg3-label' ),
		tag: 'db-g3',
		desc: mw.msg( 'pagetriage-del-tags-dbg3-desc' ),
		params: {},
		anchor: 'vandalism'
	},

	'dbg4': {
		label: mw.msg( 'pagetriage-del-tags-dbg4-label' ),
		tag: 'db-g4',
		desc: mw.msg( 'pagetriage-del-tags-dbg4-desc' ),
		params: {},
		anchor: 'repost'
	},

	'dbg5': {
		label: mw.msg( 'pagetriage-del-tags-dbg5-label' ),
		tag: 'db-g5',
		desc: mw.msg( 'pagetriage-del-tags-dbg5-desc' ),
		params: {},
		anchor: 'banned'
	},

	'dbg10': {
		label: mw.msg( 'pagetriage-del-tags-dbg10-label' ),
		tag: 'db-g10',
		desc: mw.msg( 'pagetriage-del-tags-dbg10-desc' ),
		params: {},
		anchor: 'attack'
	},

	'dbg11': {
		label: mw.msg( 'pagetriage-del-tags-dbg11-label' ),
		tag: 'db-g11',
		desc: mw.msg( 'pagetriage-del-tags-dbg11-desc' ),
		params: {},
		anchor: 'spam'
	},

	'dbg12': {
		label: mw.msg( 'pagetriage-del-tags-dbg12-label' ),
		tag: 'db-g12',
		desc: mw.msg( 'pagetriage-del-tags-dbg12-desc' ),
		params: {
			'url': $.extend( true, {}, param.url )
		},
		anchor: 'copyvio'
	},

	'dbu2': {
		label: mw.msg( 'pagetriage-del-tags-dbu2-label' ),
		tag: 'db-u2',
		desc: mw.msg( 'pagetriage-del-tags-dbu2-desc' ),
		params: {},
		anchor: 'nouser'
	},

	'dbu3': {
		label: mw.msg( 'pagetriage-del-tags-dbu3-label' ),
		tag: 'db-u3',
		desc: mw.msg( 'pagetriage-del-tags-dbu3-desc' ),
		params: {},
		anchor: 'fairusegallery'
	},

	'prod': {
		label: mw.msg( 'pagetriage-del-tags-prod-label' ),
		tag: 'prod',
		desc: mw.msg( 'pagetriage-del-tags-prod-desc' ),
		params: {
			'1': {
				input: 'required',
				type: 'textarea',
				value: ''
			}
		},
		anchor: ''
	}

};

$.pageTriageDeletionTagsMultiple = 'Db-multiple';

$.pageTriageDeletionTagsOptions = {

	'Main': {
		'speedydeletioncommon': {
			label: mw.msg( 'pagetriage-del-tags-cat-csd-label' ),
			multiple: true,
			tags: {
				'dbg3': $.extend( true, {}, tags.dbg3 ),
				'dbg10': $.extend( true, {}, tags.dbg10 ),
				'dbg11': $.extend( true, {}, tags.dbg11 ),
				'dbg12': $.extend( true, {}, tags.dbg12 ),
				'dba1': $.extend( true, {}, tags.dba1 ),
				'dba7': $.extend( true, {}, tags.dba7 ),
				'dbg1': $.extend( true, {}, tags.dbg1 ),
				'dba3': $.extend( true, {}, tags.dba3 ),
				'dba9': $.extend( true, {}, tags.dba9 ),
				'dbg2': $.extend( true, {}, tags.dbg2 ),
				'dbg4': $.extend( true, {}, tags.dbg4 ),
				'dbg5': $.extend( true, {}, tags.dbg5 ),
				'dba10': $.extend( true, {}, tags.dba10 ),
				'dba2': $.extend( true, {}, tags.dba2 )
			}
		},

		'proposeddeletion': {
			label: mw.msg( 'pagetriage-del-tags-cat-prod-label' ),
			multiple: false,
			tags: {
				'blpprod': $.extend( true, {}, tags.blpprod ),
				'prod': $.extend( true, {}, tags.prod )
			}
		},

		'xfd': {
			label: '',
			multiple: false,
			tags: {
				'articlefordeletion': {
					label: mw.msg( 'pagetriage-del-tags-articlefordeletion-label' ),
					desc: '',
					tag: 'afd',
					prefix: 'Wikipedia:Articles for deletion',
					discussion: true,
					params: {
						'1': {
							input: 'required',
							type: 'textarea',
							value: ''
						}
					}
				},

				'redirectsfordiscussion': {
					label: mw.msg( 'pagetriage-del-tags-redirectsfordiscussion-label' ),
					desc: '',
					tag: 'rfd',
					prefix: 'Wikipedia:Redirects for discussion',
					params: {
						'1': {
							input: 'required',
							type: 'textarea',
							value: ''
						}
					}
				}
			}
		}
	},

	'User': {
		'csdcommon': {
			label: mw.msg( 'pagetriage-del-tags-cat-csd-label' ),
			multiple: true,
			tags: {
				'dbg11': $.extend( true, {}, tags.dbg11 ),
				'dbu2': $.extend( true, {}, tags.dbu2 ),
				'dbu3': $.extend( true, {}, tags.dbu3 ),
				'dbg4': $.extend( true, {}, tags.dbg4 ),
				'dbg10': $.extend( true, {}, tags.dbg10 ),
				'dbg12': $.extend( true, {}, tags.dbg12 )
			}
		},

		'prod': {
			label: mw.msg( 'pagetriage-del-tags-cat-prod-label' ),
			multiple: false,
			tags: {
				'prod': $.extend( true, {}, tags.prod )
			}
		},

		'mfd': {
			label: '',
			multiple: false,
			tags: {
				'miscellanyfordeletion': {
					label: mw.msg( 'pagetriage-del-tags-miscellanyfordeletion-label' ),
					desc: '',
					tag: 'mfd',
					prefix: 'Wikipedia:Miscellany_for_deletion',
					discussion: true,
					params: {
						'1': {
							input: 'required',
							type: 'textarea',
							value: ''
						}
					}
				}
			}
		}
	}

};

} ) ( jQuery );
//</nowiki>