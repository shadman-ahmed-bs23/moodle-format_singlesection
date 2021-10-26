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
function get_section_redirect_url($modinfo, $section, $course, $userid)
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
function get_first_activity_url($modinfo, $section, $course) {
    foreach ($modinfo->sections[$section->section] as $cmid) {
        $thismod = $modinfo->cms[$cmid];

        if ($thismod->uservisible) {
            if (!empty($thismod->url)) {
                $urlObj = $thismod->url;
                $url = $urlObj->get_path();
                $id = $urlObj->get_param('id');
                break; // Only the first url is needed.
            }
        }
    }
    // The URL returns like '/moodle/mod/....'.
    // Don't need the '/moodle' part.
    $urlArray = explode('/', $url);

    // Removing first two elements of array. ('/' and 'moodle').
    array_shift($urlArray);
    array_shift($urlArray);

    // Join the array using implode() function
    $urlString = "/";
    $urlString .= implode("/", $urlArray);
    $urlString .= '?id=' . $id;

    // Returns the url as a string to be passed in 'moodle_url()'.
    return $urlString;
}

/**
 * @param $modinfo
 * @param $section
 * @param $course
 * @return null[]
 * @throws coding_exception
 */
function section_last_activity_url($modinfo, $section, $course, $userid, $onlyid)
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
function course_completed($course, $userid)
{
    $cinfo = new completion_info($course);
    return $cinfo->is_course_complete($userid);
}

function progress_bar_info($course, $modid)
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
function course_completion_percentage($course, $userid)
{

    $progressinfo = \core_completion\progress::get_course_progress_percentage($course, $userid);
    var_dump($progressinfo);
    return $progressinfo;
//    $cinfo = new completion_info($course);
//    //var_dump($cinfo);
//    return $cinfo->get_course_progress_percentage($course, $id);
}