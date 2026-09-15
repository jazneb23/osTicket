<?php
/*********************************************************************
    GracePeriodCalculator.php

    Extracted MOD-25 seam. SLA::addGracePeriod() in include/class.sla.php
    delegates here for the core grace-period math, once the facade has
    resolved which BusinessHoursSchedule (if any) applies.

    Schedule resolution (requested $schedule ?: SLA::getSchedule() ?:
    $cfg->getDefaultSchedule()), the `global $cfg` read, and any other
    caller-specific orchestration stay in the facade — this service only
    receives the already-resolved $schedule (or null) and performs the
    two computation branches:

      1) Schedule-based path — delegates to
         BusinessHoursSchedule::addWorkingHours($date, $hours, &$timeline)
         (include/class.schedule.php), which itself builds a fresh
         BusinessHours instance per call and mutates $date's timezone and
         the by-reference $timeline audit log as a side effect. If that
         call returns truthy, $date is returned immediately.

      2) No-schedule / fallback path — plain inline math: round the SLA's
         grace_period hours to seconds and add that as a DateInterval
         directly to $date. $timeline is left untouched here, matching
         current behavior.

    This service calls back into the owning SLA instance only for its
    plain getter (getGracePeriod()) — it never calls back into
    SLA::addGracePeriod() itself, to avoid recursion once the facade
    delegates to it.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class GracePeriodCalculator {

    /**
     * Exact lift of the core computation inside SLA::addGracePeriod().
     *
     * @param SLA $sla
     *     The owning SLA instance. Used only for its getGracePeriod()
     *     getter — never called back into addGracePeriod() itself.
     * @param Datetime $date
     *     Mutated in place (both branches) and returned as the same
     *     object identity — callers rely on in-place mutation semantics.
     * @param BusinessHoursSchedule|null $schedule
     *     Already-resolved schedule (requested param, SLA's own schedule,
     *     or system default) — resolution itself stays in the facade.
     *     Pass null when no schedule resolved from any source.
     * @param array &$timeline
     *     By-reference out-param. Populated only via the schedule path
     *     (BusinessHoursSchedule::addWorkingHours assigns it from the
     *     underlying BusinessHours timeline/auditlog); left untouched on
     *     the no-schedule fallback path.
     *
     * @return Datetime
     *     The same $date object, advanced forward by the grace period.
     */
    public function addGracePeriod(SLA $sla, Datetime $date,
            BusinessHoursSchedule $schedule = null, &$timeline = array()) {

        // Schedule-based path: delegate to BusinessHoursSchedule /
        // BusinessHours business-hours math. Return immediately if it
        // succeeds — do not also apply the flat-hours fallback below.
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
