<?php
// This file is part of Moodle - http://moodle.org/.
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
 * @package mod
 * @subpackage dataform
 * @copyright 2013 Itamar Tzadok {@link http://substantialmethods.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * The Dataform has been developed as an enhanced counterpart
 * of Moodle's Database activity module (1.9.11+ (20110323)).
 * To the extent that Dataform code corresponds to Database code,
 * certain copyrights on the Database module may obtain.
 */
defined('MOODLE_INTERNAL') or die;

require_once("$CFG->dirroot/course/moodleform_mod.php");

class mod_dataform_mod_form extends moodleform_mod {

    protected $_df = null;

    public function definition() {
        $mform = &$this->_form;

        $this->add_action_buttons();

        $this->definition_general();
        $this->definition_appearance();
        $this->definition_timing();
        $this->definition_entry_settings();
        $this->standard_grading_coursemodule_elements();
        $this->definition_grading();
        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     *
     */
    protected function definition_general() {
        global $CFG;

        $mform = &$this->_form;
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Name
        $mform->addElement('text', 'name', get_string('name'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->setDefault('name', get_string('dataformnew', 'dataform'));

        // Intro
        $this->add_intro_editor(false, get_string('description'));
    }

    /**
     *
     */
    protected function definition_appearance() {
        global $COURSE;

        // We want to hide that when using the singleactivity course format because it is confusing.
        if (!$this->courseformat->has_view_page()) {
            return;
        }

        $mform = &$this->_form;

        $mform->addElement('header', 'coursedisplayhdr', get_string('appearance'));
        // Activity icon
        $options = array('subdirs' => 0, 'maxbytes' => $COURSE->maxbytes, 'maxfiles' => 1, 'accepted_types' => array('image'));
        $draftitemid = file_get_submitted_draft_itemid('activityicon');
        file_prepare_draft_area($draftitemid, $this->context->id, 'mod_dataform', 'activityicon', 0, $options);
        $mform->addElement('filemanager', 'activityicon', get_string('activityicon', 'dataform'), null, $options);
        $mform->setDefault('activityicon', $draftitemid);
        $mform->addHelpButton('activityicon', 'activityicon', 'mod_dataform');

        // Displayed view
        $options = array(0 => get_string('choosedots'));
        if ($this->_instance) {
            if ($views = mod_dataform_view_manager::instance($this->_instance)->get_views_menu(array('forceget' => true))) {
                $options = $options + $views;
            }
        }
        $mform->addElement('select', 'inlineview', get_string('inlineview', 'mod_dataform'), $options);
        $mform->addHelpButton('inlineview', 'inlineview', 'mod_dataform');

        // Embedded
        $mform->addElement('selectyesno', 'embedded', get_string('embedded', 'mod_dataform'));
        $mform->addHelpButton('embedded', 'embedded', 'mod_dataform');
        $mform->disabledIf('embedded', 'inlineview', 'eq', 0);
    }

    /**
     *
     */
    protected function definition_timing() {
        $mform = &$this->_form;
        $mform->addElement('header', 'timinghdr', get_string('timing', 'dataform'));

        // Time available
        $mform->addElement('date_time_selector', 'timeavailable', get_string('timeavailable', 'dataform'), array('optional' => true));
        $mform->addHelpButton('timeavailable', 'timeavailable', 'mod_dataform');

        // Time due
        $mform->addElement('date_time_selector', 'timedue', get_string('timedue', 'dataform'), array('optional' => true));
        $mform->addHelpButton('timedue', 'timedue', 'mod_dataform');
        $mform->disabledIf('timedue', 'interval', 'gt', 0);

        // Activity interval
        $mform->addElement('duration', 'timeinterval', get_string('timeinterval', 'dataform'));
        $mform->addHelpButton('timeinterval', 'timeinterval', 'mod_dataform');
        $mform->disabledIf('timeinterval', 'timeavailable[enabled]', 'notchecked');
        $mform->disabledIf('timeinterval', 'timedue[enabled]', 'checked');

        // Number of intervals
        $mform->addElement('select', 'intervalcount', get_string('intervalcount', 'dataform'), array_combine(range(1, 100), range(1, 100)));
        $mform->setDefault('intervalcount', 1);
        $mform->addHelpButton('intervalcount', 'intervalcount', 'mod_dataform');
        $mform->disabledIf('intervalcount', 'timeavailable[enabled]', 'notchecked');
        $mform->disabledIf('intervalcount', 'timedue[enabled]', 'checked');
        $mform->disabledIf('intervalcount', 'timeinterval[number]', 'eq', '');
        $mform->disabledIf('intervalcount', 'timeinterval[number]', 'eq', 0);

    }

    /**
     *
     */
    protected function definition_entry_settings() {
        global $CFG;

        $mform = &$this->_form;
        $mform->addElement('header', 'entrysettingshdr', get_string('entries', 'dataform'));

        if ($CFG->dataform_maxentries > 0) {
            // Admin limit, select from dropdown
            $maxoptions = (array_combine(range(0, $CFG->dataform_maxentries), range(0, $CFG->dataform_maxentries)));
            // Max entries
            $mform->addElement('select', 'maxentries', get_string('entriesmax', 'dataform'), $maxoptions);
            $mform->setDefault('maxentries', $CFG->dataform_maxentries);
            // Required entries
            $mform->addElement('select', 'entriesrequired', get_string('entriesrequired', 'dataform'), array(0 => get_string('none')) + $maxoptions);

        } else {
            // No limit or no entries
            $admindeniesentries = (int) !$CFG->dataform_maxentries;
            $mform->addElement('hidden', 'admindeniesentries', $admindeniesentries);
            $mform->setType('admindeniesentries', PARAM_INT);

            // Max entries
            $mform->addElement('text', 'maxentries', get_string('entriesmax', 'dataform'));
            $mform->setDefault('maxentries', -1);
            $mform->addRule('maxentries', null, 'numeric', null, 'client');
            $mform->setType('maxentries', PARAM_INT);
            $mform->disabledIf('maxentries', 'admindeniesentries', 'eq', 1);

            // Required entries
            $mform->addElement('text', 'entriesrequired', get_string('entriesrequired', 'dataform'));
            $mform->setDefault('entriesrequired', 0);
            $mform->addRule('entriesrequired', null, 'numeric', null, 'client');
            $mform->setType('entriesrequired', PARAM_INT);
            $mform->disabledIf('entriesrequired', 'admindeniesentries', 'eq', 1);

        }

        $mform->addHelpButton('maxentries', 'entriesmax', 'mod_dataform');
        $mform->addHelpButton('entriesrequired', 'entriesrequired', 'mod_dataform');

        // Force separate participants
        $mform->addElement('selectyesno', 'individualized', get_string('separateparticipants', 'dataform'));
        $mform->addHelpButton('individualized', 'separateparticipants', 'dataform');

        // Force group entries
        $mform->addElement('selectyesno', 'grouped', get_string('groupentries', 'dataform'));
        $mform->disabledIf('grouped', 'groupmode', 'eq', 0);
        $mform->addHelpButton('grouped', 'groupentries', 'mod_dataform');

        // Force anonymous entries
        if ($CFG->dataform_anonymous) {
            $mform->addElement('selectyesno', 'anonymous', get_string('anonymizeentries', 'dataform'));
            $mform->setDefault('anonymous', 0);
            $mform->addHelpButton('anonymous', 'anonymizeentries', 'mod_dataform');
        } else {
            $mform->addElement('hidden', 'anonymous', 0);
            $mform->setType('anonymous', PARAM_INT);
            $mform->addElement('static', 'anonymousna', get_string('anonymizeentries', 'dataform'), get_string('notapplicable', 'dataform'));
            $mform->addHelpButton('anonymousna', 'anonymizeentries', 'mod_dataform');
        }

        // Time limit to manage an entry
        $mform->addElement('text', 'timelimit', get_string('entrytimelimit', 'dataform'));
        $mform->setType('timelimit', PARAM_INT);
        $mform->setDefault('timelimit', -1);
        $mform->addRule('timelimit', null, 'numeric', null, 'client');
        $mform->addHelpButton('timelimit', 'entrytimelimit', 'mod_dataform');
    }

    /**
     *
     */
    protected function definition_grading() {
        $mform = &$this->_form;

        $mform->setDefault('grade', 0);

        // Grading formula
        $mform->addElement('textarea', 'gradecalc', get_string('calculation', 'grades'));
        $mform->setDefault('gradecalc', '');
        $mform->disabledIf('gradecalc', 'grade', 'eq', 0);
        $mform->disabledIf('gradecalc', 'advancedgradingmethod_activity', 'neq', '');
        $mform->addHelpButton('gradecalc', 'calculation', 'grades');

    }


    public function add_completion_rules() {
        $mform =& $this->_form;

        $group = array();
        $group[] = &$mform->createElement('checkbox', 'completionentriesenabled', '', get_string('completionentries', 'dataform'));
        $group[] = &$mform->createElement('text', 'completionentries', '', array('size' => 3));
        $mform->setType('completionentries', PARAM_INT);
        $mform->addGroup($group, 'completionentriesgroup', get_string('completionentriesgroup', 'dataform'), array(' '), false);
        $mform->disabledIf('completionentries', 'completionentriesenabled', 'notchecked');

        return array('completionentriesgroup');
    }

    public function completion_rule_enabled($data) {
        return (!empty($data['completionentriesenabled']) && $data['completionentries'] != 0);
    }

    /**
     *
     */
    public function data_preprocessing(&$data) {
        $data = (array) $data;
        parent::data_preprocessing($data);

        // Set up the completion checkboxes which aren't part of standard data.
        $data['completionentriesenabled'] = (int) !empty($data['completionentries']);
    }

    /**
     *
     */
    public function set_data($data) {
        $this->data_preprocessing($data);
        parent::set_data($data);
    }

    /**
     *
     */
    public function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return false;
        }

        if (empty($data->timeavailable)) {
            unset($data->timeinterval);
            $data->intervalcount = 1;
        }

        if (empty($data->timeinterval)) {
            $data->intervalcount = 1;
        }

        // Turn off completion settings if the checkboxes aren't ticked
        if (!empty($data->completionunlocked)) {
            $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completionentriesenabled) or !$autocompletion) {
                $data->completionentries = 0;
            }
        }

        return $data;
    }

    /**
     *
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Completion: Automatic on-view completion can not work together with 'Inline view' option
        if (empty($errors['completion'])
                    and array_key_exists('completion', $data)
                    and $data['completion'] == COMPLETION_TRACKING_AUTOMATIC
                    and !empty($data['completionview'])
                    and !empty($data['inlineview'])) {
            $errors['completion'] = get_string('noautocompletioninline', 'mod_dataform');
        }

        return $errors;
    }

}