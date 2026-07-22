<?php
/*********************************************************************
    SlaGracePeriodCalculator.php

    Extracted MOD-25 grace-period seam. include/class.sla.php's
    SLA::addGracePeriod() delegates here for the core grace-period
    math/delegation once the strangler stage patches the facade.

    Schedule *resolution* (explicit param -> SLA::getSchedule() ->
    $cfg->getDefaultSchedule() precedence, including the global $cfg
    read) is a facade concern per the seam manifest and stays in
    include/class.sla.php. This service receives the already-resolved
    $schedule (or null) and performs only the grace-period advancement:
    delegate to BusinessHoursSchedule::addWorkingHours() when a schedule
    resolved and has entries, otherwise fall back to a flat elapsed-time
    DateInterval add. This service never calls back into
    SLA::addGracePeriod() itself, to avoid recursion once the facade
    delegates to it.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaGracePeriodCalculator {

    /**
     * Exact lift of the core body of SLA::addGracePeriod(), minus the
     * schedule-resolution precedence chain (explicit $schedule param vs.
     * $sla->getSchedule() vs. $cfg->getDefaultSchedule()), which remains
     * the facade's responsibility.
     *
     * @param SLA $sla the owning SLA instance (for getGracePeriod())
     * @param Datetime $date mutated in place, the start time to advance
     * @param BusinessHoursSchedule|null $schedule the already-resolved
     *        schedule (per the facade's precedence chain), or null/falsy
     *        if none resolved
     * @param array $timeline by-reference output-only audit trail,
     *        populated solely by the business-hours path
     * @return Datetime the same $date instance passed in, advanced in
     *         place
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
