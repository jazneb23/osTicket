<?php
/*********************************************************************
    SlaGracePeriodCalculator.php

    Extracted MOD-25 grace-period seam. SLA::addGracePeriod()
    (include/class.sla.php) delegates here for the core
    schedule-precedence + business-hours-walk + plain-hours-fallback
    calculation.

    Facade-level concerns stay in include/class.sla.php: resolving the
    global $cfg default schedule and SLA-local getSchedule() lookups are
    both performed by the facade and the *already-resolved* $schedule is
    handed to this service. This service forward-delegates only to
    BusinessHoursSchedule::addWorkingHours() / BusinessHours — never back
    to SLA::addGracePeriod() — to avoid recursion once the facade
    delegates to it.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaGracePeriodCalculator {

    /**
     * Exact lift of SLA::addGracePeriod()'s core calculation.
     *
     * $schedule must already reflect the facade's precedence resolution
     * (requested $schedule ?: $sla->getSchedule() ?: $cfg->getDefaultSchedule()).
     * If it resolves truthy and its addWorkingHours() call returns a
     * truthy DateTime, that result is returned immediately and $timeline
     * is populated by reference. Otherwise execution falls through to the
     * plain-hours fallback: $date->add(new DateInterval('PT'.round($hours*3600).'S')).
     *
     * $date is mutated in place and returned as the same reference on
     * both branches. $timeline is left untouched (default array()) on
     * the fallback branch.
     */
    public function addGracePeriod(Datetime $date, $hours,
            BusinessHoursSchedule $schedule = null, &$timeline = array()) {

        if ($schedule) {
            if (($schedule->addWorkingHours($date, $hours, $timeline)))
                return $date;
        }

        // No schedule, no problem - just add the hours and call it a day.
        $time = round($hours*3600);
        $interval = new DateInterval('PT'.$time.'S');
        $date->add($interval);

        return $date;
    }
}

?>
