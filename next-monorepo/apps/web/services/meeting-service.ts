import { apiClient } from "@/lib/api-client"
import type { Meeting, MeetingFilters, MeetingInput, MeetingListResponse, RsvpStatus } from "@/types/meeting"

export const meetingService = {
  async list(filters: MeetingFilters = {}) {
    const { data } = await apiClient.get<MeetingListResponse>("/auth/meetings", {
      params: {
        ...filters,
        status: filters.status?.length ? filters.status : undefined,
        type: filters.type?.length ? filters.type : undefined,
        mine: filters.mine ? 1 : undefined,
      },
    })
    return data
  },
  async get(id: number) {
    const { data } = await apiClient.get<{ data: Meeting }>(`/auth/meetings/${id}`)
    return data.data
  },
  async create(input: MeetingInput) {
    const { data } = await apiClient.post<{ data: Meeting }>("/auth/meetings", input)
    return data.data
  },
  async update(id: number, input: MeetingInput) {
    const { data } = await apiClient.put<{ data: Meeting }>(`/auth/meetings/${id}`, input)
    return data.data
  },
  async remove(id: number) {
    await apiClient.delete(`/auth/meetings/${id}`)
  },
  /** Moves the meeting and clears every RSVP, so callers should confirm first. */
  async reschedule(id: number, input: { starts_at: string; ends_at: string; timezone?: string | null }) {
    const { data } = await apiClient.patch<{ data: Meeting }>(`/auth/meetings/${id}/reschedule`, input)
    return data.data
  },
  async cancel(id: number) {
    const { data } = await apiClient.patch<{ data: Meeting }>(`/auth/meetings/${id}/cancel`)
    return data.data
  },
  async duplicate(id: number, input: { starts_at: string; ends_at: string }) {
    const { data } = await apiClient.post<{ data: Meeting }>(`/auth/meetings/${id}/duplicate`, input)
    return data.data
  },
  async invite(id: number, input: { participant_ids?: number[]; guests?: { email: string; name?: string | null }[] }) {
    const { data } = await apiClient.post<{ data: Meeting }>(`/auth/meetings/${id}/invite`, input)
    return data.data
  },
  async respond(id: number, rsvp: RsvpStatus) {
    const { data } = await apiClient.post<{ data: { rsvp: RsvpStatus; responded_at: string | null } }>(`/auth/meetings/${id}/respond`, { rsvp })
    return data.data
  },
  async recordAttendance(id: number, input: { participants?: Record<number, boolean>; guests?: Record<number, boolean> }) {
    const { data } = await apiClient.patch<{ data: Meeting }>(`/auth/meetings/${id}/attendance`, input)
    return data.data
  },
}
