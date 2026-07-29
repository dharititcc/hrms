"use client"

import { AlertTriangle, CalendarClock, CheckSquare, Inbox } from "lucide-react"
import Link from "next/link"
import { Button } from "@workspace/ui/components/button"
import { QuickActions } from "@/features/dashboard/quick-actions"
import { useDashboardStats } from "@/hooks/use-dashboard"
import { usePermissions } from "@/hooks/use-permissions"
import { useAuthStore } from "@/store/auth-store"

export function OverviewModule() {
  const { data: stats, isLoading, isError, refetch } = useDashboardStats()
  const { user } = useAuthStore()
  const { can } = usePermissions()

  if (isError) {
    return (
      <div className="mx-auto grid max-w-6xl place-items-center rounded-2xl border bg-background p-12 text-center">
        <p className="font-medium">Unable to load your dashboard</p>
        <Button className="mt-4" variant="outline" onPress={() => refetch()}>Retry</Button>
      </div>
    )
  }

  // The API omits whole sections the caller has no permission for, so every
  // panel below is conditional rather than assumed present.
  const tasks = stats?.tasks
  const meetings = stats?.meetings
  const attendance = stats?.attendance
  const leave = stats?.leave
  const payroll = stats?.payroll
  const people = stats?.people
  const projects = stats?.projects
  const activity = stats?.recent_activity
  const nothingVisible = !isLoading && !tasks && !meetings && !attendance && !leave && !payroll && !people && !projects && !activity

  return (
    <div className="mx-auto grid max-w-6xl gap-6">
      <div>
        <p className="text-sm font-medium text-muted-foreground">Workspace</p>
        <h1 className="mt-2 text-2xl font-semibold tracking-tight">
          {user?.name ? `Good to see you, ${user.name.split(" ")[0]}.` : "Good to see you."}
        </h1>
        <p className="mt-2 text-sm text-muted-foreground">Here’s what needs your attention.</p>
      </div>

      {nothingVisible && (
        <div className="rounded-2xl border bg-background p-8 text-center">
          <p className="font-medium">Nothing to show yet</p>
          <p className="mt-1 text-sm text-muted-foreground">Your role doesn’t include access to these areas.</p>
        </div>
      )}

      {!isLoading && <QuickActions attendance={attendance} canCheckIn={can("attendance.create")} />}

      {/* Aimed at this person specifically, rather than workspace totals. */}
      {(isLoading || tasks || meetings) && (
        <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {(isLoading || tasks) && (
            <>
              <StatCard label="My open tasks" value={tasks?.mine} loading={isLoading} icon={<CheckSquare className="size-5" />} href="/dashboard/tasks" />
              <StatCard label="Overdue" value={tasks?.overdue} loading={isLoading} icon={<AlertTriangle className="size-5" />} href="/dashboard/tasks" tone={tasks?.overdue ? "danger" : undefined} />
              <StatCard label="Due today" value={tasks?.due_today} loading={isLoading} icon={<CalendarClock className="size-5" />} href="/dashboard/tasks" tone={tasks?.due_today ? "warning" : undefined} />
            </>
          )}
          {(isLoading || meetings) && (
            <StatCard label="Awaiting my reply" value={meetings?.awaiting_my_reply} loading={isLoading} icon={<Inbox className="size-5" />} href="/dashboard/meetings" tone={meetings?.awaiting_my_reply ? "warning" : undefined} />
          )}
        </section>
      )}

      <div className="grid gap-6 lg:grid-cols-3">
        {(isLoading || tasks) && (
          <Panel title="Tasks" href="/dashboard/tasks" className="lg:col-span-2">
            <div className="grid gap-3 sm:grid-cols-3">
              <Metric label="Pending" value={tasks?.pending} loading={isLoading} />
              <Metric label="In progress" value={tasks?.in_progress} loading={isLoading} />
              <Metric label="In review" value={tasks?.review} loading={isLoading} />
              <Metric label="Completed" value={tasks?.completed} loading={isLoading} />
              <Metric label="Unassigned" value={tasks?.unassigned} loading={isLoading} />
              <Metric label="Total" value={tasks?.total} loading={isLoading} />
            </div>
            {tasks && tasks.total > 0 && <CompletionBar completed={tasks.completed} total={tasks.total} />}
          </Panel>
        )}

        {(isLoading || meetings) && (
          <Panel title="Meetings" href="/dashboard/meetings">
            <div className="grid gap-3">
              <Metric label="Today" value={meetings?.today} loading={isLoading} />
              <Metric label="This week" value={meetings?.this_week} loading={isLoading} />
              <Metric label="Upcoming" value={meetings?.upcoming} loading={isLoading} />
            </div>
          </Panel>
        )}
      </div>

      {(attendance?.active_employees !== undefined || leave || payroll) && (
        <div className="grid gap-6 lg:grid-cols-3">
          {/* Only for someone who can see the team; an employee's own day is
              already in the quick actions above. */}
          {attendance?.active_employees !== undefined && (
            <Panel title="Today" href="/dashboard/attendance">
              <div className="grid gap-3">
                <Metric label="Present" value={attendance.present_today} loading={false} />
                <Metric label="Late" value={attendance.late_today} loading={false} />
                <Metric label="On leave" value={attendance.on_leave_today} loading={false} />
                <Metric label="Not recorded" value={attendance.not_recorded} loading={false} />
              </div>
              {(attendance.awaiting_approval ?? 0) > 0 && (
                <p className="mt-3 text-xs text-muted-foreground">
                  <Link href="/dashboard/attendance" className="text-amber-600 hover:underline dark:text-amber-400">
                    {attendance.awaiting_approval} awaiting approval
                  </Link>
                  {" "}— flagged for being outside an office or entered by hand.
                </p>
              )}
            </Panel>
          )}

          {leave && (
            <Panel title="Leave" href="/dashboard/leave">
              {leave.balances.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                  {leave.awaiting_approval === undefined
                    ? "No leave types are set up yet."
                    : "Balances appear once you have a employee record and leave types exist."}
                </p>
              ) : (
                <ul className="grid gap-3">
                  {leave.balances.map((balance) => (
                    <li key={balance.id}>
                      <div className="flex items-baseline justify-between gap-2 text-sm">
                        <span className="truncate text-muted-foreground">{balance.name}</span>
                        <span className="shrink-0 tabular-nums">
                          <span className="font-semibold">{balance.remaining}</span>
                          <span className="text-xs text-muted-foreground"> / {balance.entitlement}</span>
                        </span>
                      </div>
                      <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-muted">
                        <div
                          className="h-full rounded-full bg-primary transition-all"
                          style={{ width: `${balance.entitlement > 0 ? Math.min(100, (balance.taken / balance.entitlement) * 100) : 0}%` }}
                        />
                      </div>
                    </li>
                  ))}
                </ul>
              )}

              <div className="mt-3 grid gap-1 text-xs text-muted-foreground">
                {leave.my_pending > 0 && <p>{leave.my_pending} of your requests awaiting a decision.</p>}
                {(leave.awaiting_approval ?? 0) > 0 && (
                  <p>
                    <Link href="/dashboard/leave" className="text-amber-600 hover:underline dark:text-amber-400">
                      {leave.awaiting_approval} request{leave.awaiting_approval === 1 ? "" : "s"} need your decision
                    </Link>
                  </p>
                )}
                {leave.balances.length > 0 && <p>Calendar days, so a booking spanning a weekend counts it.</p>}
              </div>
            </Panel>
          )}

          {payroll && (
            <Panel title="Payroll" href="/dashboard/payroll">
              {payroll.latest_slip ? (
                <Link href="/dashboard/payroll" className="block rounded-lg bg-muted/30 px-3 py-2.5 transition-colors hover:bg-muted/50">
                  <p className="text-xs text-muted-foreground">Latest payslip{payroll.latest_slip.period ? ` · ${payroll.latest_slip.period}` : ""}</p>
                  <p className="mt-1 text-lg font-semibold tabular-nums">
                    {payroll.latest_slip.currency_symbol}{Number(payroll.latest_slip.net_salary).toLocaleString()}
                  </p>
                  <p className="mt-0.5 font-mono text-[0.7rem] text-muted-foreground">{payroll.latest_slip.slip_number}</p>
                </Link>
              ) : (
                payroll.draft_runs === undefined && (
                  <p className="text-sm text-muted-foreground">No payslip issued to you yet.</p>
                )
              )}

              {payroll.draft_runs !== undefined && (
                <div className="mt-3 grid gap-3">
                  <Metric label="Drafts" value={payroll.draft_runs} loading={false} />
                  <Metric label="Awaiting approval" value={payroll.awaiting_approval} loading={false} />
                  <Metric label="Approved, unpaid" value={payroll.awaiting_payment} loading={false} />
                </div>
              )}
            </Panel>
          )}
        </div>
      )}

      <div className="grid gap-6 lg:grid-cols-3">
        {people && (
          <Panel title="People" href="/dashboard/employees">
            <div className="grid gap-3">
              <Metric label="Employee" value={people.employees} loading={false} />
              <Metric label="Active" value={people.active} loading={false} />
              <Metric label="With accounts" value={people.with_accounts} loading={false} />
            </div>
            {/* Employee without accounts cannot be assigned tasks, which is easy to miss. */}
            {people.employees > people.with_accounts && (
              <p className="mt-3 text-xs text-muted-foreground">
                {people.employees - people.with_accounts} without an account cannot be assigned tasks.{" "}
                <Link href="/dashboard/employees" className="text-primary hover:underline">Invite them</Link>.
              </p>
            )}
          </Panel>
        )}

        {projects && (
          <Panel title="Projects" href="/dashboard/projects">
            <div className="grid gap-3">
              <Metric label="Active" value={projects.active} loading={false} />
              <Metric label="Total" value={projects.total} loading={false} />
            </div>
          </Panel>
        )}

        {activity && (
          <Panel title="Recent activity">
            {activity.length === 0 ? (
              <p className="text-sm text-muted-foreground">Nothing yet.</p>
            ) : (
              <ol className="grid gap-2 text-sm">
                {activity.map((entry) => (
                  <li key={entry.id} className="flex items-baseline justify-between gap-2">
                    <span className="truncate">
                      <span className="text-muted-foreground">{entry.user_name ?? "Someone"}</span>{" "}
                      {entry.action} {entry.entity.replace(/_/g, " ")}
                    </span>
                    <time className="shrink-0 text-xs text-muted-foreground" dateTime={entry.created_at}>
                      {new Date(entry.created_at).toLocaleDateString()}
                    </time>
                  </li>
                ))}
              </ol>
            )}
          </Panel>
        )}
      </div>
    </div>
  )
}

