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
 * mod_dataform access validators.
 *
 * @package    mod_dataform
 * @copyright  2013 Itamar Tzadok {@link http://substantialmethods.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dataform\access;

defined('MOODLE_INTERNAL') || die();

/**
 * mod_dataform entry delete permission class.
 *
 * @package    mod_dataform
 * @copyright  2013 Itamar Tzadok {@link http://substantialmethods.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entry_delete extends base {

    /**
     * @return bool
     */
    public static function validate($params) {
        $dataformid = $params['dataformid'];

        $df = \mod_dataform_dataform::instance($dataformid);

        // Unspecified entry
        if (empty($params['entry'])) {
            return self::has_capability('mod/dataform:entryanydelete', $params);
        }

        // Early entries
        if ($df->is_early()) {
            $params['capabilities'] = array('mod/dataform:entryearlydelete');
            if (!parent::validate($params)) {
                return false;
            }
        }

        // Late entries
        if ($df->is_past_due()) {
            $params['capabilities'] = array('mod/dataform:entrylatedelete');
            if (!parent::validate($params)) {
                return false;
            }
        }

        $entry = !empty($params['entry']) ? $params['entry'] : \mod_dataform\pluginbase\dataformentry::blank_instance($df);

        // Own entry
        if (\mod_dataform\pluginbase\dataformentry::is_own($entry)) {
            $params['capabilities'] = array('mod/dataform:entryowndelete');
            return parent::validate($params);
        }

        // Group entry
        if (\mod_dataform\pluginbase\dataformentry::is_grouped($entry)) {
            if (groups_is_member($entry->groupid)) {
                $params['capabilities'] = array('mod/dataform:entrygroupdelete');
                return parent::validate($params);
            }
        }

        // Anonymous entry
        if (\mod_dataform\pluginbase\dataformentry::is_anonymous($entry)) {
            if (!$df->anonymous) {
                return false;
            }
            $params['capabilities'] = array('mod/dataform:entryanonymousdelete');
            return parent::validate($params);
        }

        // Any entry
        if (\mod_dataform\pluginbase\dataformentry::is_others($entry)) {
            $params['capabilities'] = array('mod/dataform:entryanydelete');
            return parent::validate($params);
        }

        return false;
    }

    /**
     * @return null|array
     */
    public static function get_rules(\mod_dataform_access_manager $man, array $params) {
        $viewid = $params['viewid'];

        return $man->get_type_rules('entry');
    }

    /**
     * @return array
     */
    public static function get_capabilities() {
        return array();
    }
}