"use client"

import { X } from "lucide-react"
import { useEffect, useState } from "react"
import { Controller, useForm, useWatch } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { Button } from "@workspace/ui/components/button"
import { FormField } from "@/components/ui/form-fields"
import { SelectField } from "@/components/ui/select-field"
import { meetingTypeLabels } from "@/features/meetings/labels"
import { browserTimezone, instantToZonedInput, supportedTimezones, timezoneLabel, zonedInputToInstant } from "@/features/meetings/calendar-utils"
import { meetingSchema, parseGuestEmails, type MeetingFormValues } from "@/features/meetings/schemas"
import { useMeetingMutations } from "@/hooks/use-meetings"
import { useWorkspaceUsers } from "@/hooks/use-projects"
import { useStaff } from "@/hooks/use-staff"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import type { Meeting, MeetingType } from "@/types/meeting"
import type { Staff } from "@/types/staff"

/**
 * Combines selected account-less staff with any free-text addresses into one
 * guest list, de-duplicated by email so a person invited both ways is invited
 * once.
 */
function mergeGuests(values: MeetingFormValues, staff: Staff[]): { email: string; name?: string | null }[] {
  const chosen = new Set(values.guest_staff_ids ?? [])
  const fromStaff = staff
    .filter((member) => chosen.has(member.id))
    .map((member) => ({ email: member.email, name: member.name }))

  const merged = [...fromStaff, ...parseGuestEmails(values.guest_emails)]
  const seen = new Set<string>()

  return merged.filter((guest) => {
    const key = guest.email.toLowerCase()
    if (seen.has(key)) return false
    seen.add(key)
    return true
  })
}

const emptyValues = (meeting?: Meeting | null): MeetingFormValues => {
  // Existing meetings are edited in the zone they were scheduled in, so the
  // times on screen match what the organiser originally chose.
  const timezone = meeting?.timezone ?? browserTimezone()

  return {
  title: meeting?.title ?? "",
  agenda: meeting?.agenda ?? "",
  description: meeting?.description ?? "",
  type: meeting?.type ?? "google_meet",
  timezone,
  starts_at: instantToZonedInput(meeting?.starts_at, timezone),
  ends_at: instantToZonedInput(meeting?.ends_at, timezone),
  meeting_link: meeting?.meeting_link ?? "",
  location: meeting?.location ?? "",
  reminder_minutes: meeting?.reminder_minutes != null ? String(meeting.reminder_minutes) : "",
  participant_ids: meeting?.participants?.map((participant) => participant.user_id) ?? [],
  guest_staff_ids: [],
  guest_emails: meeting?.guests?.map((guest) => guest.email).join(", ") ?? "",
  }
}

