import type { ProjectTask, TaskAssignee, TaskPriority, TaskStatus } from "@/types/project"

export type TaskSort = "subject" | "due_date" | "start_date" | "created_at" | "updated_at" | "priority" | "status"
export type DueFilter = "overdue" | "today" | "week" | "none"

export type TaskListItem = ProjectTask & { related_label?: string | null }

export type TaskListResponse = {
  data: TaskListItem[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export type TaskFilters = {
  search?: string
  status?: TaskStatus[]
  priority?: TaskPriority[]
  assignee_id?: number
  unassigned?: boolean
  mine?: boolean
  due?: DueFilter
  archived?: boolean
  sort?: TaskSort
  direction?: "asc" | "desc"
  page?: number
  per_page?: number
}

export type TaskComment = {
  id: number
  task_id: number
  parent_id: number | null
  body: string
  author: { id: number; name: string } | null
  mentions?: TaskAssignee[]
  replies?: TaskComment[]
  can_edit: boolean
  can_delete: boolean
  created_at: string
  updated_at: string
}

export type TaskChecklistItem = {
  id: number
  task_id: number
  title: string
  is_completed: boolean
  completed_at: string | null
  completed_by: number | null
  completed_by_name?: string | null
  position: number
}

export type TaskTimeEntry = {
  id: number
  task_id: number
  user_id: number
  user_name?: string | null
  started_at: string
  ended_at: string | null
  duration_minutes: number
  is_running: boolean
  description: string | null
  is_manual: boolean
}

export type TaskTimeEntryList = {
  data: TaskTimeEntry[]
  meta: { total_minutes: number }
}

export type ManualTimeInput = {
  started_at: string
  ended_at: string
  description?: string | null
}
