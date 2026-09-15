<?php
/*********************************************************************
    SlaGracePeriodCalculator.php

    Extracted MOD-25 grace-period seam. SLA::addGracePeriod() in
    include/class.sla.php delegates the schedule-resolved-delegation-vs-
    wall-clock-fallback decision and math here, once the facade has
    already resolved the schedule to use (explicit arg -> SLA's own
    getSchedule() -> global $cfg->getDefaultSchedule()).

    This service never calls back into SLA::addGracePeriod() (the
    entry-point method it replaces) — it delegates only to
    BusinessHoursSchedule::addWorkingHours() (a sub-method of a
    different class), matching the "sub-method delegation" pattern.
    All facade concerns — schedule precedence resolution, the global
    $cfg default-schedule lookup, and SLA-schedule lookup/caching —
    remain in include/class.sla.php for the strangler stage.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaGracePeriodCalculator {

    /**
     * Exact lift of the core logic in SLA::addGracePeriod(), minus the
     * facade-owned schedule precedence resolution (explicit arg ->
     * $this->getSchedule() -> $cfg->getDefaultSchedule()), which the
     * caller must perform before invoking this method and pass in as
     * $schedule already resolved (or null/false if nothing resolved).
     *
     * If $schedule resolved AND $schedule->addWorkingHours($date, $hours,
     * $timeline) returns a truthy DateTime, that result is returned
     * immediately and $timeline is populated by
     * BusinessHoursSchedule::addWorkingHours() (which delegates to
     * BusinessHours::addWorkingHours()/getTimeline()) — the wall-clock
     * fallback below must NOT run in that case.
     *
     * Otherwise (no schedule resolved at all, or the resolved schedule's
     * addWorkingHours() returned false), falls through to a plain
     * wall-clock add: $hours is converted to seconds and rounded (since
     * DateInterval cannot take fractional seconds) and added directly to
     * $date via DateInterval. $timeline is left untouched on this branch.
     *
     * $date is mutated and returned as the same instance in every
     * branch — no branch substitutes a new DateTime object.
     *
     * @param Datetime $date
     *   Start time to advance; mutated in place and returned.
     * @param mixed $hours
     *   Grace period, in (possibly fractional) hours — the resolved
     *   value of SLA::getGracePeriod().
     * @param BusinessHoursSchedule $schedule
     *   The already precedence-resolved schedule to try first, or
     *   null/false if none resolved.
     * @param array $timeline
     *   By-reference audit log, populated only on the business-hours
     *   branch.
     *
     * @return Datetime
     *   The same $date instance passed in, advanced by the grace period.
     */
    public function addGracePeriod(Datetime $date,
            $hours, BusinessHoursSchedule $schedule = null,
            &$timeline = array()) {

        if ($schedule) {
            if (($schedule->addWorkingHours($date, $hours, $timeline)))
                return $date;
        }

        // No schedule, no problem - just add the hours and call ot a day.
        $time = round($hours * 3600);
        $interval = new DateInterval('PT' . $time . 'S');
        $date->add($interval);

        return $date;
    }
}

?>
