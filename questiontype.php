<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Question type class for the multi-answer question type with regexp question type.
 *
 * @package    qtype_multianswerrgx
 * @copyright  2024 Joseph Rézeau <moodle@rezeau.org>
 * @copyright  based on work by 1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/questiontypebase.php');
require_once($CFG->dirroot . '/question/type/multichoice/question.php');
require_once($CFG->dirroot . '/question/type/numerical/questiontype.php');
require_once($CFG->dirroot . '/question/type/regexp/questiontype.php');
require_once($CFG->dirroot . '/question/type/regexp/locallib.php');

/**
 * The multi-answer question type class with regexp.
 * 
 * @copyright  2024 Joseph Rézeau <moodle@rezeau.org>
 * @copyright  based on work by 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_multianswerrgx extends question_type {

    /**
     * Generate a subquestion replacement question class.
     *
     * This method returns an anonymous class that implements the `question_automatically_gradable` interface.
     * This placeholder class is used to handle cases where subquestions are lost due to a known bug (see MDL-54724).
     *
     * @return question_automatically_gradable The replacement question class.
     */
    public static function deleted_subquestion_replacement(): question_automatically_gradable {
        return new class implements question_automatically_gradable { // phpcs:ignore
            /**
             * @var object An anonymous class instance representing the question type.
             */
            public $qtype;

            /**
             * Initializes the question type.
             *
             * Sets the `$qtype` property to an anonymous class instance that provides
             * the name of the question type as 'subquestion_replacement'.
             */
            public function __construct() {
                $this->qtype = new class { // phpcs:ignore
                    /**
                     * Get the name of the question type.
                     *
                     * @return string The name of the question type.
                     */
                    public function name() {
                        return 'subquestion_replacement';
                    }
                };
            }

            /**
             * Determine if the response is gradable.
             *
             * @param array $response The response to check.
             * @return bool False as this is a replacement question.
             */
            public function is_gradable_response(array $response): bool {
                return false;
            }

            /**
             * Determine if the response is complete.
             *
             * @param array $response The response to check.
             * @return bool False as this is a replacement question.
             */
            public function is_complete_response(array $response): bool {
                return false;
            }

            /**
             * Determine if the new response is the same as the previous response.
             *
             * @param array $prevresponse The previous response.
             * @param array $newresponse The new response.
             * @return bool False as this is a replacement question.
             */
            public function is_same_response(array $prevresponse, array $newresponse): bool {
                return false;
            }

            /**
             * Summarize the response.
             *
             * @param array $response The response to summarize.
             * @return string An empty string as this is a replacement question.
             */
            public function summarise_response(array $response): string {
                return '';
            }

            /**
             * Un-summarize the response.
             *
             * @param string $summary The summary to un-summarize.
             * @return array An empty array as this is a replacement question.
             */
            public function un_summarise_response(string $summary): array {
                return [];
            }

            /**
             * Classify the response.
             *
             * @param array $response The response to classify.
             * @return array An empty array as this is a replacement question.
             */
            public function classify_response(array $response): array {
                return [];
            }

            /**
             * Get the validation error for the response.
             *
             * @param array $response The response to validate.
             * @return string An empty string as this is a replacement question.
             */
            public function get_validation_error(array $response): string {
                return '';
            }

            /**
             * Grade the response.
             *
             * @param array $response The response to grade.
             * @return array An empty array as this is a replacement question.
             */
            public function grade_response(array $response): array {
                return [];
            }

            /**
             * Get a hint for the response.
             *
             * @param int $hintnumber The hint number.
             * @param question_attempt $qa The question attempt object.
             * @return void
             */
            public function get_hint($hintnumber, question_attempt $qa) {
                return;
            }

            /**
             * Get a summary of the right answer.
             *
             * @return string|null Null as this is a replacement question.
             */
            public function get_right_answer_summary(): ?string {
                return null;
            }
        };
    }

    /**
     * Check if responses can be analyzed.
     *
     * @return bool Always returns false, indicating that responses cannot be analyzed.
     */
    public function can_analyse_responses() {
        return false;
    }

    /**
     * Retrieve options for a given question.
     *
     * @param mixed $question The question for which options are to be retrieved.
     * @return stdClass The options for the question.
     */
    public function get_question_options($question) {
        global $DB;

        parent::get_question_options($question);
        // Get relevant data indexed by positionkey from the multianswerrgxs table.
        $sequence = $DB->get_field('question_multianswerrgx', 'sequence',
                ['question' => $question->id], MUST_EXIST);

        if (empty($sequence)) {
            $question->options->questions = [];
            return true;
        }

        $wrappedquestions = $DB->get_records_list('question', 'id',
                explode(',', $sequence), 'id ASC');

        // We want an array with question ids as index and the positions as values.
        $sequence = array_flip(explode(',', $sequence));
        array_walk($sequence, function(&$val) {
            $val++;
        });

        // Due to a bug, questions can be lost (see MDL-54724). So we first fill the question
        // options with this dummy "replacement" type. These are overridden in the loop below
        // leaving behind only those questions which no longer exist. The renderer then looks
        // for this deleted type to display information to the user about the corrupted question
        // data.
        foreach ($sequence as $seq) {
            $question->options->questions[$seq] = (object)[
                'qtype' => 'subquestion_replacement',
                'defaultmark' => 1,
                'options' => (object)[
                    'answers' => [],
                ],
            ];
        }
        foreach ($wrappedquestions as $wrapped) {
            question_bank::get_qtype($wrapped->qtype)->get_question_options($wrapped);
            // For wrapped questions the maxgrade is always equal to the defaultmark,
            // there is no entry in the question_instances table for them.
            $wrapped->category = $question->categoryobject->id ?? $question->category ?? 0;
            $question->options->questions[$sequence[$wrapped->id]] = $wrapped;
        }

        $question->hints = $DB->get_records('question_hints',
                ['questionid' => $question->id], 'id ASC');

        return true;
    }

    /**
     * Save options for a given question.
     *
     * @param mixed $question The question for which options are to be saved.
     * @return stdClass An object representing the result of the save operation.
     */
    public function save_question_options($question) {
        global $DB;
        $result = new stdClass();

        // This function needs to be able to handle the case where the existing set of wrapped
        // questions does not match the new set of wrapped questions so that some need to be
        // created, some modified and some deleted.
        // Unfortunately the code currently simply overwrites existing ones in sequence. This
        // will make re-marking after a re-ordering of wrapped questions impossible and
        // will also create difficulties if questiontype specific tables reference the id.

        // First deal with importing multianswerrgx questions using XML Moodle format.
        if (isset($question->import_process)) {
            /* In the export_to_xml process questiontext has been saved as questiontextrgx
             * so now we must restore it from the exported file.
            */
            $questiontext = [
                "text" => $question->questiontextrgx,
                "format" => $question->questiontextformat,
                "itemid" => $question->id,
            ];
            $qo = qtype_multianswerrgx_extract_question($questiontext);
            $errors = qtype_multianswerrgx_validate_question($qo);
            $qo->name = $question->name;
            $qo->questiontextformat = $question->questiontextformat;
            $sequence = [];
            foreach ($qo->options->questions as $subquestion) {
                $subquestion->parent = $question->id;
                $subquestion->name = $question->name;
                $subquestion->context = $question->context;
                $subquestion->category = $question->category;
                $subquestion->idnumber = null;
                $subquestionsave = $this->save_imported_subquestion($subquestion);
                $sequence[] = $subquestionsave;
            }
            if (!empty($sequence)) {
                $multianswerrgx = new stdClass();
                $multianswerrgx->question = $question->id;
                $multianswerrgx->sequence = implode(',', $sequence);
                $DB->insert_record('question_multianswerrgx', $multianswerrgx);
            }
            return;
        } else {
            // First we get all the existing wrapped questions.
            $oldwrappedquestions = [];
            if (isset($question->oldparent)) {
                if ($oldwrappedids = $DB->get_field('question_multianswerrgx', 'sequence',
                ['question' => $question->oldparent])) {
                    $oldwrappedidsarray = explode(',', $oldwrappedids);
                    $unorderedquestions = $DB->get_records_list('question', 'id', $oldwrappedidsarray);

                    // Keep the order as given in the sequence field.
                    foreach ($oldwrappedidsarray as $questionid) {
                        if (isset($unorderedquestions[$questionid])) {
                            $oldwrappedquestions[] = $unorderedquestions[$questionid];
                        }
                    }
                }
            }

            $sequence = [];
            foreach ($question->options->questions as $wrapped) {
                if (!empty($wrapped)) {
                    // If we still have some old wrapped question ids, reuse the next of them.
                    $wrapped->id = 0;
                    if (is_array($oldwrappedquestions) &&
                        $oldwrappedquestion = array_shift($oldwrappedquestions)) {
                        $wrapped->oldid = $oldwrappedquestion->id;
                        if ($oldwrappedquestion->qtype != $wrapped->qtype) {
                            switch ($oldwrappedquestion->qtype) {
                                case 'multichoice':
                                    $DB->delete_records('qtype_multichoice_options',
                                        ['questionid' => $oldwrappedquestion->id]);
                                    break;
                                case 'shortanswer':
                                    $DB->delete_records('qtype_shortanswer_options',
                                        ['questionid' => $oldwrappedquestion->id]);
                                    break;
                                case 'regexp':
                                    $DB->delete_records('qtype_regexp',
                                        ['questionid' => $oldwrappedquestion->id]);
                                    break;
                                case 'numerical':
                                    $DB->delete_records('question_numerical',
                                        ['question' => $oldwrappedquestion->id]);
                                    break;
                                default:
                                    throw new moodle_exception('qtypenotrecognized',
                                        'qtype_multianswerrgx', '', $oldwrappedquestion->qtype);
                            }
                        }
                    }
                }
                $wrapped->name = $question->name;
                $wrapped->parent = $question->id;
                $previousid = $wrapped->id;
                // Save_question strips this extra bit off the category again.
                $wrapped->category = $question->category . ',1';
                $wrapped = question_bank::get_qtype($wrapped->qtype)->save_question(
                    $wrapped, clone($wrapped));
                $sequence[] = $wrapped->id;
                if ($previousid != 0 && $previousid != $wrapped->id) {
                    // For some reasons a new question has been created
                    // so delete the old one.
                    question_delete_question($previousid);
                }
            }

            // Delete redundant wrapped questions.
            if (is_array($oldwrappedquestions) && count($oldwrappedquestions)) {
                foreach ($oldwrappedquestions as $oldwrappedquestion) {
                    question_delete_question($oldwrappedquestion->id);
                }
            }

            if (!empty($sequence)) {
                $multianswerrgx = new stdClass();
                $multianswerrgx->question = $question->id;
                $multianswerrgx->sequence = implode(',', $sequence);
                if ($oldid = $DB->get_field('question_multianswerrgx', 'id',
                    ['question' => $question->id])) {
                    $multianswerrgx->id = $oldid;
                    $DB->update_record('question_multianswerrgx', $multianswerrgx);
                } else {
                    $DB->insert_record('question_multianswerrgx', $multianswerrgx);
                }
            }

            $this->save_hints($question, true);
        }
    }

    /**
     * Saves an imported subquestion to the database.
     *
     * @param object $fromimport The subquestion object to be saved.
     *
     * @return int The ID of the saved subquestion.
     */
    protected function save_imported_subquestion($fromimport) {
        global $USER, $DB, $OUTPUT;
        $fromimport->stamp = make_unique_id_code();  // Set the unique code (not to be changed).
        $fromimport->createdby = $USER->id;
        $fromimport->timecreated = time();
        $fromimport->modifiedby = $USER->id;
        $fromimport->timemodified = time();
        $fileoptions = [
            'subdirs' => true,
            'maxfiles' => -1,
            'maxbytes' => 0,
        ];
        $wrapped = $fromimport;
        $wrapped = question_bank::get_qtype($wrapped->qtype)->save_question(
                        $wrapped, clone($wrapped));
        return $wrapped->id;
    }

    /**
     * Save or update a question based on form data.
     *
     * @param stdClass $authorizedquestion Existing question to update, if available.
     * @param stdClass $form Form containing question details.
     * @return void
     */
    public function save_question($authorizedquestion, $form) {
        $question = qtype_multianswerrgx_extract_question($form->questiontext);
        if (isset($authorizedquestion->id)) {
            $question->id = $authorizedquestion->id;
        }

        $question->category = $form->category;
        $form->defaultmark = $question->defaultmark;
        $form->questiontext = $question->questiontext;
        $form->questiontextformat = 0;
        $form->options = clone($question->options);
        unset($question->options);

        return parent::save_question($question, $form);
    }

    /**
     * Create a hint from a record.
     *
     * @param stdClass $hint Hint record to load.
     * @return question_hint_with_parts Hint object created from the record.
     */
    protected function make_hint($hint) {
        return question_hint_with_parts::load_from_record($hint);
    }

    /**
     * Delete a question and its related records.
     *
     * @param int $questionid The ID of the question to delete.
     * @param int $contextid The context ID related to the question.
     * @return void
     */
    public function delete_question($questionid, $contextid) {
        global $DB;
        $DB->delete_records('question_multianswerrgx', ['question' => $questionid]);

        parent::delete_question($questionid, $contextid);
    }

    /**
     * Initialize a question instance with data.
     *
     * @param question_definition $question The question instance to initialize.
     * @param stdClass $questiondata Data to initialize the question with.
     * @return void
     */
    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);

        $bits = preg_split('/\{#(\d+)\}/', $question->questiontext,
                -1, PREG_SPLIT_DELIM_CAPTURE);
        $question->textfragments[0] = array_shift($bits);
        $i = 1;
        while (!empty($bits)) {
            $question->places[$i] = array_shift($bits);
            $question->textfragments[$i] = array_shift($bits);
            $i += 1;
        }
        foreach ($questiondata->options->questions as $key => $subqdata) {
            if ($subqdata->qtype == 'subquestion_replacement') {
                continue;
            }

            $subqdata->contextid = $questiondata->contextid;
            if ($subqdata->qtype == 'multichoice') {
                $answerregs = [];
                if ($subqdata->options->shuffleanswers == 1 &&  isset($questiondata->options->shuffleanswers)
                    && $questiondata->options->shuffleanswers == 0 ) {
                    $subqdata->options->shuffleanswers = 0;
                }
            }
            $question->subquestions[$key] = question_bank::make_question($subqdata);
            $question->subquestions[$key]->defaultmark = $subqdata->defaultmark;
            if (isset($subqdata->options->layout)) {
                $question->subquestions[$key]->layout = $subqdata->options->layout;
            }
        }
    }

    /**
     * Calculate the score for a random guess based on question data.
     *
     * @param stdClass $questiondata Data related to the question.
     * @return float The calculated score for a random guess.
     */
    public function get_random_guess_score($questiondata) {
        $fractionsum = 0;
        $fractionmax = 0;
        foreach ($questiondata->options->questions as $key => $subqdata) {
            if ($subqdata->qtype == 'subquestion_replacement') {
                continue;
            }
            $fractionmax += $subqdata->defaultmark;
            $fractionsum += question_bank::get_qtype(
                    $subqdata->qtype)->get_random_guess_score($subqdata);
        }
        if ($fractionmax > question_utils::MARK_TOLERANCE) {
            return $fractionsum / $fractionmax;
        } else {
            return null;
        }
    }
    // IMPORT EXPORT FUNCTIONS.

    /**
     * Provide export functionality for xml format.
     *
     * @param stdObject $question the question object
     * @param qformat_xml $format the format object so that helper methods can be used
     * @param mixed $extra any additional format specific data that may be passed by the format (see format code for info)
     * @return string the data to append to the output buffer or false if error
     */
    public function export_to_xml($question, qformat_xml $format, $extra = null) {
        $output = '';
        $questiontext = $question->questiontext;
        foreach ($question->options->questions as $index => $subq) {
                    $questiontext = str_replace('{#' . $index . '}', $subq->questiontext, $questiontext);
        }
        $output = "<questiontextrgx format=\"html\"><text><![CDATA[$questiontext]]></text></questiontextrgx>";
        return $output;
    }

    /**
     * Imports a question from XML data.
     *
     * @param array $data      The XML data to import from.
     * @param object $question The question object being imported.
     * @param qformat_xml $format The format object for XML import.
     * @param mixed $extra     Additional data for import (optional).
     *
     * @return mixed The imported question object or false if the question type is not 'multianswerrgx'.
     */
    public function import_from_xml($data, $question, qformat_xml $format, $extra=null) {
        if (!isset($data['@']['type']) || $data['@']['type'] != 'multianswerrgx') {
            return false;
        }
        $question = $format->import_headers($data);
        $question->qtype = 'multianswerrgx';
        // Access the contents of the questiontext field.
        $questiontextcontent = $data["#"]["questiontext"][0]["#"]["text"][0]["#"];
        // Access the contents of the questiontextrgx field.
        $questiontextrgxcontent = $data["#"]["questiontextrgx"][0]["#"]["text"][0]["#"];
        $question->questiontextrgx = $questiontextrgxcontent;
        return $question;
    }
    /**
     * Move files related to a question to a new context.
     *
     * @param int $questionid The ID of the question.
     * @param int $oldcontextid The ID of the old context.
     * @param int $newcontextid The ID of the new context.
     * @return void
     */
    public function move_files($questionid, $oldcontextid, $newcontextid) {
        parent::move_files($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_hints($questionid, $oldcontextid, $newcontextid);
    }

    /**
     * Delete files related to a question from a context.
     *
     * @param int $questionid The ID of the question.
     * @param int $contextid The ID of the context.
     * @return void
     */
    protected function delete_files($questionid, $contextid) {
        parent::delete_files($questionid, $contextid);
        $this->delete_files_in_hints($questionid, $contextid);
    }
}