export function MeetingFormDialog({ meeting, onClose }: { meeting?: Meeting | null; onClose: () => void }) {
  const { toast } = useToast()
  const { create, update } = useMeetingMutations(meeting?.id)
  const { data: users } = useWorkspaceUsers()
  const { data: staff } = useStaff({ status: "active", per_page: 100 })
  const editing = Boolean(meeting)
  // Computed once: the IANA list is long and never changes during a session.
  const [timezones] = useState(supportedTimezones)

  /*
   * Anyone who can be invited, in one list.
   *
   * Users become participants and RSVP in-app. Staff without an account cannot
   * sign in, so they are invited as email guests instead — a meeting invitation
   * does not require an account, unlike a task assignment.
   */
  const knownEmails = new Set((users ?? []).map((user) => user.email.toLowerCase()))
  const people: { kind: "user" | "staff"; id: number; name: string; email: string }[] = [
    ...(users ?? []).map((user) => ({ kind: "user" as const, id: user.id, name: user.name, email: user.email })),
    ...(staff?.data ?? [])
      .filter((member) => !member.has_account && !knownEmails.has(member.email.toLowerCase()))
      .map((member) => ({ kind: "staff" as const, id: member.id, name: member.name, email: member.email })),
  ]

  const { register, control, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm<MeetingFormValues>({
    resolver: zodResolver(meetingSchema),
    defaultValues: emptyValues(meeting),
  })

  useEffect(() => { reset(emptyValues(meeting)) }, [reset, meeting])

  // useWatch rather than watch(): the latter cannot be memoized, which opts the
  // whole component out of React Compiler optimisation.
  const type = useWatch({ control, name: "type" })

  const onSubmit = async (values: MeetingFormValues) => {
    const input = {
      title: values.title,
      agenda: values.agenda || null,
      description: values.description || null,
      type: values.type,
      // The typed time is wall-clock in the chosen zone, not the browser's, so
      // it is converted with that zone's offset before being sent as an instant.
      starts_at: zonedInputToInstant(values.starts_at, values.timezone),
      ends_at: zonedInputToInstant(values.ends_at, values.timezone),
      timezone: values.timezone,
      meeting_link: values.meeting_link || null,
      location: values.location || null,
      reminder_minutes: values.reminder_minutes ? Number(values.reminder_minutes) : null,
      participant_ids: values.participant_ids ?? [],
      guests: mergeGuests(values, staff?.data ?? []),
    }

    try {
      if (meeting) await update.mutateAsync({ id: meeting.id, input })
      else await create.mutateAsync(input)
      toast({ tone: "success", title: editing ? "Meeting updated" : "Meeting scheduled", description: "Invitations have been sent." })
      onClose()
    } catch (error) {
      toast({ tone: "error", title: editing ? "Unable to update meeting" : "Unable to schedule meeting", description: getApiErrorMessage(error) })
    }
  }

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose() }}>
      <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border bg-background p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="meeting-dialog-title">
        <div className="flex items-start justify-between">
          <div>
            <h2 id="meeting-dialog-title" className="text-lg font-semibold">{editing ? "Edit meeting" : "New meeting"}</h2>
            <p className="mt-1 text-sm text-muted-foreground">Times use your local timezone.</p>
          </div>
          <Button variant="ghost" size="icon-sm" aria-label="Close dialog" onPress={onClose}><X /></Button>
        </div>

        <form className="mt-6 grid gap-4" onSubmit={handleSubmit(onSubmit)} noValidate>
          <FormField label="Title" placeholder="Sprint planning" error={errors.title?.message} {...register("title")} />

          <div className="grid gap-2">
            <label htmlFor="meeting-agenda" className="text-sm font-medium">Agenda</label>
            <textarea
              id="meeting-agenda"
              rows={3}
              placeholder="What will be covered?"
              className="w-full rounded-lg border bg-background p-3 text-sm outline-none transition placeholder:text-muted-foreground focus:border-ring focus:ring-3 focus:ring-ring/20"
              {...register("agenda")}
            />
            {errors.agenda && <p className="text-xs text-destructive">{errors.agenda.message}</p>}
          </div>

          <Controller
            name="type"
            control={control}
            render={({ field }) => (
              <SelectField
                label="Type"
                value={field.value}
                onChange={field.onChange}
                options={(Object.keys(meetingTypeLabels) as MeetingType[]).map((value) => ({ id: value, label: meetingTypeLabels[value] }))}
              />
            )}
          />

          <div className="grid gap-2">
            <label htmlFor="meeting-timezone" className="text-sm font-medium">Timezone</label>
            <select
              id="meeting-timezone"
              className="h-11 w-full rounded-lg border bg-background px-3 text-sm outline-none transition focus:border-ring focus:ring-3 focus:ring-ring/20"
              {...register("timezone")}
            >
              {timezones.map((zone) => <option key={zone} value={zone}>{timezoneLabel(zone)}</option>)}
            </select>
            {errors.timezone && <p className="text-xs text-destructive">{errors.timezone.message}</p>}
            <p className="text-xs text-muted-foreground">
              The times below are read in this zone. Attendees see the meeting in their own local time.
            </p>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <FormField label="Starts" type="datetime-local" error={errors.starts_at?.message} {...register("starts_at")} />
            <FormField label="Ends" type="datetime-local" error={errors.ends_at?.message} {...register("ends_at")} />
          </div>

          {type === "offline" ? (
            <FormField label="Location" placeholder="Meeting room 2" error={errors.location?.message} {...register("location")} />
          ) : (
            <>
              <FormField label="Joining link" placeholder="https://…" error={errors.meeting_link?.message} {...register("meeting_link")} />
              <p className="-mt-2 text-xs text-muted-foreground">
                Paste your own link. Automatic Google Meet links arrive once Google Calendar is connected.
              </p>
            </>
          )}

          <FormField label="Reminder (minutes before)" type="number" min="0" placeholder="Optional" error={errors.reminder_minutes?.message} {...register("reminder_minutes")} />

          <fieldset className="grid gap-2">
            <legend className="text-sm font-medium">Participants</legend>
            {people.length === 0 ? (
              <p className="text-xs text-muted-foreground">Nobody to invite yet. Add staff members first.</p>
            ) : (
              <div className="grid max-h-48 gap-1 overflow-y-auto rounded-lg border p-2">
                {people.map((person) => (
                  <Controller
                    key={`${person.kind}-${person.id}`}
                    name={person.kind === "user" ? "participant_ids" : "guest_staff_ids"}
                    control={control}
                    render={({ field }) => {
                      const selected = field.value?.includes(person.id) ?? false
                      return (
                        <label className="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-muted">
                          <input
                            type="checkbox"
                            checked={selected}
                            onChange={(event) => {
                              const current = field.value ?? []
                              field.onChange(event.target.checked ? [...current, person.id] : current.filter((id) => id !== person.id))
                            }}
                            className="size-4 rounded border"
                          />
                          <span>{person.name}</span>
                          {person.kind === "staff" && (
                            <span className="rounded-full bg-muted px-2 py-0.5 text-[0.7rem] text-muted-foreground" title="No login account, so they will be invited by email and reply through a private link">
                              email only
                            </span>
                          )}
                          <span className="ml-auto truncate text-xs text-muted-foreground">{person.email}</span>
                        </label>
                      )
                    }}
                  />
                ))}
              </div>
            )}
            <p className="text-xs text-muted-foreground">
              Team members without a login account are invited by email and reply through a private link. Give them an account from the Staff page if they need full access.
            </p>
          </fieldset>

          <div className="grid gap-2">
            <label htmlFor="meeting-guests" className="text-sm font-medium">External guests</label>
            <textarea
              id="meeting-guests"
              rows={2}
              placeholder="client@example.com, partner@example.com"
              className="w-full rounded-lg border bg-background p-3 text-sm outline-none transition placeholder:text-muted-foreground focus:border-ring focus:ring-3 focus:ring-ring/20"
              {...register("guest_emails")}
            />
            <p className="text-xs text-muted-foreground">They receive an email with a private link to reply, no account needed.</p>
          </div>

          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="outline" onPress={onClose}>Cancel</Button>
            <Button type="submit" isDisabled={isSubmitting || create.isPending || update.isPending}>
              {isSubmitting || create.isPending || update.isPending ? "Saving…" : editing ? "Save changes" : "Schedule meeting"}
            </Button>
          </div>
        </form>
      </div>
    </div>
  )
}
