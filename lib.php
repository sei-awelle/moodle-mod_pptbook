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
 * Library functions for the PPT Book activity.
 *
 * @package    mod_pptbook
 * @copyright  2025 Ralf Hagemeister
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Declares which features the module supports.
 *
 * @param string $feature FEATURE_* constant.
 * @return mixed True/false or other value depending on the feature.
 * @package   mod_pptbook
 */
function pptbook_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        // Offers completion tracking.
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        default:
            return null;
    }
}


/**
 * Creates a new PPT Book instance.
 *
 * @param stdClass $data  Form data from mod_form.
 * @param MoodleQuickForm $mform The form (unused but required by signature).
 * @return int New instance ID.
 * @package   mod_pptbook
 */
function pptbook_add_instance($data, $mform) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = time();

    // Set default perpage if not provided.
    $data->perpage = !empty($data->perpage) ? (int)$data->perpage : 4;

    if (!empty($data->captionsjson) && is_array($data->captionsjson)) {
        $data->captionsjson = json_encode($data->captionsjson, JSON_UNESCAPED_UNICODE);
    } else if (empty($data->captionsjson)) {
        $data->captionsjson = null;
    }

    $id = $DB->insert_record('pptbook', $data);

    // Save any uploaded slide files.
    pptbook_save_slides_files($id, $data, $data->coursemodule ?? null);

    return $id;
}

/**
 * Updates an existing PPT Book instance.
 *
 * @param stdClass $data  Form data from mod_form.
 * @param MoodleQuickForm $mform The form (unused but required by signature).
 * @return bool True on success.
 * @package   mod_pptbook
 */
function pptbook_update_instance($data, $mform) {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();

    // Set default perpage if not provided.
    $data->perpage = !empty($data->perpage) ? (int)$data->perpage : 4;

    if (!empty($data->captionsjson) && is_array($data->captionsjson)) {
        $data->captionsjson = json_encode($data->captionsjson, JSON_UNESCAPED_UNICODE);
    }

    $DB->update_record('pptbook', $data);

    // Save any uploaded slide files.
    pptbook_save_slides_files($data->id, $data, $data->coursemodule ?? null);

    return true;
}

/**
 * Deletes a PPT Book instance and its related data.
 *
 * @param int $id Instance ID.
 * @return bool True on success, false if the instance does not exist.
 * @package   mod_pptbook
 */
function pptbook_delete_instance($id) {
    global $DB;

    if (!$pptbook = $DB->get_record('pptbook', ['id' => $id])) {
        return false;
    }

    $cm = get_coursemodule_from_instance('pptbook', $id);
    $context = context_module::instance($cm->id);

    // Remove all stored slide files.
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_pptbook', 'slides');

    // Remove related records.
    $DB->delete_records('pptbook_item', ['pptbookid' => $pptbook->id]);
    $DB->delete_records('pptbook', ['id' => $pptbook->id]);

    return true;
}

/**
 * Saves uploaded slide files from the filemanager into the module file area.
 *
 * Safe to call from both add and update paths. If the coursemodule ID is not
 * yet available during add, the function returns early and the update path
 * will persist the files later.
 *
 * @param int $instanceid PPT Book instance ID.
 * @param stdClass $data  Form data containing the filemanager draft itemid.
 * @param int|null $cmid  Course module ID if available.
 * @return void
 * @package   mod_pptbook
 */
function pptbook_save_slides_files($instanceid, $data, $cmid = null) {
    global $CFG;

    require_once($CFG->libdir . '/filelib.php');

    // Prefer explicit $cmid if given; otherwise use $data->coursemodule.
    if (empty($cmid)) {
        $cmid = $data->coursemodule ?? null;
    }

    if (empty($cmid)) {
        // Try to resolve the coursemodule from the instance.
        try {
            $cm = get_coursemodule_from_instance('pptbook', $instanceid, 0, false, IGNORE_MISSING);
            if ($cm) {
                $cmid = $cm->id;
            }
        } catch (Exception $e) {
            $cmid = null;
        }
    }

    if (empty($cmid)) {
        // Defer saving files. The filemanager keeps drafts; update_instance will handle it.
        return;
    }

    $context = context_module::instance($cmid);

    file_save_draft_area_files(
        $data->slides_filemanager ?? 0,
        $context->id,
        'mod_pptbook',
        'slides',
        0,
        ['subdirs' => 0, 'maxfiles' => -1, 'accepted_types' => ['.png']]
    );
}

/**
 * Provides info for the course module listing.
 *
 * @param cm_info $cm Course module object.
 * @return cached_cm_info|null CM info or null if instance not found.
 * @package   mod_pptbook
 */
function pptbook_get_coursemodule_info($cm) {
    global $DB;

    if ($pptbook = $DB->get_record('pptbook', ['id' => $cm->instance], 'id, name')) {
        $result = new cached_cm_info();
        $result->name = $pptbook->name; // Provide the instance name.
        // No $result->content here.
        return $result;
    }

    return null;
}

/**
 * File serving callback for the module.
 *
 * @param stdClass  $course         Course object.
 * @param cm_info   $cm             Course module object.
 * @param context   $context        Context.
 * @param string    $filearea       File area name.
 * @param array     $args           Extra arguments (path components).
 * @param bool      $forcedownload  Whether the file should be downloaded.
 * @param array     $options        Additional send_stored_file() options.
 * @return bool|void False on failure, or terminates with file output.
 * @package   mod_pptbook
 */
function pptbook_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    require_login($course, true, $cm);

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    if ($filearea !== 'slides') {
        return false;
    }

    $fs = get_file_storage();
    $itemid = 0;
    $filepath = '/';
    $filename = array_pop($args);

    if (!$file = $fs->get_file($context->id, 'mod_pptbook', 'slides', $itemid, $filepath, $filename)) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}