// ANSWER_ALTERNATIVE regexes.
define('ANSWER_ALTERNATIVE_FRACTION_REGEX_RGX',
       '=|%(-?[0-9]+(?:[.,][0-9]*)?)%');
// For the syntax '(?<!' see http://www.perl.com/doc/manual/html/pod/perlre.html#item_C.
define('ANSWER_ALTERNATIVE_ANSWER_REGEX_RGX',
        '.+?(?<!\\\\|&|&amp;)(?=[~#}]|$)');
define('ANSWER_ALTERNATIVE_FEEDBACK_REGEX_RGX',
        '.*?(?<!\\\\)(?=[~}]|$)');
define('ANSWER_ALTERNATIVE_REGEX_RGX',
       '(' . ANSWER_ALTERNATIVE_FRACTION_REGEX_RGX .')?' .
       '(' . ANSWER_ALTERNATIVE_ANSWER_REGEX_RGX . ')' .
       '(#(' . ANSWER_ALTERNATIVE_FEEDBACK_REGEX_RGX .'))?');

// Parenthesis positions for ANSWER_ALTERNATIVE_REGEX_RGX.
define('ANSWER_ALTERNATIVE_REGEX_PERCENTILE_FRACTION_RGX', 2);
define('ANSWER_ALTERNATIVE_REGEX_FRACTION_RGX', 1);
define('ANSWER_ALTERNATIVE_REGEX_ANSWER_RGX', 3);
define('ANSWER_ALTERNATIVE_REGEX_FEEDBACK_RGX', 5);

