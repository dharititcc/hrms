import { TaskDetail } from "@/features/tasks/task-detail"
import type { Metadata } from "next"

export const metadata: Metadata = { title: "Task" }

export default async function TaskDetailPage({ params }: { params: Promise<{ task: string }> }) {
  const { task } = await params

  return <TaskDetail taskId={Number(task)} />
}
