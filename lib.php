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
 * @copyright 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * The Dataform has been developed as an enhanced counterpart
 * of Moodle's Database activity module (1.9.11+ (20110323)).
 * To the extent that Dataform code corresponds to Database code,
 * certain copyrights on the Database module may obtain.
 */

/**
 * MOD FUNCTIONS WHICH ARE CALLED FROM OUTSIDE THE MODULE
 */

defined('MOODLE_INTERNAL') or die;

require_once('locallib.php');

/**
 * Adds an instance of a dataform
 *
 * @global object
 * @param object $data
 * @return $int
 */
function dataform_add_instance($data) {
    global $CFG, $DB, $COURSE;

    $data->timemodified = time();

    if (empty($data->grade)) {
        $data->grade = 0;
        $data->gradecalc = null;
    }

    // Max entries
    if (!isset($data->maxentries) or $data->maxentries < -1) {
        $data->maxentries = -1;
    }
    if ($CFG->dataform_maxentries == 0) {
        $data->maxentries = 0;
    } else if ($CFG->dataform_maxentries > 0 and ($data->maxentries > $CFG->dataform_maxentries or $data->maxentries < 0)) {
        $data->maxentries = $CFG->dataform_maxentries;
    }

    if (!$data->id = $DB->insert_record('dataform', $data)) {
        return false;
    }

    // Activity icon
    if (!empty($data->activityicon)) {
        // We need to use context now, so we need to make sure all needed info is already in db
        $DB->set_field('course_modules', 'instance', $data->id, array('id' => $data->coursemodule));
        $context = context_module::instance($data->coursemodule);
        $options = array('subdirs' => 0,
                        'maxbytes' => $COURSE->maxbytes,
                        'maxfiles' => 1,
                        'accepted_types' => array('image'));
        file_save_draft_area_files($data->activityicon, $context->id, 'mod_dataform', 'activityicon', 0, $options);
    }

    // Calendar
    dataform_calendar_helper::update_event_timeavailable($data);
    dataform_calendar_helper::update_event_timedue($data);

    // Grading
    dataform_grade_item_update($data);
    return $data->id;
}

/**
 * updates an instance of a data
 *
 * @global object
 * @param object $data
 * @return bool
 */
function dataform_update_instance($data) {
    global  $CFG, $DB, $COURSE;

    $data->id = $data->instance;

    $data->timemodified = time();

    if (empty($data->grade)) {
        $data->grade = 0;
        $data->gradecalc = null;
    }

    // Max entries
    if ($CFG->dataform_maxentries == 0) {
        $data->maxentries = 0;
    } else if ($CFG->dataform_maxentries > 0 and ($data->maxentries > $CFG->dataform_maxentries or $data->maxentries < 0)) {
        $data->maxentries = $CFG->dataform_maxentries;
    } else if ($data->maxentries < -1) {
        $data->maxentries = -1;
    }

    $df = mod_dataform_dataform::instance($data->id);
    if (!$df->update($data)) {
        return false;
    }

    // Activity icon
    $context = context_module::instance($data->coursemodule);
    $options = array('subdirs' => 0,
                    'maxbytes' => $COURSE->maxbytes,
                    'maxfiles' => 1,
                    'accepted_types' => array('image'));
    file_save_draft_area_files($data->activityicon, $context->id, 'mod_dataform', 'activityicon', 0, $options);

    // Calendar
    dataform_calendar_helper::update_event_timeavailable($data);
    dataform_calendar_helper::update_event_timedue($data);

    // Grading
    dataform_update_grades($data);

    return true;
}

/**
 * deletes an instance of a data
 *
 * @global object
 * @param int $id
 * @return bool
 */
function dataform_delete_instance($id) {
    global $DB;

    if (!$data = $DB->record_exists('dataform', array('id' => $id))) {
        return false;
    }

    $df = new mod_dataform_dataform($id);
    $result = $df->delete();
    return $result;
}

/**
 * Make sure up-to-date events are created for all Dataform instances
 *
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every instance event in the site is checked, else
 * only instance events belonging to the course specified are checked.
 * This function is used, in its new format, by restore_refresh_events()
 *
 * @param $courseid int optional If zero then all module instances for all courses are covered
 * @return boolean Always returns true
 */