// NUMBER_FORMATED_ALTERNATIVE_ANSWER_REGEX is used
// for identifying numerical answers in ANSWER_ALTERNATIVE_REGEX_ANSWER_RGX.
define('NUMBER_REGEX_RGX',
        '-?(([0-9]+[.,]?[0-9]*|[.,][0-9]+)([eE][-+]?[0-9]+)?)');
define('NUMERICAL_ALTERNATIVE_REGEX_RGX',
        '^(' . NUMBER_REGEX_RGX . ')(:' . NUMBER_REGEX_RGX . ')?$');

// Parenthesis positions for NUMERICAL_FORMATED_ALTERNATIVE_ANSWER_REGEX_RGX.
define('NUMERICAL_CORRECT_ANSWER_RGX', 1);
define('NUMERICAL_ABS_ERROR_MARGIN_RGX', 6);

// Remaining ANSWER regexes.
define('ANSWER_TYPE_DEF_REGEX_RGX',
        '(NUMERICAL|NM)|(MULTICHOICE|MC)|(MULTICHOICE_V|MCV)|(MULTICHOICE_H|MCH)|' .
        '(SHORTANSWER|SA|MW)|(SHORTANSWER_C|SAC|MWC)|' .
        '(REGEXP|RX)|(REGEXP_C|RXC)|' .
        '(MULTICHOICE_S|MCS)|(MULTICHOICE_VS|MCVS)|(MULTICHOICE_HS|MCHS)|'.
        '(MULTIRESPONSE|MR)|(MULTIRESPONSE_H|MRH)|(MULTIRESPONSE_S|MRS)|(MULTIRESPONSE_HS|MRHS)');
