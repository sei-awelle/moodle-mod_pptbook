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
 * Form definition for the PPT Book activity.
 *
 * @package    mod_pptbook
 * @category   form
 * @copyright  2025 Ralf Hagemeister
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Module settings form for PPT Book.
 *
 * @package   mod_pptbook
 */
class mod_pptbook_mod_form extends moodleform_mod {
    /**
     * Defines the form fields for creating/updating a PPT Book instance.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        // Name.
        $mform->addElement('text', 'name', get_string('name', 'mod_pptbook'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // Standard intro (intro + introformat).
        $this->standard_intro_elements();

        // Slides filemanager.
        $mform->addElement(
            'filemanager',
            'slides_filemanager',
            get_string('slides', 'mod_pptbook'),
            null,
            [
                'subdirs' => 0,
                'maxfiles' => -1,
                'accepted_types' => ['.png'],
            ]
        );
        $mform->addHelpButton('slides_filemanager', 'slides', 'mod_pptbook');

        // Slides per page setting.
        $defaultperpage = get_config('pptbook', 'perpage') ?: 4;
        $mform->addElement('select', 'perpage', get_string('perpage', 'mod_pptbook'), [
            1 => '1',
            2 => '2',
            3 => '3',
            4 => '4',
        ]);
        $mform->setDefault('perpage', $defaultperpage);
        $mform->addHelpButton('perpage', 'perpage', 'mod_pptbook');

        // Captions JSON (hidden).
        $mform->addElement('hidden', 'captionsjson', '');
        $mform->setType('captionsjson', PARAM_RAW);

        // Standard course module, grading, groups, restrictions, etc.
        $this->standard_coursemodule_elements();

        // Action buttons.
        $this->add_action_buttons();
    }

    /**
     * Prepares draft areas and default values before the form is shown.
     *
     * @param stdClass $defaultvalues Default values to be populated in the form (by reference).
     * @return void
     */
    public function data_preprocessing(&$defaultvalues) {
        if (!empty($this->current->instance)) {
            $draftitemid = file_get_submitted_draft_itemid('slides_filemanager');

            file_prepare_draft_area(
                $draftitemid,
                $this->context->id,
                'mod_pptbook',
                'slides',
                0,
                [
                    'subdirs' => 0,
                    'maxfiles' => -1,
                ]
            );

            $defaultvalues['slides_filemanager'] = $draftitemid;
        }
    }
}
