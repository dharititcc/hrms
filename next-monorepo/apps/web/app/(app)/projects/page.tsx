import { ProjectsModule } from "@/features/projects/projects-module"
import type { Metadata } from "next"

export const metadata: Metadata = { title: "Projects" }

export default function ProjectsPage() { return <ProjectsModule /> }
