<?php
/*********************************************************************
    SlaGracePeriodCalculator.php

    Extracted MOD-25 seam. The facade method SLA::addGracePeriod()
    in include/class.sla.php delegates here.

    This is a sub-method-delegation extraction: coreLogic operates on
    an already-resolved (nullable) schedule and delegates the actual
    business-hours math to BusinessHoursSchedule::addWorkingHours(),
    not back to SLA::addGracePeriod(). Schedule *resolution* (the
    $schedule ?: $this->getSchedule() ?: $cfg->getDefaultSchedule()
    precedence chain, which reads global $cfg and the SLA instance's
    own schedule) is facade orchestration and stays in
    include/class.sla.php for the strangler stage; this service only
    receives the already-resolved $schedule and performs the
    two-branch grace-period math verbatim.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaGracePeriodCalculator {

    /**
     * Exact lift of the two-branch grace-period math from
     * SLA::addGracePeriod(), given an already-resolved (nullable)
     * $schedule.
     *
     * Branch 1 (schedule): if $schedule is truthy, delegate to
     * $schedule->addWorkingHours($date, $sla->getGracePeriod(),
     * $timeline); if that call returns truthy, return $date
     * immediately without evaluating the fallback. Note this
     * intentionally checks the plain truthiness of the resolved
     * $schedule (no reassignment/precedence chain here — that lives
     * in the facade).
     *
     * Branch 2 (fallback): reached when $schedule is falsy, or when
     * the schedule branch's addWorkingHours() call returned falsy
     * (e.g. a schedule with zero entries). Computes
     * $time = round($sla->getGracePeriod()*3600) seconds and adds
     * new DateInterval('PT'.$time.'S') to $date via plain calendar
     * time, verbatim from the original — no timezone mutation, no
     * $timeline update.
     *
     * $date is mutated in place (same instance) and also returned;
     * this method always returns a DateTime, never false/null.
     * $timeline is populated only by the schedule branch (copied by
     * BusinessHoursSchedule::addWorkingHours from its own
     * $bhrs->getTimeline()); it is left untouched on the fallback
     * branch.
     */
    public function addGracePeriod(SLA $sla, Datetime $date,
            BusinessHoursSchedule $schedule = null, &$timeline = array()) {

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
