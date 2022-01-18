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
 * Renderer for outputting the singlesection course format.
 *
 * @package format_singlesection
 * @copyright 2021 Brain Station 23 Ltd.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_favourites\service_factory;

defined('MOODLE_INTERNAL') || die();

const BUTTONCLASS = 'btn btn-primary mt-1 btn-block';

require_once($CFG->dirroot . '/course/format/renderer.php');
require_once($CFG->dirroot . '/course/format/singlesection/locallib.php');
require_once($CFG->dirroot . '/course/format/singlesection/singlesection_common_functions.php');

/**
 * Basic renderer for singlesection format.
 *
 * @copyright 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_singlesection_renderer extends format_section_renderer_base
{

    /**
     * Constructor method, calls the parent constructor.
     *
     * @param moodle_page $page
     * @param string $target one of rendering target constants
     */
    public function __construct(moodle_page $page, $target)
    {
        parent::__construct($page, $target);

        // Since format_topics_renderer::section_edit_control_items() only displays the 'Highlight' control
        // when editing mode is on we need to be sure that the link 'Turn editing mode on' is available for a user
        // who does not have any other managing capability.
        $page->set_other_editing_capability('moodle/course:setcurrentsection');
    }


    /**
     * Output the html for a single section page .
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     * @param int $displaysection The section number in the course which is being displayed
     */
    public function print_course_landing_page($course, $sections, $mods, $modnames, $modnamesused, $displaysection)
    {
        global $DB, $COURSE, $USER;

        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();

        $format_options = course_get_format($course)->get_settings();

        // Can we view the section in question?
        if (!($sectioninfo = $modinfo->get_section_info($displaysection))) {
            // This section doesn't exist.
            print_error('unknowncoursesection', 'error', null, $course->fullname);
            return;
        }

        if (!$sectioninfo->uservisible) {
            if (!$course->hiddensections) {
                echo $this->start_section_list();
                echo $this->section_hidden($displaysection, $course->id);
                echo $this->end_section_list();
            }
            // Can't view this section.
            return;
        }

        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, $displaysection);
        $thissection = $modinfo->get_section_info(0);

        // Start single-section div.
        echo html_writer::start_tag('div', array('class' => 'single-section'));

        // The requested section page.
        $thissection = $modinfo->get_section_info($displaysection);

        // Title with section navigation links.
        $sectionnavlinks = $this->get_nav_links($course, $modinfo->get_section_info_all(), $displaysection);
        $sectiontitle = '';
        $sectiontitle .= html_writer::start_tag('div', array('class' => 'section-title-container'));
        // Title attributes.
        $classes = 'sectionname';
        if (!$thissection->visible) {
            $classes .= ' dimmed_text';
        }
        $sectionname = html_writer::tag('span', $this->section_title_without_link($thissection, $course));
        $sectiontitle .= $this->output->heading($sectionname, 3, $classes);

        $sectiontitle .= html_writer::end_tag('div');


        echo html_writer::start_div('row');
        echo html_writer::start_div('col-md-6');
        echo $sectiontitle;
        echo $thissection->summary;

        echo html_writer::tag('a',
            html_writer::tag('button',
                html_writer::tag('span', get_string('savedata', 'format_singlesection')) .
                '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;          ' .
                html_writer::tag('i', '', ['class' => 'fas fa-arrow-right closed'])
                , [
                    'name' => 'btn_info',
                    'type' => 'submit',
                    'class' => 'btn btn-primary mt-3',
                ])
            , [
                'href' => new moodle_url('/course/view.php', [
                    'id' => $course->id,
                    'section' => 1,
                ])
            ]);
        echo html_writer::end_div();
        echo html_writer::start_div('col-md-6');
        echo $format_options['media'];
        echo html_writer::end_div();
        echo html_writer::end_div();

        $completioninfo = new completion_info($course);
        echo $completioninfo->display_help_icon();

