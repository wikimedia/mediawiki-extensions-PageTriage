@chrome @en.wikipedia.beta.wmflabs.org @firefox @internet_explorer_8 @internet_explorer_9 @internet_explorer_10 @phantomjs @test2.wikipedia.org
Feature: PageTriage

  @internet_explorer_6 @internet_explorer_7
  Scenario: Check that NewPagesFeed has correct controls for anonymous user
    Given I am at the NewPagesFeed page
    Then I should see a Learn more link
      And I should see a Leave feedback link
      And I should see a status icon for a new article
      And I should not see a Review button

  # https://phabricator.wikimedia.org/T45598 @internet_explorer_6 @internet_explorer_7
  Scenario: Check set filters selection
    Given I am at the NewPagesFeed page
    When I click Set filters
    Then I should be able to set many checkboxes for filtering new pages
      And I should see namespace selectbox
      And I should see Username text field