function dataform_refresh_events($courseid = 0) {
    global $DB;

    if ($courseid == 0) {
        if (!$dataforms = $DB->get_records('dataform')) {
            return true;
        }
    } else {
        if (!$dataforms = $DB->get_records('dataform', array('course' => $courseid))) {
            return true;
        }
    }
    $moduleid = $DB->get_field('modules', 'id', array('name' => 'dataform'));

    foreach ($dataforms as $data) {
        $data->module = $moduleid;
        $cm = get_coursemodule_from_instance('dataform', $data->id, $courseid, false, MUST_EXIST);
        $data->coursemodule = $cm->id;

        dataform_calendar_helper::update_event_timeavailable($data);
        dataform_calendar_helper::update_event_timedue($data);
    }
    return true;
}

/**
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function dataform_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GROUPMEMBERSONLY:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_ADVANCED_GRADING:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;

        default:
            return null;
    }
}

/**
 * Return a list of page types
 *
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function dataform_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $modulepagetype = array(
        'mod-dataform-*' => get_string('page-mod-dataform-x', 'dataform'),
        'mod-dataform-view-index' => get_string('page-mod-dataform-view-index', 'dataform'),
        'mod-dataform-field-index' => get_string('page-mod-dataform-field-index', 'dataform'),
        'mod-dataform-access-index' => get_string('page-mod-dataform-access-index', 'dataform'),
        'mod-dataform-notification-index' => get_string('page-mod-dataform-notification-index', 'dataform'),
    );
    return $modulepagetype;
}

/**
 * Given a coursemodule object, this function returns the extra
 * information needed to print this activity in various places.
 *
 * If displaying custom icon we store additional information
 * in customdata, so functions {@link folder_cm_info_dynamic()} and
 * {@link folder_cm_info_view()} do not need to do DB queries
 *
 * @param cm_info $cm
 * @return cached_cm_info info
 */
function dataform_get_coursemodule_info($cm) {
    global $DB;

    if (!$dataform = $DB->get_record('dataform', array('id' => $cm->instance), 'id, name, intro, introformat, inlineview, embedded')) {
        return null;
    }

    $cminfo = new cached_cm_info();

    // Activity custom icon
    if ($customiconurl = dataform_get_custom_icon_url($cm->id)) {
        $cminfo->iconurl = $customiconurl;
    }

    // Inline view
    if (!empty($dataform->inlineview)) {
        $cminfo->customdata = $dataform;
        return $cminfo;
    }

    $cminfo->name = $dataform->name;
    if ($cm->showdescription) {
        $cminfo->content = format_module_intro('dataform', $dataform, $cm->id, false);
    }

    return $cminfo;
}

/**
 * Sets dynamic information about a course module
 *
 * This function is called from cm_info when displaying the module
 * mod_dataform can be displayed inline on course page in which case it should not have course link
 *
 * @param cm_info $cm
 */
function dataform_cm_info_dynamic(cm_info $cm) {
    if ($customdata = $cm->customdata and !empty($customdata->inlineview)) {
        // The field 'customdata' is not empty IF AND ONLY IF we display content inline
        $cm->set_no_view_link();
    }
}

/**
 * Overwrites the content in the course-module object with the Dataform view content
 * if dataform.inlineview is not empty
 *
 * @param cm_info $cm
 */
function dataform_cm_info_view(cm_info $cm) {
    global $PAGE, $CFG, $OUTPUT;

    if (!$cm->uservisible) {
        return;
    }

    // Default content if not displaying inline view
    if (!$dataform = $cm->customdata or empty($dataform->inlineview)) {
        return;
    }

    if (!empty($dataform->embedded)) {
        $content = mod_dataform_dataform::get_content_embedded($dataform->id, $dataform->inlineview);
    } else {
        $content = mod_dataform_dataform::get_content_inline($dataform->id, $dataform->inlineview);
    }

    if (!empty($content)) {
        $cm->set_content($content);
    }
}

/**
 * Gets the instance custom icon if exists.
 * Filearea: activityicon
 * Itemid: 0
 *
 * @param int cm id
 * @return moodle_url
 */
