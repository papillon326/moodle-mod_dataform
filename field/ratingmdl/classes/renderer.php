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
 * @package dataformfield
 * @subpackage e_rating
 * @copyright 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die;

/**
 *
 */
class dataformfield_ratingmdl_renderer extends mod_dataform\pluginbase\dataformfieldrenderer {

    /**
     * Search and collate field patterns that occur in given text.
     * Overriding parent to add regexp patterns of form [[fieldname:count:\d]],
     * which should retrieve the rating count for the specified value.
     *
     * @param string Text that may contain field patterns
     * @return array Field patterns found in the text
     */
    public function search($text, array $patterns = null) {
        $fieldid = $this->_field->id;
        $fieldname = $this->_field->name;

        $found = parent::search($text);

        // Search for counts
        $pattern = "\[\[$fieldname:count:\d+\]\]";
        preg_match_all("/$pattern/", $text, $matches);
        if (!empty($matches[0])) {
            $found = array_merge($found, array_unique($matches[0]));
        }

        return $found;
    }

    /**
     *
     */
    protected function replacements(array $patterns, $entry, array $options = null) {
        global $CFG, $DB;

        $field = $this->_field;
        $fieldname = $field->name;
        $fieldid = $field->id;
        $entryid = $entry->id;
        $edit = !empty($options['edit']);

        // No edit mode
        if ($edit or !$field->get_scaleid()) {
            if ($patterns) {
                $replacements = array();
                foreach ($patterns as $pattern) {
                    switch($pattern) {
                        case "[[$fieldname:count]]":
                        case "[[$fieldname:avg]]":
                        case "[[$fieldname:max]]":
                        case "[[$fieldname:min]]":
                        case "[[$fieldname:sum]]":
                            $str = '-';
                            break;
                        default:
                            $str = '';
                    }
                    $replacements[$pattern] = $str;
                }

                return $replacements;

            } else {
                return null;
            }
        }

        $rating = $field->get_entry_rating($entry);

        $replacements = array();
        foreach ($patterns as $pattern) {
            if ($entry->id > 0 and $rating) {
                $entry->rating = $rating;

                if ($pattern == "[[$fieldname:count]]") {
                    $str = !empty($rating->count) ? $rating->count : '-';

                } else if (strpos($pattern, "[[$fieldname:count:") === 0) {
                    list(, , $value) = explode(':', trim($pattern, '[]'));
                    $str = $this->display_count_for_value($entry, $value);

                } else if ($pattern == "[[$fieldname:avg]]") {
                    $str = ($rating->count and !empty($rating->ratingavg)) ? round($rating->ratingavg, 2) : '-';
                    $str = html_writer::tag('span', $str, array('id' => "ratingavg_{$fieldid}_$entryid"));

                } else if ($pattern == "[[$fieldname:max]]") {
                    $str = ($rating->count and !empty($rating->ratingmax)) ? round($rating->ratingmax, 2) : '-';
                    $str = html_writer::tag('span', $str, array('id' => "ratingmax_{$fieldid}_$entryid"));

                } else if ($pattern == "[[$fieldname:min]]") {
                    $str = ($rating->count and !empty($rating->ratingmin)) ? round($rating->ratingmin, 2) : '-';
                    $str = html_writer::tag('span', $str, array('id' => "ratingmin_{$fieldid}_$entryid"));

                } else if ($pattern == "[[$fieldname:sum]]") {
                    $str = ($rating->count and !empty($rating->ratingsum)) ? round($rating->ratingsum, 2) : '-';
                    $str = html_writer::tag('span', $str, array('id' => "ratingsum_{$fieldid}_$entryid"));

                } else if ($pattern == "[[$fieldname:view]]" or $pattern == "[[$fieldname:viewurl]]") {
                    $str = $this->display_view($entry, $pattern);

                } else if ($pattern == "[[$fieldname:viewinline]]") {
                    $str = $this->display_view_inline($entry);

                } else if ($pattern == "[[$fieldname]]" or $pattern == "[[$fieldname:rate]]") {
                    $str = $this->render_rating($entry);

                } else if ($pattern == "[[$fieldname:avg:bar]]") {
                    $value = !empty($rating) ? round($rating->aggregate[dataformfield_ratingmdl::AGGREGATE_AVG], 2) : 0;
                    $str = $this->display_bar($entry, $value);

                } else if ($pattern == "[[$fieldname:avg:star]]") {
                    $value = !empty($rating) ? round($rating->aggregate[dataformfield_ratingmdl::AGGREGATE_AVG], 2) : 0;
                    $str = $this->display_star($entry, $value);

                } else {
                     $str = '';
                }
                $replacements[$pattern] = $str;
            }
        }
        return $replacements;
    }

