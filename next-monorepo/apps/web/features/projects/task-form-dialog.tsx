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
import { useProjectTaskMutations } from "@/hooks/use-projects"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import type { ProjectTask, TaskPriority, TaskStatus } from "@/types/project"
import type { Staff } from "@/types/staff"

const emptyValues = (task: ProjectTask | null | undefined, defaultStatus: TaskStatus): TaskFormValues => ({
  title: task?.title ?? "",
  description: task?.description ?? "",
  status: task?.status ?? defaultStatus,
  priority: task?.priority ?? "medium",
  due_date: task?.due_date ?? "",
  staff_id: task?.staff_id ? String(task.staff_id) : "",
})

export function TaskFormDialog({ projectId, members, task, defaultStatus, onClose }: {
  projectId: number
  members: Staff[]
  task?: ProjectTask | null
  defaultStatus: TaskStatus
  onClose: () => void
}) {
  const { toast } = useToast()
  const { create, update } = useProjectTaskMutations(projectId)
  const editing = Boolean(task)

  const { register, control, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm<TaskFormValues>({
    resolver: zodResolver(taskSchema),
    defaultValues: emptyValues(task, defaultStatus),
  })

  useEffect(() => { reset(emptyValues(task, defaultStatus)) }, [reset, task, defaultStatus])

  const onSubmit = async (values: TaskFormValues) => {
    const input = {
      title: values.title,
      description: values.description || null,
      status: values.status,
      priority: values.priority,
      due_date: values.due_date || null,
      staff_id: values.staff_id ? Number(values.staff_id) : null,
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
          <FormField label="Title" placeholder="Draft the sitemap" error={errors.title?.message} {...register("title")} />

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
                <SelectField label="Column" value={field.value} onChange={field.onChange} options={taskStatusOrder.map((value) => ({ id: value, label: taskStatusLabels[value] }))} />
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

          <Controller
            name="staff_id"
            control={control}
            render={({ field }) => (
              <SelectField
                label="Assignee"
                value={field.value ?? ""}
                onChange={field.onChange}
                options={[{ id: "", label: "Unassigned" }, ...members.map((member) => ({ id: String(member.id), label: member.name }))]}
              />
            )}
          />
          {members.length === 0 && <p className="-mt-2 text-xs text-muted-foreground">Add team members to this project to assign tasks.</p>}

          <FormField label="Due date" type="date" error={errors.due_date?.message} {...register("due_date")} />

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
