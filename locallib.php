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
 * This file contains main class for singlesection course format.
 *
 * @package   format_singlesection
 * @copyright 2021 Brain Station 23 Ltd.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
global $CFG;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/format/lib.php');
//require_once($CFG->dirroot . '/completion/classes/progress.php');



/**
 * @param $modinfo
 * @param $section
 * @param $course
 * @return moodle_url|null
 * @throws coding_exception
 */
function format_singlesection_get_section_redirect_url($modinfo, $section, $course, $userid)
{
    if (empty($modinfo->sections[$section->section])) {
        return null;
    }

    foreach ($modinfo->sections[$section->section] as $cmid) {
        $thismod = $modinfo->cms[$cmid];

        if ($thismod->uservisible) {
            if (!empty($thismod->url)) {
                return $thismod->url;
            }
        }
    }
    return null;

}

/**
 * Returns the first activity url of the course as a string.
 * @param $modinfo
 * @param $section
 * @param $course
 * @return string
 */
function format_singlesection_get_first_activity_url($modules) {
    $urlObj = null;
    foreach ($modules as $module) {
        // Only add activities the user can access, aren't in stealth mode and have a url (eg. mod_label does not).
        if (!$module->uservisible || $module->is_stealth() || empty($module->url)) {
            continue;
        }
        // Module URL.
        $urlObj = new moodle_url($module->url, array('forceview' => 1));
        break;
    }
    return $urlObj;

}

/**
 * Returns the  activity url of the customcert of the course as a ur..
 * @param $modinfo
 * @param $section
 * @param $course
 * @return string
 */

function format_singlesection_get_certificate_activity_url($modules) {
    $urlObj = null;
    foreach ($modules as $module) {
        // Only add activities the user can access, aren't in stealth mode and have a url (eg. mod_label does not).
        if (!$module->uservisible || $module->is_stealth() || empty($module->url)) {
            continue;
        }
        // Module URL.
        if($module->modname == 'customcert') {
            $urlObj = new moodle_url($module->url, array('downloadown' => 1));
            break;
        }
        // break;
    }
    return $urlObj;
}

/**
 * @param $course
 * @param $userid
 * @param $modules
 * @return moodle_url|null
 * @throws moodle_exception
 */
function format_singlesection_resumed_course_activity_url($course, $userid,$modules) {
    $urlObj = null;
    $completion = new \completion_info($course);
    foreach ($modules as $module) {
        // Only add activities the user can access, aren't in stealth mode and have a url (eg. mod_label does not).
        if (!$module->uservisible || $module->is_stealth() || empty($module->url)) {
            continue;
        }

        $data = $completion->get_data($module, true, $userid);
        $completed = $data->completionstate == COMPLETION_INCOMPLETE ? 0 : 1;
        // Module URL.
        if(!$completed) {
            $urlObj = new moodle_url($module->url, array('forceview' => 1));
            break;
        }
    }
    return $urlObj;
}



/**
 * @param $modinfo
 * @param $section
 * @param $course
 * @return null[]
 * @throws coding_exception
 */
function format_singlesection_section_last_activity_url($modinfo, $section, $course, $userid, $onlyid)
{
    if (empty($modinfo->sections[$section->section])) {
        return [null,null];
    }

    // Generate array with count of activities in this section.
    foreach (array_reverse($modinfo->sections[$section->section]) as $cmid) {
        $thismod = $modinfo->cms[$cmid];

        if ($thismod->uservisible) {

            return [$thismod->id, $thismod->url];
        }
    }
    return [null,null];

}

/**
 * @param $course
 * @param $userid
 * @return bool
 */
function format_singlesection_course_completed($course, $userid)
{
    $cinfo = new completion_info($course);
    return $cinfo->is_course_complete($userid);
}

function format_singlesection_progress_bar_info($course, $modid)
{
    $modinfo = get_fast_modinfo($course);

    $sections = $modinfo->get_section_info_all();

    $mods = [];

    foreach ($sections as $section => $thissection) :

        if ($thissection->section == 0) continue;

        if (empty($modinfo->sections[$thissection->section])) {

            continue;
        }
        foreach ($modinfo->sections[$thissection->section] as $cmid) :

            $thismod = $modinfo->cms[$cmid];

//            if ($thismod->uservisible) {

            // Temporary solution for delete in progress bug
            if ($thismod->uservisible && !$thismod->deletioninprogress) {

                $mods[$thismod->id] = $thismod;
            }

        endforeach;

    endforeach;

    $total = count($mods);

    $mods = array_keys($mods);

    $position = array_search($modid, $mods);

    return [$total, $position];
}

function format_singlesection_get_certificate_module_url($course, $userid)
{
    global $DB;

    $modinfo = get_fast_modinfo($course);
    $sections = $modinfo->get_section_info_all();

    foreach ($sections as $section => $thissection) :
        foreach ($modinfo->sections[$thissection->section] as $cmid) :

            $thismod = $modinfo->cms[$cmid];
            if ($thismod->modname == 'customcert') {
                return $thismod->url;
            }
        endforeach;
    endforeach;

    $last_section = array_key_last($sections);

    return new moodle_url('/course/view.php', [
        'id' => $course->id,
        'section' => $last_section ?? 1,
    ]);


}

/**
 * @param $course
 * @param $userid
 * @return $cinfo
 */
function format_singlesection_course_completion_percentage($course, $userid)
{

    $progressinfo = \core_completion\progress::get_course_progress_percentage($course, $userid);
    return $progressinfo;
}