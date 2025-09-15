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
 * Edit form for block_requestcoursecopy
 *
 * @package    block_requestcoursecopy
 * @copyright  2025 Stefan Hanauska <stefan.hanauska@altmuehlnet.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_requestcoursecopy_edit_form extends block_edit_form {
    /**
     * Add specific elements to the standard block form
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    protected function specific_definition($mform): void {
        global $DB;
        $mform->addElement('header', 'config_header', get_string('blocksettings', 'block'));
        $mform->addElement(
            'course',
            'config_courseid',
            get_string('coursetocopyfrom', 'block_requestcoursecopy')
        );
        $mform->setType('config_courseid', PARAM_INT);

        $displaylist = core_course_category::make_categories_list('moodle/course:create');
        $mform->addElement('autocomplete', 'config_categoryid', get_string('coursecategory'), $displaylist);
        $mform->setType('config_categoryid', PARAM_INT);

        $roleids = array_values(get_roles_for_contextlevels(CONTEXT_COURSE));
        $rolelist = [];
        foreach ($roleids as $roleid) {
            $rolelist[$roleid] = $DB->get_field('role', 'name', ['id' => $roleid]);
        }

        $mform->addElement('select', 'config_roleid', get_string('role'), $rolelist);
        $mform->setType('config_roleid', PARAM_INT);

        $mform->addElement('advcheckbox', 'config_onlyonecopy', get_string('allowonlyonecopy', 'block_requestcoursecopy'));
        $mform->setType('config_onlyonecopy', PARAM_BOOL);

        $mform->addElement('text', 'config_title', get_string('title', 'block_requestcoursecopy'));
        $mform->setType('title', PARAM_TEXT);

        $mform->addElement('editor', 'config_description', get_string('description'));
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('text', 'config_buttontext_request', get_string('buttontext_request'));
        $mform->setType('config_buttontext_request', PARAM_TEXT);

        $mform->addElement('text', 'config_buttontext_goto', get_string('buttontext_goto'));
        $mform->setType('config_buttontext_goto', PARAM_TEXT);

    }
}
