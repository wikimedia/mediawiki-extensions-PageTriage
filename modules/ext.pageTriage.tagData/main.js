const deletionTags = require( './deletionTags.json' );
const maintenanceTags = require( './maintenanceTags.json' );

/*
* "Tags" are items that appear in the Page Curation toolbar "Add tags" flyout menu.
*
* @param {string} label - The name of the tag shown in the Page Curation toolbar "Add tags"
* flyout menu.
* @param {string} tag - The name of the template to be written to the page.
* @param {string} desc - The detailed description of the tag shown in the Page Curation
* toolbar "Add tags" flyout menu.
* @param {Object} params - Data to include as template parameters. May also be related to
* collecting data from the user via the "Add details" link.
* @param {string} position - top, bottom, categories, redirectTag
* @param {string} dest - If this is a duplicate tag, such as a tag in the "all" or "common"
* menus, in what menu is the "main" tag located? This is used to automatically tick the
* boxes for both tags, prevents placing two of the same tag, and causes the tag count to be
* applied to the other menu instead of the "all" or "common" menus.
* @param {boolean} multiple - If there are multiple tags being placed, include this tag in
* the {{Multiple issues}} tag.
*/

module.exports = {
	deletionTags,
	maintenanceTags
};
