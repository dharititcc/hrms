import { publicApiClient } from "@/lib/public-api-client"
import type { MeetingStatus, RsvpStatus } from "@/types/meeting"

/** Only what an invitee needs to decide — never the attendee list or notes. */
export type GuestInvitation = {
  title: string
  agenda: string | null
  starts_at: string
  ends_at: string
  timezone: string
  duration_minutes: number
  status: MeetingStatus
  location: string | null
  meeting_link: string | null
  guest_name: string | null
  guest_email: string
  rsvp: RsvpStatus
}

export const guestRsvpService = {
  async get(token: string) {
    const { data } = await publicApiClient.get<{ data: GuestInvitation }>(`/meetings/invite/${token}`)
    return data.data
  },
  async respond(token: string, rsvp: RsvpStatus) {
    const { data } = await publicApiClient.post<{ data: { rsvp: RsvpStatus; responded_at: string | null } }>(
      `/meetings/invite/${token}/respond`,
      { rsvp },
    )
    return data.data
  },
}
