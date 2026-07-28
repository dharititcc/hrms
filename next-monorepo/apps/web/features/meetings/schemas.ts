import { z } from "zod"

export const meetingSchema = z
  .object({
    title: z.string().trim().min(2, "Enter a meeting title"),
    agenda: z.string().max(20000, "Agenda is too long").optional(),
    description: z.string().max(20000, "Description is too long").optional(),
    type: z.enum(["google_meet", "zoom", "teams", "offline"]),
    timezone: z.string().min(1, "Choose a timezone"),
    starts_at: z.string().min(1, "Choose a start time"),
    ends_at: z.string().min(1, "Choose an end time"),
    meeting_link: z.union([z.url("Enter a valid URL"), z.literal("")]).optional(),
    location: z.string().max(255, "Location is too long").optional(),
    reminder_minutes: z.string().optional(),
    participant_ids: z.array(z.number()).optional(),
    guest_emails: z.string().optional(),
  })
  .refine((values) => !values.starts_at || !values.ends_at || values.ends_at > values.starts_at, {
    message: "The end time must be after the start time",
    path: ["ends_at"],
  })
  // Mirrors the server rule, so the user finds out before submitting.
  .refine((values) => values.type !== "offline" || Boolean(values.location?.trim()), {
    message: "An in-person meeting needs a location",
    path: ["location"],
  })

export type MeetingFormValues = z.infer<typeof meetingSchema>

/** Splits the comma or newline separated guest field into unique addresses. */
export function parseGuestEmails(raw: string | undefined): { email: string }[] {
  if (!raw?.trim()) return []

  const seen = new Set<string>()

  return raw
    .split(/[\s,;]+/)
    .map((value) => value.trim())
    .filter((value) => value.length > 0 && !seen.has(value.toLowerCase()) && seen.add(value.toLowerCase()) !== undefined)
    .map((email) => ({ email }))
}
