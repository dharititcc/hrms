import type { TaskAssignee } from "@/types/project"

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
