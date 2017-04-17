#
# This file is subject to the license terms in the LICENSE file found in the
# qa-browsertests top-level directory and at
# https://phabricator.wikimedia.org/diffusion/MSEL/browse/master/LICENSE. No part of
# qa-browsertests, including this file, may be copied, modified, propagated, or
# distributed except according to the terms contained in the LICENSE file.
#
# Copyright 2012-2014 by the Mediawiki developers. See the CREDITS file in the
# qa-browsertests top-level directory and at
# https://phabricator.wikimedia.org/diffusion/MSEL/browse/master/CREDITS
#
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
