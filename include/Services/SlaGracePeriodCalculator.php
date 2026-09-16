<?php
/*********************************************************************
    SlaGracePeriodCalculator.php

    Extracted MOD-25 grace-period seam. The facade method
    SLA::addGracePeriod() in include/class.sla.php delegates here for
    the portion of its body that does NOT belong to schedule
    resolution:

      - Given an ALREADY-RESOLVED schedule (or null), advance the
        working-hours path via BusinessHoursSchedule::addWorkingHours().
      - Fall back to a plain wall-clock DateInterval add when there is
        no schedule at all, or the working-hours call itself returns
        falsy (e.g. an empty/entry-less schedule).

    Facade concerns that stay in include/class.sla.php (per the seam
    manifest, this class must NOT re-implement them):
      - Schedule precedence resolution: requested $schedule param,
        then $this->getSchedule(), then global $cfg->getDefaultSchedule().
      - Reading the global $cfg singleton.
      - Reading $this->getGracePeriod() off the SLA instance.

    This class never calls back into SLA::addGracePeriod() itself; it
    only delegates to BusinessHoursSchedule::addWorkingHours(), which is
    a different class/method than the entry point being stripped, so
    there is no recursion risk once the facade delegates to it.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaGracePeriodCalculator {

    /**
     * Exact lift of the body of SLA::addGracePeriod() *after* schedule
     * precedence resolution has already produced $schedule (or null).
     *
     * @param DateTime $date
     *   The start time to advance. Mutated in place and also returned,
     *   matching addGracePeriod()'s contract — callers rely on both the
     *   mutation and the return value, so this must never be swapped
     *   for a fresh DateTime copy.
     * @param int|float $hours
     *   The grace period, in hours (the SLA's grace_period field, read
     *   by the facade via $this->getGracePeriod() and passed in here —
     *   never overridable by the caller of addGracePeriod()).
     * @param BusinessHoursSchedule|null $schedule
     *   The already-resolved schedule (requested param, SLA's own
     *   schedule, or the system default — resolution happens in the
     *   facade, not here), or null if none resolved at all.
     * @param array $timeline
     *   By-reference. Left untouched (stays whatever the caller passed
     *   in, default empty array) unless the schedule path actually
     *   runs BusinessHours::addWorkingHours(), in which case it is
     *   populated with that run's audit-log timeline.
     *
     * @return DateTime
     *   The same $date instance passed in, advanced by the grace
     *   period (never false/null).
     */
    public function addGracePeriod(DateTime $date, $hours,
            BusinessHoursSchedule $schedule = null, &$timeline = array()) {

        // If a schedule resolved AND its working-hours walk returns
        // truthy, that result wins outright — the wall-clock fallback
        // below must never execute in this case.
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
