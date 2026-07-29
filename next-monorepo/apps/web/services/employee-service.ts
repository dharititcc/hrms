import { apiClient } from "@/lib/api-client"
import type { Employee, EmployeeInput, EmployeeListResponse, EmployeeRole, EmployeeStatus } from "@/types/employee"

export type EmployeeFilters = { search?: string; status?: EmployeeStatus | "all"; role?: EmployeeRole | "all"; page?: number; per_page?: number }

export const employeeService = {
  async list(filters: EmployeeFilters = {}) {
    const { data } = await apiClient.get<EmployeeListResponse>("/auth/employees", { params: { ...filters, status: filters.status === "all" ? undefined : filters.status, role: filters.role === "all" ? undefined : filters.role } })
    return data
  },
  async create(input: EmployeeInput) {
    const { data } = await apiClient.post<{ data: Employee }>("/auth/employees", input)
    return data.data
  },
  async update(id: number, input: EmployeeInput) {
    const { data } = await apiClient.put<{ data: Employee }>(`/auth/employees/${id}`, input)
    return data.data
  },
  async remove(id: number) {
    await apiClient.delete(`/auth/employees/${id}`)
  },
  /** Creates a login account and emails a set-password link. */
  async invite(id: number) {
    const { data } = await apiClient.post<{ data: Employee }>(`/auth/employees/${id}/invite`)
    return data.data
  },
  /** Unlinks the account, returning them to a directory-only record. */
  async revokeAccess(id: number) {
    await apiClient.delete(`/auth/employees/${id}/invite`)
  },
}
