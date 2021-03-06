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
 * PHPUnit data generator tests
 *
 * @package    mod_dataform
 * @category   phpunit
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit dataform generator testcase
 *
 * @package    mod_dataform
 * @category   phpunit
 * @group      mod_dataform
 * @copyright  2014 Itamar Tzadok {@link http://substantialmethods.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_dataform_generator_testcase extends advanced_testcase {
    protected $_course;

    /**
     * Test set up.
     *
     * This is executed before running any test in this file.
     */
    public function setUp() {
        $this->resetAfterTest();

        // Create a course we are going to add a data module to.
        $this->_course = $this->getDataGenerator()->create_course();
    }

    public function test_generator() {
        global $DB;

        $this->setAdminUser();

        $this->assertEquals(0, $DB->count_records('dataform'));

        $course = $this->getDataGenerator()->create_course();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_dataform');
        $this->assertInstanceOf('mod_dataform_generator', $generator);
        $this->assertEquals('dataform', $generator->get_modulename());

        $data1 = $generator->create_instance(array('course' => $course->id));
        $data2 = $generator->create_instance(array('course' => $course->id));
        $this->assertEquals(2, $DB->count_records('dataform'));

        $df1 = mod_dataform_dataform::instance($data1->id);
        $df2 = mod_dataform_dataform::instance($data2->id);

        foreach (array($df1, $df2) as $df) {
            $this->assertEquals($df->course->id, $course->id);

            $cm = get_coursemodule_from_instance('dataform', $df->id);
            $this->assertEquals($df->cm->id, $cm->id);
            $this->assertEquals($df->id, $cm->instance);
            $this->assertEquals('dataform', $cm->modname);
            $this->assertEquals($course->id, $cm->course);

            $context = context_module::instance($cm->id);
            $this->assertEquals($df->context->id, $context->id);
            $this->assertEquals($df->cm->id, $context->instanceid);
        }

        // Test gradebook integration using low level DB access - DO NOT USE IN PLUGIN CODE!
        $data = $generator->create_instance(array('course' => $course->id, 'grade' => 100));
        $gitem = $DB->get_record('grade_items', array('courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => 'dataform', 'iteminstance' => $data->id));
        $this->assertNotEmpty($gitem);
        $this->assertEquals(100, $gitem->grademax);
        $this->assertEquals(0, $gitem->grademin);
        $this->assertEquals(GRADE_TYPE_VALUE, $gitem->gradetype);

        $this->assertNotEquals(2, $DB->count_records('dataform'));
        $df2->delete();
        $this->assertEquals(2, $DB->count_records('dataform'));
    }
}