function dataform_get_custom_icon_url($cmid) {
    $fs = get_file_storage();
    $context = context_module::instance($cmid);
    if ($files = $fs->get_area_files($context->id, 'mod_dataform', 'activityicon', 0, 'sortorder', false)) {
        $file = reset($files);
        $filename = $file->get_filename();
        $path = "/{$context->id}/mod_dataform/activityicon/0";
        return moodle_url::make_file_url('/pluginfile.php', "$path/$filename");
    }
    return null;
}

/**
 * Obtains the automatic completion state for this dataform based on any conditions
 * in dataform settings.
 *
 * @global object
 * @global object
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not. (If no conditions, then return
 *   value depends on comparison type)
 */
function dataform_get_completion_state($course, $cm, $userid, $type) {
    global $CFG, $DB;

    // Get dataform details
    if (!($dataform = $DB->get_record('dataform', array('id' => $cm->instance)))) {
        throw new Exception("Can't find dataform {$cm->instance}");
    }

    $result = $type;
    if ($dataform->completionentries) {
        $entriescount = $DB->count_records('dataform_entries', array('dataid' => $dataform->id, 'userid' => $userid));
        $value = ($entriescount >= $dataform->completionentries);
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }

    return $result;
}

// ------------------------------------------------------------
// BACKUP/RESTORE
// ------------------------------------------------------------

/**
 * Checks if a scale is being used by an dataform
 *
 * This is used by the backup code to decide whether to back up a scale
 * @param $dataformid int
 * @param $scaleid int
 * @return boolean True if the scale is used by the dataform
 */
function dataform_scale_used($dataformid, $scaleid) {
    global $DB;

    if ($scaleid) {
        // Check the dataform instance
        if ($DB->record_exists('dataform', array('id' => $dataformid, 'grade' => -$scaleid))) {
            return true;
        }
        // Check all fields which are instances of interface rating
        foreach (array_keys(core_component::get_plugin_list('dataformfield')) as $type) {
            $fieldclass = "dataformfield_{$type}_$type";
            if (is_subclass_of($fieldclass, '\mod_dataform\interfaces\grading')) {
                $scaleparam = $fieldclass::get_scale_param();
                if ($DB->record_exists('dataform_fields', array('dataid' => $dataformid, 'type' => $type, $scaleparam => -$scaleid))) {
                    return true;
                }
            }
        }
    }

    return false;
}

/**
 * Checks if scale is being used in any instance of dataform
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used in any dataform
 */
function dataform_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid) {
        if ($DB->record_exists('dataform', array('grade' => -$scaleid))) {
            return true;
        }
        // Check all fields which are instances of interface rating
        foreach (array_keys(core_component::get_plugin_list('dataformfield')) as $type) {
            $fieldclass = "dataformfield_{$type}_$type";
            if (is_subclass_of($fieldclass, '\mod_dataform\interfaces\grading')) {
                $scaleparam = $fieldclass::get_scale_param();
                if ($DB->record_exists('dataform_fields', array('type' => $type, $scaleparam => -$scaleid))) {
                    return true;
                }
            }
        }
    }

    return false;
}

// ------------------------------------------------------------
// RESET
// ------------------------------------------------------------

/**
 * prints the form elements that control
 * whether the course reset functionality affects the data.
 *
 * @param $mform form passed by reference
 */
function dataform_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'dataformheader', get_string('modulenameplural', 'dataform'));

    $mform->addElement('checkbox', 'reset_dataform_data', get_string('entriesdeleteall', 'dataform'));

    $mform->addElement('checkbox', 'reset_dataform_notenrolled', get_string('deletenotenrolled', 'dataform'));
    $mform->disabledIf('reset_dataform_notenrolled', 'reset_dataform_data', 'checked');
}

/**
 * Course reset form defaults.
 * @return array
 */
function dataform_reset_course_form_defaults($course) {
    return array('reset_dataform_data' => 0, 'reset_dataform_notenrolled' => 0);
}