    /**
     *
     */
    public function get_aggregations($patterns) {

        $aggr = array(
            dataformfield_ratingmdl::AGGREGATE_AVG => "[[$fieldname:avg]]",
            dataformfield_ratingmdl::AGGREGATE_MAX => "[[$fieldname:max]]",
            dataformfield_ratingmdl::AGGREGATE_MIN => "[[$fieldname:min]]",
            dataformfield_ratingmdl::AGGREGATE_SUM => "[[$fieldname:sum]]"
        );
        if ($aggregations = array_intersect($aggr, $patterns)) {
            return array_keys($aggregations);
        } else {
            return null;
        }
    }

    /**
     *
     */
    public function render_rating($entry) {
        global $CFG, $PAGE;

        $fieldname = $this->_field->name;
        $fieldid = $this->_field->id;
        $entryid = $entry->id;

        if (empty($entry->rating)) {
            return null;
        }

        $rating = $entry->rating;
        if (!$rating->user_can_rate() and !has_capability('mod/dataform:manageratings', $this->_field->get_df()->context)) {
            return null;
        }

        $ratinghtml = ''; // the string we'll return
        $strrate = get_string("rate", "rating");

        // Rating params
        $rateparams = '';
        $rateurl = $rating->get_rate_url();
        $inputs = $rateurl->params();
        foreach ($inputs as $name => $value) {
            $attributes = array('type' => 'hidden', 'class' => 'ratinginput', 'name' => $name, 'value' => $value);
            $rateparams .= html_writer::empty_tag('input', $attributes);
        }

        // Select dropdown
        $scalearray = array(RATING_UNSET_RATING => $strrate. '...') + $rating->settings->scale->scaleitems;
        $scaleattrs = array(
            'class' => 'postratingmenu ratinginput',
            'id' => "ratingmenu_{$fieldid}_$entryid"
        );
        $rateselect = html_writer::select($scalearray, 'rating', $rating->rating, false, $scaleattrs);

        // Submit button
        $attributes = array(
            'type' => 'submit',
            'class' => 'postratingmenusubmit',
            'id' => "ratingpostsubmit_{$fieldid}_$entryid",
            'value' => s(get_string('rate', 'rating'))
        );
        $submitbutton = html_writer::start_tag('span', array('class' => "ratingsubmit"));
        $submitbutton .= html_writer::empty_tag('input', $attributes);
        if (!$rating->settings->scale->isnumeric) {
            $submitbutton .= $this->help_icon_scale($rating->settings->scale->courseid, $rating->settings->scale);
        }
        $submitbutton .= html_writer::end_tag('span');

        $wrapper = html_writer::tag('div', $rateparams. $rateselect. $submitbutton, array('class' => 'ratingform'));

        // Start the rating form
        $formattrs = array(
            'id' => "ratingpost_{$fieldid}_$entryid",
            'class'  => 'postratingform',
            'method' => 'post',
            'action' => $rateurl->out_omit_querystring()
        );
        $rateform = html_writer::tag('form', $wrapper, $formattrs);

        // Initialize ajax
        $config = array(array('fieldid' => $fieldid, 'entryid' => $entryid));
        $this->initialise_rating_javascript($PAGE, $config);

        return $rateform;
    }

    /**
     *
     */
    protected function display_view($entry, $tag) {
        global $OUTPUT;

        $fieldname = $this->_field->name;

        if (isset($entry->rating)) {
            $rating = $entry->rating;
            if ($rating->settings->permissions->viewall
                and $rating->settings->pluginpermissions->viewall) {

                $nonpopuplink = $rating->get_view_ratings_url();
                $popuplink = $rating->get_view_ratings_url(true);
                $popupaction = new popup_action('click', $popuplink, 'ratings', array('height' => 400, 'width' => 600));

                if ($tag == "[[$fieldname:view]]") {
                    return $OUTPUT->action_link($nonpopuplink, 'view all', $popupaction);
                } else {
                    return $popuplink;
                }
            }
        }
        return '';
    }

    /**
     *
     */
    protected function display_count_for_value($entry, $value) {
        $field = $this->_field;

        if (!isset($entry->rating->itemid)) {
            return 0;
        }

        $rating = $entry->rating;
        $canviewall = ($rating->settings->permissions->viewall and $rating->settings->pluginpermissions->viewall);
        if (!$canviewall) {
            return 0;
        }

        if (!$records = $field->get_rating_records(array('itemid' => $entry->id, 'rating' => $value))) {
            return 0;
        }

        return count($records);
    }

