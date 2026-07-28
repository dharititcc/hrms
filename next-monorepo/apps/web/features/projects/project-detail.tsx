"use client"

import { ArrowLeft, Edit3 } from "lucide-react"
import Link from "next/link"
import { useState } from "react"
import { Button } from "@workspace/ui/components/button"
import { ProjectBoard } from "@/features/projects/project-board"
import { ProjectFormDialog } from "@/features/projects/project-form-dialog"
import { projectStatusLabels, projectStatusStyles } from "@/features/projects/labels"
import { useProject } from "@/hooks/use-projects"

export function ProjectDetail({ projectId }: { projectId: number }) {
  const { data: project, isLoading, isError, refetch } = useProject(projectId)
  const [editing, setEditing] = useState(false)

  if (isLoading) {
    return (
      <div className="mx-auto grid max-w-6xl gap-6">
        <div className="h-8 w-56 animate-pulse rounded bg-muted" />
        <div className="grid gap-4 lg:grid-cols-3">{[1, 2, 3].map((column) => <div key={column} className="h-64 animate-pulse rounded-2xl bg-muted" />)}</div>
      </div>
    )
  }

  if (isError || !project) {
    return (
      <div className="mx-auto grid max-w-6xl place-items-center rounded-2xl border bg-background p-12 text-center">
        <p className="font-medium">Unable to load this project</p>
        <p className="mt-1 text-sm text-muted-foreground">It may have been deleted, or you may not have access.</p>
        <div className="mt-4 flex gap-2">
          <Button variant="outline" onPress={() => refetch()}>Retry</Button>
          <Link href="/dashboard/projects"><Button variant="ghost">Back to projects</Button></Link>
        </div>
      </div>
    )
  }

  const total = project.tasks_total ?? 0
  const done = project.tasks_done ?? 0
  const percent = total === 0 ? 0 : Math.round((done / total) * 100)
  const members = project.members ?? []

  return (
    <div className="mx-auto grid max-w-6xl gap-6">
      <div>
        <Link href="/dashboard/projects" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
          <ArrowLeft className="size-4" />Projects
        </Link>
        <div className="mt-3 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <div className="flex flex-wrap items-center gap-3">
              <h1 className="text-2xl font-semibold tracking-tight">{project.name}</h1>
              <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${projectStatusStyles[project.status]}`}>{projectStatusLabels[project.status]}</span>
            </div>
            <p className="mt-2 text-sm text-muted-foreground">{project.client || "No client"}</p>
          </div>
          <Button variant="outline" onPress={() => setEditing(true)}><Edit3 />Edit project</Button>
        </div>
      </div>

      {project.description && <p className="max-w-3xl text-sm text-muted-foreground">{project.description}</p>}

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <SummaryCard label="Progress">
          <div className="flex items-center gap-2">
            <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
              <div className="h-full rounded-full bg-primary transition-all" style={{ width: `${percent}%` }} />
            </div>
            <span className="text-sm font-medium">{percent}%</span>
          </div>
          <p className="mt-1 text-xs text-muted-foreground">{done} of {total} tasks done</p>
        </SummaryCard>
        <SummaryCard label="Timeline">
          <p className="text-sm font-medium">{project.start_date || "—"}</p>
          <p className="mt-1 text-xs text-muted-foreground">to {project.end_date || "—"}</p>
        </SummaryCard>
        <SummaryCard label="Budget">
          <p className="text-sm font-medium">{project.budget ? Number(project.budget).toLocaleString(undefined, { minimumFractionDigits: 2 }) : "—"}</p>
        </SummaryCard>
        <SummaryCard label="Team">
          {members.length === 0 ? (
            <p className="text-xs text-muted-foreground">No members assigned</p>
          ) : (
            <div className="flex flex-wrap gap-1">
              {members.map((member) => (
                <span key={member.id} className="rounded-full bg-muted px-2 py-0.5 text-xs" title={member.email}>{member.name}</span>
              ))}
            </div>
          )}
        </SummaryCard>
      </div>

      <ProjectBoard project={project} />

      {editing && <ProjectFormDialog project={project} onClose={() => setEditing(false)} />}
    </div>
  )
}

function SummaryCard({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="rounded-2xl border bg-background p-4">
      <p className="text-xs font-medium text-muted-foreground">{label}</p>
      <div className="mt-2">{children}</div>
    </div>
  )
}
