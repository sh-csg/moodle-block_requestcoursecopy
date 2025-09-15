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
 * Requesting course copy
 *
 * @package    block_requestcoursecopy
 * @copyright  2025 Stefan Hanauska
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\plugininfo\enrol;

use function DI\get;

require('../../config.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

require_login();

$url = new moodle_url('/blocks/requestcoursecopy/requestcoursecopy.php', []);
$PAGE->set_url($url);

$blockid = required_param('blockid', PARAM_INT);

$block = block_instance_by_id($blockid);

$PAGE->set_context($block->context);

require_capability('block/requestcoursecopy:view', $block->context);

$PAGE->set_heading($block->config->title);
echo $OUTPUT->header();

$course = $DB->get_record('course', ['id' => $block->config->courseid]);

$CFG->backup_file_logger_level = backup::LOG_NONE;
$bc = new backup_controller(
    backup::TYPE_1COURSE,
    $course->id,
    backup::FORMAT_MOODLE,
    backup::INTERACTIVE_NO,
    backup::MODE_AUTOMATED,
    $USER->id
);
$backupid = $bc->get_backupid();
$bc->execute_plan();
$destination = $bc->get_plan()->get_results()['backup_destination'];
$bc->destroy();

$category = $block->config->categoryid ?? $course->category;

// Restore it to a new course.
$targetcourseid = restore_dbops::create_new_course(
    $course->fullname,
    $course->shortname . uniqid(),
    $category
);
$tempfile = $destination->copy_content_to_temp('backup');
$fp = get_file_packer('application/vnd.moodle.backup');
$subdir = '/restore_' . uniqid();
$tmpdir = $CFG->backuptempdir . $subdir;
$extracted = $fp->extract_to_pathname($tempfile, $tmpdir);
@unlink($tempfile);
$rc = new restore_controller(
    $subdir,
    $targetcourseid,
    backup::INTERACTIVE_NO,
    backup::MODE_GENERAL,
    $USER->id,
    backup::TARGET_NEW_COURSE
);
$rc->get_plan()->get_setting('enrolments')->set_value(backup::ENROL_ALWAYS);
$rc->execute_precheck();
$rc->execute_plan();
$rc->destroy();

// Now enrol the user.
$enrol = enrol_get_plugin('manual');
$manualinstance = null;
$enrolmentinstances = array_values(enrol_get_instances($targetcourseid, true));
foreach ($enrolmentinstances as $instance) {
    if ($instance->enrol === 'manual') {
        enrol_get_plugin('manual')->delete_instance($instance);
    }
    if ($instance->enrol === 'autoenrol') {
        enrol_get_plugin('autoenrol')->delete_instance($instance);
    }
}

$targetcourse = $DB->get_record('course', ['id' => $targetcourseid]);
// Create a new manual enrolment instance.
$manualinstanceid = $enrol->add_instance($targetcourse, [
    'status' => ENROL_INSTANCE_ENABLED,
    'sortorder' => 0,
]);

$manualinstance = $DB->get_record('enrol', ['id' => $manualinstanceid, 'enrol' => 'manual'], '*', MUST_EXIST);

$guestroleid = $DB->get_field('role', 'id', ['shortname' => 'guest']);
// Unenrol user to make sure there are no leftover role assignments.
$enrol->unenrol_user($manualinstance, $USER->id);
$enrol->enrol_user($manualinstance, $USER->id, $block->config->roleid ?? $guestroleid);

// Copy recyclebin.
$sourcecoursecontext = context_course::instance($course->id);
$targetcoursecontext = context_course::instance($targetcourseid);

$fs = get_file_storage();

$recyclebinfiles = $fs->get_area_files($sourcecoursecontext->id, 'tool_recyclebin', 'recyclebin_course');

foreach ($recyclebinfiles as $file) {
    $record = [
        'contextid' => $targetcoursecontext->id,
        'component' => 'tool_recyclebin',
        'filearea' => 'recyclebin_course',
        'itemid' => 0,
        'filepath' => '/',
        'filename' => $file->get_filename(),
        'userid' => $USER->id,
    ];
    $fs->create_file_from_storedfile($record, $file);
}

echo $OUTPUT->render_from_template('block_requestcoursecopy/success', [
    'course' => $targetcourseid,
    'link' => new moodle_url('/course/view.php', ['id' => $targetcourseid]),
]);

echo $OUTPUT->footer();
