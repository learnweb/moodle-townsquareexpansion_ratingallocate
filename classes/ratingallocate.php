<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Core class of the subplugin. This class is accessed by townsquaresupport.
 *
 * @package     townsquareexpansion_ratingallocate
 * @copyright   2024 Tamaro Walter
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace townsquareexpansion_ratingallocate;

defined('MOODLE_INTERNAL') || die();

use local_townsquaresupport\townsquaresupportinterface;

global $CFG;
require_once($CFG->dirroot . '/blocks/townsquare/locallib.php');

/**
 * Class that implements the townsquaresupportinterface with the function to get the events from the plugin.
 *
 * @package     townsquareexpansion_ratingallocate
 * @copyright   2024 Tamaro Walter
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ratingallocate implements townsquaresupportinterface {

    /**
     * Function from the interface.
     * @return array
     */
    public static function get_events(): array {
        global $DB;
        // If ratingallocate is not installed or not activated, return empty array.
        if (!$DB->get_record('modules', ['name' => 'ratingallocate', 'visible' => 1])) {
            return [];
        }

        // Get important parameters directly from townsquare.
        $courses = townsquare_get_courses();
        $timestart = townsquare_get_timestart();
        $timeend = townsquare_get_timeend();

        // Get all ratingallocate events.
        $ratingallocateevents = self::get_events_from_db($courses, $timestart, $timeend);

        // Filter out events that the user should not see.
        foreach ($ratingallocateevents as $key => $event) {
            if (townsquare_filter_availability($event) ||
                ($event->eventtype == "expectcompletionon" && townsquare_filter_activitycompletions($event))) {
                unset($ratingallocateevents[$key]);
                continue;
            }

            // Add the instance name to the event.
            $event->instancename = $DB->get_field($event->modulename, 'name', ['id' => $event->instance]);

        }

        return $ratingallocateevents;
    }

    private static function get_events_from_db($courses, $timestart, $timeend): array {
        global $DB;

        // Prepare the courses parameter for sql query.
        list($insqlcourses, $inparamscourses) = $DB->get_in_or_equal($courses, SQL_PARAMS_NAMED);
        $params = ['timestart' => $timestart, 'timeduration' => $timestart, 'timeend' => $timeend, 'courses' => $inparamscourses]
                  + $inparamscourses;

        // Set the sql statement.
        $sql = "SELECT e.id, e.name, e.courseid, cm.id AS coursemoduleid, cm.availability AS availability, e.groupid, e.userid,
                       e.modulename, e.instance, e.eventtype, e.timestart, e.timemodified, e.visible
                FROM {event} e
                JOIN {modules} m ON e.modulename = m.name
                JOIN {course_modules} cm ON (cm.course = e.courseid AND cm.module = m.id AND cm.instance = e.instance)
                WHERE (e.timestart >= :timestart OR e.timestart+e.timeduration > :timeduration)
                      AND e.timestart <= :timeend
                      AND e.courseid $insqlcourses
                      AND e.modulename = 'ratingallocate'
                      AND m.visible = 1
                      AND (e.name NOT LIKE '" .'0'. "' AND e.eventtype NOT LIKE '" .'0'. "' )
                      AND (e.instance <> 0 AND e.visible = 1)
                ORDER BY e.timestart DESC";

        // Get all events.
        return $DB->get_records_sql($sql, $params);
    }
}