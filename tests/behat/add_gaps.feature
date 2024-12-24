@qtype @qtype_multianswerrgx
Feature: Test creating a Multianswerrgx (Cloze) question with the create gaps feature
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
    And the following "user private files" exist:
      | user    | filepath                                  |
      | teacher | question/type/multianswerrgx/tests/fixtures/dick_s_cat.jpg |

  @javascript
  Scenario: Create a multianswerrgx question with addclozegaps disabled
    Given the following config values are set as admin:
      | addclozegaps | 0 | qtype_multianswerrgx |
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I press "Create a new question ..."
    And I set the field "Embedded answers with REGEXP (Clozergx)" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I set the field "Question name" to "multianswer-01"
    And I set the field "Question text" to "Once upon a time"
    Then I should not see "Add cloze gaps"
    And I log out

  @javascript
  Scenario: Create a Cloze question with the create gaps feature
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I press "Create a new question ..."
    And I set the field "Embedded answers with REGEXP (Clozergx)" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I set the field "Question name" to "multianswer-01"

    ## Try to create 1/5 gaps with a question text containing 4 words only.
    And I set the field "Question text" to "Once upon a time"
    And I press "id_button_group_add_gaps_5"
    Then I should see "Not enough text to create gaps!"
    And I set the field "Question text" to multiline:
    """
    <h5>The story of a poor boy who made his fortune.</h5><p>Once upon a time, many hundreds of years ago, lived a poor boy named Dick Whittington. He was an orphan and had little in the way of comfort, but he was a bright, hopeful lad, and he had heard stories of a place which had been called London, a city said to be so rich that its streets were paved with gold.</p><p>Dreaming of a better life, young Dick decided to leave his small village and set off on foot for London.</p>
    """

    ## Create 1/5 gaps including capitalised words.
    And I press "id_button_group_add_gaps_5"
    Then the field "Question text" matches multiline:
    """
    <h5>The story of a poor boy who made his fortune.</h5>
    <p>Once upon a time, {1:SA:=many} hundreds of years ago, {1:SA:=lived} a poor boy named {1:SA:=Dick} Whittington. He was an {1:SA:=orphan} and had little in {1:SA:=the} way of comfort, but {1:SA:=he} was a bright, hopeful {1:SA:=lad}, and he had heard {1:SA:=stories} of a place which {1:SA:=had} been called London, a {1:SA:=city} said to be so {1:SA:=rich} that its streets were {1:SA:=paved} with gold.</p>
    <p>Dreaming of a better {1:SA:=life}, young Dick decided to {1:SA:=leave} his small village and {1:SA:=set} off on foot for {1:SA:=London}.</p>
    """

    ## Try to add gaps to question text already containing gaps.
    And I press "id_button_group_add_gaps_5"
    Then I should see "This question text already has gaps"
    Then I press "id_button_group_remove_gaps_button"

    ## Create 1/5 gaps excluding first occurrence of capitalised words.
    And I click on "Skip capitalised words" "checkbox"
    And I press "id_button_group_add_gaps_5"
    Then the field "Question text" matches multiline:
    """
    <h5>The story of a poor boy who made his fortune.</h5><p>Once upon a time, {1:SA:=many} hundreds of years ago, {1:SA:=lived} a poor boy named Dick Whittington. He {1:SA:=was} an orphan and had {1:SA:=little} in the way of {1:SA:=comfort}, but he was a {1:SA:=bright}, hopeful lad, and he {1:SA:=had} heard stories of a {1:SA:=place} which had been called London, {1:SA:=a} city said to be {1:SA:=so} rich that its streets {1:SA:=were} paved with gold.</p><p>Dreaming of a better {1:SA:=life}, young Dick decided to {1:SA:=leave} his small village and {1:SA:=set} off on foot for {1:SA:=London}.</p>
    """

  @javascript
  Scenario: Create a minimal Cloze question with the create gaps feature and preview it
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I press "Create a new question ..."
    And I set the field "Embedded answers with REGEXP (Clozergx)" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I set the field "Question name" to "multianswer-01"
    And I set the field "Question text" to "Once upon a time, many hundreds of years ago lived Dick Whittington."
    And I click on "Skip capitalised words" "checkbox"
    And I press "id_button_group_add_gaps_5"
    And I press "id_submitbutton"
    Then I should see "multianswer-01" in the "categoryquestions" "table"
    # Preview it.
    And I choose "Preview" action for "multianswer-01" in the question bank
    And I should see "Once upon a time"
    # Set behaviour options
    And I set the following fields to these values:
      | behaviour | immediatefeedback |
    And I press "saverestart"
    And I set the field with xpath "//input[contains(@id, '1_sub1_answer')]" to "many"
    And I set the field with xpath "//input[contains(@id, '1_sub2_answer')]" to "died"
    And I press "Check"
    # Click on feedbacktrigger for blank #1
    And I click on "(//a[contains(@class, 'feedbacktrigger')])[1]" "xpath_element"
    Then I should see "Correct"
    And I should see "The correct answer is: many"
    And I should see "Mark 1.00 out of 1.00"
    # Click on feedbacktrigger for blank #2
    And I click on "(//a[contains(@class, 'feedbacktrigger')])[2]" "xpath_element"
    Then I should see "Incorrect"
    And I should see "The correct answer is: lived"
    And I should see "Mark 0.00 out of 1.00"
    Then I log out

  @javascript @editor_tiny
  Scenario: Create a Cloze question with the create gaps feature with an image and preview it
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I press "Create a new question ..."
    And I set the field "Embedded answers with REGEXP (Clozergx)" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I set the field "Question name" to "multianswer-01"
    And I set the field "Question text" to multiline:
    """
    <h4>The story of Dick Whittington</h4><img><p>Once upon a time, many hundreds of years ago, lived a poor boy named Dick Whittington. He was an orphan and had little in the way of comfort, but he was a bright, hopeful lad, and he had heard stories of a place which had been called London, a city said to be so rich that its streets were paved with gold.</p><p>Dreaming of a better life, young Dick decided to leave his small village and set off on foot for London.</p>
    """
    And I select the "img" element in position "0" of the "Question text" TinyMCE editor
    And I click on the "Image" button for the "Question text" TinyMCE editor
    And I click on "Browse repositories" "button" in the "Insert image" "dialogue"
    And I click on "Private files" "link" in the ".fp-repo-area" "css_element"
    And I click on "dick_s_cat.jpg" "link"
    And I click on "Select this file" "button"
    And I set the field "How would you describe this image to someone who can't see it?" to "It's Dick's cat"
    And I click on "Save" "button" in the "Image details" "dialogue"
    And I click on "Skip capitalised words" "checkbox"
    # Check that gaps are only created on text formatted as paragraph
    And I press "id_button_group_add_gaps_5"
    # Check that removing gaps does not remove text not formatted as paragraph nor media
    Then I press "id_button_group_remove_gaps_button"
    And I press "id_button_group_add_gaps_9"
    And I press "id_submitbutton"
    Then I should see "multianswer-01" in the "categoryquestions" "table"
    # Preview it.
    And I choose "Preview" action for "multianswer-01" in the question bank
    And I should see "The story of Dick Whittington"
    # Set behaviour options
    And I set the following fields to these values:
      | behaviour | immediatefeedback |
    And I press "saverestart"
    And I press "Fill in correct responses"
    And I press "Check"
    And I log out

  @javascript
  Scenario: Create a question with all the sub-questions from the multianswer doc and remove the sub-questions.
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I press "Create a new question ..."
    And I set the field "Embedded answers with REGEXP (Clozergx)" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I set the field "Question name" to "multianswer-01"

    And I set the field "Question text" to multiline:
    """
    <p>This question consists of some text with an answer embedded right here {1:MULTICHOICE:Wrong answer#Feedback for this wrong answer~Another wrong answer#Feedback for the other wrong answer~=Correct answer#Feedback for correct answer~%50%Answer that gives half the credit#Feedback for half credit answer} and right after that you will have to deal with this short answer {1:SHORTANSWER:Wrong answer#Feedback for this wrong answer~=Correct answer#Feedback for correct answer~%50%Answer that gives half the credit#Feedback for half credit answer} and finally we have a floating point number {2:NUMERICAL:=23.8:0.1#Feedback for correct answer 23.8~%50%23.8:2#Feedback for half credit answer in the nearby region of the correct answer}.</p><p>The multichoice question can also be shown in the vertical display of the standard moodle multiple choice.{2:MCV:1. Wrong answer#Feedback for this wrong answer~2. Another wrong answer#Feedback for the other wrong answer~=3. Correct answer#Feedback for correct answer~%50%4. Answer that gives half the credit#Feedback for half credit answer} Or in an horizontal display that is included here in a table {2:MCH:a. Wrong answer#Feedback for this wrong answer~b. Another wrong answer#Feedback for the other wrong answer~=c. Correct answer#Feedback for correct answer~%50%d. Answer that gives half the credit#Feedback for half credit answer}</p><p>A shortanswer question where case must match. Write moodle in upper case letters {1:SHORTANSWER_C:moodle#Feedback for moodle in lower case ~=MOODLE#Feedback for MOODLE in upper case ~%50%Moodle#Feedback for only first letter in upper case}</p><p>Note that addresses like www.moodle.org and smileys :-) all work as normal: a) How good is this? {:MULTICHOICE:=Yes#Correct~No#We have a different opinion} b) What grade would you give it? {3:NUMERICAL:=3:2}<p>
    """
    # Check that removing sub-questions restores the first correct answer of each sub-questions
    Then I press "id_button_group_remove_gaps_button"
    Then the field "Question text" matches multiline:
    """
    <p>This question consists of some text with an answer embedded right here Correct answer and right after that you will have to deal with this short answer Correct answer and finally we have a floating point number 23.8:0.1.</p><p>The multichoice question can also be shown in the vertical display of the standard moodle multiple choice.3. Correct answer Or in an horizontal display that is included here in a table c. Correct answer</p><p>A shortanswer question where case must match. Write moodle in upper case letters MOODLE</p><p>Note that addresses like www.moodle.org and smileys :-) all work as normal: a) How good is this? Yes b) What grade would you give it? 3:2</p><p></p>
    """
    And I log out
