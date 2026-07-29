import { apiClient } from "@/lib/api-client"
import type { ProjectTask, ProjectTaskInput } from "@/types/project"
import type { ManualTimeInput, TaskChecklistItem, TaskComment, TaskFilters, TaskListResponse, TaskTimeEntry, TaskTimeEntryList } from "@/types/task"

export const taskService = {
  /** A task with no project behind it, added from the task list. */
  async create(input: ProjectTaskInput) {
    const { data } = await apiClient.post<{ data: ProjectTask }>("/auth/tasks", input)
    return data.data
  },
  async list(filters: TaskFilters = {}) {
    const { data } = await apiClient.get<TaskListResponse>("/auth/tasks", {
      params: {
        ...filters,
        // Laravel reads repeated keys as arrays; axios serialises them as
        // status[]=a&status[]=b which the request class accepts.
        status: filters.status?.length ? filters.status : undefined,
        priority: filters.priority?.length ? filters.priority : undefined,
        unassigned: filters.unassigned ? 1 : undefined,
        mine: filters.mine ? 1 : undefined,
        archived: filters.archived ? 1 : undefined,
      },
    })
    return data
  },
  async get(taskId: number) {
    const { data } = await apiClient.get<{ data: ProjectTask & { related_label?: string | null } }>(`/auth/tasks/${taskId}`)
    return data.data
  },

  // Comments
  async comments(taskId: number) {
    const { data } = await apiClient.get<{ data: TaskComment[] }>(`/auth/tasks/${taskId}/comments`)
    return data.data
  },
  async addComment(taskId: number, body: string, parentId?: number | null) {
    const { data } = await apiClient.post<{ data: TaskComment }>(`/auth/tasks/${taskId}/comments`, { body, parent_id: parentId ?? null })
    return data.data
  },
  async editComment(commentId: number, body: string) {
    const { data } = await apiClient.put<{ data: TaskComment }>(`/auth/comments/${commentId}`, { body })
    return data.data
  },
  async removeComment(commentId: number) {
    await apiClient.delete(`/auth/comments/${commentId}`)
  },

  // Checklist
  async checklist(taskId: number) {
    const { data } = await apiClient.get<{ data: TaskChecklistItem[] }>(`/auth/tasks/${taskId}/checklist`)
    return data.data
  },
  async addChecklistItem(taskId: number, title: string) {
    const { data } = await apiClient.post<{ data: TaskChecklistItem }>(`/auth/tasks/${taskId}/checklist`, { title })
    return data.data
  },
  async updateChecklistItem(itemId: number, input: { title?: string; is_completed?: boolean }) {
    const { data } = await apiClient.patch<{ data: TaskChecklistItem }>(`/auth/checklist-items/${itemId}`, input)
    return data.data
  },
  async removeChecklistItem(itemId: number) {
    await apiClient.delete(`/auth/checklist-items/${itemId}`)
  },

  // Time tracking
  async timeEntries(taskId: number) {
    const { data } = await apiClient.get<TaskTimeEntryList>(`/auth/tasks/${taskId}/time-entries`)
    return data
  },
  async runningTimer() {
    const { data } = await apiClient.get<{ data: TaskTimeEntry | null }>("/auth/time-entries/running")
    return data.data
  },
  async startTimer(taskId: number) {
    const { data } = await apiClient.post<{ data: TaskTimeEntry }>(`/auth/tasks/${taskId}/timer/start`)
    return data.data
  },
  async stopTimer(taskId: number) {
    const { data } = await apiClient.post<{ data: TaskTimeEntry }>(`/auth/tasks/${taskId}/timer/stop`)
    return data.data
  },
  async logTime(taskId: number, input: ManualTimeInput) {
    const { data } = await apiClient.post<{ data: TaskTimeEntry }>(`/auth/tasks/${taskId}/time-entries`, input)
    return data.data
  },
  async removeTimeEntry(entryId: number) {
    await apiClient.delete(`/auth/time-entries/${entryId}`)
  },
}