define('ANSWER_START_REGEX_RGX',
       '\{([0-9]*):(' . ANSWER_TYPE_DEF_REGEX_RGX . '):');

define('ANSWER_REGEX_RGX',
        ANSWER_START_REGEX_RGX
        . '(' . ANSWER_ALTERNATIVE_REGEX_RGX
        . '(~'
        . ANSWER_ALTERNATIVE_REGEX_RGX
        . ')*)\}');

// Parenthesis positions for singulars in ANSWER_REGEX.
define('ANSWER_REGEX_NORM_RGX', 1);
define('ANSWER_REGEX_ANSWER_TYPE_NUMERICAL_RGX', 3);
define('ANSWER_REGEX_ANSWER_TYPE_MULTICHOICE_RGX', 4);
define('ANSWER_REGEX_ANSWER_TYPE_MULTICHOICE_REGULAR_RGX', 5);
define('ANSWER_REGEX_ANSWER_TYPE_MULTICHOICE_HORIZONTAL_RGX', 6);
define('ANSWER_REGEX_ANSWER_TYPE_SHORTANSWER_RGX', 7);
define('ANSWER_REGEX_ANSWER_TYPE_SHORTANSWER_C_RGX', 8);
define('ANSWER_REGEX_ANSWER_TYPE_REGEXP_RGX', 9);
define('ANSWER_REGEX_ANSWER_TYPE_REGEXP_C_RGX', 10);
define('ANSWER_REGEX_ANSWER_TYPE_MULTICHOICE_SHUFFLED_RGX', 11);
define('ANSWER_REGEX_ANSWER_TYPE_MULTICHOICE_REGULAR_SHUFFLED_RGX', 12);
define('ANSWER_REGEX_ANSWER_TYPE_MULTICHOICE_HORIZONTAL_SHUFFLED_RGX', 13);
define('ANSWER_REGEX_ANSWER_TYPE_MULTIRESPONSE_RGX', 14);
define('ANSWER_REGEX_ANSWER_TYPE_MULTIRESPONSE_HORIZONTAL_RGX', 15);
define('ANSWER_REGEX_ANSWER_TYPE_MULTIRESPONSE_SHUFFLED_RGX', 16);
define('ANSWER_REGEX_ANSWER_TYPE_MULTIRESPONSE_HORIZONTAL_SHUFFLED_RGX', 17);
define('ANSWER_REGEX_ALTERNATIVES_RGX', 18);

