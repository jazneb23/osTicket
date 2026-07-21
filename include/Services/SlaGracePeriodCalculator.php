<?php
/*********************************************************************
    SlaGracePeriodCalculator.php

    Extracted MOD-25 grace-period seam. include/class.sla.php's
    SLA::addGracePeriod() keeps schedule-precedence resolution
    ($schedule parameter ?: $this->getSchedule() ?: $cfg->getDefaultSchedule())
    and the global $cfg read in the facade, then delegates here with the
    already-resolved $schedule for the branch dispatch and grace-period
    math.

    This service forward-delegates only to
    BusinessHoursSchedule::addWorkingHours() and DateInterval math — it
    never calls back into SLA::addGracePeriod() itself, to avoid
    recursion once the facade delegates to it.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaGracePeriodCalculator {

    /**
     * Exact lift of the branch dispatch + grace-period math from
     * SLA::addGracePeriod(), given an already-resolved $schedule (the
     * facade performs the $schedule ?: $this->getSchedule() ?:
     * $cfg->getDefaultSchedule() precedence chain before calling here).
     *
     * If $schedule resolved (truthy) and
     * BusinessHoursSchedule::addWorkingHours() succeeds (returns
     * truthy, not false), $date (mutated in place) is returned
     * immediately and the flat-hours fallback is not applied.
     *
     * Otherwise (no schedule resolved, or addWorkingHours() returned
     * false) execution falls through to the flat-hours fallback: add
     * round($sla->getGracePeriod() * 3600) seconds to $date via a
     * DateInterval.
     *
     * $date is mutated in place (by object handle) and returned.
     * &$timeline is populated only on the schedule/business-hours
     * branch (via BusinessHoursSchedule::addWorkingHours), left as the
     * caller's passed-in default on the flat-hours fallback branch.
     */
    public function addGracePeriod(SLA $sla, Datetime $date,
            BusinessHoursSchedule $schedule = null, &$timeline = array()) {

        if ($schedule) {
            if (($schedule->addWorkingHours($date,
                            $sla->getGracePeriod(), $timeline)))
                return $date;
        }

        // No schedule, no problem - just add the hours and call ot a day.
        $time = round($sla->getGracePeriod() * 3600);
        $interval = new DateInterval('PT' . $time . 'S');
        $date->add($interval);

        return $date;
    }
}

?>
