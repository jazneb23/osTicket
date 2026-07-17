<?php
/*********************************************************************
    SlaGracePeriodCalculator.php

    Extracted grace-period computation for SLA::addGracePeriod
    (include/class.sla.php). Accepts an already-resolved schedule (or
    null) plus the grace-period hours and the DateTime to advance;
    performs only the schedule-delegation/fallback math. Schedule
    resolution precedence (explicit schedule ?: SLA's own schedule ?:
    system default) and reads of global $cfg remain facade concerns in
    include/class.sla.php.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
include_once INCLUDE_DIR.'class.schedule.php';

class SlaGracePeriodCalculator {

    // Add Grace Period to datetime, given an already-resolved schedule
    // (or null) and the grace-period hours. Mirrors the core logic of
    // SLA::addGracePeriod without the facade's schedule-resolution
    // precedence chain.
    static function addGracePeriod(Datetime $date, $hours,
            BusinessHoursSchedule $schedule = null, &$timeline = array()) {

        if ($schedule) {
            if (($schedule->addWorkingHours($date, $hours, $timeline)))
                return $date;
        }

        // No schedule, no problem - just add the hours and call ot a day.
        $time = round($hours*3600);
        $interval = new DateInterval('PT'.$time.'S');
        $date->add($interval);

        return $date;
    }
}
