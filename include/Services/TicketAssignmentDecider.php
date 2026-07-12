<?php
/*********************************************************************
    TicketAssignmentDecider.php

    Lifted pure decision logic from Ticket::assign(): assignee-type guards,
    permission checks, in-memory staff_id/team_id mutations, stale-referral
    cleanup, and computation of evd/audit/refer/alert-suppression records.
    Persistence, signals, alerts, and referral creation stay in the facade.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class TicketAssignmentDecider {

    /**
     * Evaluate assignment guards and apply in-memory ticket mutations.
     *
     * Mutates $errors and $alert by reference. On success returns a decision
     * payload for facade orchestration after save(); on guard failure returns
     * null (with $errors populated).
     *
     * @param Ticket $ticket
     * @param AssignmentForm $form
     * @param array $errors
     * @param bool $alert
     * @param Staff|null $thisstaff Current staff for self-claim detection
     * @return array|null Decision with assignee, evd, audit, refer keys
     */
    public function decide(Ticket $ticket, AssignmentForm $form, &$errors,
            &$alert, $thisstaff = null) {

        $evd = array();
        $audit = array();
        $refer = null;
        $dept = $ticket->getDept();
        $assignee = $form->getAssignee();
        if ($assignee instanceof Staff) {
            if ($ticket->getStaffId() == $assignee->getId()) {
                $errors['assignee'] = sprintf(__('%s already assigned to %s'),
                        __('Ticket'),
                        __('the agent')
                        );
            } elseif (!$assignee->isAvailable()) {
                $errors['assignee'] = __('Agent is unavailable for assignment');
            } elseif (!$dept->canAssign($assignee)) {
                $errors['err'] = __('Permission denied');
            } else {
                $refer = $ticket->staff ?: null;
                $ticket->staff_id = $assignee->getId();
                if ($thisstaff && $thisstaff->getId() == $assignee->getId()) {
                    $alert = false;
                    $evd['claim'] = true;
                    $audit = array('staff' => $assignee->getName()->name,'claim' => true);
                } else {
                    $evd['staff'] = array($assignee->getId(), (string) $assignee->getName()->getOriginal());
                    $audit = array('staff' => $assignee->getName()->name);
                }

                if (($referral=$ticket->hasReferral($assignee,ObjectModel::OBJECT_TYPE_STAFF)))
                    $referral->delete();
            }
        } elseif ($assignee instanceof Team) {
            if ($ticket->getTeamId() == $assignee->getId()) {
                $errors['assignee'] = sprintf(__('%s already assigned to %s'),
                        __('Ticket'),
                        __('the team')
                        );
            } elseif (!$dept->canAssign($assignee)) {
                $errors['err'] = __('Permission denied');
            } else {
                $refer = $ticket->team ?: null;
                $ticket->team_id = $assignee->getId();
                $evd = array('team' => $assignee->getId());
                $audit = array('team' => $assignee->getName());
                if (($referral=$ticket->hasReferral($assignee,ObjectModel::OBJECT_TYPE_TEAM)))
                    $referral->delete();
            }
        } else {
            $errors['assignee'] = __('Unknown assignee');
        }

        if ($errors)
            return null;

        return array(
            'assignee' => $assignee,
            'evd' => $evd,
            'audit' => $audit,
            'refer' => $refer,
        );
    }
}

?>
