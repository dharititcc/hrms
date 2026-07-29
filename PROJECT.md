# HRMS

A human resources system: employees, attendance, leave, payroll, and the
project and meeting work that surrounds them.

Two applications in one repository.

| | |
|---|---|
| `backend/` | Laravel 13 API, PHP 8.3, MySQL. Sanctum tokens. |
| `next-monorepo/` | Next.js 16 App Router, React 19, TanStack Query, Tailwind, React Aria Components. |

Everything the interface does goes through the API. There are no Blade
screens; the one Blade view renders a payslip PDF.

---

## Ideas the whole system rests on

Read these first. Most of the code makes sense only in their light.

### A workspace is a user

Every record carries an `owner_id` pointing at the `users` row that owns the
workspace. There is no separate tenants table. A person invited into somebody
else's workspace gets their own `users` row linked to an `employees` record,
and `User::workspaceOwnerId()` resolves to the owner either way. Every query
is scoped by it.

### Permissions are resource-scoped and live in code

A permission is always `module.action` — `payroll.view`, `tasks.create`. The
matrix lives in `app/Support/PermissionRegistry.php`, not the database,
because the set is fixed by the product and a role that can be edited at
runtime can be misconfigured into locking everybody out.

Every combination is registered as a Laravel gate, so routes read
`->middleware('can:payroll.approve')`. There is deliberately **no
`Gate::before` bypass for admins**: an admin of one workspace must not reach
another's records, and a blanket bypass would have granted exactly that.

`view` and `view-all` are different things. On the personal modules —
attendance, leave, payroll, expenses — `view` means your own records and
`view-all` means everybody's. `App\Support\RecordScope` enforces the split in
the query, so an employee reads their own payslip without seeing the payroll.

### Figures are frozen when they are committed

Payslip lines, worked hours and salary assignments are all written once and
kept, never recomputed on read. Correcting a salary structure next month must
not silently rewrite a payslip already issued, and changing a shift must not
restate hours somebody has been paid for. Where a figure can legitimately be
restated — a corrected attendance record — the rules it was judged under are
stored on the row so the restatement uses them rather than today's.

### Times are instants plus a wall clock

Attendance stores three things: the wall clock where the employee was, the
absolute instant it maps to, and the IANA zone they were in. The wall clock is
what a shift start of 09:00 is compared against; the instant is what lets a
manager elsewhere read the day in their own zone. `APP_TIMEZONE` sets the
server's own clock and should match where the people using it work.

---

## Modules

### Employees

The directory. Table is `staff` and its foreign keys are `staff_id` — the
model, API and interface all say Employee, and only the schema still says
staff, because renaming those columns would touch every table for no
behavioural gain.

An employee may be invited, which creates a `users` row and emails a
set-password link. **No password is ever emailed**; the invitation carries a
link to set one.

An employee can be assigned an office, which is preferred when deciding which
office a geofenced check-in happened at.

### Attendance

Check in and out, with device, coordinates and timezone captured.

- **Shifts** (`work_shifts`) define office hours: start, end, grace, unpaid
  break. Falls back to `config/attendance.php` when a workspace has none.
- **Offices** (`attendance_locations`) are geofences with a haversine radius.
  Outside every office is flagged for approval; with
  `ATTENDANCE_ENFORCE_GEOFENCE=true` it is refused instead.
- **Lateness** is measured from the shift start, not the end of the grace.
  Grace forgives lateness; it does not move the start of the day.
- **The unpaid break** is only deducted once somebody has been present long
  enough for one to be due, and the result is held at that threshold until the
  break is absorbed, so a longer day is never worth less than a shorter one.
- **Corrections** restate everything derived from the times, mark the day as
  entered by hand, and send it back for approval.
- **Days never closed** are picked up nightly by `attendance:close-abandoned`,
  closed at the end of their shift, and sent for approval.

### Leave

Types with an annual entitlement, and requests against them. Approving is a
separate permission from editing. Balances on the dashboard count approved
requests only, and measure calendar days inclusive of both ends — which
overstates a booking spanning a weekend, and says so.

### Payroll

The largest module, and the one where being wrong costs the most.

```
salary structures ──> components (earning / deduction / employer contribution)
        │
        └─> employee salary assignment (basic + per-employee overrides)
                    │
                    └─> payroll run ──> salary slips ──> frozen slip lines
                                             │
                                             └─> payments
```

- **Structures and components** describe how a payslip is built. A component
  is fixed, a percentage of basic, a percentage of gross, or entered per
  payslip. An earning may not be a percentage of gross — gross includes
  earnings, so the value would depend on itself — and this is refused at save
  time rather than at generation.
- **Assignments** hold an employee's basic salary. A revision never edits the
  current row: it closes it the day before the new one starts, so a payslip
  can always be explained by the assignment in force when it was generated.
- **Runs** produce a draft slip per active employee with a salary in that
  country, calculated from the assignment in force at the period end.
- **Progressive taxes are `manual`**, not a flat rate. They generate as zero
  until somebody fills them in, and the interface counts and flags the unset
  ones, because approving as-is would under-deduct.
- **Workflow** is draft → pending approval → approved → paid. Approval is the
  point of no return: figures commit, and the run can no longer be
  recalculated or deleted. Payment is recorded per payslip, can be partial,
  and is refused before approval.
- **Payslips** render to PDF and can be emailed. Whether the PDF is attached
  is a config decision (`config/payslip.php`): attaching is expected, but it
  puts salary figures in an inbox.
- **Bank and tax details** are encrypted at rest, returned only as their last
  four digits, and their changes are logged with the values withheld —
  changing bank details is the classic payroll diversion attack.

> `config/payroll.php` carries statutory rates for seven countries. **They are
> starting defaults, not verified law.** Check every one against current
> legislation before anybody is paid.

### Tasks and projects

Projects with members, and tasks that may hang off a project or stand alone.
Tasks carry comments with @mentions, checklists, time entries with a running
timer, attachments, and recurrence. Assignees, followers and a public flag
decide who can see one.

### Meetings

Scheduling with participants, external guests, RSVP, reschedule, cancel and
recurrence. Guests have no account, so an unguessable token is their only
credential and the public RSVP endpoints are throttled. Meeting links are
behind a provider interface so Google Meet can be added without touching the
scheduling code.

### Notifications

Eight notification classes on the mail and database channels, surfaced by a
bell in the header. Reminders and recurrence run on the scheduler.

### Everything else

Expenses, recruitment, performance, assets, announcements and reports exist in
a thinner form: workspace-scoped, permission-gated, without the depth of the
modules above.

---

## Running it

```bash
cd backend && composer install && php artisan migrate --seed && php artisan serve
```

```bash
cd next-monorepo && npm install && npm run dev
```

The scheduler must run for recurrence, reminders and abandoned check-outs:
`php artisan schedule:work` locally, or `schedule:run` from cron every minute.

Tests are `php artisan test` in `backend/`. The frontend has no test harness;
it is checked with `npx tsc --noEmit`, `npm run lint` and `npm run build`.

## Conventions

- Repository and service layers behind controllers; form requests validate;
  API resources shape every response.
- Enums for anything with fixed values, never loose strings.
- Polymorphic aliases live in `App\Support\WorkspaceRecords`, and the morph map
  is enforced, so an unmapped model throws rather than leaking a class name.
- On the frontend, react-aria's `TextField` does not read react-hook-form's
  `defaultValues`. Every form that prefills must call `reset()` in an effect,
  with the defaults built by a module-level function so the dependency is
  stable.