/**
 * Initialise subquestion fields that are constant across all MULTICHOICE
 * types.
 *
 * @param objet $wrapped  The subquestion to initialise
 *
 */
function qtype_multianswerrgx_initialise_multichoice_subquestion($wrapped) {
    $wrapped->qtype = 'multichoice';
    $wrapped->single = 1;
    $wrapped->answernumbering = 0;
    $wrapped->correctfeedback['text'] = '';
    $wrapped->correctfeedback['format'] = FORMAT_HTML;
    $wrapped->correctfeedback['itemid'] = '';
    $wrapped->partiallycorrectfeedback['text'] = '';
    $wrapped->partiallycorrectfeedback['format'] = FORMAT_HTML;
    $wrapped->partiallycorrectfeedback['itemid'] = '';
    $wrapped->incorrectfeedback['text'] = '';
    $wrapped->incorrectfeedback['format'] = FORMAT_HTML;
    $wrapped->incorrectfeedback['itemid'] = '';
}

    /**
     * Extract a question object from a text input.
     *
     * @param array $text The text array containing question details.
     *                      - `text`     : The question text.
     *                      - `format`   : The text format.
     *                      - `itemid`   : The item ID.
     * @return stdClass The extracted question object with type and feedback details.
     */
function qtype_multianswerrgx_extract_question($text) {
    // Variable $text is an array [text][format][itemid].
    $question = new stdClass();
    $question->qtype = 'multianswerrgx';
    $question->questiontext = $text;
    $question->generalfeedback['text'] = '';
    $question->generalfeedback['format'] = FORMAT_HTML;
    $question->generalfeedback['itemid'] = '';

    $question->options = new stdClass();
    $question->options->questions = [];
    $question->defaultmark = 0; // Will be increased for each answer norm.

    for ($positionkey = 1; preg_match('/'.ANSWER_REGEX_RGX.'/s', $question->questiontext['text'], $answerregs); ++$positionkey) {
        $wrapped = new stdClass();
        $wrapped->generalfeedback['text'] = '';
        $wrapped->generalfeedback['format'] = FORMAT_HTML;
        $wrapped->generalfeedback['itemid'] = '';
        if (isset($answerregs[ANSWER_REGEX_NORM_RGX]) && $answerregs[ANSWER_REGEX_NORM_RGX] !== '') {
            $wrapped->defaultmark = $answerregs[ANSWER_REGEX_NORM_RGX];
        } else {
            $wrapped->defaultmark = '1';
        }
        if (!empty($answerregs[ANSWER_REGEX_ANSWER_TYPE_NUMERICAL_RGX])) {
            $wrapped->qtype = 'numerical';
            $wrapped->multiplier = [];
            $wrapped->units      = [];
            $wrapped->instructions['text'] = '';
            $wrapped->instructions['format'] = FORMAT_HTML;
            $wrapped->instructions['itemid'] = '';
        } else if (!empty($answerregs[ANSWER_REGEX_ANSWER_TYPE_SHORTANSWER_RGX])) {
            $wrapped->qtype = 'shortanswer';
            $wrapped->usecase = 0;
        } else if (!empty($answerregs[ANSWER_REGEX_ANSWER_TYPE_SHORTANSWER_C_RGX])) {
            $wrapped->qtype = 'shortanswer';
            $wrapped->usecase = 1;
        } else if (!empty($answerregs[ANSWER_REGEX_ANSWER_TYPE_REGEXP_RGX])) {
            $wrapped->qtype = 'regexp';
            $wrapped->usecase = 0;
        } else if (!empty($answerregs[ANSWER_REGEX_ANSWER_TYPE_REGEXP_C_RGX])) {
            $wrapped->qtype = 'regexp';
            $wrapped->usecase = 1;
        } else if (!empty($answerregs[ANSWER_REGEX_ANSWER_TYPE_MULTICHOICE_RGX])) {
            qtype_multianswerrgx_initialise_multichoice_subquestion($wrapped);
            $wrapped->shuffleanswers = 0;
            $wrapped->layout = qtype_multichoice_base::LAYOUT_DROPDOWN;
        } else if (!empty($answerregs[ANSWER_REGEX_ANSWER_TYPE_MULTICHOICE_SHUFFLED_RGX])) {
            qtype_multianswerrgx_initialise_multichoice_subquestion($wrapped);
            $wrapped->shuffleanswers = 1;
            $wrapped->layout = qtype_multichoice_base::LAYOUT_DROPDOWN;
        } else if (!empty($answerregs[ANSWER_REGEX_ANSWER_TYPE_MULTICHOICE_REGULAR_RGX])) {
            qtype_multianswerrgx_initialise_multichoice_subquestion($wrapped);
            $wrapped->shuffleanswers = 0;
            $wrapped->layout = qtype_multichoice_base::LAYOUT_VERTICAL;
        } else if (!empty($answerregs[ANSWER_REGEX_ANSWER_TYPE_MULTICHOICE_REGULAR_SHUFFLED_RGX])) {
            qtype_multianswerrgx_initialise_multichoice_subquestion($wrapped);
            $wrapped->shuffleanswers = 1;
            $wrapped->layout = qtype_multichoice_base::LAYOUT_VERTICAL;
        } else if (!empty($answerregs[ANSWER_REGEX_ANSWER_TYPE_MULTICHOICE_HORIZONTAL_RGX])) {
            qtype_multianswerrgx_initialise_multichoice_subquestion($wrapped);
            $wrapped->shuffleanswers = 0;
            $wrapped->layout = qtype_multichoice_base::LAYOUT_HORIZONTAL;
        } else if (!empty($answerregs[ANSWER_REGEX_ANSWER_TYPE_MULTICHOICE_HORIZONTAL_SHUFFLED_RGX])) {
            qtype_multianswerrgx_initialise_multichoice_subquestion($wrapped);
            $wrapped->shuffleanswers = 1;
            $wrapped->layout = qtype_multichoice_base::LAYOUT_HORIZONTAL;
        } else if (!empty($answerregs[ANSWER_REGEX_ANSWER_TYPE_MULTIRESPONSE_RGX])) {
            qtype_multianswerrgx_initialise_multichoice_subquestion($wrapped);
            $wrapped->single = 0;
            $wrapped->shuffleanswers = 0;
            $wrapped->layout = qtype_multichoice_base::LAYOUT_VERTICAL;
        } else if (!empty($answerregs[ANSWER_REGEX_ANSWER_TYPE_MULTIRESPONSE_HORIZONTAL_RGX])) {
            qtype_multianswerrgx_initialise_multichoice_subquestion($wrapped);
            $wrapped->single = 0;
            $wrapped->shuffleanswers = 0;
            $wrapped->layout = qtype_multichoice_base::LAYOUT_HORIZONTAL;
        } else if (!empty($answerregs[ANSWER_REGEX_ANSWER_TYPE_MULTIRESPONSE_SHUFFLED_RGX])) {
            qtype_multianswerrgx_initialise_multichoice_subquestion($wrapped);
            $wrapped->single = 0;
            $wrapped->shuffleanswers = 1;
            $wrapped->layout = qtype_multichoice_base::LAYOUT_VERTICAL;
        } else if (!empty($answerregs[ANSWER_REGEX_ANSWER_TYPE_MULTIRESPONSE_HORIZONTAL_SHUFFLED_RGX])) {
            qtype_multianswerrgx_initialise_multichoice_subquestion($wrapped);
            $wrapped->single = 0;
            $wrapped->shuffleanswers = 1;
            $wrapped->layout = qtype_multichoice_base::LAYOUT_HORIZONTAL;
        } else {
            throw new \moodle_exception('unknownquestiontype', 'question', '', $answerregs[2]);
            return false;
        }

        // Each $wrapped simulates a $form that can be processed by the
        // respective save_question and save_question_options methods of the
        // wrapped questiontypes.
        $wrapped->answer   = [];
        $wrapped->fraction = [];
        $wrapped->feedback = [];
        $wrapped->questiontext['text'] = $answerregs[0];
        $wrapped->questiontext['format'] = FORMAT_HTML;
        $wrapped->questiontext['itemid'] = '';
        $answerindex = 0;

        $hasspecificfraction = false;
        $remainingalts = $answerregs[ANSWER_REGEX_ALTERNATIVES_RGX];
        while (preg_match('/~?'.ANSWER_ALTERNATIVE_REGEX_RGX.'/s', $remainingalts, $altregs)) {
            if ('=' == $altregs[ANSWER_ALTERNATIVE_REGEX_FRACTION_RGX]) {
                $wrapped->fraction["{$answerindex}"] = '1';
            } else if ($percentile = $altregs[ANSWER_ALTERNATIVE_REGEX_PERCENTILE_FRACTION_RGX]) {
                // Accept either decimal place character.
                $wrapped->fraction["{$answerindex}"] = .01 * str_replace(',', '.', $percentile);
                $hasspecificfraction = true;
            } else {
                $wrapped->fraction["{$answerindex}"] = '0';
            }
            if (isset($altregs[ANSWER_ALTERNATIVE_REGEX_FEEDBACK_RGX])) {
                $feedback = html_entity_decode(
                        $altregs[ANSWER_ALTERNATIVE_REGEX_FEEDBACK_RGX], ENT_QUOTES, 'UTF-8');
                $feedback = str_replace('\}', '}', $feedback);
                $wrapped->feedback["{$answerindex}"]['text'] = str_replace('\#', '#', $feedback);
                $wrapped->feedback["{$answerindex}"]['format'] = FORMAT_HTML;
                $wrapped->feedback["{$answerindex}"]['itemid'] = '';
            } else {
                $wrapped->feedback["{$answerindex}"]['text'] = '';
                $wrapped->feedback["{$answerindex}"]['format'] = FORMAT_HTML;
                $wrapped->feedback["{$answerindex}"]['itemid'] = '';

            }
            if (!empty($answerregs[ANSWER_REGEX_ANSWER_TYPE_NUMERICAL_RGX])
                    && preg_match('~'.NUMERICAL_ALTERNATIVE_REGEX_RGX.'~s',
                            $altregs[ANSWER_ALTERNATIVE_REGEX_ANSWER_RGX], $numregs)) {
                $wrapped->answer[] = $numregs[NUMERICAL_CORRECT_ANSWER_RGX];
                if (array_key_exists(NUMERICAL_ABS_ERROR_MARGIN_RGX, $numregs)) {
                    $wrapped->tolerance["{$answerindex}"] =
                    $numregs[NUMERICAL_ABS_ERROR_MARGIN_RGX];
                } else {
                    $wrapped->tolerance["{$answerindex}"] = 0;
                }
            } else { // Tolerance can stay undefined for non numerical questions.
                // Undo quoting done by the HTML editor.
                $answer = html_entity_decode(
                        $altregs[ANSWER_ALTERNATIVE_REGEX_ANSWER_RGX], ENT_QUOTES, 'UTF-8');
                $answer = str_replace('\}', '}', $answer);
                $wrapped->answer["{$answerindex}"] = str_replace('\#', '#', $answer);
                if ($wrapped->qtype == 'multichoice') {
                    $wrapped->answer["{$answerindex}"] = [
                            'text' => $wrapped->answer["{$answerindex}"],
                            'format' => FORMAT_HTML,
                            'itemid' => ''];
                }
            }
            $tmp = explode($altregs[0], $remainingalts, 2);
            $remainingalts = $tmp[1];
            $answerindex++;
        }

        // Fix the score for multichoice_multi questions (as positive scores should add up to 1, not have a maximum of 1).
        if (isset($wrapped->single) && $wrapped->single == 0) {
            $total = 0;
            foreach ($wrapped->fraction as $idx => $fraction) {
                if ($fraction > 0) {
                    $total += $fraction;
                }
            }
            if ($total) {
                foreach ($wrapped->fraction as $idx => $fraction) {
                    if ($fraction > 0) {
                        $wrapped->fraction[$idx] = $fraction / $total;
                    } else if (!$hasspecificfraction) {
                        // If no specific fractions are given, set incorrect answers to each cancel out one correct answer.
                        $wrapped->fraction[$idx] = -(1.0 / $total);
                    }
                }
            }
        }

        $question->defaultmark += $wrapped->defaultmark;
        $question->options->questions[$positionkey] = clone($wrapped);
        $question->questiontext['text'] = implode("{#$positionkey}",
                    explode($answerregs[0], $question->questiontext['text'], 2));
    }
    return $question;
}

