import { apiClient } from "@/lib/api-client"
import type { Staff, StaffInput, StaffListResponse, StaffRole, StaffStatus } from "@/types/staff"

export type StaffFilters = { search?: string; status?: StaffStatus | "all"; role?: StaffRole | "all"; page?: number; per_page?: number }

export const staffService = {
  async list(filters: StaffFilters = {}) {
    const { data } = await apiClient.get<StaffListResponse>("/auth/staff", { params: { ...filters, status: filters.status === "all" ? undefined : filters.status, role: filters.role === "all" ? undefined : filters.role } })
    return data
  },
  async create(input: StaffInput) {
    const { data } = await apiClient.post<{ data: Staff }>("/auth/staff", input)
    return data.data
  },
  async update(id: number, input: StaffInput) {
    const { data } = await apiClient.put<{ data: Staff }>(`/auth/staff/${id}`, input)
    return data.data
  },
  async remove(id: number) {
    await apiClient.delete(`/auth/staff/${id}`)
  },
  /** Creates a login account and emails a set-password link. */
  async invite(id: number) {
    const { data } = await apiClient.post<{ data: Staff }>(`/auth/staff/${id}/invite`)
    return data.data
  },
  /** Unlinks the account, returning them to a directory-only record. */
  async revokeAccess(id: number) {
    await apiClient.delete(`/auth/staff/${id}/invite`)
  },
}
