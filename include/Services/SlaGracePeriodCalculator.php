<?php
/*********************************************************************
    SlaGracePeriodCalculator.php

    Extracted MOD-25 grace-period calculation seam. The facade entry
    point, SLA::addGracePeriod() in include/class.sla.php, retains the
    schedule-precedence orchestration (explicit $schedule argument vs.
    $this->getSchedule() vs. global $cfg->getDefaultSchedule()) and
    calls this service with an already-resolved schedule (or null) plus
    the SLA's grace-period hours.

    This service only performs the two-branch calculation documented
    in the MOD-25 seam manifest:
      (1) schedule path  — delegate to
          BusinessHoursSchedule::addWorkingHours($date, $hours, $timeline);
          on success, return $date immediately (already advanced/
          timezone-shifted by BusinessHours internally).
      (2) fallback path — reached when no schedule was resolved OR
          addWorkingHours() returned false: add a flat
          round($hours * 3600) seconds via DateInterval to $date in
          its existing timezone.

    This never calls back into SLA::addGracePeriod() (anti-recursion);
    it only forward-delegates to BusinessHoursSchedule::addWorkingHours().

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaGracePeriodCalculator {

    /**
     * Exact lift of the two-branch body of SLA::addGracePeriod(), minus
     * the schedule-precedence resolution (that stays in include/class.sla.php).
     *
     * @param Datetime $date
     *   Advance point for the grace period. Mutated in place and also
     *   returned, matching the original method's contract.
     * @param float|int $gracePeriodHours
     *   The SLA's own grace_period field (hours). Always sourced from the
     *   SLA instance on both paths — never from $schedule's own config —
     *   so callers must pass the SLA's getGracePeriod() value here even when
     *   a different $schedule object is supplied.
     * @param BusinessHoursSchedule|null $schedule
     *   Already-resolved schedule (explicit argument > SLA's own schedule >
     *   system default), or null if none resolved. Resolution precedence is
     *   a facade concern and must happen before calling this service.
     * @param array $timeline
     *   By-reference audit-log output. Populated only on the successful
     *   schedule path; retains the caller-supplied value (default empty
     *   array) on the fallback path.
     *
     * @return Datetime
     *   The same $date instance passed in, advanced in place.
     */
    public function addGracePeriod(Datetime $date, $gracePeriodHours,
            BusinessHoursSchedule $schedule = null, &$timeline = array()) {

        // Schedule path: only attempted when a schedule resolved. If
        // addWorkingHours() succeeds (returns the DateTime, not false),
        // return immediately and skip the fallback entirely.
        if ($schedule) {
            if (($schedule->addWorkingHours($date, $gracePeriodHours,
                            $timeline)))
                return $date;
        }

        // Fallback path: no schedule resolved, or addWorkingHours()
        // returned false (e.g. zero business-hours entries). No timezone
        // change here — add the interval in $date's existing timezone.
        $time = round($gracePeriodHours * 3600);
        $interval = new DateInterval('PT'.$time.'S');
        $date->add($interval);

        return $date;
    }
}