/**
 * Validate a multianswerrgx question.
 *
 * @param stdClass $question The multianswerrgx question to validate, as returned by qtype_multianswerrgx_extract_question.
 *                           This object should include options and subquestions with answers and fractions.
 *
 * @return array An associative array of error messages, where keys correspond to specific question field names,
 *               and values are the error messages related to those fields. If no errors are found, an empty array is returned.
 */
function qtype_multianswerrgx_validate_question(stdClass $question): array {
    $errors = [];
    if (!isset($question->options->questions)) {
        $errors['questiontext'] = get_string('questionsmissing', 'qtype_multianswer');
    } else {
        $subquestions = fullclone($question->options->questions);
        if (count($subquestions)) {
            $sub = 1;
            foreach ($subquestions as $subquestion) {
                $prefix = 'sub_'.$sub.'_';
                $answercount = 0;
                $maxgrade = false;
                $maxfraction = -1;

                foreach ($subquestion->answer as $key => $answer) {
                    if (is_array($answer)) {
                        $answer = $answer['text'];
                    }
                    $trimmedanswer = trim($answer);
                    if ($trimmedanswer !== '') {
                        $answercount++;
                        if ($subquestion->qtype == 'numerical' &&
                                !(qtype_numerical::is_valid_number($trimmedanswer) || $trimmedanswer == '*')) {
                            $errors[$prefix.'answer['.$key.']'] =
                                    get_string('answermustbenumberorstar', 'qtype_numerical');
                        }
                        if ($subquestion->fraction[$key] == 1) {
                            $maxgrade = true;
                        }
                        if ($subquestion->fraction[$key] > $maxfraction) {
                            $maxfraction = $subquestion->fraction[$key];
                        }
                        // For 'multiresponse' we are OK if there is at least one fraction > 0.
                        if ($subquestion->qtype == 'multichoice' && $subquestion->single == 0 &&
                            $subquestion->fraction[$key] > 0) {
                            $maxgrade = true;
                        }
                    }
                }
                if ($subquestion->qtype == 'multichoice' && $answercount < 2) {
                    $errors[$prefix.'answer[0]'] = get_string('notenoughanswers', 'qtype_multichoice', 2);
                } else if ($answercount == 0) {
                    $errors[$prefix.'answer[0]'] = get_string('notenoughanswers', 'question', 1);
                }
                if ($maxgrade == false) {
                    $errors[$prefix.'fraction[0]'] = get_string('fractionsnomax', 'question');
                }
                $sub++;
            }
        } else {
            $errors['questiontext'] = get_string('questionsmissing', 'qtype_multianswer');
        }
    }
    return $errors;
}
/**
 * Check the regexp sub-question answers are valid. Adapted from regexp/locallib.php
 @param string $answer The sub-question answer to be validated. It is expected to be a regular expression.
 *
 * @return string The error text describing any validation issues found. If no errors are found, an empty string is returned.
 */
