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


/**
 * Restore plugin class that provides the necessary information
 * needed to restore one multianswerrgx qtype plugin.
 *
 * @package    qtype_multianswerrgx
 * @subpackage backup-moodle2
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_qtype_multianswerrgx_plugin extends backup_qtype_plugin {

    /**
     * Returns the qtype information to attach to question element
     */
    protected function define_question_plugin_structure() {

        // Define the virtual plugin element with the condition to fulfill.
        $plugin = $this->get_plugin_element(null, '../../qtype', 'multianswerrgx');

        // Create one standard named plugin element (the visible container).
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Connect the visible container ASAP.
        $plugin->add_child($pluginwrapper);

        // This qtype uses standard question_answers, add them here
        // to the tree before any other information that will use them.
        $this->add_question_question_answers($pluginwrapper);

        // Now create the qtype own structures.
        $multianswerrgx = new backup_nested_element('multianswerrgx', ['id'], [
            'question', 'sequence']);

        // Now the own qtype tree.
        $pluginwrapper->add_child($multianswerrgx);

        // Set source to populate the data.
        $multianswerrgx->set_source_table('question_multianswerrgx',
                ['question' => backup::VAR_PARENTID]);

        // Don't need to annotate ids nor files.

        return $plugin;
    }
}
