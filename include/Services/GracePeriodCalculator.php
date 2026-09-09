<?php
/*********************************************************************
    GracePeriodCalculator.php

    Extracted MOD-25 grace-period seam. The facade method
    SLA::addGracePeriod() in include/class.sla.php delegates here for
    the core grace-hours math, once it has resolved which schedule (if
    any) applies (requested $schedule ?: SLA::getSchedule() ?:
    $cfg->getDefaultSchedule()).

    This service never calls back into SLA::addGracePeriod() (the
    entry-point method it replaces) — it delegates forward to
    BusinessHoursSchedule::addWorkingHours() on the already-resolved
    schedule, or to plain DateInterval math, matching the existing
    include/Services/TicketOverdueService.php pattern of forward-only
    delegation.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class GracePeriodCalculator {

    /**
     * Exact lift of the core branch/math from SLA::addGracePeriod().
     *
     * Schedule resolution (requested $schedule ?: $sla->getSchedule() ?:
     * $cfg->getDefaultSchedule()) is a facade concern and stays in
     * include/class.sla.php; this method receives the already-resolved
     * $schedule (which may be null) and only performs:
     *
     *   - the schedule-based add via
     *     $schedule->addWorkingHours($date, $sla->getGracePeriod(), $timeline),
     *     returning $date immediately when that call succeeds; and
     *   - the plain-hours fallback
     *     ($date->add(new DateInterval('PT'.round($sla->getGracePeriod()*3600).'S')))
     *     when no schedule was resolved, or the schedule's
     *     addWorkingHours() call returned false.
     *
     * $date is mutated in place and returned as the same object identity
     * (no cloning). $timeline is populated by reference only on the
     * schedule-success branch, left as its input value otherwise. The
     * grace period always comes from $sla->getGracePeriod().
     */
    public function addGracePeriod(SLA $sla, DateTime $date,
            BusinessHoursSchedule $schedule = null, &$timeline = array()) {

        // Requested/resolved schedule takes precedence when it succeeds
        // in adding the working hours.
        if ($schedule) {
            if (($schedule->addWorkingHours($date,
                            $sla->getGracePeriod(), $timeline)))
                return $date;
        }

        // No schedule, no problem - just add the hours and call ot a day.
        $time = round($sla->getGracePeriod()*3600);
        $interval = new DateInterval('PT'.$time.'S');
        $date->add($interval);

        return $date;
    }
}

?>
