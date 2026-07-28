"use client"

import { X } from "lucide-react"
import { useEffect } from "react"
import { Controller, useForm } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { Button } from "@workspace/ui/components/button"
import { FormField } from "@/components/ui/form-fields"
import { SelectField } from "@/components/ui/select-field"
import { projectStatusLabels } from "@/features/projects/labels"
import { projectSchema, type ProjectFormValues } from "@/features/projects/schemas"
import { useProjectMutations } from "@/hooks/use-projects"
import { useStaff } from "@/hooks/use-staff"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import type { Project, ProjectStatus } from "@/types/project"

const emptyValues = (project?: Project | null): ProjectFormValues => ({
  name: project?.name ?? "",
  client: project?.client ?? "",
  description: project?.description ?? "",
  status: project?.status ?? "planning",
  start_date: project?.start_date ?? "",
  end_date: project?.end_date ?? "",
  budget: project?.budget ?? "",
  member_ids: project?.members?.map((member) => member.id) ?? [],
})

export function ProjectFormDialog({ project, onClose }: { project?: Project | null; onClose: () => void }) {
  const { toast } = useToast()
  const { create, update } = useProjectMutations()
  const editing = Boolean(project)
  // Active staff only — up to 100, which covers the member picker without paging.
  const { data: staffData } = useStaff({ status: "active", per_page: 100 })
  const staffOptions = staffData?.data ?? []

  const { register, control, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm<ProjectFormValues>({
    resolver: zodResolver(projectSchema),
    defaultValues: emptyValues(project),
  })

  useEffect(() => { reset(emptyValues(project)) }, [reset, project])

  const onSubmit = async (values: ProjectFormValues) => {
    const input = {
      name: values.name,
      client: values.client || null,
      description: values.description || null,
      status: values.status,
      start_date: values.start_date || null,
      end_date: values.end_date || null,
      budget: values.budget ? Number(values.budget) : null,
      member_ids: values.member_ids ?? [],
    }
    try {
      if (project) await update.mutateAsync({ id: project.id, input })
      else await create.mutateAsync(input)
      toast({ tone: "success", title: editing ? "Project updated" : "Project created" })
      onClose()
    } catch (error) {
      toast({ tone: "error", title: editing ? "Unable to update project" : "Unable to create project", description: getApiErrorMessage(error) })
    }
  }

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose() }}>
      <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border bg-background p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="project-dialog-title">
        <div className="flex items-start justify-between">
          <div>
            <h2 id="project-dialog-title" className="text-lg font-semibold">{editing ? "Edit project" : "New project"}</h2>
            <p className="mt-1 text-sm text-muted-foreground">{editing ? "Update this project’s details and team." : "Set up a project and assign your team."}</p>
          </div>
          <Button variant="ghost" size="icon-sm" aria-label="Close dialog" onPress={onClose}><X /></Button>
        </div>

        <form className="mt-6 grid gap-4" onSubmit={handleSubmit(onSubmit)} noValidate>
          <FormField label="Project name" placeholder="Website relaunch" error={errors.name?.message} {...register("name")} />
          <FormField label="Client" placeholder="Optional" error={errors.client?.message} {...register("client")} />

          <div className="grid gap-2">
            <label htmlFor="project-description" className="text-sm font-medium">Description</label>
            <textarea
              id="project-description"
              rows={3}
              placeholder="What is this project about?"
              className="w-full rounded-lg border bg-background p-3 text-sm outline-none transition placeholder:text-muted-foreground focus:border-ring focus:ring-3 focus:ring-ring/20"
              {...register("description")}
            />
            {errors.description && <p className="text-xs text-destructive">{errors.description.message}</p>}
          </div>

          <Controller
            name="status"
            control={control}
            render={({ field }) => (
              <SelectField
                label="Status"
                value={field.value}
                onChange={field.onChange}
                options={(Object.keys(projectStatusLabels) as ProjectStatus[]).map((value) => ({ id: value, label: projectStatusLabels[value] }))}
              />
            )}
          />

          <div className="grid gap-4 sm:grid-cols-2">
            <FormField label="Start date" type="date" error={errors.start_date?.message} {...register("start_date")} />
            <FormField label="End date" type="date" error={errors.end_date?.message} {...register("end_date")} />
          </div>

          <FormField label="Budget" type="number" step="0.01" min="0" placeholder="Optional" error={errors.budget?.message} {...register("budget")} />

          <Controller
            name="member_ids"
            control={control}
            render={({ field }) => (
              <fieldset className="grid gap-2">
                <legend className="text-sm font-medium">Team members</legend>
                {staffOptions.length === 0 ? (
                  <p className="text-xs text-muted-foreground">No active staff yet. Add staff members first to assign a team.</p>
                ) : (
                  <div className="grid max-h-40 gap-1 overflow-y-auto rounded-lg border p-2">
                    {staffOptions.map((member) => {
                      const selected = field.value?.includes(member.id) ?? false
                      return (
                        <label key={member.id} className="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-muted">
                          <input
                            type="checkbox"
                            checked={selected}
                            onChange={(event) => {
                              const current = field.value ?? []
                              field.onChange(event.target.checked ? [...current, member.id] : current.filter((id) => id !== member.id))
                            }}
                            className="size-4 rounded border"
                          />
                          <span>{member.name}</span>
                          <span className="ml-auto text-xs text-muted-foreground">{member.email}</span>
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
              {isSubmitting || create.isPending || update.isPending ? "Saving…" : editing ? "Save changes" : "Create project"}
            </Button>
          </div>
        </form>
      </div>
    </div>
  )
}
