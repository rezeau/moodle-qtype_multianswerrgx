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
 * Defines the editing form for the multi-answer question type.
 * Hacked version by Joseph Rézeau to include the REGEXP question type.
 * Version dated 18:46 29/06/2024
 *
 * @package    qtype_multianswerrgx
 * @copyright  2024 Joseph Rézeau <moodle@rezeau.org>
 * @copyright  based on work by 2007 Jamie Pratt me@jamiep.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/numerical/questiontype.php');

/**
 * Form for editing multi-answer questions.
 *
 * @copyright  2024 Joseph Rézeau <moodle@rezeau.org>
 * @copyright  based on work by 2007 Jamie Pratt me@jamiep.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class qtype_multianswerrgx_edit_form extends question_edit_form {

    /** @var $questiondisplay will contain the qtype_multianswerrgx_extract_question from the questiontext. */
    public $questiondisplay;
    /** @var $savedquestiondisplay will contain the qtype_multianswerrgx_extract_question
     from the questiontext in database. */
    public $savedquestion;
    /** @var $savedquestiondisplay */
    public $savedquestiondisplay;
    /** @var bool this question is used in quiz */
    public $usedinquiz = false;
    /** @var bool the qtype has been changed */
    public $qtypechange = false;
    /** @var int number of questions that have been deleted */
    public $negativediff = 0;
    /** @var int number of quiz that used this question */
    public $nbofquiz = 0;
    /** @var int number of attempts that used this question */
    public $nbofattempts = 0;
    /** @var int @confirm */
    public $confirm = 0;
    /** @var bool @reload */
    public $reload = false;
    /** @var qtype_numerical_answer_processor used when validating numerical answers. */
    protected $ap = null;
    /** @var bool */
    public $regenerate;
    /** @var array */
    public $editas;

    /**
     * Function construct description.
     *
     * @param string  $submiturl    The URL to submit.
     * @param string  $question     The question text.
     * @param string  $category     The category name.
     * @param array   $contexts     The contexts array.
     * @param bool    $formeditable Whether the form is editable.
     */
    public function __construct($submiturl, $question, $category, $contexts, $formeditable = true) {
        $this->regenerate = true;
        $this->reload = optional_param('reload', false, PARAM_BOOL);

        $this->usedinquiz = false;

        if (isset($question->id) && $question->id != 0) {
            // TODO MDL-43779 should not have quiz-specific code here.
            $this->savedquestiondisplay = fullclone($question);
            $questiondata = question_bank::load_question($question->id);
            $this->nbofquiz = \qbank_usage\helper::get_question_entry_usage_count($questiondata);
            $this->usedinquiz = $this->nbofquiz > 0;
            $this->nbofattempts = \qbank_usage\helper::get_question_attempts_count_in_quiz((int)$question->id);
        }

        parent::__construct($submiturl, $question, $category, $contexts, $formeditable);
    }

    /**
     * Provides the inner definition for the form.
     *
     * This method is responsible for adding the inner elements to the form.
     *
     * @param mixed $mform The form object to which elements will be added.
     *
     * @return void
     */
    protected function definition_inner($mform) {
        global $USER;
        $mform = $this->form_setup($mform);
        $defaultaddclozegaps = get_config('qtype_multianswerrgx', 'addclozegaps');
        $currenteditor = get_user_preferences('htmleditor', 'tiny', $USER);
        $mform->addElement('hidden', 'reload', 1);
        $mform->setType('reload', PARAM_INT);

        // Remove meaningless defaultmark field.
        $mform->removeElement('defaultmark');
        $this->confirm = optional_param('confirm', false, PARAM_BOOL);

        $insertbefore = 'status'; // Moodle 4.x.
        if ($defaultaddclozegaps) {
            if ($currenteditor === 'tiny') {
                $buttonarray = [];
                $buttonarray[] = $mform->createElement('button', 'add_gaps_5', get_string('addclozegaps5', 'qtype_multianswerrgx'));
                $buttonarray[] = $mform->createElement('button', 'add_gaps_9', get_string('addclozegaps9', 'qtype_multianswerrgx'));
                $buttonarray[] = $mform->createElement('button', 'remove_gaps_button',
                get_string('removegaps', 'qtype_multianswerrgx'));
                $buttonarray[] = $mform->createElement('checkbox', 'skip_caps_words',
                get_string('skipcapswords', 'qtype_multianswerrgx'));

                // Insert the button group after the 'questiontext' field.
                $mform->insertElementBefore($mform->createElement('group', 'button_group',
                get_string('addgapslabel', 'qtype_multianswerrgx'), $buttonarray, ' '), $insertbefore);
                $mform->addHelpButton('button_group', 'addgapslabel', 'qtype_multianswerrgx');
            } else {
                $mform->insertElementBefore($mform->createElement('group', 'button_group',
                get_string('addgapslabelatto', 'qtype_multianswerrgx'), '', ' '), $insertbefore);
                $mform->addHelpButton('button_group', 'addgapslabelatto', 'qtype_multianswerrgx');
            }
        };
        // Display the questions from questiontext.
        if ($questiontext = optional_param_array('questiontext', false, PARAM_RAW)) {
            $this->questiondisplay = fullclone(qtype_multianswerrgx_extract_question($questiontext));

        } else {
            if (!$this->reload && !empty($this->savedquestiondisplay->id)) {
                // Use database data as this is first pass
                // question->id == 0 so no stored datasets.
                $this->questiondisplay = fullclone($this->savedquestiondisplay);
                foreach ($this->questiondisplay->options->questions as $subquestion) {
                    if (!empty($subquestion)) {
                        $subquestion->answer = [''];
                        foreach ($subquestion->options->answers as $ans) {
                            $subquestion->answer[] = $ans->answer;
                        }
                    }
                }
            } else {
                $this->questiondisplay = "";
            }
        }

        if (isset($this->savedquestiondisplay->options->questions) &&
                is_array($this->savedquestiondisplay->options->questions)) {
            $countsavedsubquestions = 0;
            foreach ($this->savedquestiondisplay->options->questions as $subquestion) {
                if (!empty($subquestion)) {
                    $countsavedsubquestions++;
                }
            }
        } else {
            $countsavedsubquestions = 0;
        }
        if ($this->reload) {
            if (isset($this->questiondisplay->options->questions) &&
                    is_array($this->questiondisplay->options->questions)) {
                $countsubquestions = 0;
                foreach ($this->questiondisplay->options->questions as $subquestion) {
                    if (!empty($subquestion)) {
                        $countsubquestions++;
                    }
                }
            } else {
                $countsubquestions = 0;
            }
        } else {
            $countsubquestions = $countsavedsubquestions;
        }
        $mform->addElement('submit', 'analyzequestion',
                get_string('decodeverifyquestiontext', 'qtype_multianswer'));

        $mform->registerNoSubmitButton('analyzequestion');
        if ($this->reload) {
            for ($sub = 1; $sub <= $countsubquestions; $sub++) {

                if (isset($this->questiondisplay->options->questions[$sub]->qtype)) {
                    $this->editas[$sub] = $this->questiondisplay->options->questions[$sub]->qtype;
                } else {
                    $this->editas[$sub] = optional_param('sub_'.$sub.'_qtype', 'unknown type', PARAM_PLUGIN);
                }

                $storemess = '';
                if (isset($this->savedquestiondisplay->options->questions[$sub]->qtype) &&
                        $this->savedquestiondisplay->options->questions[$sub]->qtype !=
                                $this->questiondisplay->options->questions[$sub]->qtype &&
                        $this->savedquestiondisplay->options->questions[$sub]->qtype != 'subquestion_replacement') {
                    $this->qtypechange = true;
                    $storemess = ' ' . html_writer::tag('span', get_string(
                            'storedqtype', 'qtype_multianswer', question_bank::get_qtype_name(
                                    $this->savedquestiondisplay->options->questions[$sub]->qtype)),
                            ['class' => 'error']);
                }
                    $mform->addElement('header', 'subhdr'.$sub, get_string('questionno', 'question',
                       '{#'.$sub.'}').'&nbsp;'.question_bank::get_qtype_name(
                            $this->questiondisplay->options->questions[$sub]->qtype).$storemess);

                $mform->addElement('static', 'sub_'.$sub.'_questiontext',
                        get_string('questiondefinition', 'qtype_multianswer'));

                if (isset ($this->questiondisplay->options->questions[$sub]->questiontext)) {
                    $mform->setDefault('sub_'.$sub.'_questiontext',
                            $this->questiondisplay->options->questions[$sub]->questiontext['text']);
                }

                $mform->addElement('static', 'sub_'.$sub.'_defaultmark',
                        get_string('defaultmark', 'question'));
                $mform->setDefault('sub_'.$sub.'_defaultmark',
                        $this->questiondisplay->options->questions[$sub]->defaultmark);

                if ($this->questiondisplay->options->questions[$sub]->qtype == 'shortanswer'
                    || $this->questiondisplay->options->questions[$sub]->qtype == 'regexp') {
                    $mform->addElement('static', 'sub_'.$sub.'_usecase',
                            get_string('casesensitive', 'qtype_shortanswer'));
                }

                if ($this->questiondisplay->options->questions[$sub]->qtype == 'regexp') {
                    $answers = $this->questiondisplay->options->questions[$sub];
                    // Initialize a new array to store the answers and fractions.
                    $newarray = [];

                    // Loop through the answers and fractions and combine them.
                    foreach ($answers->answer as $index => $answer) {
                         // Only include if fraction is not zero.
                        if ($index !== 0 && $answers->fraction[$index] != 0) {
                            $newarray[] = [
                                "answer" => $answer,
                                "fraction" => $answers->fraction[$index],
                            ];
                        }
                    }
                    $alternateq = $this->get_alternateanswers($newarray);
                }
                if ($this->questiondisplay->options->questions[$sub]->qtype == 'multichoice') {
                    $mform->addElement('static', 'sub_'.$sub.'_layout',
                            get_string('layout', 'qtype_multianswer'));
                    $mform->addElement('static', 'sub_'.$sub.'_shuffleanswers',
                            get_string('shuffleanswers', 'qtype_multichoice'));
                }
                $answer = $this->questiondisplay->options->questions[$sub];
                $alternateqa = [];
                foreach ($answer->answer as $key => $ans) {
                    $mform->addElement('html', '<hr />');
                    if ($this->questiondisplay->options->questions[$sub]->qtype == 'regexp' && (count($alternateq) !== 0 )) {
                        if (!empty($alternateq[$key]) ) {
                            $alternateqa = $alternateq[$key];
                        };
                        $ans0 = $ans;
                        $ans = has_permutations($ans);
                        $mform->addElement('static', 'sub_'.$sub.'_answer['.$key.']', get_string('answer', 'question').' '
                            .($key + 1).' ('.($answer->fraction[$key] * 100).'%)', $ans);
                        if ($key !== 0 && $ans === $alternateqa['regexp']) {
                            if ($ans !== $ans0) {
                                $mform->addElement('html', '<div class="developedanswersrgx">');
                                $mform->addElement('static', '', get_string('developedanswer', 'qtype_multianswerrgx')
                                    .' ', $alternateqa['regexp']);
                                $mform->addElement('html', '</div>');
                            }
                            if (count($alternateqa['answers']) > 1) {
                                $mform->addElement('static', '', get_string('alternativecorrectanswers', 'qtype_multianswerrgx'));
                                $list = '';
                                $mform->addElement('html', '<div class="alternateanswersrgx">');
                                foreach ($alternateqa['answers'] as $alternate) {
                                    $list .= '<li>'.$alternate.'</li>';
                                }
                                $mform->addElement('static', 'alternateanswersrgx', '', '<ul>'.$list.'</ul>');
                                $mform->addElement('html', '</div>');
                            }
                        }
                    } else {
                        $mform->addElement('static', 'sub_'.$sub.'_answer['.$key.']',
                            get_string('answer', 'question'));

                        if ($this->questiondisplay->options->questions[$sub]->qtype == 'numerical' &&
                                $key == 0) {
                            $mform->addElement('static', 'sub_'.$sub.'_tolerance['.$key.']',
                                    get_string('acceptederror', 'qtype_numerical'));
                        }

                        $mform->addElement('static', 'sub_'.$sub.'_fraction['.$key.']',
                                get_string('gradenoun'));
                    }
                    $mform->addElement('static', 'sub_'.$sub.'_feedback['.$key.']',
                            get_string('feedback', 'question'));
                }
            }

            $this->negativediff = $countsavedsubquestions - $countsubquestions;
            if (($this->negativediff > 0) ||$this->qtypechange ||
                    ($this->usedinquiz && $this->negativediff != 0)) {
                $mform->addElement('header', 'additemhdr',
                        get_string('warningquestionmodified', 'qtype_multianswer'));
            }
            if ($this->negativediff > 0) {
                $mform->addElement('static', 'alert1', "<strong>".
                        get_string('questiondeleted', 'qtype_multianswer')."</strong>",
                        get_string('questionsless', 'qtype_multianswer', $this->negativediff));
            }
            if ($this->qtypechange) {
                $mform->addElement('static', 'alert1', "<strong>".
                        get_string('questiontypechanged', 'qtype_multianswer')."</strong>",
                        get_string('questiontypechangedcomment', 'qtype_multianswer'));
            }
        }
        if ($this->usedinquiz) {
            if ($this->negativediff < 0) {
                $diff = $countsubquestions - $countsavedsubquestions;
                $mform->addElement('static', 'alert1', "<strong>".
                        get_string('questionsadded', 'qtype_multianswer')."</strong>",
                        "<strong>".get_string('questionsmore', 'qtype_multianswer', $diff).
                        "</strong>");
            }
            $a = new stdClass();
            $a->nb_of_quiz = $this->nbofquiz;
            $a->nb_of_attempts = $this->nbofattempts;
            $mform->addElement('header', 'additemhdr2',
                    get_string('questionusedinquiz', 'qtype_multianswer', $a));
            $mform->addElement('static', 'alertas',
                    get_string('youshouldnot', 'qtype_multianswer'));
        }
        if (($this->negativediff > 0 || $this->usedinquiz &&
                ($this->negativediff > 0 || $this->negativediff < 0 || $this->qtypechange)) &&
                        $this->reload) {
            $mform->addElement('header', 'additemhdr',
                    get_string('questionsaveasedited', 'qtype_multianswer'));
            $mform->addElement('checkbox', 'confirm', '',
                    get_string('confirmquestionsaveasedited', 'qtype_multianswer'));
            $mform->setDefault('confirm', 0);
        } else {
            $mform->addElement('hidden', 'confirm', 0);
            $mform->setType('confirm', PARAM_BOOL);
        }

        $this->add_interactive_settings(true, true);
    }
    /**
     * Sets the data for the question.
     *
     * This method sets or updates the data related to a question.
     *
     * @param Question $question The data for the question.
     *
     * @return void
     */
    public function set_data($question) {
        global $DB;
        $defaultvalues = [];
        if (isset($question->id) && $question->id && $question->qtype &&
                $question->questiontext) {

            foreach ($question->options->questions as $key => $wrapped) {
                if (!empty($wrapped)) {
                    // The old way of restoring the definitions is kept to gradually
                    // update all multianswerrgx questions.
                    if (empty($wrapped->questiontext)) {
                        $parsableanswerdef = '{' . $wrapped->defaultmark . ':';
                        switch ($wrapped->qtype) {
                            case 'multichoice':
                                $parsableanswerdef .= 'MULTICHOICE:';
                                break;
                            case 'shortanswer':
                                $parsableanswerdef .= 'SHORTANSWER:';
                                break;
                            case 'regexp':
                                $parsableanswerdef .= 'REGEXP:';
                                break;
                            case 'numerical':
                                $parsableanswerdef .= 'NUMERICAL:';
                                break;
                            case 'subquestion_replacement':
                                continue 2;
                            default:
                                throw new \moodle_exception('unknownquestiontype', 'question', '',
                                        $wrapped->qtype);
                        }
                        $separator = '';
                        foreach ($wrapped->options->answers as $subanswer) {
                            $parsableanswerdef .= $separator
                                . '%' . round(100 * $subanswer->fraction) . '%';
                            if (is_array($subanswer->answer)) {
                                $parsableanswerdef .= $subanswer->answer['text'];
                            } else {
                                $parsableanswerdef .= $subanswer->answer;
                            }
                            if (!empty($wrapped->options->tolerance)) {
                                // Special for numerical answers.
                                $parsableanswerdef .= ":{$wrapped->options->tolerance}";
                                // We only want tolerance for the first alternative, it will
                                // be applied to all of the alternatives.
                                unset($wrapped->options->tolerance);
                            }
                            if ($subanswer->feedback) {
                                $parsableanswerdef .= "#{$subanswer->feedback}";
                            }
                            $separator = '~';
                        }
                        $parsableanswerdef .= '}';
                        // Fix the questiontext fields of old questions.
                        $DB->set_field('question', 'questiontext', $parsableanswerdef,
                                ['id' => $wrapped->id]);
                    } else {
                        $parsableanswerdef = str_replace('&#', '&\#', $wrapped->questiontext);
                    }
                    $question->questiontext = str_replace("{#$key}", $parsableanswerdef,
                            $question->questiontext);
                }
            }
        }

        // Set default to $questiondisplay questions elements.
        if ($this->reload) {
            if (isset($this->questiondisplay->options->questions)) {
                $subquestions = fullclone($this->questiondisplay->options->questions);
                if (count($subquestions)) {
                    $sub = 1;
                    foreach ($subquestions as $subquestion) {
                        $prefix = 'sub_'.$sub.'_';

                        // Validate parameters.
                        $answercount = 0;
                        $maxgrade = false;
                        $maxfraction = -1;
                        $errortext = '';
                        if ($subquestion->qtype == 'shortanswer' || $subquestion->qtype == 'regexp') {
                            switch ($subquestion->usecase) {
                                case '1':
                                    $defaultvalues[$prefix.'usecase'] =
                                            get_string('caseyes', 'qtype_shortanswer');
                                    break;
                                case '0':
                                default :
                                    $defaultvalues[$prefix.'usecase'] =
                                            get_string('caseno', 'qtype_shortanswer');
                            }
                        }

                        if ($subquestion->qtype == 'multichoice') {
                            $defaultvalues[$prefix.'layout'] = $subquestion->layout;
                            if ($subquestion->single == 1) {
                                switch ($subquestion->layout) {
                                    case '0':
                                        $defaultvalues[$prefix.'layout'] =
                                            get_string('layoutselectinline', 'qtype_multianswer');
                                        break;
                                    case '1':
                                        $defaultvalues[$prefix.'layout'] =
                                            get_string('layoutvertical', 'qtype_multianswer');
                                        break;
                                    case '2':
                                        $defaultvalues[$prefix.'layout'] =
                                            get_string('layouthorizontal', 'qtype_multianswer');
                                        break;
                                    default:
                                        $defaultvalues[$prefix.'layout'] =
                                            get_string('layoutundefined', 'qtype_multianswer');
                                }
                            } else {
                                switch ($subquestion->layout) {
                                    case '1':
                                        $defaultvalues[$prefix.'layout'] =
                                            get_string('layoutmultiple_vertical', 'qtype_multianswer');
                                        break;
                                    case '2':
                                        $defaultvalues[$prefix.'layout'] =
                                            get_string('layoutmultiple_horizontal', 'qtype_multianswer');
                                        break;
                                    default:
                                        $defaultvalues[$prefix.'layout'] =
                                            get_string('layoutundefined', 'qtype_multianswer');
                                }
                            }
                            if ($subquestion->shuffleanswers ) {
                                $defaultvalues[$prefix.'shuffleanswers'] = get_string('yes', 'moodle');
                            } else {
                                $defaultvalues[$prefix.'shuffleanswers'] = get_string('no', 'moodle');
                            }
                        }
                        foreach ($subquestion->answer as $key => $answer) {
                            if ($subquestion->qtype == 'numerical' && $key == 0) {
                                $defaultvalues[$prefix.'tolerance['.$key.']'] =
                                        $subquestion->tolerance[0];
                            }
                            if (is_array($answer)) {
                                $answer = $answer['text'];
                            }
                            $trimmedanswer = trim($answer);
                            if ($trimmedanswer !== '') {
                                $answercount++;
                                if ($subquestion->qtype == 'numerical' &&
                                        !(qtype_numerical::is_valid_number($trimmedanswer) || $trimmedanswer == '*')) {
                                    $this->_form->setElementError($prefix.'answer['.$key.']',
                                            get_string('answermustbenumberorstar',
                                                    'qtype_numerical'));
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
                                if ($subquestion->qtype == 'regexp') {
                                    $errortext = '';
                                    if ($answercount === 1 && $subquestion->fraction[$key] < 1) {
                                        $errortext = get_string("filloutoneanswer", "qtype_regexp");
                                    }
                                    if ($answercount > 1) {
                                        $errortext = validate_regexp_subquestion($trimmedanswer, $subquestion->fraction[$key]);
                                    }
                                    if ($errortext) {
                                        $this->_form->setElementError($prefix.'answer['.$key.']',
                                            $errortext);
                                    }
                                }
                            }

                            $defaultvalues[$prefix.'answer['.$key.']'] =
                                    htmlspecialchars($answer, ENT_COMPAT);
                        }
                        if ($answercount == 0) {
                            if ($subquestion->qtype == 'multichoice') {
                                $this->_form->setElementError($prefix.'answer[0]',
                                        get_string('notenoughanswers', 'qtype_multichoice', 2));
                            } else {
                                $this->_form->setElementError($prefix.'answer[0]',
                                        get_string('notenoughanswers', 'question', 1));
                            }
                        }
                        if ($maxgrade == false) {
                            $this->_form->setElementError($prefix.'fraction[0]',
                                    get_string('fractionsnomax', 'question'));
                        }
                        foreach ($subquestion->feedback as $key => $answer) {

                            $defaultvalues[$prefix.'feedback['.$key.']'] =
                                    htmlspecialchars ($answer['text'], ENT_COMPAT);
                        }
                        foreach ($subquestion->fraction as $key => $answer) {
                            $defaultvalues[$prefix.'fraction['.$key.']'] = $answer;
                        }

                        $sub++;
                    }
                }
            }
        }
        $defaultvalues['alertas'] = "<strong>".get_string('questioninquiz', 'qtype_multianswer').
                "</strong>";

        if ($defaultvalues != "") {
            $question = (object)((array)$question + $defaultvalues);
        }
        $question = $this->data_preprocessing_hints($question, true, true);
        parent::set_data($question);
    }

    /**
     * Validates the form data and files.
     *
     * This method validates the submitted form data and associated files.
     *
     * @param array $data The form data to validate.
     * @param array $files The files associated with the form data.
     *
     * @return array An array of error messages, or an empty array if validation passes.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $questiondisplay = qtype_multianswerrgx_extract_question($data['questiontext']);

        $errors = array_merge($errors, qtype_multianswerrgx_validate_question($questiondisplay));

        if (($this->negativediff > 0 || $this->usedinquiz &&
                ($this->negativediff > 0 || $this->negativediff < 0 ||
                        $this->qtypechange)) && !$this->confirm) {
            $errors['confirm'] =
                    get_string('confirmsave', 'qtype_multianswerrgx', $this->negativediff);
        }

        return $errors;
    }

    /**
     * Gets the question type.
     *
     * This method returns the type of the question. Possible return values include
     * 'multiple_choice', 'true_false', 'short_answer', etc.
     *
     * @return string The type of the question.
     */
    public function qtype() {
        return 'multianswerrgx';
    }

    /**
     * Generates alternate answers with corresponding fractions.
     *
     * @param object $answers Contains answer data with:
     *                        - answer: An array of answers.
     *                        - fraction: An array of fractions for each answer.
     *
     * @return array An array of alternate answers with:
     *               - 'fraction': The fraction as a percentage.
     *               - 'regexp': The associated regular expression.
     *               - 'answers': An array of possible answers.
     */
    public function get_alternateanswers($answers) {
        $alternateanswers = [];
        $i = 1;
        foreach ($answers as $index => $answer) {
            // JR added permutations OCT 2012.
            $answer['answer'] = has_permutations($answer['answer']);
            $r = expand_regexp($answer['answer']);
            if ($r) {
                $alternateanswers[$i]['fraction'] = (($answer['fraction']) * 100).'%';
                $alternateanswers[$i]['regexp'] = $answer['answer'];
                if (is_array($r)) {
                    $alternateanswers[$i]['answers'] = $r; // Normal alternateanswers (expanded).
                } else {
                    $alternateanswers[$i]['answers'][] = $r; // Regexp was not expanded.
                }
            }
            $i++;
        }
        return $alternateanswers;
    }

    /**
     * Setup form elements that are very unlikely to change
     *
     * @param MoodleQuickForm $mform
     * @return MoodleQuickForm
     */
    protected function form_setup(MoodleQuickForm $mform): MoodleQuickForm {
        global $PAGE;
        $PAGE->requires->strings_for_js(['addgapserror', 'tooshortforgapserror'], 'qtype_multianswerrgx');
        $PAGE->requires->js_call_amd('qtype_multianswerrgx/questionedit', 'init');
        $mform->addElement('hidden', 'reload', 1);
        $mform->setType('reload', PARAM_RAW);
        if (isset($question->id) && $question->id && $question->qtype && $question->questiontext) {
            $mform->removeelement('questiontext');
        }
        return $mform;
    }

}
