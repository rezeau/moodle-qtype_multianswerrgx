@qtype @qtype_multianswerrgx @javascript
Feature: Test creating a Multianswer (Cloze) question with REGEXP sub-question and Preview it
  As a teacher
  In order to test my students
  I need to be able to create a Cloze question including a REGEXP sub-question and Preview it

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

  Scenario: Create a Cloze question with basic REGEXP sub-question with errors
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I press "Create a new question ..."
    And I set the field "Embedded answers with REGEXP (Clozergx)" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I set the field "Question name" to "multianswer-00"
    And I set the field "Question text" to "The French flag is {1:REGEXP:%10%blue, white and red#Congratulations! ~%0%--.*blue.*#Missing blue!}."
    And I press "id_analyzequestion"
    Then I should see "Answer 1 must be a correct answer (grade = 100%) and it will not be analysed as a regular expression."
    And I set the field "Question text" to "The French flag is {1:REGEXP:%100%blue, white and red#Congratulations! ~%100%((white, blue and red|red, white and blue)}."
    And I press "id_analyzequestion"
    Then I should see "ERROR! Check your parentheses or square brackets!"
    And I set the field "Question text" to "The French flag is {1:REGEXP:%100%blue, white and red#Congratulations! ~%0%--.*blue.*#Missing blue!}."
    And I press "id_submitbutton"
    Then I should see "multianswer-00" in the "categoryquestions" "table"

  Scenario: Create a Cloze question with basic REGEXP sub-question and Preview it
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I add a "Embedded answers with REGEXP (Clozergx)" question filling the form with:
      | Question name        | multianswer-01                                     |
      | Question text        | The French flag is {1:REGEXP:%100%blue, white and red#Congratulations! ~%0%--.*blue.*#Missing blue!}. The German flag is {1:REGEXP_C:%100%Black, red and gold#Very good!~%0%black, red and gold#Start with a capital letter}.     |
      | General feedback     | Both flags have 3 colours.|
    Then I should see "multianswer-01" in the "categoryquestions" "table"

    # Preview it.
    And I choose "Preview" action for "multianswer-01" in the question bank
    And I should see "The French flag is"
    # Set behaviour options
    And I set the following fields to these values:
      | behaviour | immediatefeedback |
    And I press "saverestart"

    And I set the field with xpath "//input[contains(@id, '1_sub1_answer')]" to "blue, white and red"
    And I set the field with xpath "//input[contains(@id, '1_sub2_answer')]" to "Black, red and gold"
    And I press "Check"

    # see https://stackoverflow.com/questions/5818681/xpath-how-to-select-node-with-some-attribute-by-index
    # Click on feedbacktrigger for blank #1
    And I click on "(//a[contains(@class, 'feedbacktrigger')])[1]" "xpath_element"
    And I wait "1" seconds
    Then I should see "Congratulations!"
    # Click on feedbacktrigger for blank #2
    And I click on "(//a[contains(@class, 'feedbacktrigger')])[2]" "xpath_element"
    And I wait "1" seconds
    Then I should see "Very good!"
    And I press "Start again"
    And I set the field with xpath "//input[contains(@id, '1_sub1_answer')]" to "white and red"
    And I set the field with xpath "//input[contains(@id, '1_sub2_answer')]" to "black, red and gold"
    And I press "Check"
    And I click on "(//a[contains(@class, 'feedbacktrigger')])[1]" "xpath_element"
    Then I should see "Missing blue!"
    And I click on "(//a[contains(@class, 'feedbacktrigger')])[2]" "xpath_element"
    Then I should see "Start with a capital letter"

  Scenario: Create a Cloze question with REGEXP sub-question with permutations and Preview it
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I press "Create a new question ..."
    And I set the field "Embedded answers with REGEXP (Clozergx)" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I set the field "Question name" to "multianswer-01"
    And I set the field "Question text" to "The French flag is {1:REGEXP:%100%blue, white and red#Congratulations!~%0%--.*blue.*#Missing blue!~%100%[[_blue_, _white_(,| and) _red_]]#One of the 12 accepted answers}."
    And I set the field "General feedback" to "The general feedback."
    And I press "id_analyzequestion"
    When I click on "collapseElement-1" "link"
    Then I should see "Answer 3"
    And I should see "Developed answer"
    And I should see "(blue, white(,| and) red|blue, red(,| and) white|white, blue(,| and) red|white, red(,| and) blue|red, blue(,| and) white|red, white(,| and) blue)"
    And I should see "Alternative correct answers"
    And I should see "red, white and blue"
    And I should see "One of the 12 accepted answers"
    And I press "id_submitbutton"
    Then I should see "multianswer-01" in the "categoryquestions" "table"

    # Preview it.
    And I choose "Preview" action for "multianswer-01" in the question bank
    And I should see "The French flag is"
    # Set behaviour options
    And I set the following fields to these values:
      | behaviour | immediatefeedback |
    And I press "saverestart"

    And I set the field with xpath "//input[contains(@id, '1_sub1_answer')]" to "blue, white and red"
    And I press "Check"

    # Click on feedbacktrigger for blank #1
    And I click on "(//a[contains(@class, 'feedbacktrigger')])" "xpath_element"
    Then I should see "Congratulations!"

    And I press "Start again"
    And I set the field with xpath "//input[contains(@id, '1_sub1_answer')]" to "white and red"
    And I press "Check"
    And I click on "(//a[contains(@class, 'feedbacktrigger')])" "xpath_element"
    Then I should see "Missing blue!"

    And I press "Start again"
    And I set the field with xpath "//input[contains(@id, '1_sub1_answer')]" to "white, blue and red"
    And I press "Check"
    And I click on "(//a[contains(@class, 'feedbacktrigger')])" "xpath_element"
    Then I should see "One of the 12 accepted answers"

  Scenario: Create a Cloze question with REGEXP sub-question with match case
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I press "Create a new question ..."
    And I set the field "Embedded answers with REGEXP (Clozergx)" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I set the field "Question name" to "multianswer-01"
    And I set the field "Question text" to "What colours is the French flag?{1:REGEXP_C:%100%It's blue, white and red.#Congratulations!~it's.*#Please begin with a capital letter.}"
    And I set the field "General feedback" to "The general feedback."
    And I press "id_submitbutton"

    Then I should see "multianswer-01" in the "categoryquestions" "table"

    # Preview it.
    And I choose "Preview" action for "multianswer-01" in the question bank
    And I should see "What colours is the French flag?"
    # Set behaviour options
    And I set the following fields to these values:
      | behaviour | immediatefeedback |
    And I press "saverestart"

    And I set the field with xpath "//input[contains(@id, '1_sub1_answer')]" to "it's blue, white and red."
    And I press "Check"
    # Click on feedbacktrigger for blank #1
    And I click on "(//a[contains(@class, 'feedbacktrigger')])" "xpath_element"
    Then I should see "Please begin with a capital letter."

    And I press "Start again"
    And I set the field with xpath "//input[contains(@id, '1_sub1_answer')]" to "It's blue, white and red."
    And I press "Check"
    And I click on "(//a[contains(@class, 'feedbacktrigger')])" "xpath_element"
    Then I should see "Congratulations"