/**
 * Actual implementation of the rest coures functionality, delete all the
 * data responses for course $data->courseid.
 *
 * @global object
 * @global object
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function dataform_reset_userdata($data) {
    global $DB;

    $componentstr = get_string('modulenameplural', 'dataform');
    $entriesdeleteallstr = get_string('entriesdeleteall', 'dataform');
    $deletenotenrolledstr = get_string('deletenotenrolled', 'dataform');
    $datachangedstr = get_string('datechanged');

    $status = array();

    if (!$dataforms = $DB->get_records('dataform', array('dataid' => $data->courseid), '', 'id')) {
        return $status;
    }

    foreach (array_keys($dataforms) as $dataformid) {
        $df = mod_dataform_dataform::instance($dataformid);
        // Delete all user data
        if (!empty($data->reset_dataform_data)) {
            $df->reset_user_data();
            $status['entriesdeleteall'] = array('component' => $componentstr, 'item' => $entriesdeleteallstr, 'error' => false);
        }
        // Delete user data for not enrolled users
        if (!empty($data->reset_dataform_notenrolled)) {
            $sql = "
                SELECT
                    e.id,
                    e.userid,
                    e.dataid,
                    u.id AS userexists,
                    u.deleted AS userdeleted
                FROM
                    {dataform_entries} e
                    LEFT JOIN {user} u ON e.userid = u.id
                WHERE
                    e.dataid = ?
            ";

            $coursecontext = context_course::instance($this->course->id);
            $skipped = array();
            $notenrolled = array();
            $entries = $DB->get_records_sql($sql, array($dataformid));
            foreach ($entries as $entry) {
                if (array_key_exists($entry->userid, $notenrolled)) {
                    continue;
                }
                if (array_key_exists($entry->userid, $skipped)) {
                    continue;
                }
                if (!$entry->userexists or $entry->userdeleted or !is_enrolled($coursecontext, $entry->userid)) {
                    $df->reset_user_data($entry->userid);
                    $notenrolled[$entry->userid] = true;
                    continue;
                }
                $skipped[$entry->userid] = true;
                $status['deletenotenrolled'] = array('component' => $componentstr, 'item' => $deletenotenrolledstr, 'error' => false);
            }
        }
        // Updating dates - shift may be negative too
        if ($data->timeshift) {
            shift_course_mod_dates('dataform', array('timeavailable', 'timedue'), $data->timeshift, $data->courseid);
            $status['datechanged'] = array('component' => $componentstr, 'item' => $datechangedstr, 'error' => false);
        }
    }

    return $status;
}

/**
 * Removes all grades from gradebook
 *
 * @global object
 * @global object
 * @param int $courseid
 * @param string $type optional type
 */
function dataform_reset_gradebook($courseid, $type='') {
    global $DB;

    $sql = "SELECT d.*, cm.idnumber as cmidnumber, d.course as courseid
              FROM {dataform} d, {course_modules} cm, {modules} m
             WHERE m.name='dataform' AND m.id=cm.module AND cm.instance=d.id AND d.course=?";

    if ($dataforms = $DB->get_records_sql($sql, array($courseid))) {
        foreach ($dataforms as $dataform) {
            dataform_grade_item_update($dataform, 'reset');
        }
    }
}

// ------------------------------------------------------------
// PERMISSIONS AND NAVIGATION
// ------------------------------------------------------------

/**
 * Returns all other caps used in module
 *
 * @return array
 */
function dataform_get_extra_capabilities() {
    return array('moodle/site:accessallgroups',
                'moodle/site:viewfullnames',
                'moodle/rating:view',
                'moodle/rating:viewany',
                'moodle/rating:viewall',
                'moodle/rating:rate',
                'moodle/comment:view',
                'moodle/comment:post',
                'moodle/comment:delete');
}

/**
 * Lists all browsable file areas
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @return array
 */
function dataform_get_file_areas($course, $cm, $context) {
    $areas = array();
    return $areas;
}

/**
 * Serves the dataform attachments. Implements needed access control ;-)
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - justsend the file
 */
