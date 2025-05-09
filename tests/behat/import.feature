@qtype @qtype_multianswerrgx
Feature: Test importing multianswerrgx questions
  As a teacher
  In order to reuse multianswerrgx questions
    I need to be able to import them

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

  @javascript @_file_upload
  Scenario: Import multianswerrgx question.
    # Import sample file.
    When I am on the "Course 1" "core_question > course question import" page logged in as teacher
    And I set the field "id_format_xml" to "1"
    And I upload "question/type/multianswerrgx/tests/fixtures/testquestion.moodle.xml" file to "Import" filemanager
    And I press "id_submitbutton"
    Then I should see "Parsing questions from import file."
    And I should see "Importing 1 questions from file"
    And I should see "1. This question consists of some text with an answer embedded right here {#1}. Then you will deal with this short answer {#2} please. The French flag is: {#3}."
    And I press "Continue"
    And I should see "Cloze with RX complete"
    And I log out