//        echo $this->courserenderer->course_section_cm_list($course, $thissection, $displaysection);
        echo $this->courserenderer->course_section_add_cm_control($course, $displaysection, $displaysection);
        echo $this->section_footer();
        // Close single-section div.
        echo html_writer::end_tag('div');
    }


    /**
     * Output the html for a single section page .
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     * @param int $displaysection The section number in the course which is being displayed
     */
    public function print_single_section_page($course, $sections, $mods, $modnames, $modnamesused, $displaysection)
    {
        global $CFG, $DB, $USER;
        $modinfo = get_fast_modinfo($course);
        $courseformat = course_get_format($course);
        $course = $courseformat->get_course();

        // Can we view the section in question?
        if (!($sectioninfo = $modinfo->get_section_info($displaysection))) {
            // This section doesn't exist.
            print_error('unknowncoursesection', 'error', null, $course->fullname);
            return;
        }

        if (!$sectioninfo->uservisible) {
            if (!$course->hiddensections) {
                echo $this->start_section_list();
                echo $this->section_hidden($displaysection, $course->id);
                echo $this->end_section_list();
            }
            // Can't view this section.
            return;
        }
        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, $displaysection);

        // Start single-section div.
        echo html_writer::start_tag('div', array('class' => 'single-section'));

        // The requested section page.
        $thissection = $modinfo->get_section_info($displaysection);

        // Title with section navigation links.
        $sectionnavlinks = $this->get_nav_links($course, $modinfo->get_section_info_all(), $displaysection);
        $sectiontitle = '';
        $sectiontitle .= html_writer::start_tag('div', array('class' => 'section-title-container'));
        // Title attributes.
        $classes = 'sectionname';
        if (!$thissection->visible) {
            $classes .= ' dimmed_text';
        }
        $sectionname = html_writer::tag('span', $this->section_title_without_link($thissection, $course));
        $sectiontitle .= $this->output->heading($sectionname, 3, $classes);

        $sectiontitle .= html_writer::end_tag('div');
        echo $sectiontitle;

        echo $this->start_section_list();

        echo $this->section_header($thissection, $course, true, $displaysection);

        $completioninfo = new completion_info($course);
        echo $completioninfo->display_help_icon();

        echo $this->courserenderer->course_section_cm_list($course, $thissection, $displaysection);
        echo $this->courserenderer->course_section_add_cm_control($course, $displaysection, $displaysection);
        echo $this->section_footer();
        echo $this->end_section_list();

        // Close single-section div.
        echo html_writer::end_tag('div');
    }


    /**
     * Output the html for a multiple section page
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     */
    public function print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused)
    {

        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();

        $context = context_course::instance($course->id);
        // Title with completion help icon.
        $completioninfo = new completion_info($course);
        echo $completioninfo->display_help_icon();
        echo $this->output->heading($this->page_title(), 2, 'accesshide');

        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, 0);

        // Now the list of sections..
        echo $this->start_section_list();
        $numsections = course_get_format($course)->get_last_section_number();

        $hasrow = false;
        foreach ($modinfo->get_section_info_all() as $section => $thissection) {
            if ($section == 0) {
                // 0-section is displayed a little different then the others.
                if ($thissection->summary or !empty($modinfo->sections[0]) or $this->page->user_is_editing()) {
                    echo $this->section_header($thissection, $course, false, 0);
                    echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                    echo $this->courserenderer->course_section_add_cm_control($course, 0, 0);
                    echo $this->section_footer();
                }
                continue;
            }
            if ($section > $numsections) {
                // Activities inside this section are 'orphaned', this section will be printed as 'stealth' below.
                continue;
            }

            if (!$hasrow && $course->coursedisplay) {
                // Initialize or reinitialize a new row to print the course sections boxes.
                echo "<div class='course-sections'>";

                $hasrow = true;
            }

            // Show the section if the user is permitted to access it, OR if it's not available
            // but there is some available info text which explains the reason & should display.
            $showsection = $thissection->uservisible ||
                ($thissection->visible && !$thissection->available &&
                    !empty($thissection->availableinfo));
            if (!$showsection) {
                // If the hiddensections option is set to 'show hidden sections in collapsed
                // form', then display the hidden section message - UNLESS the section is
                // hidden by the availability system, which is set to hide the reason.
                if (!$course->hiddensections && $thissection->available) {
                    echo $this->section_hidden($section, $course->id);
                }

                continue;
            }

            if (!$this->page->user_is_editing() && $course->coursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                // Display section summary only.
                echo $this->section_summary($thissection, $course, null);
            } else {
                echo $this->section_header($thissection, $course, false, 0);
                if ($thissection->uservisible) {
                    echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                    echo $this->courserenderer->course_section_add_cm_control($course, $section, 0);
                }
                echo $this->section_footer();
            }

            if (!($section % 2) && $course->coursedisplay) {
                // There are at least 3 columns. So it's time to create a new row.
                $hasrow = false;

                echo "</div>";
            }
        }

        if ($hasrow && $course->coursedisplay) {
            // We need to close the opened row.
            echo "</div>";
        }

        if ($this->page->user_is_editing() and has_capability('moodle/course:update', $context)) {
            // Print stealth sections if present.
            foreach ($modinfo->get_section_info_all() as $section => $thissection) {
                if ($section <= $numsections or empty($modinfo->sections[$section])) {
                    // This is not stealth section or it is empty.
                    continue;
                }
                echo $this->stealth_section_header($section);
                echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                echo $this->stealth_section_footer();
            }

            echo $this->end_section_list();

            echo $this->change_number_sections($course, 0);
        } else {
            echo $this->end_section_list();
        }

    }
    /**
     * Output the html for the course starting page.
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @throws moodle_exception
     */
    public function print_course_starting_page($course) {
        global $USER, $OUTPUT;

        // Fetch course format.
        $singlesection_format = course_get_format($course);
        $course = $singlesection_format->get_course();
        $format_options = $singlesection_format->get_settings();
        $modinfo = get_fast_modinfo($course);

        // Get course completion percentage.
        $percentage = floor(format_singlesection_course_completion_percentage($course, $USER->id));

        //Get the url for start course button.
        $url = format_singlesection_get_first_activity_url($modinfo->get_cms());

        // Get the url for resume course button.
        $url = format_singlesection_resumed_course_activity_url($course, $userid, $modinfo->get_cms()) ?? $url;

        // Get the url of the custom certificate activity of the url.
        $lastactivityurl = format_singlesection_get_certificate_activity_url($modinfo->get_cms());

        $startcourse = $percentage == 0;
        $iscompleted = $percentage == 100;

        $bg_image = get_course_image();
        $bg_image = !empty($bg_image) ? $bg_image : $OUTPUT->image_url('default_course_image', 'format_singlesection')->out();

        $templatecontext = [
            'url' => $url,
            'lastactivityurl' => $lastactivityurl,
            'startcourse' => $startcourse,
            'iscompleted' => $iscompleted,
            'coursename' => $course->fullname,
            'imageurl' => $bg_image,
            'progresspercentage' => $percentage,
            'coursesummary' => $course->summary,
        ];

        echo $OUTPUT->render_from_template('format_singlesection/course_landing_page_content', $templatecontext);
    }

    public function is_favourite()
    {
        global $USER, $COURSE;
        $usercontext = context_user::instance($USER->id);
        $ufservice = service_factory::get_service_for_user_context($usercontext);
        $is_favourite =  $ufservice->favourite_exists('core_course', 'courses', $COURSE->id,
            \context_course::instance($COURSE->id));

        if($is_favourite):
            return html_writer::tag('i','',
                array(
                    'class' => 'ami-star text-primary fa fa-star star pl-2 pr-2',
                    'data-action' => 'remove-favourite',
                    'data-courseid' => $COURSE->id,

                )
            );
        else:
            return html_writer::tag('i','',
                array(
                    'class' => 'ami-star fa fa-star star pl-2 pr-2',
                    'data-action' => 'add-favourite',
                    'data-courseid' => $COURSE->id,


                )
            );
        endif;
    }


    /**
     * Generate the starting container html for a list of sections.
     *
     * @return string HTML to output.
     */
    protected function start_section_list()
    {
        return html_writer::start_tag('ul', ['class' => 'topics']);
    }

    /**
     * Generate the closing container html for a list of sections.
     *
     * @return string HTML to output.
     */
    protected function end_section_list()
    {
        return html_writer::end_tag('ul');
    }

    /**
     * Generate the title for this section page.
     *
     * @return string the page title
     */
    protected function page_title()
    {
        return get_string('topicoutline');
    }

    /**
     * Generate the section title, wraps it in a link to the section page if page is to be displayed on a separate page.
     *
     * @param section_info|stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title($section, $course)
    {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section));
    }

    /**
     * Generate the section title to be displayed on the section page, without a link.
     *
     * @param section_info|stdClass $section The course_section entry from DB
     * @param int|stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title_without_link($section, $course)
    {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, false));
    }

    /**
     * Generate the edit control items of a section.
     *
     * @param int|stdClass $course The course entry from DB
     * @param section_info|stdClass $section The course_section entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return array of edit control items
     */
    protected function section_edit_control_items($course, $section, $onsectionpage = false)
    {
        if (!$this->page->user_is_editing()) {
            return [];
        }

        $coursecontext = context_course::instance($course->id);

        if ($onsectionpage) {
            $url = course_get_url($course, $section->section);
        } else {
            $url = course_get_url($course);
        }
        $url->param('sesskey', sesskey());

        $controls = [];
        if ($section->section && has_capability('moodle/course:setcurrentsection', $coursecontext)) {
            if ($course->marker == $section->section) {  // Show the "light globe" on/off.
                $url->param('marker', 0);
                $highlightoff = get_string('highlightoff');
                $controls['highlight'] = [
                    'url' => $url,
                    'icon' => 'i/marked',
                    'name' => $highlightoff,
                    'pixattr' => ['class' => ''],
                    'attr' => [
                        'class' => 'editing_highlight',
                        'data-action' => 'removemarker'
                    ],
                ];
            } else {
                $url->param('marker', $section->section);
                $highlight = get_string('highlight');
                $controls['highlight'] = [
                    'url' => $url,
                    'icon' => 'i/marker',
                    'name' => $highlight,
                    'pixattr' => ['class' => ''],
                    'attr' => [
                        'class' => 'editing_highlight',
                        'data-action' => 'setmarker'
                    ],
                ];
            }
        }

        $parentcontrols = parent::section_edit_control_items($course, $section, $onsectionpage);

        // If the edit key exists, we are going to insert our controls after it.
        if (array_key_exists("edit", $parentcontrols)) {
            $merged = [];
            // We can't use splice because we are using associative arrays.
            // Step through the array and merge the arrays.
            foreach ($parentcontrols as $key => $action) {
                $merged[$key] = $action;
                if ($key == "edit") {
                    // If we have come to the edit key, merge these controls here.
                    $merged = array_merge($merged, $controls);
                }
            }

            return $merged;
        } else {
            return array_merge($controls, $parentcontrols);
        }
    }
}