function StatCard({ label, value, loading, icon, href, tone }: {
  label: string
  value: number | undefined
  loading: boolean
  icon: React.ReactNode
  href: string
  tone?: "danger" | "warning"
}) {
  const toneClass = tone === "danger"
    ? "text-destructive"
    : tone === "warning"
      ? "text-amber-600 dark:text-amber-400"
      : "text-foreground"

  return (
    <Link href={href} className="rounded-2xl border bg-background p-5 transition-colors hover:bg-muted/40">
      <span className={tone ? toneClass : "text-muted-foreground"}>{icon}</span>
      <p className="mt-6 text-sm text-muted-foreground">{label}</p>
      {loading
        ? <div className="mt-1 h-8 w-12 animate-pulse rounded bg-muted" />
        : <p className={`mt-1 text-2xl font-semibold ${toneClass}`}>{value ?? 0}</p>}
    </Link>
  )
}

function Panel({ title, href, className, children }: { title: string; href?: string; className?: string; children: React.ReactNode }) {
  return (
    <section className={`rounded-2xl border bg-background p-5 ${className ?? ""}`}>
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-semibold">{title}</h2>
        {href && <Link href={href} className="text-xs text-muted-foreground hover:text-foreground">View all</Link>}
      </div>
      <div className="mt-4">{children}</div>
    </section>
  )
}

function Metric({ label, value, loading }: { label: string; value: number | undefined; loading: boolean }) {
  return (
    <div className="flex items-baseline justify-between gap-2 rounded-lg bg-muted/30 px-3 py-2">
      <span className="text-sm text-muted-foreground">{label}</span>
      {loading
        ? <span className="h-5 w-8 animate-pulse rounded bg-muted" />
        : <span className="text-lg font-semibold tabular-nums">{value ?? 0}</span>}
    </div>
  )
}

function CompletionBar({ completed, total }: { completed: number; total: number }) {
  const percent = Math.round((completed / total) * 100)

  return (
    <div className="mt-4">
      <div className="flex items-center justify-between text-xs text-muted-foreground">
        <span>Completion</span>
        <span>{percent}%</span>
      </div>
      <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
        <div className="h-full rounded-full bg-primary transition-all" style={{ width: `${percent}%` }} />
      </div>
    </div>
  )
}
