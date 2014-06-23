#
# This file is subject to the license terms in the LICENSE file found in the
# qa-browsertests top-level directory and at
# https://git.wikimedia.org/blob/qa%2Fbrowsertests/HEAD/LICENSE. No part of
# qa-browsertests, including this file, may be copied, modified, propagated, or
# distributed except according to the terms contained in the LICENSE file.
#
# Copyright 2012-2014 by the Mediawiki developers. See the CREDITS file in the
# qa-browsertests top-level directory and at
# https://git.wikimedia.org/blob/qa%2Fbrowsertests/HEAD/CREDITS
#
Given(/^I am at the NewPagesFeed page$/) do
  visit PageTriagePage
end

When(/^I click Set filters$/) do
  on(PageTriagePage).set_filters_element.when_present.click
end

Then(/^I should see a Learn more link$/) do
  on(PageTriagePage).learn_more_element.when_present.should exist
end
Then(/^I should be able to set many checkboxes for filtering new pages$/) do
  on(PageTriagePage) do |page|
    page.select_blocked
    page.select_bots
    page.check_deletion
    page.select_new_editors
    page.select_no_categories
    page.select_orphan
    page.check_redirects
    page.check_reviewed_pages
    page.check_unreviewed_pages
    page.select_user_selected
  end
end
Then(/^I should see a Leave feedback link$/) do
  on(PageTriagePage).leave_feedback_element.when_present.should exist
end
Then(/^I should see a status icon for a new article$/) do
  on(PageTriagePage).status_element.when_present.should exist
end
Then(/^I should not see a Review button$/) do
  on(PageTriagePage).review_element.should_not exist
end
Then(/^I should see namespace selectbox$/) do
  on(PageTriagePage).namespace_element.should exist
end
Then(/^I should see Username text field$/) do
  on(PageTriagePage).username_element.should exist
end
