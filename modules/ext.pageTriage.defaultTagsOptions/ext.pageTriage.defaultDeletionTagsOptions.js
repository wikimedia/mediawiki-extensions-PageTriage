// See https://www.mediawiki.org/wiki/Extension:PageTriage for basic documentation on configuration.

( function () {
	const param = {
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
				value: mw.config.get( 'wgPageName' ).replace( /_/g, ' ' )
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

			dbg13: {
				label: mw.msg( 'pagetriage-del-tags-dbg13-label' ),
				tag: 'speedy deletion-abandoned draft', // redirect to db-g13
				code: 'G13',
				desc: mw.msg( 'pagetriage-del-tags-dbg13-desc' ),
				params: {},
				anchor: 'afc',
				talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
				talkpagenotiftpl: 'Afc-warn-NPF'
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
				articletalkpagenotiftpl: 'Old prod',
				rejectionTemplates: {
					article: [ 'Article for deletion/dated' ],
					talkPage: [ 'Old prod', 'Old XfD multi', 'Old DRV' ]
				},
				subst: true
			}
		};

	const pageTriageDeletionTagsMultiple = {
		tag: 'Db-multiple',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Db-notice-multiple-NPF'
	};

	const pageTriageDeletionTagsOptions = {

		Main: {
			speedydeletioncommon: {
				label: mw.msg( 'pagetriage-del-tags-cat-csd-label' ),
				desc: mw.msg( 'pagetriage-del-tags-cat-csd-desc' ),
				multiple: true,
				// do NOT mark page as reviewed when this deletion tag option is selected.
				reviewed: '0',
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
				// do NOT mark page as reviewed when this deletion tag option is selected
				reviewed: '0',
				tags: {
					blpprod: $.extend( true, {}, tags.blpprod ),
					prod: $.extend( true, {}, tags.prod )
				}
			},

			xfd: {
				label: '',
				desc: mw.msg( 'pagetriage-del-tags-cat-discussion-desc' ),
				multiple: false,
				// mark page as reviewed when this deletion tag option is selected
				reviewed: '1',
				tags: {
					articlefordeletion: {
						label: mw.msg( 'pagetriage-del-tags-articlefordeletion-label' ),
						desc: '',
						tag: 'afd',
						prefix: 'Wikipedia:Articles for deletion',
						discussion: true,
						usesSubpages: true,
						params: {
							1: {
								label: mw.msg( 'pagetriage-del-tags-param-discussion-label' ),
								input: 'required',
								type: 'textarea',
								value: '',
								skip: true // don't use this param in the main template
							},
							2: $.extend( true, { skip: true }, param.pagename )
						},
						talkpagenotiftopictitle: 'pagetriage-del-tags-xfd-notify-topic-title',
						talkpagenotiftpl: 'AfD-notice-NPF',
						subst: true
					},

					redirectsfordiscussion: {
						label: mw.msg( 'pagetriage-del-tags-redirectsfordiscussion-label' ),
						desc: '',
						tag: 'rfd-NPF',
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
		}
	};

	if ( typeof $ !== 'undefined' ) {
		$.pageTriageDeletionTagsMultiple = pageTriageDeletionTagsMultiple;
		$.pageTriageDeletionTagsOptions = pageTriageDeletionTagsOptions;
	}

	module.exports = {
		pageTriageDeletionTagsMultiple,
		pageTriageDeletionTagsOptions
	};
}() );

// ============================================================================================
// Copy/paste from https://en.wikipedia.org/w/index.php?title=MediaWiki:PageTriageExternalDeletionTagsOptions.js&action=edit begins here.
// TODO: Refactor to combine code above here and code below here, reconciling duplicates.
// In case of duplicates, the code below should be used since it is newer.
// ============================================================================================

$.pageTriageDeletionTagsOptions.Main.proposeddeletion.tags.prod.tag = 'prod';

$.pageTriageDeletionTagsOptions.Main.proposeddeletion.tags.blpprod.tag = 'blp-prod';

$.pageTriageDeletionTagsOptions.Main.speedydeletioncommon.desc = 'Mark this page for speedy deletion only if it fits one of the criteria below. There is no catch-all – if it doesn’t fit, use PROD or AfD.';

// Redefine all speedy-deletion tags in order to add new ones in their correct order.
$.pageTriageDeletionTagsOptions.Main.speedydeletioncommon.tags = {
	dba1: {
		label: 'No context',
		tag: 'speedy deletion-no context',
		code: 'A1',
		desc: 'Articles lacking sufficient context to identify the subject of the article. (A1)',
		params: {},
		anchor: 'nocontext',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Empty-warn-NPF'
	},
	dba2: {
		label: 'Foreign language articles that exist on another Wikimedia project',
		tag: 'speedy deletion-foreign language',
		code: 'A2',
		desc: 'Articles having essentially the same content as an article on another Wikimedia project. (A2)',
		params: {
			source: {
				label: 'Please add a URL for that source:',
				input: 'required',
				type: 'text',
				value: ''
			}
		},
		anchor: 'notenglish',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Db-foreign-notice-NPF'
	},
	dba3: {
		label: 'No content',
		tag: 'speedy deletion-no content',
		code: 'A3',
		desc: 'Any article (other than disambiguation pages, redirects, or soft redirects) consisting only of external links, category tags and "see also" sections, a rephrasing of the title, attempts to correspond with the person or group named by its title, a question that should have been asked at the help or reference desks, chat-like comments, template tags, and/or images. (A3)',
		params: {},
		anchor: 'nocontent',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Nocontent-warn-NPF'
	},
	dba7Person: {
		label: 'No indication of importance (person)',
		tag: 'db-person',
		code: 'A7',
		desc: 'An article about a real person that does not assert the importance or significance of its subject. If controversial, or if there has been a previous AfD that resulted in the article being kept, the article should be nominated for AfD instead. (A7)',
		params: {},
		anchor: 'importance',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Db-significance-notice-NPF'
	},
	dba7Band: {
		label: 'No indication of importance (musician(s) or band)',
		tag: 'db-band',
		code: 'A7',
		desc: 'Article about a band, singer, musician, or musical ensemble that does not assert the importance or significance of the subject. (A7)',
		params: {},
		anchor: 'importance',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Db-significance-notice-NPF'
	},
	dba7Club: {
		label: 'No indication of importance (club, society or group)',
		tag: 'db-club',
		code: 'A7',
		desc: 'Article about a club that does not assert the importance or significance of the subject. (A7)',
		params: {},
		anchor: 'importance',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Db-significance-notice-NPF'
	},
	dba7Corp: {
		label: 'No indication of importance (company or organization)',
		tag: 'db-corp',
		code: 'A7',
		desc: 'Article about a company or organization that does not assert the importance or significance of the subject. (A7)',
		params: {},
		anchor: 'importance',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Db-significance-notice-NPF'
	},
	dba7Web: {
		label: 'No indication of importance (website or web content)',
		tag: 'db-web',
		code: 'A7',
		desc: 'Article about a web site, blog, online forum, webcomic, podcast, or similar web content that does not assert the importance or significance of its subject. (A7)',
		params: {},
		anchor: 'importance',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Db-significance-notice-NPF'
	},
	dba7Animal: {
		label: 'No indication of importance (individual animal)',
		tag: 'db-animal',
		code: 'A7',
		desc: 'Article about an individual animal (e.g. pet) that does not assert the importance or significance of its subject. (A7)',
		params: {},
		anchor: 'importance',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Db-significance-notice-NPF'
	},
	dba7Event: {
		label: 'No indication of importance (organized event)',
		tag: 'db-event',
		code: 'A7',
		desc: 'Article about an organized event (tour, function, meeting, party, etc.) that does not assert the importance or significance of its subject. (A7)',
		params: {},
		anchor: 'importance',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Db-significance-notice-NPF'
	},
	dba9: {
		label: 'No indication of importance (musical recordings)',
		tag: 'speedy deletion-musical recording',
		code: 'A9',
		desc: 'An article about a musical recording that does not indicate why its subject is important or significant and where the artist\'s article does not exist (both conditions must be true). (A9)',
		params: {},
		anchor: 'music',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Db-a9-notice-NPF'
	},
	dba10: {
		label: 'Recently created article that duplicates an existing topic',
		tag: 'speedy deletion-duplicate article',
		code: 'A10',
		desc: 'A recently created article with no relevant page history that duplicates an existing English Wikipedia topic, and that does not expand upon, detail or improve information within any existing article(s) on the subject, and where the title is not a plausible redirect. (A10)',
		params: {
			article: {
				label: 'Article:',
				input: 'required',
				type: 'text',
				value: ''
			}
		},
		anchor: 'duplicate',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Db-a10-notice-NPF'
	},
	dba11: {
		label: 'Obviously made up by creator, and no claim of significance',
		tag: 'db-a11',
		code: 'A11',
		desc: 'An article which plainly indicates that the subject was invented/coined/discovered by the article\'s creator or someone they know personally, and does not credibly indicate why its subject is important or significant. (A11)',
		params: {},
		anchor: '',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Db-invented-notice-NPF'
	},
	dbg1: {
		label: 'Patent nonsense',
		tag: 'speedy deletion-nonsense',
		code: 'G1',
		desc: 'A page that is patent nonsense, consisting purely of incoherent text or gibberish with no meaningful content or history. (G1)',
		params: {},
		anchor: 'nonsense',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Db-nonsense-notice-NPF'
	},
	dbg2: {
		label: 'Test pages',
		tag: 'speedy deletion-test page',
		code: 'G2',
		desc: 'A page created to test editing or other Wikipedia functions. (G2)',
		params: {},
		anchor: 'test',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Db-test-notice-NPF'
	},
	dbg3Vandalism: {
		label: 'Pure vandalism',
		tag: 'speedy deletion-vandalism',
		code: 'G3',
		desc: 'Plain pure vandalism including redirects left behind by page move vandalism. (G3)',
		params: {},
		anchor: 'vandalism',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Db-vandalism-notice-NPF'
	},
	dbg3Hoax: {
		label: 'Blatant hoax',
		tag: 'db-hoax',
		code: 'G3',
		desc: 'Blatant and obvious misinformation to the point of vandalism. (G3)',
		params: {},
		anchor: 'vandalism',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Db-vandalism-notice-NPF'
	},
	dbg4: {
		label: 'Recreation of a page that was deleted per a deletion discussion',
		tag: 'speedy deletion-previously deleted',
		code: 'G4',
		desc: 'A sufficiently identical and unimproved copy, having any title, of a page deleted via its most recent deletion discussion. (G4)',
		params: {
			1: {
				label: 'Please add a link to the deletion discussion.',
				input: 'required',
				type: 'text',
				value: ''
			}
		},
		anchor: 'repost',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Uw-repost-NPF'
	},
	dbg5: {
		label: 'Creations by banned or blocked users',
		tag: 'speedy deletion-blocked user',
		code: 'G5',
		desc: 'Pages created by banned or blocked users in violation of their ban or block, and which have no substantial edits by others. (G5)',
		params: {
			user: {
				label: 'Username of banned user (if available):',
				input: 'optional',
				type: 'text',
				value: ''
			}
		},
		anchor: 'banned'
	},
	dbg7: {
		label: 'Author requests deletion',
		tag: 'speedy deletion-author request',
		code: 'G7',
		desc: 'Pages where the author has requested deletion, either explicitly or by blanking the page. (G7)',
		params: {},
		anchor: 'blanked',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Db-author-notice-NPF'
	},
	dbg10Attack: {
		label: 'Attack pages',
		tag: 'speedy deletion-attack',
		code: 'G10',
		desc: 'Pages that disparage, threaten, intimidate or harass their subject or some other entity, and serve no other purpose. (G10)',
		params: {},
		anchor: 'attack',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Db-attack-notice-NPF'
	},
	dbg10Negublp: {
		label: 'Wholly negative, unsourced BLP',
		tag: 'db-negublp',
		code: 'G10',
		desc: 'A biography of a living person that is entirely negative in tone and unsourced, where there is no neutral version in the history to revert to. (G10)',
		params: {},
		anchor: '',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'db-negublp-notice'
	},
	dbg11: {
		label: 'Unambiguous advertising or promotion',
		tag: 'speedy deletion-advertising',
		code: 'G11',
		desc: 'Pages that are exclusively promotional, and would need to be fundamentally rewritten to become encyclopedic. (G11)',
		params: {},
		anchor: 'spam',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Spam-warn-NPF'
	},
	dbg12: {
		label: 'Unambiguous copyright infringement',
		tag: 'speedy deletion-copyright violation',
		code: 'G12',
		desc: 'Text pages that contain copyrighted material with no credible assertion of public domain, fair use, or a compatible free license, where there is no non-infringing content on the page worth saving. (G12)',
		params: {
			url: {
				label: 'Please add a URL for that source.',
				input: 'required',
				type: 'text',
				value: ''
			}
		},
		anchor: 'copyvio',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Nothanks-sd-NPF'
	},
	dbg14: {
		label: 'Unnecessary disambiguation page',
		tag: 'db-disambig',
		code: 'G14',
		desc: 'This only applies to disambiguation pages which either: (1) disambiguate only one existing Wikipedia page and whose title ends in "(disambiguation)" (i.e., there is a primary topic); or (2) disambiguate no (zero) existing Wikipedia pages, regardless of its title. (G14)',
		params: {},
		anchor: '',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Db-disambig-notice-NPF'
	},
	dbr2: {
		label: 'Redirect to non-permitted namespaces',
		tag: 'Db-r2',
		code: 'R2',
		desc: 'Redirect from mainspace to any other namespace except the Category:, Template:, Wikipedia:, Help: and Portal: namespaces. (R2)',
		params: {},
		anchor: 'rediruser',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'CSD R2-warn-NPF'
	},
	dbr3: {
		label: 'Redirect from implausible typo or misnomer',
		tag: 'Db-r3',
		code: 'R3',
		desc: 'Recently created redirect from an implausible typo or misnomer. (R3)',
		params: {},
		anchor: 'redirtypo',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Redirtypo-warn-NPF'
	},
	dbg8Redirnone: {
		label: 'Redirect to non-existent/deleted page',
		tag: 'Db-redirnone',
		code: 'G8',
		desc: 'Redirect to a page that does not exist or has been deleted. (G8)',
		params: {},
		anchor: 'redirnone',
		talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
		talkpagenotiftpl: 'Redirnone-warn-NPF'
	}
};