    /**
     *
     */
    protected function display_view_inline($entry) {
        global $OUTPUT;

        if (!isset($entry->rating->itemid)) {
            return null;
        }

        $rating = $entry->rating;
        $canviewall = ($rating->settings->permissions->viewall and $rating->settings->pluginpermissions->viewall);
        if (!$canviewall) {
            return null;
        }

        if (!$records = $this->_field->get_rating_records(array('itemid' => $entry->id))) {
            return null;
        }

        $scalemenu = make_grades_menu($rating->settings->scale->id);

        $table = new html_table;
        $table->cellpadding = 3;
        $table->cellspacing = 3;
        $table->attributes['class'] = 'generalbox ratingtable';
        $table->colclasses = array('', 'firstname', 'rating', 'time');
        $table->data = array();

        // If the scale was changed after ratings were submitted some ratings may have a value above the current maximum
        // We can't just do count($scalemenu) - 1 as custom scales start at index 1, not 0
        $maxrating = $rating->settings->scale->max;

        foreach ($records as $raterecord) {
            // Undo the aliasing of the user id column from user_picture::fields()
            // we could clone the rating object or preserve the rating id if we needed it again
            // but we don't
            $raterecord->id = $raterecord->userid;

            $row = new html_table_row();
            $row->attributes['class'] = 'ratingitemheader';
            $row->cells[] = $OUTPUT->user_picture($raterecord, array('courseid' => $this->_field->get_df()->course->id));
            $row->cells[] = fullname($raterecord);
            if ($raterecord->rating > $maxrating) {
                $raterecord->rating = $maxrating;
            }
            $row->cells[] = $scalemenu[$raterecord->rating];
            $row->cells[] = userdate($raterecord->timemodified, get_string('strftimedate', 'langconfig'));
            $table->data[] = $row;
        }
        return html_writer::table($table);
    }

    /**
     *
     */
    protected function display_bar($entry, $value) {
        if (isset($entry->rating) and $value) {
            $rating = $entry->rating;

            $width = round(($value / $rating->settings->scale->max) * 100);
            $displayvalue = round($value, 2);
            $bar = html_writer::tag('div', '.', array('style' => "width:$width%;height:100%;background:gold;color:gold"));
            return $bar;
        }
        return '';
    }

    /**
     *
     */
    protected function display_star($entry, $value) {
        global $OUTPUT;

        if (isset($entry->rating)) {
            $rating = $entry->rating;
            $numstars = $rating->settings->scale->max;
            $width = $numstars * 20;

            $innerstyle = 'width:100%;height:19px;position:absolute;top:0;left:0;';
            $bgdiv = html_writer::tag('div', '.', array('style' => "background:#ccc;color:#ccc;$innerstyle"));
            $bar = html_writer::tag('div', $this->display_bar($entry, $value), array('style' => "z-index:5;$innerstyle"));
            $stars = implode('', array_fill(0, $numstars, $OUTPUT->pix_icon('star_grey', '', 'dataformfield_ratingmdl', array('style' => 'float:left;'))));
            $starsdiv = html_writer::tag('div', $stars, array('style' => "z-index:10;$innerstyle"));
            $wrapper = html_writer::tag('div', "$bgdiv $bar $starsdiv", array('style' => "width:{$width}px;position:relative;"));
            return $wrapper;
        }
        return '';
    }

    /**
     * Initialises JavaScript to enable AJAX ratings on the provided page.
     *
     * @param moodle_page $page
     * @return true always returns true
     */
    protected function initialise_rating_javascript(moodle_page $page, $config = null) {
        global $CFG;

        if (!empty($CFG->enableajax)) {
            $page->requires->yui_module('moodle-dataformfield_ratingmdl-rater', 'M.dataformfield_ratingmdl.rater.init', $config);
        }

        return true;
    }

    /**
     * Array of patterns this field supports
     */
    protected function patterns() {
        $fieldname = $this->_field->name;

        $patterns = parent::patterns();
        $patterns["[[$fieldname]]"] = array(true, $fieldname);
        $patterns["[[$fieldname:rate]]"] = array(true, $fieldname);
        $patterns["[[$fieldname:view]]"] = array(true, $fieldname);
        $patterns["[[$fieldname:viewurl]]"] = array(true, $fieldname);
        $patterns["[[$fieldname:viewinline]]"] = array(true, $fieldname);
        $patterns["[[$fieldname:avg]]"] = array(true, $fieldname);
        $patterns["[[$fieldname:count]]"] = array(true, $fieldname);
        $patterns["[[$fieldname:max]]"] = array(true, $fieldname);
        $patterns["[[$fieldname:min]]"] = array(true, $fieldname);
        $patterns["[[$fieldname:sum]]"] = array(true, $fieldname);

        return $patterns;
    }
}