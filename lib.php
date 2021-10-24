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
require_once($CFG->dirroot. '/course/format/lib.php');

use core\output\inplace_editable;

/**
 * Main class for the singlesection course format.
 *
 * @package    format_singlesection
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_singlesection extends format_base {

    /**
     * Creates a new instance of class
     * Please use course_get_format($courseorid) to get an instance of the format class
     * @param string $format
     * @param int $courseid
     * @return format_remuiformat
     */
    protected function __construct($format, $courseid) {
        global $PAGE;
        if ($courseid === 0) {
            global $COURSE;
            $courseid = $COURSE->id;  // Save lots of global $COURSE as we will never be the site course.
        }

        // Pass constants defined for the formats.
        parent::__construct($format, $courseid);
    }


    /**
     * Returns true if this course format uses sections.
     *
     * @return bool
     */
    public function uses_sections() {
        return true;
    }




    /**
     * Returns the format's settings and gets them if they do not exist.
     * @return array The settings as an array.
     */
    public function get_settings() {
        if (empty($this->settings) == true) {
            $this->settings = $this->get_format_options();
            $this->settings['singlesectioncoursesinglesectionimage_filemanager'] = $this->get_singlesectioncoursesinglesectionimage_filemanager();
        }
        $this->settings['coursedisplay'] = 1;
        return $this->settings;
    }





    /**
     * Returns the display name of the given section that the course prefers.
     *
     * Use section name is specified by user. Otherwise use default ("Topic #").
     *
     * @param int|stdClass $section Section object from database or just field section.section
     * @return string Display name that the course format prefers, e.g. "Topic 2"
     */
    public function get_section_name($section) {
        $section = $this->get_section($section);
        if ((string)$section->name !== '') {
            return format_string($section->name, true,
                ['context' => context_course::instance($this->courseid)]);
        } else {
            return $this->get_default_section_name($section);
        }
    }

    /**
     * Returns the default section name for the singlesection course format.
     *
     * If the section number is 0, it will use the string with key = section0name from the course format's lang file.
     * If the section number is not 0, the base implementation of format_base::get_default_section_name which uses
     * the string with the key = 'sectionname' from the course format's lang file + the section number will be used.
     *
     * @param stdClass $section Section object from database or just field course_sections section
     * @return string The default value for the section name.
     */
    public function get_default_section_name($section) {
        if ($section->section == 0) {
            // Return the general section.
            return get_string('section0name', 'format_singlesection');
        } else {
            // Use format_base::get_default_section_name implementation which
            // will display the section name in "Topic n" format.
            return parent::get_default_section_name($section);
        }
    }

    /**
     * The URL to use for the specified course (with section).
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     *     if omitted the course view page is returned
     * @param array $options options for view URL. At the moment core uses:
     *     'navigation' (bool) if true and section has no separate page, the function returns null
     *     'sr' (int) used by multipage formats to specify to which section to return
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = []) {
        global $CFG;
        $course = $this->get_course();
        $url = new moodle_url('/course/view.php', ['id' => $course->id]);

        $sr = null;
        if (array_key_exists('sr', $options)) {
            $sr = $options['sr'];
        }
        if (is_object($section)) {
            $sectionno = $section->section;
        } else {
            $sectionno = $section;
        }
        if ($sectionno !== null) {
            if ($sr !== null) {
                if ($sr) {
                    $usercoursedisplay = COURSE_DISPLAY_MULTIPAGE;
                    $sectionno = $sr;
                } else {
                    $usercoursedisplay = COURSE_DISPLAY_SINGLEPAGE;
                }
            } else {
                $usercoursedisplay = $course->coursedisplay;
            }
            if ($sectionno != 0 && $usercoursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                $url->param('section', $sectionno);
            } else {
                if (empty($CFG->linkcoursesections) && !empty($options['navigation'])) {
                    return null;
                }
                $url->set_anchor('section-'.$sectionno);
            }
        }
        return $url;
    }

    /**
     * Returns the information about the ajax support in the given source format.
     *
     * The returned object's property (boolean)capable indicates that
     * the course format supports Moodle course ajax features.
     *
     * @return stdClass
     */
    public function supports_ajax() {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        return $ajaxsupport;
    }

    /**
     * Loads all of the course sections into the navigation.
     *
     * @param global_navigation $navigation
     * @param navigation_node $node The course node within the navigation
     * @return void
     */
    public function extend_course_navigation($navigation, navigation_node $node) {
        global $PAGE;
        // If section is specified in course/view.php, make sure it is expanded in navigation.
        if ($navigation->includesectionnum === false) {
            $selectedsection = optional_param('section', null, PARAM_INT);
            if ($selectedsection !== null && (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0') &&
                    $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
                $navigation->includesectionnum = $selectedsection;
            }
        }

        // Check if there are callbacks to extend course navigation.
        parent::extend_course_navigation($navigation, $node);

        // We want to remove the general section if it is empty.
        $modinfo = get_fast_modinfo($this->get_course());
        $sections = $modinfo->get_sections();
//        if (!isset($sections[0])) {
//            // The general section is empty to find the navigation node for it we need to get its ID.
//            $section = $modinfo->get_section_info(0);
//            $generalsection = $node->get($section->id, navigation_node::TYPE_SECTION);
//            if ($generalsection) {
//                // We found the node - now remove it.
//                $generalsection->remove();
//            }
//        }
    }

    /**
     * singlesection action after section has been moved in AJAX mode.
     *
     * Used in course/rest.php
     *
     * @return array This will be passed in ajax respose
     */
    public function ajax_section_move() {
        global $PAGE;
        $titles = [];
        $course = $this->get_course();
        $modinfo = get_fast_modinfo($course);
        $renderer = $this->get_renderer($PAGE);
        if ($renderer && ($sections = $modinfo->get_section_info_all())) {
            foreach ($sections as $number => $section) {
                $titles[$number] = $renderer->section_title($section, $course);
            }
        }
        return ['sectiontitles' => $titles, 'action' => 'move'];
    }

    /**
     * Returns the list of blocks to be automatically added for the newly created course.
     *
     * @return array of default blocks, must contain two keys BLOCK_POS_LEFT and BLOCK_POS_RIGHT
     *     each of values is an array of block names (for left and right side columns)
     */
    public function get_default_blocks() {
        return [
            BLOCK_POS_LEFT => [],
            BLOCK_POS_RIGHT => [],
        ];
    }

    /**
     * Definitions of the additional options that this course format uses for course.
     *
     * singlesection format uses the following options:
     * - coursedisplay
     * - hiddensections
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;
        if ($courseformatoptions === false) {
            $courseconfig = get_config('moodlecourse');
            $courseformatoptions = [
                'hiddensections' => [
                    'default' => $courseconfig->hiddensections,
                    'type' => PARAM_INT,
                ],
                'coursedisplay' => [
                    'default' => $courseconfig->coursedisplay,
                    'type' => PARAM_INT,
                ],
                'media' => [
                    'type' => PARAM_RAW,
                ]
            ];
        }
        if ($foreditform && !isset($courseformatoptions['coursedisplay']['label'])) {
            $courseformatoptionsedit = [
                'hiddensections' => [
                    'label' => new lang_string('hiddensections'),
                    'help' => 'hiddensections',
                    'help_component' => 'moodle',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            0 => new lang_string('hiddensectionscollapsed'),
                            1 => new lang_string('hiddensectionsinvisible')
                        ],
                    ],
                ],
                'coursedisplay' => [
                    'label' => new lang_string('coursedisplay'),
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            COURSE_DISPLAY_SINGLEPAGE => new lang_string('coursedisplay_single'),
                            COURSE_DISPLAY_MULTIPAGE => new lang_string('coursedisplay_multi'),
                        ],
                    ],
                    'help' => 'coursedisplay',
                    'help_component' => 'moodle',
                ],
                'media' => array(
                    'label' => get_string('media','format_singlesection'),
                    'help' => 'media',
                    'element_type' => 'textarea',
                    'help_component' => 'format_singlesection',
                ),
            ];
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }
        return $courseformatoptions;
    }

    /**
     * Adds format options elements to the course/section edit form.
     *
     * This function is called from {@link course_edit_form::definition_after_data()}.
     *
     * @param MoodleQuickForm $mform form the elements are added to.
     * @param bool $forsection 'true' if this is a section edit form, 'false' if this is course edit form.
     * @return array array of references to the added form elements.
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        global $COURSE, $USER;
        $elements = parent::create_edit_form_elements($mform, $forsection);

        if (!$forsection && (empty($COURSE->id) || $COURSE->id == SITEID)) {
            // Add "numsections" element to the create course form - it will force new course to be prepopulated
            // with empty sections.
            // The "Number of sections" option is no longer available when editing course, instead teachers should
            // delete and add sections when needed.
            $courseconfig = get_config('moodlecourse');
            $max = (int)$courseconfig->maxsections;
            $element = $mform->addElement('select', 'numsections', get_string('numberweeks'), range(0, $max ?: 52));
            $mform->setType('numsections', PARAM_INT);
            if (is_null($mform->getElementValue('numsections'))) {
                $mform->setDefault('numsections', $courseconfig->numsections);
            }
            array_unshift($elements, $element);
        }

        $elementsnew = [];

        $fs = get_file_storage();
        $coursecontext = context_course::instance($this->courseid);
        $usercontext = context_user::instance($USER->id);

        foreach ($elements as $key => $element) {

            if ($element->getName() == 'hiddensections') {
                $single_data = new stdClass;
                $single_file_item_id = $this->get_singlesectioncoursesinglesectionimage_filemanager();
                $fs->delete_area_files($usercontext->id, 'user', 'draft', $single_file_item_id);
                $single_data = file_prepare_standard_filemanager(
                    $single_data,
                    'singlesectioncoursesinglesectionimage',
                    array('accepted_types' => array('.jpg', '.gif', '.png'), 'maxfiles' => 1),
                    $coursecontext,
                    'format_singlesection',
                    'singlesectioncoursesinglesectionimage_filearea',
                    $single_file_item_id
                );

                $single_filemanager = $mform->addElement(
                    'filemanager',
                    'singlesectioncoursesinglesectionimage_filemanager',
                    new lang_string('singlesectionsinglesectionimage', 'format_singlesection'),
                    null,
                    array('maxfiles' => 1, 'accepted_types' => array('.jpg', '.gif', '.png'))
                );
                $single_filemanager->setValue($single_data->singlesectioncoursesinglesectionimage_filemanager);
                $elementsnew[] = $single_filemanager;
            }
            unset($elements[$key]);
            $elementsnew[] = $element;

        }
        //Get the values the values from the editor coursestart that are loaded in the form and transform then in a array (

        if(isset($mform->_defaultValues['coursestart'])) {
            $mform->_defaultValues['coursestart'] = array (
                'text' => $mform->_defaultValues['coursecurriculum'],
                'format' => 1,
                'itemid' => null
            );
        }

        return $elementsnew;

    }

    /**
     * Updates format options for a course.
     *
     * In case if course format was changed to 'singlesection', we try to copy options
     * 'coursedisplay' and 'hiddensections' from the previous format.
     *
     * @param stdClass|array $data return value from {@link moodleform::get_data()} or array with data
     * @param stdClass $oldcourse if this function is called from {@link update_course()}
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) {

        if (!isset($data->singlesectioncoursesinglesectionimage_filemanager)) {
            $data->singlesectioncoursesinglesectionimage_filemanager = '';
        }
        if (!empty($data)) {

            // Used optional_param() instead of using $_POST and $_GET.
            $contextid = context_course::instance($this->courseid);

            if (!empty($data->singlesectioncoursesinglesectionimage_filemanager)) {
                file_postupdate_standard_filemanager(
                    $data,
                    'singlesectioncoursesinglesectionimage',
                    array ('accepted_types' => 'images', 'maxfiles' => 1),
                    $contextid,
                    'format_singlesection',
                    'singlesectioncoursesinglesectionimage_filearea',
                    $data->singlesectioncoursesinglesectionimage_filemanager
                );
            }

            $this->set_singlesectioncoursesinglesectionimage_filemanager($data->singlesectioncoursesinglesectionimage_filemanager);
        }

        $data->coursedisplay = 1;

        return $this->update_format_options($data);

    }

    /**
     * DB value setter for remuicourseimage_filemanager option
     * @param boolean $itemid Image itemid
     */
    public function set_singlesectioncoursesinglesectionimage_filemanager($itemid = false) {
        global $DB;
        $courseimage = $DB->get_record('course_format_options', array(
            'courseid' => $this->courseid,
            'format' => 'singlesection',
            'sectionid' => 0,
            'name' => 'singlesectioncoursesinglesectionimage_filemanager'
        ));
        if ($courseimage == false) {
            $courseimage = (object) array(
                'courseid' => $this->courseid,
                'format' => 'singlesection',
                'sectionid' => 0,
                'name' => 'singlesectioncoursesinglesectionimage_filemanager'
            );
            $courseimage->id = $DB->insert_record('course_format_options', $courseimage);
        }
        $courseimage->value = $itemid;
        $DB->update_record('course_format_options', $courseimage);
        return true;
    }

    /**
     * DB value setter for remuicourseimage_filemanager option
     * @return int Item id
     */
    public function get_singlesectioncoursesinglesectionimage_filemanager() {
        global $DB;
        $itemid = $DB->get_field('course_format_options', 'value', array(
            'courseid' => $this->courseid,
            'format' => 'singlesection',
            'sectionid' => 0,
            'name' => 'singlesectioncoursesinglesectionimage_filemanager'
        ));
        if (!$itemid) {
            $itemid = file_get_unused_draft_itemid();
        }
        return $itemid;
    }

    public function get_singlesectioncourse_sections_style() {
        global $DB;
        return $DB->get_records('course_format_options', array(
            'courseid' => $this->courseid,
            'format' => 'singlesection',
            'name' => 'styles'
        ), 'sectionid','sectionid,value');
    }








    /**
     * Whether this format allows to delete sections.
     *
     * Do not call this function directly, instead use {@link course_can_delete_section()}
     *
     * @param int|stdClass|section_info $section
     * @return bool
     */
    public function can_delete_section($section) {
        return true;
    }

    /**
     * Prepares the templateable object to display section name.
     *
     * @param \section_info|\stdClass $section
     * @param bool $linkifneeded
     * @param bool $editable
     * @param null|lang_string|string $edithint
     * @param null|lang_string|string $editlabel
     * @return inplace_editable
     */
    public function inplace_editable_render_section_name($section, $linkifneeded = true,
            $editable = null, $edithint = null, $editlabel = null) {
        if (empty($edithint)) {
            $edithint = new lang_string('editsectionname', 'format_singlesection');
        }
        if (empty($editlabel)) {
            $title = get_section_name($section->course, $section);
            $editlabel = new lang_string('newsectionname', 'format_singlesection', $title);
        }
        return parent::inplace_editable_render_section_name($section, $linkifneeded, $editable, $edithint, $editlabel);
    }

    /**
     * Indicates whether the course format supports the creation of a news forum.
     *
     * @return bool
     */
    public function supports_news() {
        return false;
    }

    /**
     * Returns whether this course format allows the activity to
     * have "triple visibility state" - visible always, hidden on course page but available, hidden.
     *
     * @param stdClass|cm_info $cm course module (may be null if we are displaying a form for adding a module)
     * @param stdClass|section_info $section section where this module is located or will be added to
     * @return bool
     */
    public function allow_stealth_module_visibility($cm, $section) {
        // Allow the third visibility state inside visible sections or in section 0.
        return !$section->section || $section->visible;
    }

    /**
     * Callback used in WS core_course_edit_section when teacher performs an AJAX action on a section (show/hide).
     *
     * Access to the course is already validated in the WS but the callback has to make sure
     * that particular action is allowed by checking capabilities
     *
     * Course formats should register.
     *
     * @param section_info|stdClass $section
     * @param string $action
     * @param int $sr
     * @return null|array any data for the Javascript post-processor (must be json-encodeable)
     */
    public function section_action($section, $action, $sr) {
        global $PAGE;

        if ($section->section && ($action === 'setmarker' || $action === 'removemarker')) {
            // Format 'singlesection' allows to set and remove markers in addition to common section actions.
            require_capability('moodle/course:setcurrentsection', context_course::instance($this->courseid));
            course_set_marker($this->courseid, ($action === 'setmarker') ? $section->section : 0);
            return null;
        }

        // For show/hide actions call the parent method and return the new content for .section_availability element.
        $rv = parent::section_action($section, $action, $sr);
        $renderer = $PAGE->get_renderer('format_singlesection');
        $rv['section_availability'] = $renderer->section_availability($this->get_section($section));
        return $rv;
    }

    /**
     * Return the plugin configs for external functions.
     *
     * @return array the list of configuration settings
     * @since Moodle 3.5
     */
    public function get_config_for_external() {
        // Return everything (nothing to hide).
        return $this->get_format_options();
    }
}

/**
 * Implements callback inplace_editable() allowing to edit values in-place.
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return inplace_editable
 */
function format_singlesection_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            [$itemid, 'singlesection'], MUST_EXIST);
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}


function format_singlesection_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $DB;
    if ($context->contextlevel != CONTEXT_COURSE) {
        return false;
    }
    require_login();
    $areas = array(
        'singlesectioncourseimage_filearea',
        'singlesectioncoursesinglesectionimage_filearea'
    );
    if (!in_array($filearea,$areas )) {
        return false;
    }

    $itemid = (int)array_shift($args);
    $fs = get_file_storage();
    $filename = array_pop($args);

    if (empty($args)) {
        $filepath = '/';
    } else {
        $filepath = '/'.implode('/', $args).'/';
    }
    $file = $fs->get_file($context->id, 'format_singlesection', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }
    send_stored_file($file, 0, 0, 0, $options);
}

