import type { Staff } from "@/types/staff"

export type ProjectStatus = "planning" | "active" | "on_hold" | "completed" | "cancelled"
export type TaskStatus = "todo" | "in_progress" | "done"
export type TaskPriority = "low" | "medium" | "high" | "urgent"

export type Project = {
  id: number
  name: string
  client: string | null
  description: string | null
  status: ProjectStatus
  start_date: string | null
  end_date: string | null
  /** Laravel casts decimals to strings, e.g. "15000.00". */
  budget: string | null
  members?: Staff[]
  tasks_total?: number
  tasks_done?: number
  created_at: string
  updated_at: string
}

export type ProjectTask = {
  id: number
  project_id: number
  staff_id: number | null
  assignee_name?: string | null
  title: string
  description: string | null
  status: TaskStatus
  priority: TaskPriority
  due_date: string | null
  position: number
  created_at: string
  updated_at: string
}

export type ProjectInput = {
  name: string
  client?: string | null
  description?: string | null
  status: ProjectStatus
  start_date?: string | null
  end_date?: string | null
  budget?: number | null
  member_ids?: number[]
}

export type ProjectTaskInput = {
  title: string
  description?: string | null
  status: TaskStatus
  priority: TaskPriority
  due_date?: string | null
  staff_id?: number | null
}

export type ProjectListResponse = {
  data: Project[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}
