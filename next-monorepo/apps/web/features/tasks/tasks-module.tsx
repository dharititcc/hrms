"use client"

import { ArrowDown, ArrowUp, CheckSquare, Search } from "lucide-react"
import Link from "next/link"
import { useState } from "react"
import { Button } from "@workspace/ui/components/button"
import { taskPriorityLabels, taskPriorityStyles, taskStatusLabels, taskStatusOrder } from "@/features/projects/labels"
import { useTasks } from "@/hooks/use-task-detail"
import { useWorkspaceUsers } from "@/hooks/use-projects"
import type { TaskPriority, TaskStatus } from "@/types/project"
import type { TaskFilters, TaskListItem, TaskSort } from "@/types/task"

/** Preset views matching the dashboard widgets in the spec. */
const QUICK_VIEWS: { id: string; label: string; filters: Partial<TaskFilters> }[] = [
  { id: "all", label: "All", filters: {} },
  { id: "mine", label: "My tasks", filters: { mine: true } },
  { id: "today", label: "Due today", filters: { due: "today" } },
  { id: "overdue", label: "Overdue", filters: { due: "overdue" } },
  { id: "week", label: "This week", filters: { due: "week" } },
  { id: "unassigned", label: "Unassigned", filters: { unassigned: true } },
  { id: "archived", label: "Archived", filters: { archived: true } },
]

