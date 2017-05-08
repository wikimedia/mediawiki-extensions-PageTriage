Given(/^I am at the NewPagesFeed page$/) do
  visit PageTriagePage
end

When(/^I click Set filters$/) do
  on(PageTriagePage).set_filters_element.when_present.click
end

Then(/^I should see a Learn more link$/) do
  expect(on(PageTriagePage).learn_more_element.when_present).to exist
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
  expect(on(PageTriagePage).leave_feedback_element.when_present).to exist
end

Then(/^I should see a status icon for a new article$/) do
  expect(on(PageTriagePage).status_element.when_present).to exist
end

Then(/^I should not see a Review button$/) do
  expect(on(PageTriagePage).review_element).not_to exist
end

Then(/^I should see namespace selectbox$/) do
  expect(on(PageTriagePage).namespace_element).to exist
end

Then(/^I should see Username text field$/) do
  expect(on(PageTriagePage).username_element).to exist
end
