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
 * Block Request course copy
 *
 * Documentation: {@link https://moodledev.io/docs/apis/plugintypes/blocks}
 *
 * @package    block_requestcoursecopy
 * @copyright  2025 Stefan Hanauska
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_requestcoursecopy extends block_base {

    /**
     * Block initialisation
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_requestcoursecopy');
    }

    /**
     * Get content
     *
     * @return stdClass
     */
    public function get_content() {
        global $DB, $OUTPUT, $USER;
        if (!has_capability('block/requestcoursecopy:view', $this->context)) {
            return null;
        }
        if ($this->content !== null) {
            return $this->content;
        }

        $text = '';

        if ($this->config->onlyonecopy ?? false) {
            $existingcopies = $DB->get_records('course', ['originalcourseid' => $this->config->courseid]);
            $existingcopiescourseids = array_column($existingcopies, 'id');
            $mycourses = enrol_get_my_courses(null, null, 0, $existingcopiescourseids);
            foreach ($mycourses as $mycourse) {
                $mycoursecontext = context_course::instance($mycourse->id);
                if (user_has_role_assignment($USER->id, $this->config->roleid, $mycoursecontext->id)) {
                    // Show message that course copy already exists.
                    $text .= $OUTPUT->render_from_template('block_requestcoursecopy/existingcourse', [
                        'course' => $mycourse,
                        'buttontext' => $this->config->buttontext_goto ?? '',
                    ]);
                    break;
                }
            }
        }
        if ($text === '') {
            $text = $OUTPUT->render_from_template(
                'block_requestcoursecopy/copybutton',
                ['blockid' => $this->instance->id, 'buttontext' => $this->config->buttontext_request ?? '']
            );
        }

        if (!empty($this->config->description['text'])) {
            $text = format_string($this->config->description['text']) . $text;
        }

        $this->content = (object)[
            'footer' => '',
            'text' => $text,
        ];

        if (!empty($this->config->title)) {
            $this->title = format_string($this->config->title);
        }

        return $this->content;
    }

    /**
     * Get applicable formats
     *
     * @return array
     */
    public function applicable_formats() {
        return [
            'site-index' => true,
            'course-view' => true,
            'my' => false,
        ];
    }
}
