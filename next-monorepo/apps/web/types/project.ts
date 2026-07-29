import type { Employee } from "@/types/employee"

export type ProjectStatus = "planning" | "active" | "on_hold" | "completed" | "cancelled"
export type TaskStatus = "pending" | "in_progress" | "review" | "completed" | "cancelled" | "on_hold"
export type TaskPriority = "low" | "medium" | "high" | "urgent"
export type RepeatFrequency = "daily" | "weekly" | "monthly" | "yearly"

export type WorkspaceUser = { id: number; name: string; email: string }
export type TaskAssignee = { id: number; name: string }
export type TaskTag = { id: number; name: string; color: string }

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
  members?: Employee[]
  tasks_total?: number
  tasks_done?: number
  created_at: string
  updated_at: string
}

export type ProjectTask = {
  id: number
  subject: string
  description: string | null
  status: TaskStatus
  priority: TaskPriority
  is_public: boolean
  is_billable: boolean
  hourly_rate: string | null
  estimated_hours: string | null
  start_date: string | null
  due_date: string | null
  completed_at: string | null
  archived_at: string | null
  parent_task_id: number | null
  /** Morph alias of the record this task hangs off, e.g. "project". */
  related_type: string | null
  related_id: number | null
  repeat_frequency: RepeatFrequency | null
  repeat_interval: number
  repeat_until: string | null
  position: number
  assignees?: TaskAssignee[]
  tags?: TaskTag[]
  created_by: number | null
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
  subject: string
  description?: string | null
  status: TaskStatus
  priority: TaskPriority
  is_billable?: boolean
  estimated_hours?: number | null
  start_date?: string | null
  due_date?: string | null
  assignee_ids?: number[]
}

export type ProjectListResponse = {
  data: Project[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}