function validate_regexp_subquestion($answer) {
    $trimmedanswer = trim($answer);
    $parenserror = '';
    $metacharserror = '';
    $error = '';
    $illegalmetacharacters = ". ^ $ * + { } \\";

    $parenserror = check_permutations($trimmedanswer);
    if ($parenserror) {
        $error = $parenserror.'<br />';
    }
    $markedline = '';
    for ($i = 0; $i < strlen($trimmedanswer); $i++) {
        $markedline .= ' ';
    }
    $parenserror = check_my_parens($trimmedanswer, $markedline);
    if ($parenserror) {
        $markedline = $parenserror;
        $error = get_string("regexperrorparen", "qtype_regexp").'<br />';
    }
    $metacharserror = check_unescaped_metachars($trimmedanswer, $markedline);
    if ($metacharserror) {
        $error .= get_string("illegalcharacters", "qtype_regexp", $illegalmetacharacters);
    }
    if ($metacharserror || $parenserror) {
        $answerstringchunks = splitstring ($trimmedanswer);
        $nbchunks = count($answerstringchunks);
        $error .= '<pre class="displayvalidationerrors">';
        if ($metacharserror) {
            $illegalcharschunks = splitstring ($metacharserror);
            for ($i = 0; $i < $nbchunks; $i++) {
                $error .= '<br />'.$answerstringchunks[$i].'<br />'.$illegalcharschunks[$i];
            }
        } else if ($parenserror) {
            $illegalcharschunks = splitstring ($parenserror);
            for ($i = 0; $i < $nbchunks; $i++) {
                $error .= '<br />'.$answerstringchunks[$i].'<br />'.$illegalcharschunks[$i];
            }
        }
        $error .= '</pre>';
    }
    return $error;
}
