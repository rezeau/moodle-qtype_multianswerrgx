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
 * Multianswerrgx question definition class.
 *
 * @package    qtype_multianswerrgx
 * @subpackage multianswerrgx
 * @copyright  2010 Pierre Pichet
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/question/type/questionbase.php');
require_once($CFG->dirroot . '/question/type/shortanswer/question.php');
require_once($CFG->dirroot . '/question/type/numerical/question.php');
require_once($CFG->dirroot . '/question/type/multichoice/question.php');


/**
 * Represents a multianswerrgx question.
 *
 * A multi-answer question is made of of several subquestions of various types.
 * You can think of it as an application of the composite pattern to qusetion
 * types.
 *
 * @copyright  2010 Pierre Pichet
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_multianswerrgx_question extends question_graded_automatically_with_countback {
    /** @var array of question_graded_automatically. */
    public $subquestions = [];

    /**
     * @var array place number => insex in the $subquestions array. Places are
     * numbered from 1.
     */
    public $places;

    /**
     * @var array of strings, one longer than $places, which is achieved by
     * indexing from 0. The bits of question text that go between the subquestions.
     */
    public $textfragments;

    /**
     * Get a question_attempt_step_subquestion_adapter
     * @param question_attempt_step $step the step to adapt.
     * @param int $i the subquestion index.
     * @return question_attempt_step_subquestion_adapter.
     */
    protected function get_substep($step, $i) {
        return new question_attempt_step_subquestion_adapter($step, 'sub' . $i . '_');
    }

    /**
     * Starts a new question attempt.
     *
     * This method initializes a new attempt for the given question step and variant.
     *
     * @param question_attempt_step $step The step object representing the current state of the question attempt.
     * @param int $variant The variant of the question to attempt.
     *
     * @return void
     */
    public function start_attempt(question_attempt_step $step, $variant) {
        foreach ($this->subquestions as $i => $subq) {
            $subq->start_attempt($this->get_substep($step, $i), $variant);
        }
    }

    /**
     * Applies the attempt state to the question.
     *
     * This method updates the question's state based on the provided attempt step.
     *
     * @param question_attempt_step $step The step of the question attempt to apply.
     *
     * @return void
     */
    public function apply_attempt_state(question_attempt_step $step) {
        foreach ($this->subquestions as $i => $subq) {
            $subq->apply_attempt_state($this->get_substep($step, $i));
        }
    }

    /**
     * Validates if the question can be regraded with another version.
     *
     * This method checks whether the current question can be regraded using a different version of the question.
     * It returns a validation message if regrading is not possible, or null if regrading is possible.
     *
     * @param question_definition $otherversion The other version of the question to validate against.
     *
     * @return string|null A string containing the validation message if regrading is not possible,
     * or null if regrading is possible.
     */
    public function validate_can_regrade_with_other_version(question_definition $otherversion): ?string {
        $basemessage = parent::validate_can_regrade_with_other_version($otherversion);
        if ($basemessage) {
            return $basemessage;
        }

        if (count($this->subquestions) != count($otherversion->subquestions)) {
            return get_string('regradeissuenumsubquestionschanged', 'qtype_multianswer');
        }

        foreach ($this->subquestions as $i => $subq) {
            $subqmessage = $subq->validate_can_regrade_with_other_version($otherversion->subquestions[$i]);
            if ($subqmessage) {
                return $subqmessage;
            }
        }

        return null;
    }

    /**
     * Updates the attempt state data for a new version of the question.
     *
     * This method updates the attempt state data when switching to a new version of the question.
     *
     * @param question_attempt_step $oldstep The old step of the question attempt.
     * @param question_definition $oldquestion The old version of the question definition.
     *
     * @return void
     */
    public function update_attempt_state_data_for_new_version(
            question_attempt_step $oldstep, question_definition $oldquestion) {
        parent::update_attempt_state_data_for_new_version($oldstep, $oldquestion);

        $result = [];
        foreach ($this->subquestions as $i => $subq) {
            $substep = $this->get_substep($oldstep, $i);
            $statedata = $subq->update_attempt_state_data_for_new_version(
                    $substep, $oldquestion->subquestions[$i]);
            foreach ($statedata as $name => $value) {
                $result[$substep->add_prefix($name)] = $value;
            }
        }

        return $result;
    }

    /**
     * Retrieves a summary of the question.
     *
     * This method returns a summary or brief description of the question.
     *
     * @return string A summary or brief description of the question.
     */
    public function get_question_summary() {
        $summary = $this->html_to_text($this->questiontext, $this->questiontextformat);
        foreach ($this->subquestions as $i => $subq) {
            switch ($subq->qtype->name()) {
                case 'multichoice':
                    $choices = [];
                    $dummyqa = new question_attempt($subq, $this->contextid);
                    foreach ($subq->get_order($dummyqa) as $ansid) {
                        $choices[] = $this->html_to_text($subq->answers[$ansid]->answer,
                                $subq->answers[$ansid]->answerformat);
                    }
                    $answerbit = '{' . implode('; ', $choices) . '}';
                    break;
                case 'numerical':
                case 'shortanswer':
                case 'regexp':
                    $answerbit = '_____';
                    break;
                default:
                    $answerbit = '{ERR unknown sub-question type}';
            }
            $summary = str_replace('{#' . $i . '}', $answerbit, $summary);
        }
        return $summary;
    }

    /**
     * Calculates the minimum fraction of the total score.
     *
     * This method computes the minimum fraction of the total score based on the `defaultmark` of each subquestion and
     * their respective minimum fractions. It sums the minimum fractions of all subquestions, weighted by their `defaultmark`,
     * and divides this sum by the total `defaultmark` of all subquestions to get the fraction of the total score.
     *
     * @return float The minimum fraction of the total score, represented as a decimal value between 0 and 1.
     */
    public function get_min_fraction() {
        $fractionsum = 0;
        $fractionmax = 0;
        foreach ($this->subquestions as $i => $subq) {
            $fractionmax += $subq->defaultmark;
            $fractionsum += $subq->defaultmark * $subq->get_min_fraction();
        }
        if (empty($fractionsum)) {
            return 0;
        }
        return $fractionsum / (!empty($this->subquestions) ? $fractionmax : 1);
    }

    /**
     * Calculates the maximum fraction of the total score.
     *
     * This method calculates the maximum fraction of the total score based on the `defaultmark` of each subquestion and
     * their respective maximum fractions. It sums the maximum fractions of all subquestions, weighted by their `defaultmark`,
     * and divides this sum by the total `defaultmark` of all subquestions to get the fraction of the total score.
     *
     * @return float The maximum fraction of the total score, represented as a decimal value between 0 and 1.
     */
    public function get_max_fraction() {
        $fractionsum = 0;
        $fractionmax = 0;
        foreach ($this->subquestions as $i => $subq) {
            $fractionmax += $subq->defaultmark;
            $fractionsum += $subq->defaultmark * $subq->get_max_fraction();
        }
        if (empty($fractionsum)) {
            return 1;
        }
        return $fractionsum / (!empty($this->subquestions) ? $fractionmax : 1);
    }

    /**
     * Retrieves the expected data structure for the question.
     *
     * This method constructs an associative array where each key represents a data name (with an added prefix) and each value
     * represents the expected data type. It iterates through the subquestions, retrieves their expected data, and applies
     * specific handling based on the type and layout of the subquestions
     * (e.g., handling multichoice questions with dropdown layout).
     *
     * @return array An associative array where:
     *               - The keys are data names with prefixes added.
     *               - The values are data types, represented by constants such as PARAM_RAW or other type identifiers.
     */
    public function get_expected_data() {
        $expected = [];
        foreach ($this->subquestions as $i => $subq) {
            $substep = $this->get_substep(null, $i);
            foreach ($subq->get_expected_data() as $name => $type) {
                if ($subq->qtype->name() == 'multichoice' &&
                        $subq->layout == qtype_multichoice_base::LAYOUT_DROPDOWN) {
                    // Hack or MC inline does not work.
                    $expected[$substep->add_prefix($name)] = PARAM_RAW;
                } else {
                    $expected[$substep->add_prefix($name)] = $type;
                }
            }
        }
        return $expected;
    }

    /**
     * Retrieves the correct responses for the question.
     *
     * This method constructs and returns an associative array of correct responses for the question.
     * For each subquestion, it calls `get_correct_response()` to get the correct responses, prefixes each response name
     * with a relevant identifier, and collects them in an array.
     *
     * @return array An associative array where:
     *               - The keys are the names of the correct responses, with prefixes added by the `get_substep()` method.
     *               - The values are the types associated with the correct responses.
     */
    public function get_correct_response() {
        $right = [];
        foreach ($this->subquestions as $i => $subq) {
            $substep = $this->get_substep(null, $i);
            foreach ($subq->get_correct_response() as $name => $type) {
                $right[$substep->add_prefix($name)] = $type;
            }
        }
        return $right;
    }

    /**
     * Prepares simulated post data for submission.
     *
     * This method generates an associative array of post data for a simulated submission.
     * It iterates over each subquestion, retrieves its simulated post data, prefixes the names of the data fields
     * with identifiers, and aggregates them into a single post data array.
     *
     * @param array $simulatedresponse An array of simulated responses for each subquestion.
     *  Each entry in this array should be formatted according to the expected input for that subquestion.
     *
     * @return array An associative array where:
     *               - The keys are the names of the post data fields with prefixes added by the `get_substep()` method.
     *               - The values are the corresponding simulated response values.
     */
    public function prepare_simulated_post_data($simulatedresponse) {
        $postdata = [];
        foreach ($this->subquestions as $i => $subq) {
            $substep = $this->get_substep(null, $i);
            foreach ($subq->prepare_simulated_post_data($simulatedresponse[$i]) as $name => $value) {
                $postdata[$substep->add_prefix($name)] = $value;
            }
        }
        return $postdata;
    }

    /**
     * Retrieves student response values for simulation based on post data.
     *
     * This method processes the provided post data to generate simulated responses for each subquestion.
     * For each subquestion, it filters the post data using the `filter_array` method, retrieves the simulated responses,
     * and formats them with a key that combines the subquestion index and the response key.
     * The final array is sorted by the keys and returned.
     *
     * @param array $postdata An associative array of post data where:
     *                        - The keys are data field names with prefixes.
     *                        - The values are the corresponding posted values.
     *
     * @return array An associative array where:
     *               - The keys are formatted as `index.responsekey`, combining the subquestion index and the response key.
     *               - The values are the simulated response values for each subquestion.
     */
    public function get_student_response_values_for_simulation($postdata) {
        $simulatedresponse = [];
        foreach ($this->subquestions as $i => $subq) {
            $substep = $this->get_substep(null, $i);
            $subqpostdata = $substep->filter_array($postdata);
            $subqsimulatedresponse = $subq->get_student_response_values_for_simulation($subqpostdata);
            foreach ($subqsimulatedresponse as $subresponsekey => $responsevalue) {
                $simulatedresponse[$i.'.'.$subresponsekey] = $responsevalue;
            }
        }
        ksort($simulatedresponse);
        return $simulatedresponse;
    }

    /**
     * Checks if the provided response is complete for all subquestions.
     *
     * This method verifies whether the response array includes complete responses for every subquestion.
     * It processes each subquestion by filtering the response data relevant to that subquestion and then checks if the response
     * is complete using the `is_complete_response` method of the subquestion. If any subquestion's response is found to be
     * incomplete, the method returns `false`. If all subquestions are complete, it returns `true`.
     *
     * @param array $response An associative array where:
     *                        - The keys represent data field names related to the subquestions.
     *                        - The values represent the response data.
     *
     * @return bool `true` if all subquestions have complete responses; `false` if any subquestion's response is incomplete.
     */
    public function is_complete_response(array $response) {
        foreach ($this->subquestions as $i => $subq) {
            $substep = $this->get_substep(null, $i);
            if (!$subq->is_complete_response($substep->filter_array($response))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Checks if the provided response is gradable for any subquestion.
     *
     * This method evaluates whether the given response array is gradable for at least one of the subquestions.
     * It iterates through each subquestion, filters the relevant portion of the response array using the `filter_array` method,
     * and checks if the filtered response is gradable using the `is_gradable_response` method of the subquestion.
     * If any subquestion's response is gradable, the method returns `true`. If none of the subquestions have a gradable response,
     * it returns `false`.
     *
     * @param array $response An associative array representing the response data for the question.
     *   The array should include data fields related to the subquestions, with keys that match the expected format.
     *
     * @return bool `true` if at least one subquestion has a gradable response; `false` otherwise.
     */
    public function is_gradable_response(array $response) {
        foreach ($this->subquestions as $i => $subq) {
            $substep = $this->get_substep(null, $i);
            if ($subq->is_gradable_response($substep->filter_array($response))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if the previous response is the same as the new response for all subquestions.
     *
     * This method compares the previous response with the new response for each subquestion to determine if they are identical.
     * It filters the responses for each subquestion using the `filter_array` method and then checks if the filtered previous
     * and new responses are the same using the `is_same_response` method of the subquestion.
     * If all subquestions have the same previous and new responses, the method returns `true`. If any subquestion's responses
     * differ, it returns `false`.
     *
     * @param array $prevresponse An associative array representing the previous response data for the question.
     *   The array should include data fields related to the subquestions, with keys that match the expected format.
     * @param array $newresponse An associative array representing the new response data for the question.
     *   The array should include data fields related to the subquestions, with keys that match the expected format.
     *
     * @return bool `true` if the previous response is the same as the new response for all subquestions; `false` otherwise.
     */
    public function is_same_response(array $prevresponse, array $newresponse) {
        foreach ($this->subquestions as $i => $subq) {
            $substep = $this->get_substep(null, $i);
            if (!$subq->is_same_response($substep->filter_array($prevresponse),
                    $substep->filter_array($newresponse))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Retrieves a validation error message based on the provided response.
     *
     * This method checks if the response data is complete by calling `is_complete_response`.
     * If the response is complete, it returns an empty string, signifying no validation errors.
     * If the response is not complete, it returns a specific error message indicating
     * that all parts of the question need to be answered.
     *
     * @param array $response An associative array where:
     *                        - The keys represent data field names related to the subquestions.
     *                        - The values represent the response data.
     *
     * @return string An empty string if the response is complete;
     * otherwise, a validation error message requesting that all parts be answered.
     */
    public function get_validation_error(array $response) {
        if ($this->is_complete_response($response)) {
            return '';
        }
        return get_string('pleaseananswerallparts', 'qtype_multianswer');
    }

    /**
     * Used by grade_response to combine the states of the subquestions.
     * The combined state is accumulates in $overallstate. That will be right
     * if all the separate states are right; and wrong if all the separate states
     * are wrong, otherwise, it will be partially right.
     * @param question_state $overallstate the result so far.
     * @param question_state $newstate the new state to add to the combination.
     * @return question_state the new combined state.
     */
    protected function combine_states($overallstate, $newstate) {
        if (is_null($overallstate)) {
            return $newstate;
        } else if ($overallstate == question_state::$gaveup &&
                $newstate == question_state::$gaveup) {
            return question_state::$gaveup;
        } else if ($overallstate == question_state::$gaveup &&
                $newstate == question_state::$gradedwrong) {
            return question_state::$gradedwrong;
        } else if ($overallstate == question_state::$gradedwrong &&
                $newstate == question_state::$gaveup) {
            return question_state::$gradedwrong;
        } else if ($overallstate == question_state::$gradedwrong &&
                $newstate == question_state::$gradedwrong) {
            return question_state::$gradedwrong;
        } else if ($overallstate == question_state::$gradedright &&
                $newstate == question_state::$gradedright) {
            return question_state::$gradedright;
        } else {
            return question_state::$gradedpartial;
        }
    }

    /**
     * Grades the provided response and calculates the overall score.
     *
     * This method evaluates each subquestion's response, calculates the total score, and determines the overall state.
     * It filters the response data for each subquestion and grades it using the `grade_response` method of the subquestion.
     * If a subquestion is not gradable, the overall state is updated to `gaveup`. If it is gradable, the score is calculated
     * and added to the total score, and the overall state is updated accordingly.
     *
     * The overall score is computed as the ratio of the total score achieved to the maximum possible score.
     * If there are no subquestions,
     * the method returns `null` for the score and the overall state (or `finished` if the state is `null`).
     *
     * @param array $response An associative array where:
     * - The keys represent data field names related to the subquestions.
     * - The values represent the response data.
     *
     * @return array An array with two elements:
     * - A float representing the overall score as a fraction of the maximum score. It is `null` if there are no subquestions.
     * - The overall state of the response, indicating the result of the grading process.
     */
    public function grade_response(array $response) {
        $overallstate = null;
        $fractionsum = 0;
        $fractionmax = 0;
        foreach ($this->subquestions as $i => $subq) {
            $fractionmax += $subq->defaultmark;
            $substep = $this->get_substep(null, $i);
            $subresp = $substep->filter_array($response);
            if (!$subq->is_gradable_response($subresp)) {
                $overallstate = $this->combine_states($overallstate, question_state::$gaveup);
            } else {
                list($subfraction, $newstate) = $subq->grade_response($subresp);
                $fractionsum += $subfraction * $subq->defaultmark;
                $overallstate = $this->combine_states($overallstate, $newstate);
            }
        }
        if (empty($fractionmax)) {
            return [null, $overallstate ?? question_state::$finished];
        }
        return [$fractionsum / $fractionmax, $overallstate];
    }

    /**
     * Clears incorrect responses from the provided response array.
     *
     * This method processes each subquestion to determine if it is correctly answered. For each subquestion:
     * - The method filters the response data specific to that subquestion.
     * - It grades the filtered response using `grade_response`.
     * - If the subquestion's response is not graded as correct (`question_state::$gradedright`), it updates the response array:
     *   - For "multichoice" questions with vertical or horizontal layouts, incorrect answers are marked with `'-1'`.
     *   - For other types of questions, incorrect answers are cleared by setting them to an empty string.
     *
     * @param array $response An associative array where:
     * - The keys represent data field names related to the subquestions.
     * - The values represent the response data.
     *
     * @return array The updated response array with incorrect answers cleared, ready for further processing or validation.
     */
    public function clear_wrong_from_response(array $response) {
        foreach ($this->subquestions as $i => $subq) {
            $substep = $this->get_substep(null, $i);
            $subresp = $substep->filter_array($response);
            list($subfraction, $newstate) = $subq->grade_response($subresp);
            if ($newstate != question_state::$gradedright) {
                foreach ($subresp as $ind => $resp) {
                    if ($subq->qtype == 'multichoice' && ($subq->layout == qtype_multichoice_base::LAYOUT_VERTICAL
                            || $subq->layout == qtype_multichoice_base::LAYOUT_HORIZONTAL)) {
                        $response[$substep->add_prefix($ind)] = '-1';
                    } else {
                        $response[$substep->add_prefix($ind)] = '';
                    }
                }
            }
        }
        return $response;
    }

    /**
     * Counts the number of subquestions answered correctly.
     *
     * Evaluates each subquestion in the response to determine if it is answered correctly. Returns the count of correct answers
     * and the total number of subquestions.
     *
     * @param array $response The response data for the question, including all subquestions.
     *
     * @return array An array with:
     *               - The number of subquestions answered correctly.
     *               - The total number of subquestions.
     */
    public function get_num_parts_right(array $response) {
        $numright = 0;
        foreach ($this->subquestions as $i => $subq) {
            $substep = $this->get_substep(null, $i);
            $subresp = $substep->filter_array($response);
            list($subfraction, $newstate) = $subq->grade_response($subresp);
            if ($newstate == question_state::$gradedright) {
                $numright += 1;
            }
        }
        return [$numright, count($this->subquestions)];
    }

    /**
     * Computes the final grade based on responses and penalties for multiple attempts.
     *
     * Calculates the weighted grade for each subquestion considering penalties for multiple attempts.
     * The final grade is computed by summing the adjusted scores for each subquestion and dividing by the total possible marks.
     *
     * @param array $responses An array of responses, where each element represents a response for a subquestion.
     * @param int $totaltries The number of attempts made by the user.
     *
     * @return float The final grade as a fraction of the total marks, ranging from 0 to 1.
     */
    public function compute_final_grade($responses, $totaltries) {
        $fractionsum = 0;
        $fractionmax = 0;
        foreach ($this->subquestions as $i => $subq) {
            $fractionmax += $subq->defaultmark;

            $lastresponse = [];
            $lastchange = 0;
            $subfraction = 0;
            foreach ($responses as $responseindex => $response) {
                $substep = $this->get_substep(null, $i);
                $subresp = $substep->filter_array($response);
                if ($subq->is_same_response($lastresponse, $subresp)) {
                    continue;
                }
                $lastresponse = $subresp;
                $lastchange = $responseindex;
                list($subfraction, $newstate) = $subq->grade_response($subresp);
            }

            $fractionsum += $subq->defaultmark * max(0, $subfraction - $lastchange * $this->penalty);
        }

        return $fractionsum / $fractionmax;
    }

    /**
     * Summarizes the responses for all subquestions into a single string.
     *
     * Iterates through each subquestion, summarizes the response for each, and formats it into a string.
     * The summaries for all subquestions are combined into a single string, separated by semicolons.
     *
     * @param array $response An associative array of responses for the question, including data for each subquestion.
     *
     * @return string A summary of responses for all subquestions, with each subquestion's summary separated by a semicolon.
     */
    public function summarise_response(array $response) {
        $summary = [];
        foreach ($this->subquestions as $i => $subq) {
            $substep = $this->get_substep(null, $i);
            $a = new stdClass();
            $a->i = $i;
            $a->response = $subq->summarise_response($substep->filter_array($response));
            $summary[] = get_string('subqresponse', 'qtype_multianswer', $a);
        }

        return implode('; ', $summary);
    }

    /**
     * Checks access permissions for file areas.
     *
     * - Always allows access to 'answer' files.
     * - Allows access to 'answerfeedback' files if feedback is enabled.
     * - Delegates 'hint' files access check to another method.
     * - Uses the parent method for other cases.
     *
     * @param object $qa The question attempt context.
     * @param object $options Access options.
     * @param string $component Component name.
     * @param string $filearea File area name.
     * @param array $args Additional arguments.
     * @param bool $forcedownload Whether to force download.
     *
     * @return bool True if access is allowed, false otherwise.
     */
    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload) {
        if ($component == 'question' && $filearea == 'answer') {
            return true;

        } else if ($component == 'question' && $filearea == 'answerfeedback') {
            // Full logic to control which feedbacks a student can see is too complex.
            // Just allow access to all images. There is a theoretical chance the
            // students could see files they are not meant to see by guessing URLs,
            // but it is remote.
            return $options->feedback;

        } else if ($component == 'question' && $filearea == 'hint') {
            return $this->check_hint_file_access($qa, $options, $args);

        } else {
            return parent::check_file_access($qa, $options, $component, $filearea,
                    $args, $forcedownload);
        }
    }

    /**
     * Return the question settings that define this question as structured data.
     *
     * @param question_attempt $qa the current attempt for which we are exporting the settings.
     * @param question_display_options $options the question display options which say which aspects of the question
     * should be visible.
     * @return mixed structure representing the question settings. In web services, this will be JSON-encoded.
     */
    public function get_question_definition_for_external_rendering00(question_attempt $qa, question_display_options $options) {
        // Empty implementation for now in order to avoid debugging in core questions (generated in the parent class),
        // ideally, we should return as much as settings as possible (depending on the state and display options).

        return null;
    }
}
