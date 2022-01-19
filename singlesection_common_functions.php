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
 * This is built using the bootstrapbase template to allow for new theme's using Moodle's new Bootstrap theme engine
 *
 * @package   format_singlesection
 * @copyright 2021 Brain Station 23 Ltd.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Display image file
 * @param int $itemid File item id
 * @param string $singlesectioncourseimage_filearea
 * @return string      Image file url
 * @throws coding_exception
 * @throws dml_exception
 */
function display_file($itemid, $singlesectioncoursesinglesectionimagefilearea =
'singlesectioncoursesinglesectionimagefilearea'): string {
    global $DB, $CFG;

    // Added empty check here to check if 'kidscourseimage_filearea' is set or not.
    if ( !empty($itemid) ) {
        $filedata = $DB->get_records('files', array('itemid' => $itemid));

        $tempdata = array();
        foreach ($filedata as $key => $value) {
            if ($value->filesize > 0 && $value->filearea == $singlesectioncoursesinglesectionimagefilearea) {
                $tempdata = $value;
            }
        }

        $fs = get_file_storage();
        if (!empty($tempdata)) {
            $files = $fs->get_area_files(
                $tempdata->contextid,
                'format_singlesection',
                $singlesectioncoursesinglesectionimagefilearea,
                $itemid
            );

            $url = '';
            foreach ($files as $key => $file) {
                $file->portfoliobutton = '';
                $url = moodle_url::make_pluginfile_url($tempdata->contextid, 'format_singlesection',
                        $singlesectioncoursesinglesectionimagefilearea,
                    $file->get_itemid(), $file->get_filepath(), $file->get_filename() );
            }
            return $url;
        }
    }
    return '';
}

/**
 * Display course image file
 * @return string      Image file url
 * @throws coding_exception
 * @throws dml_exception
 */

function get_course_image() {
    global $COURSE, $CFG;
    $url = '';
    require_once( $CFG->libdir . '/filelib.php' );

    $context = context_course::instance( $COURSE->id );
    $fs = get_file_storage();
    $files = $fs->get_area_files( $context->id, 'course', 'overviewfiles', 0 );

    foreach ($files as $f) {
        if ($f->is_valid_image()) {
            $url = moodle_url::make_pluginfile_url( $f->get_contextid(), $f->get_component(), $f->get_filearea(), null,
                $f->get_filepath(), $f->get_filename(), false );
        }
    }

    return $url;
}
