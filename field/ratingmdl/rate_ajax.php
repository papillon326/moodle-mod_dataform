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
 * This page receives ratingmdl ajax rating submissions
 *
 * Similar to rating/rate_ajax.php except for it allows retrieving multiple aggregations.
 *
 * @package dataformfield
 * @subpackage ratingmdl
 * @copyright 2013 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../../../config.php');
require_once('ratinglib.php');

$contextid         = required_param('contextid', PARAM_INT);
$component         = required_param('component', PARAM_COMPONENT);
$ratingarea        = required_param('ratingarea', PARAM_AREA);
$itemid            = required_param('itemid', PARAM_INT);
$scaleid           = required_param('scaleid', PARAM_INT);
$userrating        = required_param('rating', PARAM_INT);
$rateduserid       = required_param('rateduserid', PARAM_INT); // Which user is being rated. Required to update their grade
$aggregationmethod = optional_param('aggregation', RATING_AGGREGATE_NONE, PARAM_SEQUENCE); // We're going to calculate the aggregate and return it to the client

$result = new stdClass;

// If session has expired and its an ajax request so we cant do a page redirect
if (!isloggedin()) {
    $result->error = get_string('sessionerroruser', 'error');
    echo json_encode($result);
    die();
}

list($context, $course, $cm) = get_context_info_array($contextid);
require_login($course, false, $cm);

$contextid = null; // Now we have a context object throw away the id from the user
$PAGE->set_context($context);
$PAGE->set_url('/mod/dataform/field/ratingmdl/rate_ajax.php', array('contextid' => $context->id));

if (!confirm_sesskey() || !has_capability('moodle/rating:rate', $context)) {
    echo $OUTPUT->header();
    echo get_string('ratepermissiondenied', 'rating');
    echo $OUTPUT->footer();
    die();
}

$rm = new ratingmdl_rating_manager();

// Check the module rating permissions
// Doing this check here rather than within rating_manager::get_ratings() so we can return a json error response
$pluginpermissionsarray = $rm->get_plugin_permissions_array($context->id, $component, $ratingarea);

if (!$pluginpermissionsarray['rate']) {
    $result->error = get_string('ratepermissiondenied', 'rating');
    echo json_encode($result);
    die();
} else {
    $params = array(
        'context'     => $context,
        'component'   => $component,
        'ratingarea'  => $ratingarea,
        'itemid'      => $itemid,
        'scaleid'     => $scaleid,
        'rating'      => $userrating,
        'rateduserid' => $rateduserid,
        'aggregation' => $aggregationmethod
    );
    if (!$rm->check_rating_is_valid($params)) {
        $result->error = get_string('ratinginvalid', 'rating');
        echo json_encode($result);
        die();
    }
}

// Rating options used to update the rating then retrieve the aggregations
$ratingoptions = new stdClass;
$ratingoptions->context = $context;
$ratingoptions->ratingarea = $ratingarea;
$ratingoptions->component = $component;
$ratingoptions->itemid  = $itemid;
$ratingoptions->scaleid = $scaleid;
$ratingoptions->userid  = $USER->id;

if ($userrating != RATING_UNSET_RATING) {
    $rating = new rating($ratingoptions);
    $rating->update_rating($userrating);
} else {
    // Delete the rating if the user set to Rate...
    $options = new stdClass;
    $options->contextid = $context->id;
    $options->component = $component;
    $options->ratingarea = $ratingarea;
    $options->userid = $USER->id;
    $options->itemid = $itemid;

    $rm->delete_ratings($options);
}

// Future possible enhancement: add a setting to turn grade updating off for those who don't want them in gradebook
// Note that this would need to be done in both rate.php and rate_ajax.php
if ($context->contextlevel == CONTEXT_MODULE) {
    // Tell the module that its grades have changed
    $modinstance = $DB->get_record($cm->modname, array('id' => $cm->instance));
    if ($modinstance) {
        $modinstance->cmidnumber = $cm->id; // MDL-12961
        $functionname = $cm->modname.'_update_grades';
        require_once($CFG->dirroot."/mod/{$cm->modname}/lib.php");
        if (function_exists($functionname)) {
            $functionname($modinstance, $rateduserid);
        }
    }
}

// Need to retrieve the updated item to get its new aggregate value
$item = new stdClass;
$item->id = $itemid;

// Most of $ratingoptions variables were previously set
$ratingoptions->items = array($itemid => $item);
$ratingoptions->aggregate = array(
    RATING_AGGREGATE_AVERAGE,
    RATING_AGGREGATE_MAXIMUM,
    RATING_AGGREGATE_MINIMUM,
    RATING_AGGREGATE_SUM,
);

$items = $rm->get_ratings($ratingoptions);
$firstitem = reset($items);
$firstrating = $firstitem->rating;
$ratingcount = $firstrating->count;
$ratingavg = '';
$ratingmax = '';
$ratingmin = '';
$ratingsum = '';

// Add aggregations
if ($firstrating->user_can_view_aggregate()) {
    $ratingavg = round($firstrating->ratingavg, 2);
    $ratingmax = round($firstrating->ratingmax, 2);
    $ratingmin = round($firstrating->ratingmin, 2);
    $ratingsum = round($firstrating->ratingsum, 2);

    // For custom scales return text not the value
    // This scales weirdness will go away when scales are refactored
    if ($firstrating->settings->scale->id < 0) {
        $scalerecord = $DB->get_record('scale', array('id' => -$firstrating->settings->scale->id));
        $scalearray = explode(',', $scalerecord->scale);

        $ratingavg = $scalearray[round($ratingavg) - 1];
        $ratingmax = $scalearray[round($ratingmax) - 1];
        $ratingmin = $scalearray[round($ratingmin) - 1];
        // For sum take the highest
        if (round($ratingsum, 1) > count($scalearray)) {
            $ratingsum = count($scalearray);
        }
        $ratingsum = $scalearray[round($ratingsum) - 1];
    }
}

// Result
$result->success = true;
$result->ratingcount = $ratingcount;
$result->ratingavg = $ratingavg;
$result->ratingmax = $ratingmax;
$result->ratingmin = $ratingmin;
$result->ratingsum = $ratingsum;
$result->itemid = $itemid;

echo json_encode($result);