function mod_dataform_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG, $DB, $USER;

    // DATAFORM activity icon
    if ($filearea === 'activityicon' and $context->contextlevel == CONTEXT_MODULE) {
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_dataform/activityicon/$relativepath";

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }
        // Finally send the file
        send_stored_file($file, 0, 0, true); // Download MUST be forced - security!
    }

    // FIELD CONTENT files
    if ($filearea === 'content' and $context->contextlevel == CONTEXT_MODULE) {

        $contentid = (int)array_shift($args);

        if (!$content = $DB->get_record('dataform_contents', array('id' => $contentid))) {
            return false;
        }

        if (!$field = $DB->get_record('dataform_fields', array('id' => $content->fieldid))) {
            return false;
        }

        // Nanogong ugly hack
        if ($field->type != 'nanogong') {
            if (empty($USER->id)) {
                return false;
            }

            require_course_login($course, true, $cm);
        }

        if (!$entry = $DB->get_record('dataform_entries', array('id' => $content->entryid))) {
            return false;
        }

        if (!$dataform = $DB->get_record('dataform', array('id' => $field->dataid))) {
            return false;
        }

        if ($dataform->id != $cm->instance) {
            // Hacker attempt - context does not match the contentid
            return false;
        }

        // Group access
        if ($entry->groupid) {
            $groupmode = groups_get_activity_groupmode($cm, $course);
            if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                if (!groups_is_member($record->groupid)) {
                    return false;
                }
            }
        }

        // Separate participants
        if ($dataform->individualized) {
            if (empty($USER->id)) {
                return false;
            }
            if ($USER->id != $entry->userid and !has_capability('mod/dataform:manageentries', $context)) {
                return false;
            }
        }

        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_dataform/content/$contentid/$relativepath";

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }
        // Finally send the file
        send_stored_file($file, 0, 0, true); // Download MUST be forced - security!
    }

    // PRESET files
    if (($filearea === 'course_presets' or $filearea === 'site_presets')) {
        require_course_login($course, true, $cm);

        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_dataform/$filearea/$relativepath";

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }

        // Finally send the file
        send_stored_file($file, 0, 0, true); // Download MUST be forced - security!
    }

    if (($filearea === 'js' or $filearea === 'css')) {
        require_course_login($course, true, $cm);

        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_dataform/$filearea/$relativepath";

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }

        // Finally send the file
        send_stored_file($file, 0, 0, true); // Download MUST be forced - security!
    }

    return false;
}

/**
 *
 */
function dataform_extend_navigation($navigation, $course, $module, $cm) {
    global $PAGE, $USER, $CFG;

    $df = mod_dataform_dataform::instance($cm->instance);

    if ($views = $df->view_manager->views_menu) {
        foreach ($views as $viewid => $name) {
            $navigation->add($name, new moodle_url('/mod/dataform/view.php', array('d' => $df->id, 'view' => $viewid)));
        }
    }

    // RSS links
    if (!empty($CFG->enablerssfeeds) and !empty($CFG->dataform_enablerssfeeds)) {
        if ($rssviews = $df->get_rss_views()) {
            require_once("$CFG->libdir/rsslib.php");
            $rssstr = get_string('rss');
            foreach ($rssviews as $viewid => $view) {
                $feedname = $view->name;
                $componentinstance = "$df->id/$viewid";
                $url = new moodle_url(rss_get_url($PAGE->cm->context->id, $USER->id, 'mod_dataform', $componentinstance));
                $navigation->add($feedname, $url, settings_navigation::TYPE_SETTING, null, null, new pix_icon('i/rss', $rssstr));
            }
        }
    }

}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $datanode The node to add module settings to
 */
