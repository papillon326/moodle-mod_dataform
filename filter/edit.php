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
 * @package mod_dataform
 * @category filter
 * @copyright 2013 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');

$urlparams = new stdClass;
$urlparams->d = required_param('d', PARAM_INT);             // Dataform id
$urlparams->fid = optional_param('fid', 0 , PARAM_INT);          // Update filter id

// Set a dataform object
$df = mod_dataform_dataform::instance($urlparams->d);

$df->set_page('filter/edit', array('urlparams' => $urlparams));
$df->require_manage_permission('filters');

// Activate navigation node
navigation_node::override_active_url(new moodle_url('/mod/dataform/filter/index.php', array('id' => $df->cm->id)));

$fm = mod_dataform_filter_manager::instance($urlparams->d);

if ($urlparams->fid) {
    $filter = $fm->get_filter_by_id($urlparams->fid);
} else {
    $filter = $fm->get_filter_blank();
}

$mform = $fm->get_filter_form($filter);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/dataform/filter/index.php', array('d' => $df->id)));
}

if ($mform->no_submit_button_pressed() ) {
    // No submit buttons: return to form
    // Get clean filter from submitted data
    if ($data = $mform->get_submitted_data()) {
        $filter = $fm->get_filter_from_form($filter, $data, true);
        $mform = $fm->get_filter_form($filter);
    }

} else if ($data = $mform->get_data()) {
    // Process validated
    // Get clean filter from data
    $filter = $fm->get_filter_from_form($filter, $data, true);
    $filter->update();

    if ($data->submitbutton != get_string('savecont', 'dataform')) {
        redirect(new moodle_url('/mod/dataform/filter/index.php', array('d' => $df->id)));
    }
}

$output = $df->get_renderer();
echo $output->header(array('tab' => 'filters', 'heading' => $df->name, 'nonotifications' => true, 'urlparams' => $urlparams));

$streditinga = $filter->id ? get_string('filteredit', 'dataform', $filter->name) : get_string('filternew', 'dataform');
echo html_writer::tag('h2', format_string($streditinga), array('class' => 'mdl-align'));

// Display form
$mform->display();

echo $output->footer();