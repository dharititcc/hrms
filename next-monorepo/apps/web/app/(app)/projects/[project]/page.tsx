import { ProjectDetail } from "@/features/projects/project-detail"
import type { Metadata } from "next"

export const metadata: Metadata = { title: "Project board" }

export default async function ProjectDetailPage({ params }: { params: Promise<{ project: string }> }) {
  const { project } = await params

  return <ProjectDetail projectId={Number(project)} />
}