function dataform_extend_settings_navigation(settings_navigation $settings, navigation_node $dfnode) {
    global $PAGE;

    // INDEX
    $coursecontext = context_course::instance($PAGE->course->id);
    if (has_capability('mod/dataform:indexview', $coursecontext)) {
        $dfnode->add(get_string('index', 'dataform'), new moodle_url('/mod/dataform/index.php', array('id' => $PAGE->course->id)));
    }

    // MANAGEMENT
    // Must be activity manager
    $df = \mod_dataform_dataform::instance(null, $PAGE->cm->id);
    if (!$manager = $df->user_manage_permissions) {
        return;
    }

    // View gradebook
    if ($df->grade) {
        $dfnode->add(get_string('gradebook', 'grades'), new moodle_url('/grade/report/index.php', array('id' => $PAGE->course->id)));
    }

    // Delete
    if ($manager['templates']) {
        $dfnode->add(get_string('renewactivity', 'dataform'), new moodle_url('/mod/dataform/view.php', array('id' => $PAGE->cm->id, 'renew' => 1, 'sesskey' => sesskey())));
        $dfnode->add(get_string('deleteactivity', 'dataform'), new moodle_url('/course/mod.php', array('delete' => $PAGE->cm->id, 'sesskey' => sesskey())));
    }

    // Manage
    $manage = $dfnode->add(get_string('manage', 'dataform'));

    if ($manager['views']) {
        $manage->add(get_string('views', 'dataform'), new moodle_url('/mod/dataform/view/index.php', array('id' => $PAGE->cm->id)));
    }

    if ($manager['fields']) {
        $manage->add(get_string('fields', 'dataform'), new moodle_url('/mod/dataform/field/index.php', array('id' => $PAGE->cm->id)));
    }

    if ($manager['filters']) {
        $manage->add(get_string('filters', 'dataform'), new moodle_url('/mod/dataform/filter/index.php', array('id' => $PAGE->cm->id)));
    }

    if ($manager['access']) {
        $manage->add(get_string('access', 'dataform'), new moodle_url('/mod/dataform/access/index.php', array('id' => $PAGE->cm->id)));
    }

    if ($manager['notifications']) {
        $manage->add(get_string('notifications'), new moodle_url('/mod/dataform/notification/index.php', array('id' => $PAGE->cm->id)));
    }

    if ($manager['css']) {
        $manage->add(get_string('cssinclude', 'dataform'), new moodle_url('/mod/dataform/css.php', array('id' => $PAGE->cm->id, 'cssedit' => 1)));
    }

    if ($manager['js']) {
        $manage->add(get_string('jsinclude', 'dataform'), new moodle_url('/mod/dataform/js.php', array('id' => $PAGE->cm->id, 'jsedit' => 1)));
    }

    if ($manager['tools']) {
        $manage->add(get_string('tools', 'dataform'), new moodle_url('/mod/dataform/tool/index.php', array('id' => $PAGE->cm->id)));
    }

    if ($manager['presets']) {
        $manage->add(get_string('presets', 'dataform'), new moodle_url('/mod/dataform/preset/index.php', array('id' => $PAGE->cm->id)));
    }
}

// ------------------------------------------------------------
// INFO
// ------------------------------------------------------------

/**
 * returns a list of participants of this dataform
 */
function dataform_get_participants($dataid) {
    global $DB;

    $params = array('dataid' => $dataid);

    $sql = "SELECT DISTINCT u.id
              FROM {user} u,
                   {dataform_entries} e
             WHERE e.dataid = :dataid AND
                   u.id = e.userid";
    $entries = $DB->get_records_sql($sql, $params);

    $sql = "SELECT DISTINCT u.id
              FROM {user} u,
                   {dataform_entries} e,
                   {comments} c
             WHERE e.dataid = ? AND
                   u.id = e.userid AND
                   e.id = c.itemid AND
                   c.commentarea = 'entry'";
    $comments = $DB->get_records_sql($sql, $params);

    $sql = "SELECT DISTINCT u.id
              FROM {user} u,
                   {dataform_entries} e,
                   {ratings} r
             WHERE e.dataid = ? AND
                   u.id = e.userid AND
                   e.id = r.itemid AND
                   r.component = 'mod_dataform'";
    $ratings = $DB->get_records_sql($sql, $params);

    $participants = array();

    if ($entries) {
        foreach ($entries as $entry) {
            $participants[$entry->id] = $entry;
        }
    }
    if ($comments) {
        foreach ($comments as $comment) {
            $participants[$comment->id] = $comment;
        }
    }
    if ($ratings) {
        foreach ($ratings as $rating) {
            $participants[$rating->id] = $rating;
        }
    }
    return $participants;
}

/**
 * returns a summary of data activity of this user
 *
 * @global object
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $data
 * @return object|null
 */
function dataform_user_outline($course, $user, $mod, $data) {
    global $DB, $CFG;
    require_once("$CFG->libdir/gradelib.php");

    $grades = grade_get_grades($course->id, 'mod', 'dataform', $data->id, $user->id);
    if (empty($grades->items[0]->grades)) {
        $grade = false;
    } else {
        $grade = reset($grades->items[0]->grades);
    }

    $sqlparams = array('dataid' => $data->id, 'userid' => $user->id);
    if ($countrecords = $DB->count_records('dataform_entries', $sqlparams)) {
        $result = new stdClass;
        $result->info = get_string('entriescount', 'dataform', $countrecords);
        $lastrecordset = $DB->get_records(
            'dataform_entries',
            $sqlparams,
            'timemodified DESC',
            'id,timemodified',
            0,
            1
        );
        $lastrecord = reset($lastrecordset);
        $result->time = $lastrecord->timemodified;
        if ($grade) {
            $result->info .= ', ' . get_string('grade') . ': ' . $grade->str_long_grade;
        }
        return $result;
    } else if ($grade) {
        $result = new stdClass;
        $result->info = get_string('grade') . ': ' . $grade->str_long_grade;
        $result->time = $grade->dategraded;
        return $result;
    }
    return null;
}

