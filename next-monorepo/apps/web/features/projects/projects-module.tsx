"use client"

import { Edit3, FolderKanban, Plus, Search, Trash2 } from "lucide-react"
import Link from "next/link"
import { useState } from "react"
import { Button } from "@workspace/ui/components/button"
import { ProjectFormDialog } from "@/features/projects/project-form-dialog"
import { projectStatusLabels, projectStatusStyles } from "@/features/projects/labels"
import { usePermissions } from "@/hooks/use-permissions"
import { useProjects, useProjectMutations } from "@/hooks/use-projects"
import { useToast } from "@/providers/toast-provider"
import { getApiErrorMessage } from "@/lib/api-error"
import type { Project, ProjectStatus } from "@/types/project"

export function ProjectsModule() {
  const [search, setSearch] = useState("")
  const [status, setStatus] = useState<ProjectStatus | "all">("all")
  const [page, setPage] = useState(1)
  const [dialogProject, setDialogProject] = useState<Project | null | undefined>(undefined)
  const { toast } = useToast()
  const { can } = usePermissions()
  const { remove } = useProjectMutations()
  const { data, isLoading, isError, refetch } = useProjects({ search: search || undefined, status, page })

  const projects = data?.data ?? []
  const meta = data?.meta
  if (meta && page > meta.last_page) setPage(meta.last_page)

  const confirmDelete = async (project: Project) => {
    if (!window.confirm(`Delete ${project.name}? Its tasks will be removed too. This cannot be undone.`)) return
    try {
      await remove.mutateAsync(project.id)
      toast({ tone: "success", title: "Project deleted" })
    } catch (error) {
      toast({ tone: "error", title: "Unable to delete project", description: getApiErrorMessage(error) })
    }
  }

  return (
    <div className="mx-auto grid max-w-6xl gap-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-sm font-medium text-muted-foreground">Delivery</p>
          <h1 className="mt-2 text-2xl font-semibold tracking-tight">Projects</h1>
          <p className="mt-2 text-sm text-muted-foreground">Plan work, assign your team, and track progress on a board.</p>
        </div>
        {can("projects.create") && <Button onPress={() => setDialogProject(null)}><Plus />New project</Button>}
      </div>

      <div className="flex flex-col gap-3 rounded-2xl border bg-background p-4 sm:flex-row">
        <div className="relative flex-1">
          <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
          <input
            aria-label="Search projects"
            value={search}
            onChange={(event) => { setSearch(event.target.value); setPage(1) }}
            placeholder="Search by project or client"
            className="h-10 w-full rounded-lg border bg-background pr-3 pl-9 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
          />
        </div>
        <select
          aria-label="Filter by status"
          value={status}
          onChange={(event) => { setStatus(event.target.value as ProjectStatus | "all"); setPage(1) }}
          className="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
        >
          <option value="all">All statuses</option>
          {Object.entries(projectStatusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
        </select>
      </div>

      {isError ? (
        <div className="grid place-items-center rounded-2xl border bg-background p-12 text-center">
          <p className="font-medium">Unable to load projects</p>
          <p className="mt-1 text-sm text-muted-foreground">Please try again.</p>
          <Button className="mt-4" variant="outline" onPress={() => refetch()}>Retry</Button>
        </div>
      ) : (
        <div className="overflow-hidden rounded-2xl border bg-background">
          {isLoading ? (
            <div className="animate-pulse divide-y">
              {[1, 2, 3].map((row) => (
                <div key={row} className="flex items-center justify-between p-5">
                  <div className="grid gap-2"><div className="h-4 w-40 rounded bg-muted" /><div className="h-3 w-52 rounded bg-muted" /></div>
                  <div className="h-7 w-20 rounded bg-muted" />
                </div>
              ))}
            </div>
          ) : projects.length === 0 ? (
            <div className="grid place-items-center p-12 text-center">
              <div className="grid size-12 place-items-center rounded-full bg-muted"><FolderKanban className="size-5 text-muted-foreground" /></div>
              <p className="mt-4 font-medium">No projects yet</p>
              <p className="mt-1 text-sm text-muted-foreground">Create your first project to start planning work.</p>
              <Button className="mt-5" onPress={() => setDialogProject(null)}><Plus />New project</Button>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[48rem] text-left text-sm">
                <thead className="border-b bg-muted/30 text-xs text-muted-foreground">
                  <tr>
                    <th className="px-5 py-3 font-medium">Project</th>
                    <th className="px-5 py-3 font-medium">Status</th>
                    <th className="px-5 py-3 font-medium">Progress</th>
                    <th className="px-5 py-3 font-medium">Team</th>
                    <th className="px-5 py-3 font-medium">Timeline</th>
                    <th className="px-5 py-3"><span className="sr-only">Actions</span></th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {projects.map((project) => (
                    <ProjectRow
                      key={project.id}
                      project={project}
                      onEdit={can("projects.edit") ? () => setDialogProject(project) : undefined}
                      onDelete={can("projects.delete") ? () => confirmDelete(project) : undefined}
                    />
                  ))}
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

      {dialogProject !== undefined && <ProjectFormDialog project={dialogProject} onClose={() => setDialogProject(undefined)} />}
    </div>
  )
}

function ProjectRow({ project, onEdit, onDelete }: { project: Project; onEdit?: () => void; onDelete?: () => void }) {
  const total = project.tasks_total ?? 0
  const done = project.tasks_done ?? 0
  const percent = total === 0 ? 0 : Math.round((done / total) * 100)
  const members = project.members ?? []

  return (
    <tr className="transition-colors hover:bg-muted/20">
      <td className="px-5 py-4">
        <Link href={`/dashboard/projects/${project.id}`} className="font-medium hover:underline">{project.name}</Link>
        <p className="mt-0.5 text-xs text-muted-foreground">{project.client || "No client"}</p>
      </td>
      <td className="px-5 py-4">
        <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${projectStatusStyles[project.status]}`}>{projectStatusLabels[project.status]}</span>
      </td>
      <td className="px-5 py-4">
        <div className="flex items-center gap-2">
          <div className="h-1.5 w-24 overflow-hidden rounded-full bg-muted">
            <div className="h-full rounded-full bg-primary transition-all" style={{ width: `${percent}%` }} />
          </div>
          <span className="text-xs text-muted-foreground">{done}/{total}</span>
        </div>
      </td>
      <td className="px-5 py-4 text-muted-foreground">{members.length === 0 ? "—" : `${members.length} member${members.length === 1 ? "" : "s"}`}</td>
      <td className="px-5 py-4 text-xs text-muted-foreground">{project.start_date || "—"} → {project.end_date || "—"}</td>
      <td className="px-5 py-4">
        <div className="flex justify-end gap-1">
          {onEdit && <Button variant="ghost" size="icon-sm" aria-label={`Edit ${project.name}`} onPress={onEdit}><Edit3 /></Button>}
          {onDelete && <Button variant="ghost" size="icon-sm" aria-label={`Delete ${project.name}`} onPress={onDelete}><Trash2 /></Button>}
        </div>
      </td>
    </tr>
  )
}
