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

require_once($CFG->dirroot.'/mod/grouptool/locallib.php');

class report_editdates_mod_grouptool_date_extractor
extends report_editdates_mod_date_extractor {

    public function __construct($course) {
        parent::__construct($course, 'grouptool');
        parent::load_data();
    }

    public function get_settings(cm_info $cm) {
        $grouptool = $this->mods[$cm->instance];

        return array(
                'timeavailable' => new report_editdates_date_setting(
                        get_string('availabledate', 'grouptool'),
                        $grouptool->timeavailable,
                        self::DATETIME, true, 5),
                'timedue' => new report_editdates_date_setting(
                        get_string('duedate', 'grouptool'),
                        $grouptool->timedue,
                        self::DATETIME, true, 5),
                );
    }

    public function validate_dates(cm_info $cm, array $dates) {
        $errors = array();
        if (!empty($dates['timedue']) && ($dates['timedue'] <= $dates['timeavailable'])) {
            $errors['timedue'] = get_string('determinismerror', 'grouptool');
        }

        return $errors;
    }

    public function save_dates(cm_info $cm, array $dates) {
        global $DB, $COURSE, $CFG;

        $update = new stdClass();
        $update->id = $cm->instance;
        $update->timedue = $dates['timedue'];
        $update->timeavailable = $dates['timeavailable'];

        $result = $DB->update_record('grouptool', $update);

        $module = new mod_grouptool($cm->id);
        $grouptool = $DB->get_record('grouptool', array('id' => $cm->instance));

        // Update the calendar.
        require_once($CFG->dirroot.'/calendar/lib.php');
        $event = new stdClass();
        if ($grouptool->allow_reg) {
            $event->name = get_string('registration_period_start', 'grouptool').' '.$grouptool->name;
        } else {
            $event->name = $grouptool->name.' '.get_string('availabledate', 'grouptool');
        }
        $event->description  = format_module_intro('grouptool', $grouptool, $cm->id);
        if (!empty($grouptool->timeavailable)) {
            $event->timestart = $grouptool->timeavailable;
        } else {
            $grouptool->timecreated = $DB->get_field('grouptool', 'timecreated',
                                                     array('id' => $grouptool->id));
            $event->timestart = $grouptool->timecreated;
        }
        $event->visible      = instance_is_visible('grouptool', $grouptool);
        $event->timeduration = 0;

        if ($event->id = $DB->get_field('event', 'id',
                                        array('modulename' => 'grouptool',
                                              'instance'   => $grouptool->id,
                                              'eventtype'  => 'availablefrom'))) {
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, false);
        } else {
            $event->courseid     = $grouptool->course;
            $event->groupid      = 0;
            $event->userid       = 0;
            $event->modulename   = 'grouptool';
            $event->instance     = $grouptool->id;
            /*
             *  For activity module's events, this can be used to set the alternative text of the
             *  event icon. Set it to 'pluginname' unless you have a better string.
             */
            $event->eventtype    = 'availablefrom';

            calendar_event::create($event);
        }

        if (($grouptool->timedue != 0)) {
            unset($event->id);
            unset($calendarevent);
            if ($grouptool->allow_reg) {
                $event->name = get_string('registration_period_end', 'grouptool').' '.$grouptool->name;
            } else {
                $event->name = $grouptool->name.' '.get_string('duedate', 'grouptool');
            }
            $event->timestart = $grouptool->timedue;
            $event->eventtype    = 'deadline';
            /*
             *  For activity module's events, this can be used to set the alternative text of the
             *  event icon. Set it to 'pluginname' unless you have a better string.
             */
            if ($event->id = $DB->get_field('event', 'id',
                                            array('modulename' => 'grouptool',
                                                  'instance'   => $grouptool->id,
                                                  'eventtype'  => 'deadline'))) {
                $calendarevent = calendar_event::load($event->id);
                $calendarevent->update($event, false);
            } else {
                unset($event->id);
                $event->courseid = $grouptool->course;
                // We've got some permission issues with calendar_event::create() so we work around that!
                $calev = new calendar_event($event);
                $calev->update($event, false);
            }

        } else if ($event->id = $DB->get_field('event', 'id', array('modulename' => 'grouptool',
                                                                    'instance'   => $grouptool->id,
                                                                    'eventtype'  => 'deadline'))) {
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->delete(true);
        }

    }
}
