"use client"

import { AlertTriangle, CalendarClock, CheckSquare, Inbox } from "lucide-react"
import Link from "next/link"
import { Button } from "@workspace/ui/components/button"
import { useDashboardStats } from "@/hooks/use-dashboard"
import { useAuthStore } from "@/store/auth-store"
import type { DashboardStats } from "@/types/dashboard"

export function OverviewModule() {
  const { data: stats, isLoading, isError, refetch } = useDashboardStats()
  const { user } = useAuthStore()

  if (isError) {
    return (
      <div className="mx-auto grid max-w-6xl place-items-center rounded-2xl border bg-background p-12 text-center">
        <p className="font-medium">Unable to load your dashboard</p>
        <Button className="mt-4" variant="outline" onPress={() => refetch()}>Retry</Button>
      </div>
    )
  }

  return (
    <div className="mx-auto grid max-w-6xl gap-6">
      <div>
        <p className="text-sm font-medium text-muted-foreground">Workspace</p>
        <h1 className="mt-2 text-2xl font-semibold tracking-tight">
          {user?.name ? `Good to see you, ${user.name.split(" ")[0]}.` : "Good to see you."}
        </h1>
        <p className="mt-2 text-sm text-muted-foreground">Here’s what needs your attention.</p>
      </div>

      {/* Aimed at this person specifically, rather than workspace totals. */}
      <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label="My open tasks" value={stats?.tasks.mine} loading={isLoading} icon={<CheckSquare className="size-5" />} href="/dashboard/tasks" />
        <StatCard label="Overdue" value={stats?.tasks.overdue} loading={isLoading} icon={<AlertTriangle className="size-5" />} href="/dashboard/tasks" tone={stats?.tasks.overdue ? "danger" : undefined} />
        <StatCard label="Due today" value={stats?.tasks.due_today} loading={isLoading} icon={<CalendarClock className="size-5" />} href="/dashboard/tasks" tone={stats?.tasks.due_today ? "warning" : undefined} />
        <StatCard label="Awaiting my reply" value={stats?.meetings.awaiting_my_reply} loading={isLoading} icon={<Inbox className="size-5" />} href="/dashboard/meetings" tone={stats?.meetings.awaiting_my_reply ? "warning" : undefined} />
      </section>

      <div className="grid gap-6 lg:grid-cols-3">
        <Panel title="Tasks" href="/dashboard/tasks" className="lg:col-span-2">
          <div className="grid gap-3 sm:grid-cols-3">
            <Metric label="Pending" value={stats?.tasks.pending} loading={isLoading} />
            <Metric label="In progress" value={stats?.tasks.in_progress} loading={isLoading} />
            <Metric label="In review" value={stats?.tasks.review} loading={isLoading} />
            <Metric label="Completed" value={stats?.tasks.completed} loading={isLoading} />
            <Metric label="Unassigned" value={stats?.tasks.unassigned} loading={isLoading} />
            <Metric label="Total" value={stats?.tasks.total} loading={isLoading} />
          </div>
          {stats && stats.tasks.total > 0 && <CompletionBar stats={stats} />}
        </Panel>

        <Panel title="Meetings" href="/dashboard/meetings">
          <div className="grid gap-3">
            <Metric label="Today" value={stats?.meetings.today} loading={isLoading} />
            <Metric label="This week" value={stats?.meetings.this_week} loading={isLoading} />
            <Metric label="Upcoming" value={stats?.meetings.upcoming} loading={isLoading} />
          </div>
        </Panel>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <Panel title="People" href="/dashboard/staff">
          <div className="grid gap-3">
            <Metric label="Staff" value={stats?.people.staff} loading={isLoading} />
            <Metric label="Active" value={stats?.people.active} loading={isLoading} />
            <Metric label="With accounts" value={stats?.people.with_accounts} loading={isLoading} />
          </div>
          {/* Staff without accounts cannot be assigned tasks, which is easy to miss. */}
          {stats && stats.people.staff > stats.people.with_accounts && (
            <p className="mt-3 text-xs text-muted-foreground">
              {stats.people.staff - stats.people.with_accounts} without an account cannot be assigned tasks.{" "}
              <Link href="/dashboard/staff" className="text-primary hover:underline">Invite them</Link>.
            </p>
          )}
        </Panel>

        <Panel title="Projects" href="/dashboard/projects">
          <div className="grid gap-3">
            <Metric label="Active" value={stats?.projects.active} loading={isLoading} />
            <Metric label="Total" value={stats?.projects.total} loading={isLoading} />
          </div>
        </Panel>

        <Panel title="Recent activity">
          {isLoading ? (
            <div className="grid gap-2">{[1, 2, 3].map((row) => <div key={row} className="h-5 animate-pulse rounded bg-muted" />)}</div>
          ) : (stats?.recent_activity.length ?? 0) === 0 ? (
            <p className="text-sm text-muted-foreground">Nothing yet.</p>
          ) : (
            <ol className="grid gap-2 text-sm">
              {(stats?.recent_activity ?? []).map((entry) => (
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

function CompletionBar({ stats }: { stats: DashboardStats }) {
  const percent = Math.round((stats.tasks.completed / stats.tasks.total) * 100)

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