/**
 * Print a detailed representation of what a  user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $dataform
 * @return bool
 */
function dataform_user_complete($course, $user, $mod, $dataform) {
    global $DB, $CFG, $OUTPUT;
    require_once("$CFG->libdir/gradelib.php");

    $grades = grade_get_grades($course->id, 'mod', 'dataform', $dataform->id, $user->id);
    if (!empty($grades->items[0]->grades)) {
        $grade = reset($grades->items[0]->grades);
        echo $OUTPUT->container(get_string('grade').': '.$grade->str_long_grade);
        if ($grade->str_feedback) {
            echo $OUTPUT->container(get_string('feedback').': '.$grade->str_feedback);
        }
    }
    $df = new mod_dataform_dataform($dataform->id);
    if (!$df->defaultview) {
        return true;
    }

    if ($view = $df->view_manager->get_view_by_id($df->defaultview)) {
        $view->set_viewfilter(array('users' => array($user->id)));
        $view->section = '##entries##';
        $viewcontent = $view->display();
        echo $viewcontent;
    }
    return true;
}

// ------------------------------------------------------------
// Participantion Reports
// ------------------------------------------------------------

/**
 */
function dataform_get_view_actions() {
    return array('view');
}

/**
 */
function dataform_get_post_actions() {
    return array('add', 'update', 'delete');
}

// ------------------------------------------------------------
// COMMENTS
// ------------------------------------------------------------

/**
 * Running addtional permission check on plugin, for example, plugins
 * may have switch to turn on/off comments option, this callback will
 * affect UI display, not like pluginname_comment_validate only throw
 * exceptions.
 * Capability check has been done in comment->check_permissions(), we
 * don't need to do it again here.
 *
 * @param stdClass $commentparam {
 *              context  => context the context object
 *              courseid => int course id
 *              cm       => stdClass course module object
 *              commentarea => string comment area
 *              itemid      => int itemid
 * }
 * @return array
 */
function dataform_comment_permissions($commentparam) {
    $df = mod_dataform_dataform::instance($commentparam->cm->instance);
    if ($comment = $df->field_manager->get_field_by_name($commentparam->commentarea)) {
        return $comment->permissions($commentparam);
    }
    return array('view' => true, 'post' => true);
}

/**
 * Validate comment parameter before perform other comments actions
 *
 * @param stdClass $commentparam {
 *              context  => context the context object
 *              courseid => int course id
 *              cm       => stdClass course module object
 *              commentarea => string comment area
 *              itemid      => int itemid
 * }
 * @return bool
 */
function dataform_comment_validate($commentparam) {
    $df = mod_dataform_dataform::instance($commentparam->cm->instance);
    if ($comment = $df->field_manager->get_field_by_name($commentparam->commentarea)) {
        return $comment->validation($commentparam);
    }
    return false;
}

// ------------------------------------------------------------
// RATINGS
// ------------------------------------------------------------

/**
 * Return rating related permissions
 *
 * @param string $contextid the context id
 * @param string $component the component to get rating permissions for
 * @param string $ratingarea the rating area to get permissions for
 * @return array an associative array of the user's rating permissions
 */
function dataform_rating_permissions($contextid, $component, $ratingarea) {
    if ($component == 'mod_dataform') {
        $context = context::instance_by_id($contextid, MUST_EXIST);
        $df = mod_dataform_dataform::instance(0, $context->instanceid);
        $rating = $df->field_manager->get_field_by_name($ratingarea);
        return $rating->permissions();
    }
    return null;
}

