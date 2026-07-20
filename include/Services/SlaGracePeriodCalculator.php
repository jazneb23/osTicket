<?php
/*********************************************************************
    SlaGracePeriodCalculator.php

    Extracted MOD-25 grace-period seam. The facade entry point,
    SLA::addGracePeriod() in include/class.sla.php, keeps all
    orchestration concerns:
      - reading the global $cfg object
      - resolving schedule precedence ($schedule arg > $this->getSchedule()
        > $cfg->getDefaultSchedule() > no schedule)
      - calling $this->getGracePeriod()

    and hands this service the already-resolved schedule (or null) plus
    the grace-period hours. This service performs only the core
    calculation: attempt a business-hours-aware add via the resolved
    schedule, and fall back to a flat wall-clock DateInterval add when no
    schedule resolved or the schedule call reports failure.

    This service delegates to BusinessHoursSchedule::addWorkingHours()
    (a sub-method of a different class), never back into
    SLA::addGracePeriod() itself, to avoid recursion once the facade
    delegates here.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaGracePeriodCalculator {

    /**
     * Core lift of the body of SLA::addGracePeriod().
     *
     * @param DateTime $date
     *      Mutated in place and returned, exactly as the facade method
     *      does.
     * @param BusinessHoursSchedule|null $schedule
     *      The already-resolved schedule (facade-side precedence
     *      resolution among the explicit argument, the SLA's own
     *      schedule, and the system default). Null means no schedule
     *      resolved, forcing the wall-clock fallback.
     * @param float|int $hours
     *      The SLA's grace period, in hours (facade's
     *      $this->getGracePeriod()), used as-is on the schedule path and
     *      converted to seconds on the fallback path.
     * @param array $timeline
     *      By-reference audit/timeline output. Only overwritten on the
     *      schedule-success path; left untouched on the fallback path.
     *
     * @return DateTime
     *      The same $date instance passed in, advanced in place.
     */
    public function addGracePeriod(DateTime $date,
            BusinessHoursSchedule $schedule = null, $hours = 0,
            &$timeline = array()) {

        // Schedule-based path: only attempted when a schedule resolved.
        // A truthy return short-circuits — the wall-clock fallback below
        // must not also run in that case.
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

?>
