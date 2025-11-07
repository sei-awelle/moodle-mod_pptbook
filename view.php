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
 * [Short description of the file]
 *
 * @package    mod_pptbook
 * @copyright  2025 Ralf Hagemeister <ralf.hagemeister@lernsteine.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class mod_pptbook.
 */
require('../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/mod/pptbook/locallib.php');

$id = required_param('id', PARAM_INT);
$page = optional_param('page', 1, PARAM_INT);

$cm = get_coursemodule_from_id('pptbook', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$pptbook = $DB->get_record('pptbook', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

// Prepare page.
$PAGE->set_url('/mod/pptbook/view.php', ['id' => $id, 'page' => $page]);
$PAGE->set_title(format_string($pptbook->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->css(new moodle_url('/mod/pptbook/styles.css'));
$PAGE->requires->js_call_amd('mod_pptbook/lightbox', 'init');

// Hide activityheader.
if (isset($PAGE->activityheader) && method_exists($PAGE->activityheader, 'set_attrs')) {
    $PAGE->activityheader->set_attrs([
        'title' => '',
        'subtitle' => '',
        'description' => '',
        'hasintro' => false,
        'hidecompletion' => true,
    ]);
}

// Load files and count.
$slides = pptbook_get_slide_files($context);
$total  = count($slides);

echo $OUTPUT->header();

if ($total === 0) {
    echo $OUTPUT->notification(get_string('noimages', 'mod_pptbook'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

// Sort by filenames.
usort($slides, function ($a, $b) {
    return strnatcasecmp($a->get_filename(), $b->get_filename());
});

$perpage = get_config('pptbook', 'perpage');

$pages   = max(1, (int)ceil($total / $perpage));
$page    = max(1, min((int)$page, $pages));
$start   = ($page - 1) * $perpage;
$current = array_slice($slides, $start, $perpage);

// Event „course_module_viewed“ once with snapshot.
$event = \mod_pptbook\event\course_module_viewed::create([
    'objectid' => $pptbook->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('pptbook', $pptbook);
$event->trigger();

// Completion only on last page.
$completion = new completion_info($course);
if (
    $page >= $pages && $completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC
    && !empty($cm->completionview)
) {
    $completion->set_module_viewed($cm);
}

// Read Captions.
$captions = pptbook_get_captions($pptbook);

// Built Template-Items.
$items = [];
foreach ($current as $f) {
    $filename = $f->get_filename();
    $url = moodle_url::make_pluginfile_url(
        $f->get_contextid(),
        $f->get_component(),
        $f->get_filearea(),
        $f->get_itemid(),
        $f->get_filepath(),
        $filename,
        false
    );
    $items[] = (object)[
        'filename' => $filename,
        'imgurl'   => (string)$url,
        'fullurl'  => (string)$url,
        'caption'  => $captions[$filename] ?? '',
    ];
}

$manageurl = null;
if (has_capability('mod/pptbook:manage', $context)) {
    $manageurl = new moodle_url('/mod/pptbook/edit_captions.php', [
        'cmid' => $cm->id,
        'id'   => $cm->id,
        'page' => $page,
    ]);
}

$templatecontext = (object)[
    'items'     => $items,
    'page'      => $page,
    'pages'     => $pages,
    'hasprev'   => $page > 1,
    'hasnext'   => $page < $pages,
    'preurl'    => (new moodle_url('/mod/pptbook/view.php', ['id' => $cm->id, 'page' => $page - 1]))->out(false),
    'nexturl'   => (new moodle_url('/mod/pptbook/view.php', ['id' => $cm->id, 'page' => $page + 1]))->out(false),
    'manageurl' => $manageurl ?: null,
    'manage'    => !empty($manageurl),
    'perpage'   => $perpage,
];

echo $OUTPUT->render_from_template('mod_pptbook/page', $templatecontext);
echo $OUTPUT->footer();
