"use client"

import { X } from "lucide-react"
import { useEffect } from "react"
import { Controller, useForm } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { Button } from "@workspace/ui/components/button"
import { FormField } from "@/components/ui/form-fields"
import { SelectField } from "@/components/ui/select-field"
import { taskPriorityLabels, taskStatusLabels, taskStatusOrder } from "@/features/projects/labels"
import { taskSchema, type TaskFormValues } from "@/features/projects/schemas"
import { useProjectTaskMutations, useWorkspaceUsers } from "@/hooks/use-projects"
import { useTaskMutations } from "@/hooks/use-task-detail"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import type { ProjectTask, TaskPriority, TaskStatus } from "@/types/project"

const emptyValues = (task: ProjectTask | null | undefined, defaultStatus: TaskStatus): TaskFormValues => ({
  subject: task?.subject ?? "",
  description: task?.description ?? "",
  status: task?.status ?? defaultStatus,
  priority: task?.priority ?? "medium",
  is_billable: task?.is_billable ?? false,
  estimated_hours: task?.estimated_hours ?? "",
  start_date: task?.start_date ?? "",
  due_date: task?.due_date ?? "",
  assignee_ids: task?.assignees?.map((assignee) => assignee.id) ?? [],
})

export function TaskFormDialog({ projectId, task, defaultStatus, onClose }: {
  /** Omitted for a task that belongs to no project. */
  projectId?: number | null
  task?: ProjectTask | null
  defaultStatus: TaskStatus
  onClose: () => void
}) {
  const { toast } = useToast()
  // Both are built either way: a hook cannot be called conditionally, and
  // neither fetches anything until its mutation runs.
  const projectMutations = useProjectTaskMutations(projectId ?? 0)
  const standalone = useTaskMutations()
  const create = projectId ? projectMutations.create : standalone.create
  const update = projectMutations.update
  const { data: users } = useWorkspaceUsers()
  const editing = Boolean(task)
  const assignable = users ?? []

  const { register, control, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm<TaskFormValues>({
    resolver: zodResolver(taskSchema),
    defaultValues: emptyValues(task, defaultStatus),
  })

  useEffect(() => { reset(emptyValues(task, defaultStatus)) }, [reset, task, defaultStatus])

  const onSubmit = async (values: TaskFormValues) => {
    const input = {
      subject: values.subject,
      description: values.description || null,
      status: values.status,
      priority: values.priority,
      is_billable: values.is_billable ?? false,
      estimated_hours: values.estimated_hours ? Number(values.estimated_hours) : null,
      start_date: values.start_date || null,
      due_date: values.due_date || null,
      assignee_ids: values.assignee_ids ?? [],
    }
    try {
      if (task) await update.mutateAsync({ id: task.id, input })
      else await create.mutateAsync(input)
      toast({ tone: "success", title: editing ? "Task updated" : "Task added" })
      onClose()
    } catch (error) {
      toast({ tone: "error", title: editing ? "Unable to update task" : "Unable to add task", description: getApiErrorMessage(error) })
    }
  }

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose() }}>
      <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border bg-background p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="task-dialog-title">
        <div className="flex items-start justify-between">
          <div>
            <h2 id="task-dialog-title" className="text-lg font-semibold">{editing ? "Edit task" : "New task"}</h2>
            <p className="mt-1 text-sm text-muted-foreground">{editing ? "Update this task’s details." : "Add a task to this project’s board."}</p>
          </div>
          <Button variant="ghost" size="icon-sm" aria-label="Close dialog" onPress={onClose}><X /></Button>
        </div>

        <form className="mt-6 grid gap-4" onSubmit={handleSubmit(onSubmit)} noValidate>
          <FormField label="Subject" placeholder="Draft the sitemap" error={errors.subject?.message} {...register("subject")} />

          <div className="grid gap-2">
            <label htmlFor="task-description" className="text-sm font-medium">Description</label>
            <textarea
              id="task-description"
              rows={3}
              placeholder="Optional details"
              className="w-full rounded-lg border bg-background p-3 text-sm outline-none transition placeholder:text-muted-foreground focus:border-ring focus:ring-3 focus:ring-ring/20"
              {...register("description")}
            />
            {errors.description && <p className="text-xs text-destructive">{errors.description.message}</p>}
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <Controller
              name="status"
              control={control}
              render={({ field }) => (
                <SelectField label="Status" value={field.value} onChange={field.onChange} options={taskStatusOrder.map((value) => ({ id: value, label: taskStatusLabels[value] }))} />
              )}
            />
            <Controller
              name="priority"
              control={control}
              render={({ field }) => (
                <SelectField
                  label="Priority"
                  value={field.value}
                  onChange={field.onChange}
                  options={(Object.keys(taskPriorityLabels) as TaskPriority[]).map((value) => ({ id: value, label: taskPriorityLabels[value] }))}
                />
              )}
            />
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <FormField label="Start date" type="date" error={errors.start_date?.message} {...register("start_date")} />
            <FormField label="Due date" type="date" error={errors.due_date?.message} {...register("due_date")} />
          </div>

          <FormField label="Estimated hours" type="number" step="0.25" min="0" placeholder="Optional" error={errors.estimated_hours?.message} {...register("estimated_hours")} />

          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" className="size-4 rounded border" {...register("is_billable")} />
            Billable
          </label>

          <Controller
            name="assignee_ids"
            control={control}
            render={({ field }) => (
              <fieldset className="grid gap-2">
                <legend className="text-sm font-medium">Assignees</legend>
                {assignable.length === 0 ? (
                  <p className="text-xs text-muted-foreground">No assignable users yet. Invite a employee member to give them an account.</p>
                ) : (
                  <div className="grid max-h-40 gap-1 overflow-y-auto rounded-lg border p-2">
                    {assignable.map((user) => {
                      const selected = field.value?.includes(user.id) ?? false
                      return (
                        <label key={user.id} className="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-muted">
                          <input
                            type="checkbox"
                            checked={selected}
                            onChange={(event) => {
                              const current = field.value ?? []
                              field.onChange(event.target.checked ? [...current, user.id] : current.filter((id) => id !== user.id))
                            }}
                            className="size-4 rounded border"
                          />
                          <span>{user.name}</span>
                          <span className="ml-auto text-xs text-muted-foreground">{user.email}</span>
                        </label>
                      )
                    })}
                  </div>
                )}
              </fieldset>
            )}
          />

          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="outline" onPress={onClose}>Cancel</Button>
            <Button type="submit" isDisabled={isSubmitting || create.isPending || update.isPending}>
              {isSubmitting || create.isPending || update.isPending ? "Saving…" : editing ? "Save changes" : "Add task"}
            </Button>
          </div>
        </form>
      </div>
    </div>
  )
}
