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

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_pptbook';
$plugin->version   = 2026042802; // YYYYMMDDXX - Add perpage field to db.
$plugin->release   = '1.1.1';
$plugin->maturity  = MATURITY_STABLE;
$plugin->requires  = 2023100900; // Moodle 4.3+.
