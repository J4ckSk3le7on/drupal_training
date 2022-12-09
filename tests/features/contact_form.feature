@javascript
Feature: Contact form submission

  Scenario: A visitor can submit the contact form
    Given I am an anonymous user
    And I am on homepage
    When I click "Contact" in the "footer"
    Then I should see the heading "Contact Us"
    When I fill in "Your name" with "Test visitor"
    And I fill in "Your email address" with "test-visitor@user.com"
    And I fill in "Subject" with "Test contact subject"
    And I fill in "Message" with "Test contact message"
    When I press "Send message"
    Then I should see the success message containing "Your message has been sent."