export function TasksModule() {
  const [view, setView] = useState("all")
  const [search, setSearch] = useState("")
  const [status, setStatus] = useState<TaskStatus | "all">("all")
  const [priority, setPriority] = useState<TaskPriority | "all">("all")
  const [assignee, setAssignee] = useState<number | "all">("all")
  const [sort, setSort] = useState<TaskSort>("due_date")
  const [direction, setDirection] = useState<"asc" | "desc">("asc")
  const [page, setPage] = useState(1)

  const { data: users } = useWorkspaceUsers()
  const activeView = QUICK_VIEWS.find((entry) => entry.id === view) ?? QUICK_VIEWS[0]!

  const filters: TaskFilters = {
    ...activeView.filters,
    search: search || undefined,
    status: status === "all" ? undefined : [status],
    priority: priority === "all" ? undefined : [priority],
    // A quick view that already pins the assignee wins over the dropdown.
    assignee_id: activeView.filters.mine ? undefined : assignee === "all" ? undefined : assignee,
    sort,
    direction,
    page,
  }

  const { data, isLoading, isError, refetch, isPlaceholderData } = useTasks(filters)
  const tasks = data?.data ?? []
  const meta = data?.meta
  if (meta && page > meta.last_page) setPage(meta.last_page)

  /** Any control that narrows the result set must reset to the first page. */
  const change = <T,>(setter: (value: T) => void) => (value: T) => { setter(value); setPage(1) }

  const toggleSort = (column: TaskSort) => {
    if (sort === column) setDirection((current) => (current === "asc" ? "desc" : "asc"))
    else { setSort(column); setDirection("asc") }
    setPage(1)
  }

  return (
    <div className="mx-auto grid max-w-7xl gap-6">
      <div>
        <p className="text-sm font-medium text-muted-foreground">Work</p>
        <h1 className="mt-2 text-2xl font-semibold tracking-tight">Tasks</h1>
        <p className="mt-2 text-sm text-muted-foreground">Every task across your workspace.</p>
      </div>

      <div className="flex flex-wrap gap-2" role="group" aria-label="Quick views">
        {QUICK_VIEWS.map((entry) => (
          <Button
            key={entry.id}
            size="sm"
            variant={view === entry.id ? "default" : "outline"}
            onPress={() => { setView(entry.id); setPage(1) }}
          >
            {entry.label}
          </Button>
        ))}
      </div>

      <div className="grid gap-3 rounded-2xl border bg-background p-4 sm:grid-cols-2 lg:grid-cols-4">
        <div className="relative sm:col-span-2 lg:col-span-1">
          <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
          <input
            aria-label="Search tasks"
            value={search}
            onChange={(event) => change(setSearch)(event.target.value)}
            placeholder="Search subject or description"
            className="h-10 w-full rounded-lg border bg-background pr-3 pl-9 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
          />
        </div>

        <select
          aria-label="Filter by status"
          value={status}
          onChange={(event) => change(setStatus)(event.target.value as TaskStatus | "all")}
          className="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
        >
          <option value="all">All statuses</option>
          {taskStatusOrder.map((value) => <option key={value} value={value}>{taskStatusLabels[value]}</option>)}
        </select>

        <select
          aria-label="Filter by priority"
          value={priority}
          onChange={(event) => change(setPriority)(event.target.value as TaskPriority | "all")}
          className="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
        >
          <option value="all">All priorities</option>
          {(Object.keys(taskPriorityLabels) as TaskPriority[]).map((value) => <option key={value} value={value}>{taskPriorityLabels[value]}</option>)}
        </select>

        <select
          aria-label="Filter by assignee"
          value={assignee}
          disabled={Boolean(activeView.filters.mine)}
          onChange={(event) => change(setAssignee)(event.target.value === "all" ? "all" : Number(event.target.value))}
          className="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20 disabled:opacity-50"
        >
          <option value="all">Anyone</option>
          {(users ?? []).map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
        </select>
      </div>

      {isError ? (
        <div className="grid place-items-center rounded-2xl border bg-background p-12 text-center">
          <p className="font-medium">Unable to load tasks</p>
          <Button className="mt-4" variant="outline" onPress={() => refetch()}>Retry</Button>
        </div>
      ) : (
        <div className={`overflow-hidden rounded-2xl border bg-background transition-opacity ${isPlaceholderData ? "opacity-60" : ""}`}>
          {isLoading ? (
            <div className="animate-pulse divide-y">
              {[1, 2, 3, 4].map((row) => (
                <div key={row} className="flex items-center justify-between p-5">
                  <div className="grid gap-2"><div className="h-4 w-56 rounded bg-muted" /><div className="h-3 w-32 rounded bg-muted" /></div>
                  <div className="h-7 w-24 rounded bg-muted" />
                </div>
              ))}
            </div>
          ) : tasks.length === 0 ? (
            <div className="grid place-items-center p-12 text-center">
              <div className="grid size-12 place-items-center rounded-full bg-muted"><CheckSquare className="size-5 text-muted-foreground" /></div>
              <p className="mt-4 font-medium">No tasks match these filters</p>
              <p className="mt-1 text-sm text-muted-foreground">Try a different view or clear the search.</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[56rem] text-left text-sm">
                <thead className="border-b bg-muted/30 text-xs text-muted-foreground">
                  <tr>
                    <SortableHeader label="Task" column="subject" sort={sort} direction={direction} onSort={toggleSort} />
                    <SortableHeader label="Status" column="status" sort={sort} direction={direction} onSort={toggleSort} />
                    <SortableHeader label="Priority" column="priority" sort={sort} direction={direction} onSort={toggleSort} />
                    <th className="px-5 py-3 font-medium">Assignees</th>
                    <SortableHeader label="Due" column="due_date" sort={sort} direction={direction} onSort={toggleSort} />
                    <th className="px-5 py-3 font-medium">Related</th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {tasks.map((task) => <TaskRow key={task.id} task={task} />)}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {!isError && !isLoading && meta && meta.last_page > 1 && (
        <div className="flex flex-col gap-3 rounded-2xl border bg-background px-5 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
          <p className="text-muted-foreground">
            Showing <span className="font-medium text-foreground">{(meta.current_page - 1) * meta.per_page + 1}</span>–
            <span className="font-medium text-foreground">{Math.min(meta.current_page * meta.per_page, meta.total)}</span> of{" "}
            <span className="font-medium text-foreground">{meta.total}</span>
          </p>
          <div className="flex items-center justify-end gap-2">
            <Button variant="outline" size="sm" isDisabled={meta.current_page <= 1} onPress={() => setPage((current) => Math.max(1, current - 1))}>Previous</Button>
            <span className="text-xs text-muted-foreground">Page {meta.current_page} of {meta.last_page}</span>
            <Button variant="outline" size="sm" isDisabled={meta.current_page >= meta.last_page} onPress={() => setPage((current) => current + 1)}>Next</Button>
          </div>
        </div>
      )}
    </div>
  )
}

function SortableHeader({ label, column, sort, direction, onSort }: {
  label: string
  column: TaskSort
  sort: TaskSort
  direction: "asc" | "desc"
  onSort: (column: TaskSort) => void
}) {
  const active = sort === column

  return (
    <th className="px-5 py-3 font-medium" aria-sort={active ? (direction === "asc" ? "ascending" : "descending") : "none"}>
      <button type="button" onClick={() => onSort(column)} className="inline-flex items-center gap-1 hover:text-foreground">
        {label}
        {active && (direction === "asc" ? <ArrowUp className="size-3" /> : <ArrowDown className="size-3" />)}
      </button>
    </th>
  )
}

function TaskRow({ task }: { task: TaskListItem }) {
  const assignees = task.assignees ?? []
  const overdue = task.due_date !== null
    && task.status !== "completed"
    && task.status !== "cancelled"
    && task.due_date < new Date().toISOString().slice(0, 10)

  return (
    <tr className="transition-colors hover:bg-muted/20">
      <td className="px-5 py-4">
        <Link href={`/tasks/${task.id}`} className="font-medium hover:underline">{task.subject}</Link>
        {task.is_billable && <span className="ml-2 rounded-full bg-muted px-2 py-0.5 text-[0.7rem] text-muted-foreground">Billable</span>}
      </td>
      <td className="px-5 py-4 text-muted-foreground">{taskStatusLabels[task.status]}</td>
      <td className="px-5 py-4">
        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${taskPriorityStyles[task.priority]}`}>{taskPriorityLabels[task.priority]}</span>
      </td>
      <td className="px-5 py-4 text-muted-foreground">{assignees.length === 0 ? "—" : assignees.map((a) => a.name).join(", ")}</td>
      <td className={`px-5 py-4 ${overdue ? "font-medium text-destructive" : "text-muted-foreground"}`}>{task.due_date ?? "—"}</td>
      <td className="px-5 py-4 text-xs text-muted-foreground">
        {task.related_type === "project" && task.related_id
          ? <Link href={`/projects/${task.related_id}`} className="hover:underline">{task.related_label ?? "Project"}</Link>
          : task.related_label ?? "—"}
      </td>
    </tr>
  )
}
