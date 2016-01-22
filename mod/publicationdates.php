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

class report_editdates_mod_publication_date_extractor
extends report_editdates_mod_date_extractor {

    public function __construct($course) {
        parent::__construct($course, 'publication');
        parent::load_data();
    }

    public function get_settings(cm_info $cm) {
        $publication = $this->mods[$cm->instance];

        return array(
                'allowsubmissionsfromdate' => new report_editdates_date_setting(
                        get_string('allowsubmissionsfromdate', 'publication'),
                        $publication->allowsubmissionsfromdate,
                        self::DATETIME, true, 5),
                'duedate' => new report_editdates_date_setting(
                        get_string('duedate', 'publication'),
                        $publication->duedate,
                        self::DATETIME, true, 5),
                );
    }

    public function validate_dates(cm_info $cm, array $dates) {
        $errors = array();
        if ($dates['allowsubmissionsfromdate'] && $dates['duedate']
                && $dates['duedate'] < $dates['allowsubmissionsfromdate']) {
            $errors['duedate'] = get_string('duedatevalidation', 'publication');
        }

        return $errors;
    }

    public function save_dates(cm_info $cm, array $dates) {
        global $DB, $COURSE;

        $update = new stdClass();
        $update->id = $cm->instance;
        $update->duedate = $dates['duedate'];
        $update->allowsubmissionsfromdate = $dates['allowsubmissionsfromdate'];

        $result = $DB->update_record('publication', $update);
    }
}
