// See http://www.mediawiki.org/wiki/Extension:PageTriage for basic documentation on configuration.
// <nowiki>
( function ( $, mw ) {
	var param = {
			url: {
				label: mw.msg( 'pagetriage-tags-param-url-label' ),
				input: 'required',
				type: 'text',
				value: ''
			},

			article: {
				label: mw.msg( 'pagetriage-tags-param-article-label' ),
				input: 'required',
				type: 'text',
				value: ''
			},

			source: {
				label: mw.msg( 'pagetriage-tags-param-source-label' ),
				input: 'required',
				type: 'text',
				value: ''
			},

			pagename: {
				label: '',
				input: 'automated',
				type: 'hidden',
				value: mw.config.get( 'wgPageTriagePagePrefixedText' )
			}
		},
		tags = {
			blpprod: {
				label: mw.msg( 'pagetriage-del-tags-blpprod-label' ),
				tag: 'blp-prod',
				desc: mw.msg( 'pagetriage-del-tags-blpprod-desc' ),
				params: {},
				anchor: '',
				talkpagenotiftopictitle: 'pagetriage-del-tags-prod-notify-topic-title',
				talkpagenotiftpl: 'ProdwarningBLP-NPF',
				subst: true
			},

			dba1: {
				label: mw.msg( 'pagetriage-del-tags-dba1-label' ),
				tag: 'speedy deletion-no context', // redirect to db-a1
				code: 'A1',
				desc: mw.msg( 'pagetriage-del-tags-dba1-desc' ),
				params: {},
				anchor: 'nocontext',
				talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
				talkpagenotiftpl: 'Empty-warn-NPF'
			},

			dba2: {
				label: mw.msg( 'pagetriage-del-tags-dba2-label' ),
				tag: 'speedy deletion-foreign language', // redirect to db-a2
				code: 'A2',
				desc: mw.msg( 'pagetriage-del-tags-dba2-desc' ),
				params: {
					source: $.extend( true, {}, param.source )
				},
				anchor: 'notenglish',
				talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
				talkpagenotiftpl: 'Db-foreign-notice-NPF'
			},

			dba3: {
				label: mw.msg( 'pagetriage-del-tags-dba3-label' ),
				tag: 'speedy deletion-no content', // redirect to db-a3
				code: 'A3',
				desc: mw.msg( 'pagetriage-del-tags-dba3-desc' ),
				params: {},
				anchor: 'nocontent',
				talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
				talkpagenotiftpl: 'Nocontent-warn-NPF'
			},

			dba7: {
				label: mw.msg( 'pagetriage-del-tags-dba7-label' ),
				tag: 'speedy deletion-significance', // redirect to db-a7
				code: 'A7',
				desc: mw.msg( 'pagetriage-del-tags-dba7-desc' ),
				params: {},
				anchor: 'importance',
				talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
				talkpagenotiftpl: 'Db-notability-notice-NPF'
			},

			dba9: {
				label: mw.msg( 'pagetriage-del-tags-dba9-label' ),
				tag: 'speedy deletion-musical recording', // redirect to db-a9
				code: 'A9',
				desc: mw.msg( 'pagetriage-del-tags-dba9-desc' ),
				params: {},
				anchor: 'music',
				talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
				talkpagenotiftpl: 'Db-a9-notice-NPF'
			},

			dba10: {
				label: mw.msg( 'pagetriage-del-tags-dba10-label' ),
				tag: 'speedy deletion-duplicate article', // redirect to db-a10
				code: 'A10',
				desc: mw.msg( 'pagetriage-del-tags-dba10-desc' ),
				params: {
					article: $.extend( true, {}, param.article )
				},
				anchor: 'duplicate',
				talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
				talkpagenotiftpl: 'Db-a10-notice-NPF'
			},

			dbg1: {
				label: mw.msg( 'pagetriage-del-tags-dbg1-label' ),
				tag: 'speedy deletion-nonsense', // redirect to db-g1
				code: 'G1',
				desc: mw.msg( 'pagetriage-del-tags-dbg1-desc' ),
				params: {},
				anchor: 'nonsense',
				talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
				talkpagenotiftpl: 'Db-nonsense-notice-NPF'
			},

			dbg2: {
				label: mw.msg( 'pagetriage-del-tags-dbg2-label' ),
				tag: 'speedy deletion-test page', // redirect to db-g2
				code: 'G2',
				desc: mw.msg( 'pagetriage-del-tags-dbg2-desc' ),
				params: {},
				anchor: 'test',
				talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
				talkpagenotiftpl: 'Db-test-notice-NPF'
			},

			dbg3: {
				label: mw.msg( 'pagetriage-del-tags-dbg3-label' ),
				tag: 'speedy deletion-vandalism', // redirect to db-g3
				code: 'G3',
				desc: mw.msg( 'pagetriage-del-tags-dbg3-desc' ),
				params: {},
				anchor: 'vandalism',
				talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
				talkpagenotiftpl: 'Db-vandalism-notice-NPF'
			},

			dbg4: {
				label: mw.msg( 'pagetriage-del-tags-dbg4-label' ),
				tag: 'speedy deletion-previously deleted', // redirect to db-g4
				code: 'G4',
				desc: mw.msg( 'pagetriage-del-tags-dbg4-desc' ),
				params: {
					1: $.extend( true, {}, param.article )
				},
				anchor: 'repost',
				talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
				talkpagenotiftpl: 'Uw-repost-NPF'
			},

			dbg5: {
				label: mw.msg( 'pagetriage-del-tags-dbg5-label' ),
				tag: 'speedy deletion-blocked user', // redirect to db-g5
				code: 'G5',
				desc: mw.msg( 'pagetriage-del-tags-dbg5-desc' ),
				params: {},
				anchor: 'banned',
				talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
				talkpagenotiftpl: 'Db-banned-notice-NPF'
			},

			dbg7: {
				label: mw.msg( 'pagetriage-del-tags-dbg7-label' ),
				tag: 'speedy deletion-author request', // redirect to db-g7
				code: 'G7',
				desc: mw.msg( 'pagetriage-del-tags-dbg7-desc' ),
				params: {},
				anchor: 'blanked',
				talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
				talkpagenotiftpl: 'Db-author-notice-NPF'
			},

			dbg10: {
				label: mw.msg( 'pagetriage-del-tags-dbg10-label' ),
				tag: 'speedy deletion-attack', // redirect to db-g10
				code: 'G10',
				desc: mw.msg( 'pagetriage-del-tags-dbg10-desc' ),
				params: {},
				anchor: 'attack',
				talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
				talkpagenotiftpl: 'Db-attack-notice-NPF'
			},

			dbg11: {
				label: mw.msg( 'pagetriage-del-tags-dbg11-label' ),
				tag: 'speedy deletion-advertising', // redirect to db-g11
				code: 'G11',
				desc: mw.msg( 'pagetriage-del-tags-dbg11-desc' ),
				params: {},
				anchor: 'spam',
				talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
				talkpagenotiftpl: 'Spam-warn-NPF'
			},

			dbg12: {
				label: mw.msg( 'pagetriage-del-tags-dbg12-label' ),
				tag: 'speedy deletion-copyright violation', // redirect to db-g12
				code: 'G12',
				desc: mw.msg( 'pagetriage-del-tags-dbg12-desc' ),
				params: {
					url: $.extend( true, {}, param.url )
				},
				anchor: 'copyvio',
				talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
				talkpagenotiftpl: 'Nothanks-sd-NPF'
			},

			dbu2: {
				label: mw.msg( 'pagetriage-del-tags-dbu2-label' ),
				tag: 'speedy deletion-nonexistent user', // redirect to db-u2
				code: 'U2',
				desc: mw.msg( 'pagetriage-del-tags-dbu2-desc' ),
				params: {},
				anchor: 'nouser',
				talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
				talkpagenotiftpl: 'Db-u2-notice-NPF'
			},

			dbu3: {
				label: mw.msg( 'pagetriage-del-tags-dbu3-label' ),
				tag: 'speedy deletion-nonfree galleries', // redirect to db-u3
				code: 'U3',
				desc: mw.msg( 'pagetriage-del-tags-dbu3-desc' ),
				params: {},
				anchor: 'fairusegallery',
				talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
				talkpagenotiftpl: 'Db-gallery-notice-NPF'
			},

			prod: {
				label: mw.msg( 'pagetriage-del-tags-prod-label' ),
				tag: 'prod',
				desc: mw.msg( 'pagetriage-del-tags-prod-desc' ),
				params: {
					1: {
						label: mw.msg( 'pagetriage-del-tags-param-discussion-label' ),
						input: 'required',
						type: 'textarea',
						value: ''
					}
				},
				anchor: '',
				talkpagenotiftopictitle: 'pagetriage-del-tags-prod-notify-topic-title',
				talkpagenotiftpl: 'Proposed_deletion_notify-NPF',
				subst: true
			}
		};

	$.pageTriageDeletionTagsMultiple = {
		tag: 'Db-multiple',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Db-notice-multiple-NPF'
	};

	$.pageTriageDeletionTagsOptions = {

		Main: {
			speedydeletioncommon: {
				label: mw.msg( 'pagetriage-del-tags-cat-csd-label' ),
				desc: mw.msg( 'pagetriage-del-tags-cat-csd-desc' ),
				multiple: true,
				tags: {
					dbg3: $.extend( true, {}, tags.dbg3 ),
					dbg10: $.extend( true, {}, tags.dbg10 ),
					dbg11: $.extend( true, {}, tags.dbg11 ),
					dbg12: $.extend( true, {}, tags.dbg12 ),
					dba1: $.extend( true, {}, tags.dba1 ),
					dba7: $.extend( true, {}, tags.dba7 ),
					dbg1: $.extend( true, {}, tags.dbg1 ),
					dba3: $.extend( true, {}, tags.dba3 ),
					dba9: $.extend( true, {}, tags.dba9 ),
					dbg2: $.extend( true, {}, tags.dbg2 ),
					dbg4: $.extend( true, {}, tags.dbg4 ),
					dbg5: $.extend( true, {}, tags.dbg5 ),
					dba10: $.extend( true, {}, tags.dba10 ),
					dba2: $.extend( true, {}, tags.dba2 ),
					dbg7: $.extend( true, {}, tags.dbg7 )
				}
			},

			proposeddeletion: {
				label: mw.msg( 'pagetriage-del-tags-cat-prod-label' ),
				desc: mw.msg( 'pagetriage-del-tags-cat-prod-desc' ),
				multiple: false,
				tags: {
					blpprod: $.extend( true, {}, tags.blpprod ),
					prod: $.extend( true, {}, tags.prod )
				}
			},

			xfd: {
				label: '',
				desc: mw.msg( 'pagetriage-del-tags-cat-discussion-desc' ),
				multiple: false,
				tags: {
					articlefordeletion: {
						label: mw.msg( 'pagetriage-del-tags-articlefordeletion-label' ),
						desc: '',
						tag: 'afd',
						prefix: 'Wikipedia:Articles for deletion',
						discussion: true,
						params: {
							1: {
								label: mw.msg( 'pagetriage-del-tags-param-discussion-label' ),
								input: 'required',
								type: 'textarea',
								value: '',
								skip: true // don't use this param in the main template
							},
							2: $.extend( true, {}, param.pagename )
						},
						talkpagenotiftopictitle: 'pagetriage-del-tags-xfd-notify-topic-title',
						talkpagenotiftpl: 'AfD-notice-NPF',
						subst: true
					},

					redirectsfordiscussion: {
						label: mw.msg( 'pagetriage-del-tags-redirectsfordiscussion-label' ),
						desc: '',
						tag: 'rfd',
						prefix: 'Wikipedia:Redirects for discussion',
						params: {
							1: {
								label: mw.msg( 'pagetriage-del-tags-param-discussion-label' ),
								input: 'required',
								type: 'textarea',
								value: ''
							}
						},
						talkpagenotiftopictitle: 'pagetriage-del-tags-xfd-notify-topic-title',
						talkpagenotiftpl: 'RFDNote-NPF',
						subst: true
					}
				}
			}
		},

		User: {
			csdcommon: {
				label: mw.msg( 'pagetriage-del-tags-cat-csd-label' ),
				desc: mw.msg( 'pagetriage-del-tags-cat-csd-desc' ),
				multiple: true,
				tags: {
					dbg11: $.extend( true, {}, tags.dbg11 ),
					dbu2: $.extend( true, {}, tags.dbu2 ),
					dbu3: $.extend( true, {}, tags.dbu3 ),
					dbg4: $.extend( true, {}, tags.dbg4 ),
					dbg10: $.extend( true, {}, tags.dbg10 ),
					dbg12: $.extend( true, {}, tags.dbg12 ),
					dbg7: $.extend( true, {}, tags.dbg7 )
				}
			},

			proposeddeletion: {
				label: mw.msg( 'pagetriage-del-tags-cat-prod-label' ),
				desc: mw.msg( 'pagetriage-del-tags-cat-prod-desc' ),
				multiple: false,
				tags: {
					prod: $.extend( true, {}, tags.prod )
				}
			},

			mfd: {
				label: '',
				desc: mw.msg( 'pagetriage-del-tags-cat-discussion-desc' ),
				multiple: false,
				tags: {
					miscellanyfordeletion: {
						label: mw.msg( 'pagetriage-del-tags-miscellanyfordeletion-label' ),
						desc: '',
						tag: 'mfd',
						prefix: 'Wikipedia:Miscellany_for_deletion',
						discussion: true,
						params: {
							1: {
								label: mw.msg( 'pagetriage-del-tags-param-discussion-label' ),
								input: 'required',
								type: 'textarea',
								value: '',
								skip: true // don't use this param in the main template
							}
						},
						talkpagenotiftopictitle: 'pagetriage-del-tags-xfd-notify-topic-title',
						talkpagenotiftpl: 'MFDWarning-NPF'
					}
				}
			}
		}

	};

} )( jQuery, mediaWiki );
// </nowiki>
