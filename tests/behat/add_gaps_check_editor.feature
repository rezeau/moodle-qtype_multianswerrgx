@qtype @qtype_multianswerrgx
Feature: Test creating a Multianswerrgx (Cloze) question with the create gaps feature using the tiny editor
  As a teacher
  In order to test my students
  I need to be able to create a Cloze question with the create gaps feature

  Background:
    Given the following "users" exist:
      | username |
      | teacher  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | teacher | C1     | editingteacher |
    And the following config values are set as admin:
      | addclozegaps | 1 | qtype_multianswerrgx |

  @javascript
    Scenario: Try to create a Cloze question with the create gaps feature with tiny editor
    Given the following "user preferences" exist:
      | user    | preference | value |
      | teacher | htmleditor | tiny  |
  
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I press "Create a new question ..."
    And I set the field "Embedded answers with REGEXP (Clozergx)" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I set the field "Question name" to "multianswer-01"

    And I set the field "Question text" to "Too many cooks spoil the broth"
    Then I should see "Add cloze gaps"
    And I should not see "No cloze gaps?"
    And I click on "Help with Add cloze gaps" "icon"
    Then I should see "Add cloze gaps automatically to the question text, either every 5 words or every 9 words."

  @javascript
    Scenario: Try to create a Cloze question with the create gaps feature with atto editor
    Given the following "user preferences" exist:
      | user    | preference | value |
      | teacher | htmleditor | atto  |

    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I press "Create a new question ..."
    And I set the field "Embedded answers with REGEXP (Clozergx)" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I set the field "Question name" to "multianswer-01"
    And I set the field "Question text" to "Too many cooks spoil the broth"
    Then I should not see "Add cloze gaps"
    And I should see "No cloze gaps?"
    And I click on "Help with No cloze gaps?" "icon"
    Then I should see "If you want to use the Automatic Add cloze gaps feature you must switch to the TinyMCE editor in your Preferences"
