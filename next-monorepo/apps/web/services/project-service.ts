import { apiClient } from "@/lib/api-client"
import type { Project, ProjectInput, ProjectListResponse, ProjectStatus, ProjectTask, ProjectTaskInput, TaskStatus } from "@/types/project"

export type ProjectFilters = { search?: string; status?: ProjectStatus | "all"; page?: number }

export const projectService = {
  async list(filters: ProjectFilters = {}) {
    const { data } = await apiClient.get<ProjectListResponse>("/auth/projects", {
      params: { ...filters, status: filters.status === "all" ? undefined : filters.status },
    })
    return data
  },
  async get(id: number) {
    const { data } = await apiClient.get<{ data: Project }>(`/auth/projects/${id}`)
    return data.data
  },
  async create(input: ProjectInput) {
    const { data } = await apiClient.post<{ data: Project }>("/auth/projects", input)
    return data.data
  },
  async update(id: number, input: ProjectInput) {
    const { data } = await apiClient.put<{ data: Project }>(`/auth/projects/${id}`, input)
    return data.data
  },
  async remove(id: number) {
    await apiClient.delete(`/auth/projects/${id}`)
  },
  async tasks(projectId: number) {
    const { data } = await apiClient.get<{ data: ProjectTask[] }>(`/auth/projects/${projectId}/tasks`)
    return data.data
  },
  async createTask(projectId: number, input: ProjectTaskInput) {
    const { data } = await apiClient.post<{ data: ProjectTask }>(`/auth/projects/${projectId}/tasks`, input)
    return data.data
  },
  async updateTask(taskId: number, input: ProjectTaskInput) {
    const { data } = await apiClient.put<{ data: ProjectTask }>(`/auth/tasks/${taskId}`, input)
    return data.data
  },
  async moveTask(taskId: number, status: TaskStatus) {
    const { data } = await apiClient.patch<{ data: ProjectTask }>(`/auth/tasks/${taskId}/status`, { status })
    return data.data
  },
  async removeTask(taskId: number) {
    await apiClient.delete(`/auth/tasks/${taskId}`)
  },
}
