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
 * Provides restore functionality for the multianswerrgx question type.
 *
 * @package    qtype_multianswerrgx
 * @subpackage backup-moodle2
 * @copyright  2024 Joseph Rézeau <moodle@rezeau.org>
 * @copyright  based on work by 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/multianswerrgx/questiontype.php');

/**
 * Restore plugin class that provides the necessary information
 * needed to restore the multianswerrgx question type.
 *
 * This class extends the restore_qtype_plugin base class and implements
 * the methods required to restore the multianswerrgx question type
 * from a backup file.
 *
 * @package    qtype_multianswerrgx
 * @subpackage backup-moodle2
 * @category   backup
 * @copyright  2024 Joseph Rézeau <moodle@rezeau.org>
 * @copyright  based on work by 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qtype_multianswerrgx_plugin extends restore_qtype_plugin {

    /**
     * Returns the paths to be handled by the plugin at question level
     */
    protected function define_question_plugin_structure() {
        $paths = [];

        // This qtype uses question_answers, add them.
        $this->add_question_question_answers($paths);

        // Add own qtype stuff.
        $elename = 'multianswerrgx';
        $elepath = $this->get_pathfor('/multianswerrgx');
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths; // And we return the interesting paths.
    }

    /**
     * Process the qtype/multianswerrgx element
     * @param array $data An associative array containing the data for the `multianswerrgx` element.
     *                    It is converted to an object before processing.     *
     * @return void
     */
    public function process_multianswerrgx($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Detect if the question is created or mapped.
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ? true : false;

        // If the question has been created by restore, we need to create its
        // question_multianswerrgx too.
        if ($questioncreated) {
            // Adjust some columns.
            $data->question = $newquestionid;
            // Note: multianswerrgx->sequence is a list of question->id values. We aren't
            // recoding them here (because some questions can be missing yet). Instead
            // we'll perform the recode in the {@see after_execute} method of the plugin
            // that gets executed once all questions have been created.
            // Insert record.
            $newitemid = $DB->insert_record('question_multianswerrgx', $data);
            // Create mapping (need it for after_execute recode of sequence).
            $this->set_mapping('question_multianswerrgx', $oldid, $newitemid);
        }
    }

    /**
     * This method is executed once the whole restore_structure_step
     * this step is part of ({@see restore_create_categories_and_questions})
     * has ended processing the whole xml structure. Its name is:
     * "after_execute_" + connectionpoint ("question")
     *
     * For multianswerrgx qtype we use it to restore the sequence column,
     * containing one list of question ids
     */
    public function after_execute_question() {
        global $DB;
        // Now that all the questions have been restored, let's process
        // the created question_multianswerrgx sequences (list of question ids).
        $rs = $DB->get_recordset_sql("
                SELECT qma.id, qma.sequence
                  FROM {question_multianswerrgx} qma
                  JOIN {backup_ids_temp} bi ON bi.newitemid = qma.question
                 WHERE bi.backupid = ?
                   AND bi.itemname = 'question_created'",
                [$this->get_restoreid()]);
        foreach ($rs as $rec) {
            $sequencearr = preg_split('/,/', $rec->sequence, -1, PREG_SPLIT_NO_EMPTY);
            if (substr_count($rec->sequence, ',') + 1 != count($sequencearr)) {
                $this->task->log('Invalid sequence found in restored multianswerrgx question ' . $rec->id, backup::LOG_WARNING);
            }

            foreach ($sequencearr as $key => $question) {
                $sequencearr[$key] = $this->get_mappingid('question', $question);
            }
            $sequence = implode(',', array_filter($sequencearr));
            $DB->set_field('question_multianswerrgx', 'sequence', $sequence,
                    ['id' => $rec->id]);
            if (!empty($sequence)) {
                // Get relevant data indexed by positionkey from the multianswerrgxs table.
                $wrappedquestions = $DB->get_records_list('question', 'id',
                    explode(',', $sequence), 'id ASC');
                foreach ($wrappedquestions as $wrapped) {
                    if ($wrapped->qtype == 'multichoice') {
                        question_bank::get_qtype($wrapped->qtype)->get_question_options($wrapped);
                        if (isset($wrapped->options->shuffleanswers)) {
                            preg_match('/'.ANSWER_REGEX.'/s', $wrapped->questiontext, $answerregs);
                            if (isset($answerregs[ANSWER_REGEX_ANSWER_TYPE_MULTICHOICE]) &&
                                    $answerregs[ANSWER_REGEX_ANSWER_TYPE_MULTICHOICE] !== '') {
                                $wrapped->options->shuffleanswers = 0;
                                $DB->set_field_select('qtype_multichoice_options', 'shuffleanswers', '0', "id =:select",
                                    ['select' => $wrapped->options->id] );
                            }
                        }
                    }
                }
            }
        }
        $rs->close();
    }

    /**
     * Recode the responses for a given question based on subquestions.
     *
     * This method processes the response for a parent question by iterating through
     * its subquestions and updating the response data accordingly.
     *
     * @param int $questionid The ID of the parent question.
     * @param int $sequencenumber The sequence number of the attempt.
     * @param array $response The original response data to be recoded.
     *
     * @return array The recoded response data.
     */
    public function recode_response($questionid, $sequencenumber, array $response) {
        global $DB;

        $qtypes = $DB->get_records_menu('question', ['parent' => $questionid],
                '', 'id, qtype');

        $sequence = $DB->get_field('question_multianswerrgx', 'sequence',
                ['question' => $questionid]);

        $fakestep = new question_attempt_step_read_only($response);

        foreach (explode(',', $sequence) as $key => $subqid) {
            $i = $key + 1;

            $substep = new question_attempt_step_subquestion_adapter($fakestep, 'sub' . $i . '_');
            $recodedresponse = $this->step->questions_recode_response_data($qtypes[$subqid],
                    $subqid, $sequencenumber, $substep->get_all_data());

            foreach ($recodedresponse as $name => $value) {
                $response[$substep->add_prefix($name)] = $value;
            }
        }

        return $response;
    }

    /**
     * Given one question_states record, return the answer
     * recoded pointing to all the restored stuff for multianswerrgx questions
     *
     * answer is one comma separated list of hypen separated pairs
     * containing sequence (pointing to questions sequence in question_multianswerrgx)
     * and mixed answers. We'll delegate
     * the recoding of answers to the proper qtype
     * @param stdClass $state An object representing the state of the question, including the original answer and
     *                        the question ID.
     *
     * @return string A string representing the recoded answer, formatted as a sequence of `sequenceid-newanswer` pairs
     *                separated by commas.
     */
    public function recode_legacy_state_answer($state) {
        global $DB;
        $answer = $state->answer;
        $resultarr = [];
        // Get sequence of questions.
        $sequence = $DB->get_field('question_multianswerrgx', 'sequence',
                ['question' => $state->question]);
        $sequencearr = explode(',', $sequence);
        // Let's process each pair.
        foreach (explode(',', $answer) as $pair) {
            $pairarr = explode('-', $pair);
            $sequenceid = $pairarr[0];
            $subanswer = $pairarr[1];
            // Calculate the questionid based on sequenceid.
            // Note it is already one *new* questionid that doesn't need mapping.
            $questionid = $sequencearr[$sequenceid - 1];
            // Fetch qtype of the question (needed for delegation).
            $questionqtype = $DB->get_field('question', 'qtype', ['id' => $questionid]);
            // Delegate subanswer recode to proper qtype, faking one question_states record.
            $substate = new stdClass();
            $substate->question = $questionid;
            $substate->answer = $subanswer;
            $newanswer = $this->step->restore_recode_legacy_answer($substate, $questionqtype);
            $resultarr[] = implode('-', [$sequenceid, $newanswer]);
        }
        return implode(',', $resultarr);
    }

}
