<?php
/*********************************************************************
    SlaGracePeriodCalculator.php

    Extracted grace-period math for SLA::addGracePeriod. Schedule
    resolution (explicit arg > SLA's own schedule > system default),
    the global $cfg read, and the SLA/Config lazy-load caches remain
    facade concerns and stay in include/class.sla.php. This service
    only performs the two-branch calculation once a schedule (or the
    absence of one) has already been decided by the caller.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
include_once INCLUDE_DIR.'class.schedule.php';

class SlaGracePeriodCalculator {

    // Add Grace Period to datetime, given an already-resolved schedule
    // (or null). Mirrors the two-branch logic that used to live inline
    // in SLA::addGracePeriod.
    function addGracePeriod(Datetime $date, $graceHours,
            BusinessHoursSchedule $schedule = null, &$timeline=array()) {

        if ($schedule) {
            if (($schedule->addWorkingHours($date, $graceHours, $timeline)))
                return $date;
        }

        // No schedule, no problem - just add the hours and call ot a day.
        $time = round($graceHours*3600);
        $interval = new DateInterval('PT'.$time.'S');
        $date->add($interval);

        return $date;
    }
}

?>
