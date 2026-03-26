# Ticket System Workflow

This guide explains how the helpdesk ticket system should be used from start to finish.

It is written in simple language so staff, coordinators, and end users can all understand the same process.

## Purpose

The ticket system is used to:

- record support requests in one place
- assign the right support person to the issue
- track progress from start to finish
- keep a clear message history
- collect client confirmation and feedback

## Who Uses The System

- `Client`: the person who submits a ticket and follows up on it
- `Technical User / Technician`: the person assigned to work on the ticket
- `Super User / Admin`: the person who monitors, reviews, assigns, escalates, closes, and reports on tickets

## Simple Status Meaning

Use these meanings consistently so everyone interprets the ticket the same way.

| Status | Simple meaning | When to use it |
| --- | --- | --- |
| `Open` | The ticket was received but work has not really started yet. | New ticket, or ticket reopened for more follow-up. |
| `In Progress` | Someone is already working on it. | A technician has started checking, troubleshooting, or coordinating. |
| `Pending` | The ticket is waiting on something. | Waiting for client reply, schedule, approval, parts, or outside action. |
| `Resolved` | The issue appears fixed or completed, but the ticket is not yet fully closed. | Support already completed the work and is waiting for final confirmation or the close window. |
| `Closed` | The ticket is fully finished. | Final status after resolution is confirmed and closing requirements are complete. |

## Full Workflow

### 1. Client submits a ticket

The client goes to the ticket form and fills in:

- name
- contact number
- email
- province
- municipality or city
- subject
- category
- priority
- description of the issue
- at least one attachment
- consent checkbox

What happens next:

- the system creates a unique ticket number
- the ticket starts with status `Open`
- the support team is notified that a new request was submitted

### 2. Support reviews the new ticket

The support team checks the new ticket and confirms:

- the request is complete
- the category is correct
- the priority is correct
- the attachments are usable
- the right technician is assigned

Recommended first action for staff:

1. Read the subject, description, and attachment.
2. Check if the priority is reasonable.
3. Assign the correct technician or support owner.
4. Send a public reply if the client needs to know work has started.

Important note:

- If a support user sends a normal reply on an `Open` ticket, the system moves the ticket to `In Progress`.

### 3. Ticket is assigned

Once assigned, the ticket has a clear owner.

Staff can:

- assign one or more technicians
- change the assignment later if needed
- keep the correct people attached to the ticket for visibility and accountability

Best practice:

- assign as early as possible
- avoid leaving tickets unassigned
- reassign quickly if the original assignee is unavailable

### 4. Technician works on the issue

While the technician is handling the request, they should:

- review the full ticket details
- use replies to ask questions or give updates
- attach proof, screenshots, or documents when needed
- keep the ticket status accurate

Recommended status use during work:

- keep `In Progress` while active troubleshooting is happening
- use `Pending` only when waiting for something outside the technician's immediate control

Examples of `Pending`:

- waiting for the client to send more details
- waiting for approval from another office
- waiting for replacement equipment
- waiting for a scheduled onsite visit

### 5. Internal notes vs public replies

Support staff can send two kinds of updates:

- Public reply: visible to the client
- Internal note: hidden from the client

Use a public reply for:

- progress updates
- follow-up questions
- instructions to the client
- confirmation that work has started or finished

Use an internal note for:

- internal coordination
- technical observations not meant for the client
- reminders between support staff
- handoff notes during reassignment

Important rule:

- Internal notes should not contain information that the client still needs in order to act.

### 6. Ticket reaches a solution

When the issue is fixed or service is completed, the ticket should move to `Resolved`.

`Resolved` means:

- the support team believes the issue is already addressed
- the case is essentially finished
- the ticket is not yet in its final archived state

This is the best stage for:

- asking the client to test the fix
- waiting for final confirmation
- preparing the final close reason if needed

### 7. Client confirmation and rating

Clients can confirm that the issue has been resolved.

When the client marks the ticket as resolved, they must also provide:

- a rating from `1` to `5`
- a short comment about the support experience
- confirmation that the issue is resolved

This helps the team:

- confirm service completion
- record satisfaction feedback
- review support quality later in reports

### 8. Closing the ticket

`Closed` is the final status.

Before closing:

- make sure the issue is already resolved
- add the required close reason
- confirm no more action is needed

Important closing rules in the current system:

- A close reason is required before a ticket can be closed.
- Technical users and super users can close a ticket only after `24 hours` have passed from the time it was resolved.
- Admins can manage status changes and final closure more directly.
- In the client screen, a `Closed` ticket no longer allows a normal reply.

Recommended meaning of `Closed`:

- work is complete
- the client already received the needed support
- the case does not need more routine follow-up

## Reopening And Reverting

Sometimes a ticket needs to be worked on again.

### When a client replies again

If a client replies after the ticket was already marked `Resolved`, the system can move the ticket back to `Open` so the team can continue follow-up.

### When support reverts a closed ticket

Support can revert a resolved or recently closed ticket back to `In Progress` if more work is needed.

Important system rule:

- A `Closed` ticket can only be reverted within `7 days` from the time it was closed.
- After that 7-day window, it can no longer be reverted through the normal workflow.

## Attachments And Messages

### Ticket creation

- At least one attachment is required when creating a new ticket.
- Each file can be up to `10 MB`.

### Replies

- Replies can contain a message, an attachment, or both.
- This allows users to send screenshots, documents, or follow-up proof even if the text message is short.

### Editing and deleting replies

For clients:

- their own replies can be edited only within `3 hours`
- their own replies can be deleted only within `3 hours`

For support:

- support can edit or delete their own replies from the ticket conversation

## Suggested Day-To-Day Workflow For Staff

This is the simplest routine staff can follow every day.

### For receiving staff

1. Open the new ticket.
2. Read the concern carefully.
3. Check the attachment and contact details.
4. Confirm the correct category and priority.
5. Assign the correct technician.
6. Send a public reply if the client needs acknowledgment.
7. Move or allow the ticket to move into `In Progress`.

### For technicians

1. Review the assigned ticket as soon as possible.
2. Reply to the client if more details are needed.
3. Use internal notes for team-only updates.
4. Change the status to `Pending` only when waiting on something.
5. Move the ticket to `Resolved` when the work is finished.

### For supervisors or admins

1. Monitor open and pending tickets.
2. Check for unassigned or overdue items.
3. Confirm that resolved tickets have proper follow-up.
4. Add the close reason before closing.
5. Close tickets only when the process is truly complete.

## Simple Example

Here is a plain example of how one ticket may move:

1. A client submits a printer issue with a photo and description.
2. The ticket is created as `Open`.
3. A support coordinator assigns it to a technician.
4. The technician sends a public reply: "We are checking this now."
5. The ticket becomes `In Progress`.
6. The technician discovers a part is needed and sets it to `Pending`.
7. After the part is installed, the technician tests the printer and sets the ticket to `Resolved`.
8. The client confirms the printer is working and submits a rating and comment.
9. The support team adds the close reason and closes the ticket.

## Recommended Staff Rules

To keep the workflow clean and easy to audit:

- do not leave tickets without an assignee for long
- do not use `Pending` as a long-term parking status without a reason
- do not close a ticket without a clear close reason
- do not use internal notes for information the client still needs to see
- do not mark a ticket `Resolved` if the work has not actually reached a usable outcome

## Quick Reference

If you only remember one simple flow, use this:

`Open` -> `In Progress` -> `Pending` if waiting -> `Resolved` -> `Closed`

If more work is needed after resolution:

`Resolved` -> back to `Open` or `In Progress`

If a recently closed ticket needs more work:

`Closed` -> `In Progress` within `7 days`
