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
 * Access tests.
 *
/**
 * PHPUnit dataform access testcase
 *
 * @package    mod_dataform
 * @category   phpunit
 * @group      mod_dataform
 * @copyright  2014 Itamar Tzadok {@link http://substantialmethods.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class mod_dataform_access_testcase extends advanced_testcase {
    protected $course;
    protected $guest;
    protected $teacher;
    protected $assistant;
    protected $student;

    /**
     * Test set up.
     *
     * This is executed before running any test in this file.
     */
    public function setUp() {
        global $DB;

        $this->resetAfterTest();

        // Create a course we are going to add a data module to.
        $this->course = $this->getDataGenerator()->create_course();
        $courseid = $this->course->id;

        $roles = $DB->get_records_menu('role', array(), '', 'shortname,id');

        // Teacher
        $user = $this->getDataGenerator()->create_user(array('username' => 'teacher'));
        $this->getDataGenerator()->enrol_user($user->id, $courseid, $roles['editingteacher']);
        $this->teacher = $user;

        // Assistant
        $user = $this->getDataGenerator()->create_user(array('username' => 'assistant'));
        $this->getDataGenerator()->enrol_user($user->id, $courseid, $roles['teacher']);
        $this->assistant = $user;

        // Student
        $user = $this->getDataGenerator()->create_user(array('username' => 'student'));
        $this->getDataGenerator()->enrol_user($user->id, $courseid, $roles['student']);
        $this->student = $user;

        // Guest
        $user = $DB->get_record('user', array('username' => 'guest'));
        $this->guest = $user;
    }

    /**
     * Sets up a dataform activity in a course.
     *
     * @return mod_dataform_dataform
     */
    protected function get_a_dataform($dataformid = null) {
        $this->setAdminUser();

        // The generator used to create a data module.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_dataform');

        if (!$dataformid) {
            // Create a dataform instance
            $data = $generator->create_instance(array('course' => $this->course));
            $dataformid = $data->id;
        }
        return mod_dataform_dataform::instance($dataformid);
    }

    /**
     * Sets the user.
     *
     * @return void
     */
    protected function set_user($username) {
        if ($username == 'admin') {
            $this->setAdminUser();
        } else if ($username == 'guest') {
            $this->setGuestUser();
        } else {
            $this->setUser($this->$username);
        }
    }

    /**
     * Test view events for standard types.
     */
    public function test_access() {
        $df = $this->get_a_dataform();
        $view = $df->view_manager->add_view('aligned');
        $entry = (object) array(
            'dataid' => $df->id,
            'userid' => 0,
            'groupid' => 0,
        );
        $params = array('dataformid' => $df->id, 'viewid' => $view->id, 'entry' => $entry);

        $dataset = $this->createCsvDataSet(array('cases' => __DIR__.'/fixtures/test_cases_access.csv'));
        $cases = $dataset->getTable('cases');
        $columns = $dataset->getTableMetaData('cases')->getColumns();

        for ($r = 0; $r < $cases->getRowCount(); $r++) {
            $case = (object) array_combine($columns, $cases->getRow($r));

            // Set teacher user for initial setup
            $this->setUser($this->teacher);

            // Set the dataform
            $args = array(
                'timeavailable' => ($case->timeavailable ? strtotime($case->timeavailable) : 0),
                'timedue' => ($case->timedue ? strtotime($case->timedue) : 0),
                'maxentries' => ($case->maxentries == '' ? -1 : $case->maxentries),
            );
            $df->update($args);

            // Set the view
            $view->visible = ($case->viewvisible == '0' ? 0 : 1);
            $view->update($view->data);

            // Set the entry
            if ($entryuser = $case->entryuser) {
                $params['entry']->userid = $this->$entryuser->id;
            }
            if ($entrygroup = $case->entrygroup) {
                $params['entry']->gropuid = $entrygroup;
            }

            // Set the user
            $this->set_user($case->user);

            // Check access
            $access = "mod_dataform\access\\$case->event";
            $result = $access::validate($params);
            $this->assertEquals(filter_var($case->expected, FILTER_VALIDATE_BOOLEAN), $result);
        }

        $this->setUser($this->teacher);
        $df->delete();
    }

}