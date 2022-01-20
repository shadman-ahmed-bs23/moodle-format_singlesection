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
 * singlesection course format. Display the whole course as "singlesection" made of modules.
 *
 * @package format_singlesection
 * @copyright 2021 Brain Station 23 Ltd.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG, $PAGE;

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/completionlib.php');
// Horrible backwards compatible parameter aliasing.
if ($topic = optional_param('topic', 0, PARAM_INT)) {
    $url = $PAGE->url;
    $url->param('section', $topic);
    debugging('Outdated topic param passed to course/view.php', DEBUG_DEVELOPER);
    redirect($url);
}
// End backwards-compatible aliasing.

$context = context_course::instance($course->id);
// Retrieve course format option fields and add them to the $course object.
$course = course_get_format($course)->get_course();

if (($marker >= 0) && has_capability('moodle/course:setcurrentsection', $context) && confirm_sesskey()) {
    $course->marker = $marker;
    course_set_marker($course->id, $marker);
}

// Make sure section 0 is created.
course_create_sections_if_missing($course, 0);

$renderer = $PAGE->get_renderer('format_singlesection');

if ($PAGE->user_allowed_editing()) {
        $renderer->print_multiple_section_page($course, null, null, null, null);
} else {
    // If this is a single activity course with customcert then redirect user directly to activity page.
    // No need to show course landing page.
    $modules = get_fast_modinfo($course->id)->get_cms();
    $modules = array_filter($modules, function ($cms){
        return $cms->modname != 'customcert';
    });

    if (count($modules) == 1) {
        $values = array_values($modules);
        $item = array_shift($values);
        redirect($item->url);
    } else {
        if ($displaysection) {
            $renderer->print_single_section_page($course, null, null, null, null, $displaysection);
        } else {
            $renderer->print_course_starting_page($course, null);
        }
    }
}

// Include course format js module.
$PAGE->requires->js('/course/format/topics/format.js');

