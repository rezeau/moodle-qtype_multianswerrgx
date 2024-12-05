@qtype @qtype_multianswerrgx
Feature: Test importing multianswerrgx questions
  As a teacher
  In order to reuse multianswerrgx questions
    I need to be able to import and export them

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
  Scenario: Import and export multianswerrgx question.
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

    # Now export again.
    And I am on the "Course 1" "core_question > course question export" page logged in as teacher
    And I set the field "id_format_xml" to "1"
    And I press "Export questions to file"
    # And I press "Continue"
    And following "click here" should download a file that:
      | Has mimetype                 | text/xml                 |
      | Contains text in xml element | Cloze with RX complete   |
    # If the download step is the last in the scenario then we can sometimes run
    # into the situation where the download page causes a http redirect but behat
    # has already conducted its reset (generating an error). By putting a logout
    # step we avoid behat doing the reset until we are off that page.
    And I log out
