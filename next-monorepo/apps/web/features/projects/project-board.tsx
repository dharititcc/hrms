"use client"

import { CalendarDays, ChevronLeft, ChevronRight, Edit3, Plus, Trash2, User } from "lucide-react"
import { useState } from "react"
import { Button } from "@workspace/ui/components/button"
import { TaskFormDialog } from "@/features/projects/task-form-dialog"
import { taskPriorityLabels, taskPriorityStyles, taskStatusLabels, taskStatusOrder } from "@/features/projects/labels"
import { useProjectTasks, useProjectTaskMutations } from "@/hooks/use-projects"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import type { Project, ProjectTask, TaskStatus } from "@/types/project"

export function ProjectBoard({ project }: { project: Project }) {
  const { toast } = useToast()
  const { data: tasks, isLoading, isError, refetch } = useProjectTasks(project.id)
  const { move, remove } = useProjectTaskMutations(project.id)
  const [dragOver, setDragOver] = useState<TaskStatus | null>(null)
  const [dialogTask, setDialogTask] = useState<ProjectTask | null | undefined>(undefined)
  const [dialogStatus, setDialogStatus] = useState<TaskStatus>("todo")

  const moveTask = async (task: ProjectTask, status: TaskStatus) => {
    if (task.status === status) return
    try {
      await move.mutateAsync({ id: task.id, status })
    } catch (error) {
      toast({ tone: "error", title: "Unable to move task", description: getApiErrorMessage(error) })
    }
  }

  const confirmDelete = async (task: ProjectTask) => {
    if (!window.confirm(`Delete “${task.title}”? This cannot be undone.`)) return
    try {
      await remove.mutateAsync(task.id)
      toast({ tone: "success", title: "Task deleted" })
    } catch (error) {
      toast({ tone: "error", title: "Unable to delete task", description: getApiErrorMessage(error) })
    }
  }

  const openCreate = (status: TaskStatus) => { setDialogStatus(status); setDialogTask(null) }

  if (isError) {
    return (
      <div className="grid place-items-center rounded-2xl border bg-background p-12 text-center">
        <p className="font-medium">Unable to load tasks</p>
        <Button className="mt-4" variant="outline" onPress={() => refetch()}>Retry</Button>
      </div>
    )
  }

  return (
    <>
      <div className="grid gap-4 lg:grid-cols-3">
        {taskStatusOrder.map((status) => {
          const columnTasks = (tasks ?? []).filter((task) => task.status === status)
          return (
            <section
              key={status}
              aria-label={taskStatusLabels[status]}
              onDragOver={(event) => { event.preventDefault(); setDragOver(status) }}
              onDragLeave={() => setDragOver((current) => (current === status ? null : current))}
              onDrop={(event) => {
                event.preventDefault()
                setDragOver(null)
                const id = Number(event.dataTransfer.getData("text/plain"))
                const task = (tasks ?? []).find((item) => item.id === id)
                if (task) void moveTask(task, status)
              }}
              className={`grid content-start gap-3 rounded-2xl border p-3 transition-colors ${dragOver === status ? "border-ring bg-muted/40" : "bg-muted/20"}`}
            >
              <header className="flex items-center justify-between px-1">
                <div className="flex items-center gap-2">
                  <h3 className="text-sm font-semibold">{taskStatusLabels[status]}</h3>
                  <span className="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">{columnTasks.length}</span>
                </div>
                <Button variant="ghost" size="icon-sm" aria-label={`Add task to ${taskStatusLabels[status]}`} onPress={() => openCreate(status)}><Plus /></Button>
              </header>

              {isLoading ? (
                <div className="grid gap-2">{[1, 2].map((row) => <div key={row} className="h-24 animate-pulse rounded-xl bg-muted" />)}</div>
              ) : columnTasks.length === 0 ? (
                <p className="rounded-xl border border-dashed p-6 text-center text-xs text-muted-foreground">Drop tasks here</p>
              ) : (
                columnTasks.map((task) => (
                  <TaskCard
                    key={task.id}
                    task={task}
                    onMove={(target) => void moveTask(task, target)}
                    onEdit={() => { setDialogStatus(task.status); setDialogTask(task) }}
                    onDelete={() => void confirmDelete(task)}
                  />
                ))
              )}
            </section>
          )
        })}
      </div>

      {dialogTask !== undefined && (
        <TaskFormDialog
          projectId={project.id}
          members={project.members ?? []}
          task={dialogTask}
          defaultStatus={dialogStatus}
          onClose={() => setDialogTask(undefined)}
        />
      )}
    </>
  )
}

function TaskCard({ task, onMove, onEdit, onDelete }: { task: ProjectTask; onMove: (status: TaskStatus) => void; onEdit: () => void; onDelete: () => void }) {
  const index = taskStatusOrder.indexOf(task.status)
  const previous = taskStatusOrder[index - 1]
  const next = taskStatusOrder[index + 1]

  return (
    <article
      draggable
      onDragStart={(event) => event.dataTransfer.setData("text/plain", String(task.id))}
      className="grid cursor-grab gap-2 rounded-xl border bg-background p-3 shadow-sm transition-shadow hover:shadow-md active:cursor-grabbing"
    >
      <div className="flex items-start justify-between gap-2">
        <p className="text-sm font-medium">{task.title}</p>
        <span className={`shrink-0 rounded-full px-2 py-0.5 text-[0.7rem] font-medium ${taskPriorityStyles[task.priority]}`}>{taskPriorityLabels[task.priority]}</span>
      </div>

      {task.description && <p className="line-clamp-2 text-xs text-muted-foreground">{task.description}</p>}

      <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
        <span className="inline-flex items-center gap-1"><User className="size-3" />{task.assignee_name || "Unassigned"}</span>
        {task.due_date && <span className="inline-flex items-center gap-1"><CalendarDays className="size-3" />{task.due_date}</span>}
      </div>

      <div className="flex items-center justify-between border-t pt-2">
        {/* Keyboard-accessible equivalent of dragging between columns. */}
        <div className="flex gap-1">
          <Button variant="ghost" size="icon-xs" aria-label={previous ? `Move “${task.title}” to ${taskStatusLabels[previous]}` : "Already in the first column"} isDisabled={!previous} onPress={() => previous && onMove(previous)}><ChevronLeft /></Button>
          <Button variant="ghost" size="icon-xs" aria-label={next ? `Move “${task.title}” to ${taskStatusLabels[next]}` : "Already in the last column"} isDisabled={!next} onPress={() => next && onMove(next)}><ChevronRight /></Button>
        </div>
        <div className="flex gap-1">
          <Button variant="ghost" size="icon-xs" aria-label={`Edit ${task.title}`} onPress={onEdit}><Edit3 /></Button>
          <Button variant="ghost" size="icon-xs" aria-label={`Delete ${task.title}`} onPress={onDelete}><Trash2 /></Button>
        </div>
      </div>
    </article>
  )
}
