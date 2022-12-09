@javascript
Feature: Visitor Homepage

  Scenario: A visitor can see the homepage menu items
    Given I am an anonymous user
    And I am on homepage
    Then I should see the text "Welcome"
