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

class report_editdates_mod_extserver_date_extractor
extends report_editdates_mod_date_extractor {

    public function __construct($course) {
        parent::__construct($course, 'extserver');
        parent::load_data();
    }

    public function get_settings(cm_info $cm) {
        $extserver = $this->mods[$cm->instance];

        return array(
                'allowsubmissionsfromdate' => new report_editdates_date_setting(
                        get_string('allowsubmissionsfromdate', 'extserver'),
                        $extserver->allowsubmissionsfromdate,
                        self::DATETIME, true, 5),
                'duedate' => new report_editdates_date_setting(
                        get_string('duedate', 'extserver'),
                        $extserver->duedate,
                        self::DATETIME, true, 5),
                'cutoffdate' => new report_editdates_date_setting(
                        get_string('cutoffdate', 'extserver'),
                        $extserver->cutoffdate,
                        self::DATETIME, true, 5),
                );
    }

    public function validate_dates(cm_info $cm, array $dates) {
        $errors = array();
        if ($dates['allowsubmissionsfromdate'] && $dates['duedate']
                && $dates['duedate'] < $dates['allowsubmissionsfromdate']) {
            $errors['duedate'] = get_string('duedatevalidation', 'extserver');
        }
        if ($dates['duedate'] && $dates['cutoffdate']) {
            if ($dates['duedate'] > $dates['cutoffdate']) {
                $errors['cutoffdate'] = get_string('cutoffdatevalidation', 'extserver');
            }
        }
        if ($dates['allowsubmissionsfromdate'] && $dates['cutoffdate']) {
            if ($dates['allowsubmissionsfromdate'] > $dates['cutoffdate']) {
                $errors['cutoffdate'] = get_string('cutoffdatefromdatevalidation', 'extserver');
            }
        }

        return $errors;
    }

    public function save_dates(cm_info $cm, array $dates) {
        global $DB, $COURSE;

        $update = new stdClass();
        $update->id = $cm->instance;
        $update->duedate = $dates['duedate'];
        $update->allowsubmissionsfromdate = $dates['allowsubmissionsfromdate'];
        $update->cutoffdate = $dates['cutoffdate'];

        $result = $DB->update_record('extserver', $update);

        $module = $DB->get_record('extserver', array('id' => $cm->instance));

        // Update the calendar and grades.
        if ($module->duedate) {
            $event = new stdClass();

            if ($event->id = $DB->get_field('event', 'id', array('modulename' => 'extserver',
                                                                 'instance'   => $module->id))) {

                $event->name        = $module->name;
                $event->description = format_module_intro('extserver', $module, $cm->id);
                $event->timestart   = $module->duedate;

                $calendarevent = calendar_event::load($event->id);
                $calendarevent->update($event);
            } else {
                $event = new stdClass();
                $event->name        = $module->name;
                $event->description = format_module_intro('extserver', $module, $cm->id);
                $event->courseid    = $module->course;
                $event->groupid     = 0;
                $event->userid      = 0;
                $event->modulename  = 'extserver';
                $event->instance    = $module->id;
                $event->eventtype   = 'due';
                $event->timestart   = $module->duedate;
                $event->timeduration = 0;

                calendar_event::create($event);
            }
        } else {
            $DB->delete_records('event', array('modulename' => 'extserver',
                                               'instance'   => $module->id));
        }
    }
}
