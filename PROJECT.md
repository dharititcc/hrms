# HRMS

A human resources system: employees, attendance, leave and payroll, together
with the project and meeting work that surrounds them.

Two applications in one repository.

| | |
|---|---|
| `backend/` | Laravel API, PHP 8.3, MySQL. Token authentication. |
| `next-monorepo/` | Next.js App Router, React, TanStack Query, Tailwind, React Aria Components. |

Everything the interface does goes through the API. There are no server-rendered
screens; the one exception renders a payslip to PDF.

---

## Ideas the whole system rests on

Read these first. Most of the rest makes sense only in their light.

### A workspace belongs to one person

Every record belongs to a workspace, and a workspace is owned by the account
that created it. Somebody invited into another person's workspace gets their
own login, linked to their employee record, and the system resolves either kind
of account to the same workspace. Nothing is ever visible across workspaces.

### Permissions are per module, and fixed by the product

A permission is always a module and an action together — seeing payroll,
creating a task. The set of them is defined in the application rather than
edited at runtime, so a role cannot be misconfigured into locking everybody
out. An administrator of one workspace still cannot reach another's records:
there is no blanket override anywhere.

Two levels of visibility matter. On the personal areas — attendance, leave,
payroll, expenses — one permission means *your own* records and a separate one
means *everybody's*. That distinction is enforced when the data is fetched, not
merely hidden in the interface, so an employee reads their own payslip without
being able to reach the payroll.

Roles set the starting point. Any individual can then be granted or denied
anything, and only the differences from their role are remembered — so somebody
left alone keeps following their role even if the role's meaning later changes.
Nobody can hand out access they do not hold themselves.

### Committed figures are frozen

Payslips, worked hours and salary history are written once and kept. Correcting
a salary structure next month must not quietly rewrite a payslip already
issued, and changing office hours must not restate hours somebody has been paid
for. Where a figure can legitimately be restated — a corrected attendance
record — the rules it was originally judged under are kept with it, so the
restatement uses those rather than today's.

### Times carry their place

Attendance records the clock the employee saw, the exact moment it corresponds
to, and where in the world they were. The clock reading is what a shift
starting at nine is compared against; the moment is what lets a manager
elsewhere read the same day in their own time.

---

## The modules

### Employees

The directory of people. Somebody can be invited, which creates them a login
and sends a link to choose a password — **no password is ever sent by email**.

Each person can be given a role, a home office, and individual access as
described above.

### Attendance

People check in and out. Each record captures the device, the location if
allowed, and the time zone.

- **Office hours** define when the day starts and ends, how much lateness is
  forgiven, and how long the unpaid break is. A workspace that has not set its
  own is judged against a sensible default.
- **Offices** are places with a radius around them. Checking in outside all of
  them is flagged for somebody to approve, or refused outright if enforcement
  is switched on.
- **Lateness** is counted from when the day was due to start. Grace forgives
  being late; it does not move the start.
- **The unpaid break** only comes off once somebody has been there long enough
  to have taken one, and never in a way that makes a longer day worth less than
  a shorter one.
- **A day can be corrected**, which recalculates the hours, marks it as entered
  by hand, and sends it back for approval.
- **A day nobody closed** is picked up overnight, closed at the end of its
  shift, and sent for approval rather than being left at nothing.

### Leave

Leave types carry an annual entitlement; people request against them and
somebody approves. Approving is deliberately a separate permission from
editing. Balances count approved leave only, and measure whole days from start
to end — which overstates a booking spanning a weekend, and says so where it is
shown.

### Payroll

The largest area, and the one where being wrong costs the most.

The shape of it:

> A **salary structure** describes how a payslip is built, as a set of
> **components** — allowances, deductions, employer contributions. Each
> employee gets a **salary**, which is a basic figure against a structure, plus
> any amounts particular to them. A **payroll run** covers a period and turns
> each person's salary into a **payslip**, which records its own breakdown and
> never changes afterwards. **Payments** are then recorded against payslips.

Things worth knowing:

- A component can be a fixed amount, a proportion of basic pay, a proportion of
  the total, or a figure entered each time. An allowance cannot be a proportion
  of the total, because the total includes allowances and the answer would
  depend on itself. That is refused when the component is saved, not when
  payroll is run.
- **A pay rise never edits the existing record.** The current salary is closed
  the day before the new one begins, so any payslip can still be explained by
  what was in force when it was produced.
- **Income tax is entered rather than calculated.** Progressive rates cannot be
  reduced to a single percentage without being wrong, so those lines start at
  nothing and the interface counts and flags the ones still unset — approving
  as-is would under-deduct.
- **Approval is the point of no return.** Before it, a run can be recalculated
  or thrown away; after it, neither. Payment is recorded per payslip, can be
  partial, and is refused before approval.
- **Payslips can be produced as PDFs and emailed.** Whether the file travels
  with the email is a setting: attaching it is what people expect, but it does
  put pay details in an inbox.
- **Bank details are encrypted, shown only as their last few digits, and any
  change to them is recorded** — with the numbers themselves left out of the
  record. Changing where someone's pay goes is a well-known fraud, and the
  trail is the defence.

> The statutory rates that ship for each country are **starting points, not
> verified law**. Check every one against current legislation before anybody is
> paid.

### Tasks and projects

Projects have members. Tasks can belong to a project or stand alone, and carry
comments with mentions, checklists, time tracking with a running timer,
attachments and recurrence. Who can see a task depends on whether it is public
and whether they are assigned to or following it.

### Meetings

Scheduling with participants, external guests, replies, rescheduling,
cancellation and recurrence. Guests have no account, so their invitation link
is their only credential and those pages are rate limited. Meeting links sit
behind a seam so a provider such as Google Meet can be added without disturbing
the scheduling.

### Notifications

Assignments, mentions, reminders, invitations and payslips reach people by
email and in the application, through a bell in the header. Reminders and
recurrence run on a schedule.

### Everything else

Expenses, recruitment, performance, assets, announcements and reports exist in
a lighter form — scoped and permission-gated like the rest, without the depth
of the areas above.

---

## Running it

```bash
cd backend && composer install && php artisan migrate --seed && php artisan serve
```

```bash
cd next-monorepo && npm install && npm run dev
```

Set the application's time zone to wherever the people using it work; it
decides which day a check-in falls on.

A scheduler must be running for recurrence, reminders and unclosed attendance:
`php artisan schedule:work` while developing, or a minutely cron entry in
deployment.

Tests are `php artisan test` in `backend/`. The interface has no test harness
and is checked with `npx tsc --noEmit`, `npm run lint` and `npm run build`.

## Conventions

- Controllers stay thin: validation in form requests, work in services, shape
  in resources.
- Anything with a fixed set of values is an enum, never a loose string.
- On the interface side, the form library and the component library disagree
  about initial values: every form that prefills has to push its values in
  after mounting, or the fields open empty.
