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
 * Upgrade library code for the multianswerrgx question type.
 *
 * @package    qtype_multianswerrgx
 * @subpackage multianswerrgx
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class for converting attempt data for multianswerrgx questions when upgrading
 * attempts to the new question engine.
 *
 * This class is used by the code in question/engine/upgrade/upgradelib.php.
 *
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_multianswerrgx_qe2_attempt_updater extends question_qtype_attempt_updater {
    /**
     * Generates a summary of the question.
     *
     * This method converts the question text and its subquestions into a text-based summary.
     *
     * @return string The summarized question text.
     */
    public function question_summary() {
        $summary = $this->to_text($this->question->questiontext);
        foreach ($this->question->options->questions as $i => $subq) {
            switch ($subq->qtype) {
                case 'multichoice':
                    $choices = [];
                    foreach ($subq->options->answers as $ans) {
                        $choices[] = $this->to_text($ans->answer);
                    }
                    $answerbit = '{' . implode('; ', $choices) . '}';
                    break;
                case 'numerical':
                case 'shortanswer':
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
     * Retrieves the correct answers for the question.
     *
     * This method finds the correct answers for each subquestion and formats them for display.
     *
     * @return string The formatted correct answers.
     */
    public function right_answer() {
        $right = [];

        foreach ($this->question->options->questions as $i => $subq) {
            foreach ($subq->options->answers as $ans) {
                if ($ans->fraction > 0.999) {
                    $right[$i] = $ans->answer;
                    break;
                }
            }
        }

        return $this->display_response($right);
    }

    /**
     * Parses an answer string into an array.
     *
     * @param string $answer The answer string to parse.
     * @return array The parsed response.
     */
    public function explode_answer($answer) {
        $response = [];

        foreach (explode(',', $answer) as $part) {
            list($index, $partanswer) = explode('-', $part, 2);
            $response[$index] = str_replace(
                    ['&#0044;', '&#0045;'], [",", "-"], $partanswer);
        }

        return $response;
    }

    /**
     * Formats the response for display.
     *
     * @param array $response The response to format.
     * @return string The formatted response.
     */
    public function display_response($response) {
        $summary = [];
        foreach ($this->question->options->questions as $i => $subq) {
            $a = new stdClass();
            $a->i = $i;
            $a->response = $this->to_text($response[$i]);
            $summary[] = get_string('subqresponse', 'qtype_multianswer', $a);
        }

        return implode('; ', $summary);
    }

    /**
     * Summarizes the response from the state.
     *
     * @param object $state The state containing the response.
     * @return string The summarized response.
     */
    public function response_summary($state) {
        $response = $this->explode_answer($state->answer);
        foreach ($this->question->options->questions as $i => $subq) {
            if ($response[$i] && $subq->qtype == 'multichoice') {
                $response[$i] = $subq->options->answers[$response[$i]]->answer;
            }
        }
        return $this->display_response($response);
    }

    /**
     * Checks if the question was answered.
     *
     * @param object $state The state containing the answer.
     * @return bool True if the question was answered, false otherwise.
     */
    public function was_answered($state) {
        return !empty($state->answer);
    }

    /**
     * Sets data elements for the first step of the attempt.
     *
     * @param object $state The state containing the data.
     * @param array $data The data array to modify.
     */
    public function set_first_step_data_elements($state, &$data) {
        foreach ($this->question->options->questions as $i => $subq) {
            switch ($subq->qtype) {
                case 'multichoice':
                    $data[$this->add_prefix('_order', $i)] =
                            implode(',', array_keys($subq->options->answers));
                    break;
                case 'numerical':
                    $data[$this->add_prefix('_separators', $i)] = '.$,';
                    break;
            }
        }
    }

    /**
     * Supplies missing data for the first step.
     *
     * @param array $data The data array to modify.
     */
    public function supply_missing_first_step_data(&$data) {
    }

    /**
     * Sets data elements for a given step of the attempt.
     *
     * @param object $state The state containing the data.
     * @param array $data The data array to modify.
     */
    public function set_data_elements_for_step($state, &$data) {
        $response = $this->explode_answer($state->answer);
        foreach ($this->question->options->questions as $i => $subq) {
            if (empty($response[$i])) {
                continue;
            }

            switch ($subq->qtype) {
                case 'multichoice':
                    $choices = [];
                    $order = 0;
                    foreach ($subq->options->answers as $ans) {
                        if ($ans->id == $response[$i]) {
                            $data[$this->add_prefix('answer', $i)] = $order;
                        }
                        $order++;
                    }
                    $answerbit = '{' . implode('; ', $choices) . '}';
                    break;
                case 'numerical':
                case 'shortanswer':
                    $data[$this->add_prefix('answer', $i)] = $response[$i];
                    break;
            }
        }
    }

    /**
     * Adds a prefix to a field name based on the index.
     *
     * @param string $field The field name to prefix.
     * @param int $i The index to include in the prefix.
     * @return string The field name with the added prefix.
     */
    public function add_prefix($field, $i) {
        $prefix = 'sub' . $i . '_';
        if (substr($field, 0, 2) === '!_') {
            return '-_' . $prefix . substr($field, 2);
        } else if (substr($field, 0, 1) === '-') {
            return '-' . $prefix . substr($field, 1);
        } else if (substr($field, 0, 1) === '_') {
            return '_' . $prefix . substr($field, 1);
        } else {
            return $prefix . $field;
        }
    }
}
