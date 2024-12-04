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
 * Multianswerrgx question type conversion handler.
 *
 * @package    qtype_multianswerrgx
 * @subpackage multianswerrgx
 * @category   question
 * @copyright  2024 Joseph Rézeau <moodle@rezeau.org>
 * @copyright  based on work by 2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Multianswerrgx (aka embedded, cloze) question type conversion handler.
 */
class moodle1_qtype_multianswerrgx_handler extends moodle1_qtype_handler {
    /**
     * Returns the list of subpaths within a question.
     *
     * This method provides the list of XML subpaths that belong to the multianswerrgx question type.
     *
     * @return array List of subpaths.
     */
    public function get_question_subpaths() {
        return [
            'ANSWERS/ANSWER',
            'MULTIANSWERS/MULTIANSWER',
        ];
    }

    /**
     * Appends the multianswerrgx specific information to the question
     *
     * Note that there is an upgrade step 2008050800 that is not replayed here as I suppose there
     * was an error on restore and the backup file contains correct data. If I'm wrong on this
     * assumption then the parent of the embedded questions could be fixed on conversion in theory
     * (by using a temporary stash that keeps multianswerrgx's id and its sequence) but the category
     * fix would be tricky in XML.
     * @param array $data An associative array containing question data. It should include 'answers' and 'multianswerrgxs' keys.
     * @param array $raw  An associative array containing the raw, unprocessed data. This is currently unused in the method.
     *
     * @return void
     */
    public function process_question(array $data, array $raw) {

        // Convert and write the answers first.
        if (isset($data['answers'])) {
            $this->write_answers($data['answers'], $this->pluginname);
        }

        // Convert and write the multianswerrgx extra fields.
        foreach ($data['multianswerrgxs'] as $multianswerrgxs) {
            foreach ($multianswerrgxs as $multianswerrgx) {
                $this->write_xml('multianswerrgx', $multianswerrgx, ['/multianswerrgx/id']);
            }
        }
    }
}
