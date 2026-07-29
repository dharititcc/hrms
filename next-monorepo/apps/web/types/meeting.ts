import type { TaskTag } from "@/types/project"

export type MeetingStatus = "scheduled" | "ongoing" | "completed" | "cancelled" | "rescheduled"
export type MeetingType = "google_meet" | "zoom" | "teams" | "offline"
export type RsvpStatus = "pending" | "accepted" | "declined" | "tentative"

export type MeetingParticipant = {
  id: number
  user_id: number
  name: string | null
  rsvp: RsvpStatus
  responded_at: string | null
  attended: boolean
}

export type MeetingGuest = {
  id: number
  name: string | null
  email: string
  rsvp: RsvpStatus
  responded_at: string | null
  attended: boolean
}

export type Meeting = {
  id: number
  title: string
  agenda: string | null
  description: string | null
  type: MeetingType
  status: MeetingStatus
  host_id: number | null
  host_name?: string | null
  organizer_id: number | null
  organizer_name?: string | null
  /** ISO 8601, UTC. Rendered in the viewer's local zone. */
  starts_at: string
  ends_at: string
  /** The zone the meeting was scheduled in, shown alongside local time. */
  timezone: string
  duration_minutes: number
  meeting_link: string | null
  location: string | null
  notes: string | null
  recording_url: string | null
  transcript: string | null
  reminder_minutes: number | null
  repeat_frequency: string | null
  repeat_interval: number
  repeat_until: string | null
  recurrence_parent_id: number | null
  participants?: MeetingParticipant[]
  guests?: MeetingGuest[]
  tags?: TaskTag[]
  created_at: string
  updated_at: string
}

export type MeetingListResponse = {
  data: Meeting[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export type MeetingFilters = {
  search?: string
  status?: MeetingStatus[]
  type?: MeetingType[]
  participant_id?: number
  host_id?: number
  mine?: boolean
  from?: string
  to?: string
  period?: "upcoming" | "past"
  sort?: "starts_at" | "title" | "created_at"
  direction?: "asc" | "desc"
  page?: number
  per_page?: number
}

export type MeetingInput = {
  title: string
  agenda?: string | null
  description?: string | null
  type: MeetingType
  host_id?: number | null
  organizer_id?: number | null
  starts_at: string
  ends_at: string
  timezone?: string | null
  meeting_link?: string | null
  location?: string | null
  reminder_minutes?: number | null
  participant_ids?: number[]
  guests?: { email: string; name?: string | null }[]
}
