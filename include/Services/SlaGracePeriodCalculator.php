<?php
/*********************************************************************
    SlaGracePeriodCalculator.php

    Extracts the core grace-period stepping logic from
    SLA::addGracePeriod(). Schedule resolution (explicit param vs.
    $sla->getSchedule() vs. $cfg->getDefaultSchedule()) is a facade
    concern and stays in include/class.sla.php — this service only
    forward-delegates to BusinessHoursSchedule::addWorkingHours and the
    plain DateInterval fallback.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaGracePeriodCalculator {

    /**
     * Advance $date by the SLA's grace period, in place.
     *
     * Mirrors the body of SLA::addGracePeriod() minus schedule
     * resolution: given an already-resolved $schedule (or null), try
     * the business-hours-aware path first and fall back to a flat
     * DateInterval add of the grace period (in hours) when there is no
     * schedule or the schedule's addWorkingHours() call fails.
     *
     * Does not call SLA::addGracePeriod() — the facade delegates to
     * this method, not the other way around.
     *
     * @param SLA $sla Hydrated SLA instance (used for getGracePeriod())
     * @param Datetime $date Mutated in place and returned
     * @param BusinessHoursSchedule $schedule Already-resolved schedule, or null
     * @param mixed $timeline By-reference output, populated by
     *      BusinessHoursSchedule::addWorkingHours when the schedule path runs
     * @return Datetime The same $date instance, advanced in place
     */
    public function addGracePeriod($sla, Datetime $date,
            BusinessHoursSchedule $schedule = null, &$timeline = array()) {

        if ($schedule) {
            if (($schedule->addWorkingHours($date,
                            $sla->getGracePeriod(), $timeline)))
                return $date;
        }

        // No schedule, no problem - just add the hours and call it a day.
        $time = round($sla->getGracePeriod()*3600);
        $interval = new DateInterval('PT'.$time.'S');
        $date->add($interval);

        return $date;
    }
}

?>
