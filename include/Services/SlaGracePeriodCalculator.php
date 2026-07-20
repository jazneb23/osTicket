<?php
/*********************************************************************
    SlaGracePeriodCalculator.php

    Extracted MOD-25 grace-period seam. SLA::addGracePeriod() in
    include/class.sla.php delegates here for the core grace-period
    date math, once it has already resolved the schedule to use
    (explicit argument > SLA's own schedule > system default) and
    read its own grace period hours.

    This service only ever delegates forward into
    BusinessHoursSchedule::addWorkingHours() and the DateInterval
    wall-clock fallback — it never calls back into
    SLA::addGracePeriod(), to avoid recursion once the facade
    delegates to it.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaGracePeriodCalculator {

    /**
     * Exact lift of the core two-branch calculation from
     * SLA::addGracePeriod(). Schedule resolution (explicit argument vs.
     * $sla->getSchedule() vs. $cfg->getDefaultSchedule()) is a facade
     * concern and stays in include/class.sla.php; this method receives
     * the already-resolved $schedule (or null) plus the grace period
     * hours to apply.
     *
     * If $schedule is truthy and $schedule->addWorkingHours() returns
     * truthy, $date is returned immediately (the by-reference $timeline
     * is populated by that call as a side effect). Otherwise execution
     * falls through to the wall-clock fallback, which advances $date by
     * round($graceHours*3600) seconds and leaves $timeline untouched.
     *
     * $date is mutated in place (and returned) in both branches, matching
     * the original method's behavior.
     */
    public function addGracePeriod(Datetime $date, $graceHours,
            BusinessHoursSchedule $schedule = null, &$timeline = array()) {

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
