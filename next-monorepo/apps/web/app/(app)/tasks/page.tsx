import { TasksModule } from "@/features/tasks/tasks-module"
import type { Metadata } from "next"

export const metadata: Metadata = { title: "Tasks" }

export default function TasksPage() { return <TasksModule /> }
