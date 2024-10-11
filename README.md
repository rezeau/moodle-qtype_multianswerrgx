# moodle-qtype_multianswerrgx
The multianswerrgx question type is a cloned version of the Moodle multianswer question type hacked to accept the regexp question type.
The insertion of regexp sub-questions inside the question text of a cloze/multianswer follows the same pattern as the insertion of SHORTANSWER sub-questions. For REGEXP sub-questions you have a choice of 4 formats: 
    regexp (REGEXP or RX), case is unimportant,
    regexp (REGEXP_C or RXC), case must match.
Most of the features of the REGEXP question type are available when inserting a REGEXP inside a Clozergx question. However, the Hints (Letter/Word) feature is not available, nor the automatic feedback formatting. The permutation feature is available, and the alternative accepted answers are displayed in edit mode.
A new 'Add close gaps' feature makes it easy to create a classical Cloze question. This feature is currently limited to creating cloze gaps every 5 or 9 words. There is an option to skip capitalised words (on their first appearance in the text). Once the desired gaps have been created with the default SA/SHORTANSWER question type they can be edited and their question type can be changed if desired.