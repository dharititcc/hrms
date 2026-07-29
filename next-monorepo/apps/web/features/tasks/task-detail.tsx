"use client"

import { ArrowLeft, CalendarDays, Users } from "lucide-react"
import Link from "next/link"
import { Button } from "@workspace/ui/components/button"
import { taskPriorityLabels, taskPriorityStyles, taskStatusLabels } from "@/features/projects/labels"
import { TaskChecklist } from "@/features/tasks/task-checklist"
import { TaskComments } from "@/features/tasks/task-comments"
import { TaskTimer } from "@/features/tasks/task-timer"
import { useTask } from "@/hooks/use-task-detail"

export function TaskDetail({ taskId }: { taskId: number }) {
  const { data: task, isLoading, isError, refetch } = useTask(taskId)

  if (isLoading) {
    return (
      <div className="mx-auto grid max-w-5xl gap-6">
        <div className="h-8 w-64 animate-pulse rounded bg-muted" />
        <div className="grid gap-4 lg:grid-cols-3">
          <div className="h-64 animate-pulse rounded-2xl bg-muted lg:col-span-2" />
          <div className="h-64 animate-pulse rounded-2xl bg-muted" />
        </div>
      </div>
    )
  }

  if (isError || !task) {
    return (
      <div className="mx-auto grid max-w-5xl place-items-center rounded-2xl border bg-background p-12 text-center">
        <p className="font-medium">Unable to load this task</p>
        <p className="mt-1 text-sm text-muted-foreground">It may have been deleted, or you may not have access.</p>
        <div className="mt-4 flex gap-2">
          <Button variant="outline" onPress={() => refetch()}>Retry</Button>
          <Link href="/projects"><Button variant="ghost">Back to projects</Button></Link>
        </div>
      </div>
    )
  }

  const assignees = task.assignees ?? []
  const backHref = task.related_type === "project" && task.related_id ? `/projects/${task.related_id}` : "/projects"

  return (
    <div className="mx-auto grid max-w-5xl gap-6">
      <div>
        <Link href={backHref} className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
          <ArrowLeft className="size-4" />Back
        </Link>
        <div className="mt-3 flex flex-wrap items-center gap-3">
          <h1 className="text-2xl font-semibold tracking-tight">{task.subject}</h1>
          <span className="rounded-full bg-muted px-2.5 py-1 text-xs font-medium">{taskStatusLabels[task.status]}</span>
          <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${taskPriorityStyles[task.priority]}`}>{taskPriorityLabels[task.priority]}</span>
          {task.archived_at && <span className="rounded-full bg-amber-500/10 px-2.5 py-1 text-xs font-medium text-amber-600 dark:text-amber-400">Archived</span>}
        </div>
        {task.description && <p className="mt-3 max-w-3xl text-sm whitespace-pre-wrap text-muted-foreground">{task.description}</p>}
      </div>

      <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Fact label="Assignees">
          {assignees.length === 0 ? (
            <span className="text-sm text-muted-foreground">Unassigned</span>
          ) : (
            <span className="inline-flex items-center gap-1 text-sm"><Users className="size-3.5" />{assignees.map((a) => a.name).join(", ")}</span>
          )}
        </Fact>
        <Fact label="Due">
          <span className="inline-flex items-center gap-1 text-sm">
            <CalendarDays className="size-3.5" />{task.due_date ?? "—"}
          </span>
        </Fact>
        <Fact label="Estimated">
          <span className="text-sm">{task.estimated_hours ? `${task.estimated_hours}h` : "—"}</span>
        </Fact>
        <Fact label="Billable">
          <span className="text-sm">{task.is_billable ? "Yes" : "No"}</span>
        </Fact>
      </dl>

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="grid content-start gap-6 lg:col-span-2">
          <TaskComments taskId={task.id} />
        </div>
        <div className="grid content-start gap-6">
          <TaskChecklist taskId={task.id} />
          <TaskTimer taskId={task.id} />
        </div>
      </div>
    </div>
  )
}

function Fact({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="rounded-2xl border bg-background p-4">
      <dt className="text-xs font-medium text-muted-foreground">{label}</dt>
      <dd className="mt-2">{children}</dd>
    </div>
  )
}