/**
 * Validates a submitted rating
 * @param array $params submitted data
 *            context => object the context in which the rated items exists [required]
 *            ratingarea => string 'entry' or 'activity' [required]
 *            itemid => int the ID of the object being rated
 *            scaleid => int the scale from which the user can select a rating. Used for bounds checking. [required]
 *            rating => int the submitted rating
 *            rateduserid => int the id of the user whose items have been rated. NOT the user who submitted the ratings. 0 to update all. [required]
 *            aggregation => int the aggregation method to apply when calculating grades ie RATING_AGGREGATE_AVERAGE [required]
 * @return boolean true if the rating is valid. Will throw rating_exception if not
 */
function dataform_rating_validate($params) {
    $ratingarea = $params['ratingarea'];
    $itemid = $params['itemid'];
    $context = $params['context'];

    $df = mod_dataform_dataform::instance(0, $context->instanceid);
    $rating = $df->field_manager->get_field_by_name($ratingarea);

    return $rating->validation($params);
}

// ------------------------------------------------------------
// GRADING
// ------------------------------------------------------------

/**
 * Lists all gradable areas for the advanced grading methods gramework
 *
 * @return array
 */
function dataform_grading_areas_list() {
    return array('activity' => get_string('activity'));
}

/**
 * Return grade for given user or all users.
 * @return array array of grades, false if none
 */
function dataform_get_user_grades($data, $userid = 0) {
    global $CFG;

    if (empty($data->grade)) {
        return null;
    }

    $df = mod_dataform_dataform::instance($data->id);
    return $df->get_user_grades($userid);
}

/**
 * Update grades by firing grade_updated event
 * @param object $data null means all databases
 * @param int $userid specific user only, 0 mean all
 * @param bool $nullifnone
 * @param array $grades
 */
function dataform_update_grades($data = null, $userid = 0, $nullifnone = true, $grades = null) {
    if ($data == null) {
        return;
    }

    if (!$data->grade) {
        dataform_grade_item_delete($data);
        return;
    }

    if ($grades or $grades = dataform_get_user_grades($data, $userid)) {
        dataform_grade_item_update($data, $grades);

    } else if ($userid and $nullifnone) {
        $grade = new stdClass;
        $grade->userid   = $userid;
        $grade->rawgrade = null;
        dataform_grade_item_update($data, $grade);

    } else {
        dataform_grade_item_update($data);
    }
}

/**
 * Update all grades in gradebook.
 *
 * @global object
 */
function dataform_upgrade_grades() {
    global $DB;

    $sql = "SELECT COUNT('x')
              FROM {dataform} d, {course_modules} cm, {modules} m
             WHERE m.name='dataform' AND m.id=cm.module AND cm.instance=d.id";
    $count = $DB->count_records_sql($sql);

    $sql = "SELECT d.*, cm.idnumber AS cmidnumber, d.course AS courseid
              FROM {dataform} d, {course_modules} cm, {modules} m
             WHERE m.name='dataform' AND m.id=cm.module AND cm.instance=d.id";
    $rs = $DB->get_recordset_sql($sql);
    if ($rs->valid()) {
        // Too much debug output
        $pbar = new progress_bar('dataupgradegrades', 500, true);
        $i = 0;
        foreach ($rs as $data) {
            $i++;
            upgrade_set_timeout(60 * 5); // Set up timeout, may also abort execution
            dataform_update_grades($data, 0, false);
            $pbar->update($i, $count, "Updating Dataform grades ($i / $count).");
        }
    }
    $rs->close();
}

/**
 * Update/create grade item for given dataform
 * @param object $data object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return object grade_item
 */
function dataform_grade_item_update($data, $grades = null) {
    global $CFG;
    require_once("$CFG->libdir/gradelib.php");

    $params = array(
        'itemname' => $data->name,
        'idnumber' => isset($data->cmidnumber) ? $data->cmidnumber : null
    );

    if (!$data->grade) {
        $params['gradetype'] = GRADE_TYPE_NONE;

    } else if ($data->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $data->grade;
        $params['grademin']  = 0;

    } else if ($data->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$data->grade;
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/dataform', $data->course, 'mod', 'dataform', $data->id, 0, $grades, $params);
}

/**
 * Delete grade item for given data
 * @param object $data object
 * @return object grade_item
 */
function dataform_grade_item_delete($data) {
    global $CFG;
    require_once("$CFG->libdir/gradelib.php");

    return grade_update('mod/dataform', $data->course, 'mod', 'dataform', $data->id, 0, null, array('deleted' => 1));
}