"use client"

import { X } from "lucide-react"
import { useEffect } from "react"
import { Controller, useForm, useWatch } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { Button } from "@workspace/ui/components/button"
import { FormField } from "@/components/ui/form-fields"
import { SelectField } from "@/components/ui/select-field"
import { meetingTypeLabels } from "@/features/meetings/labels"
import { meetingSchema, parseGuestEmails, type MeetingFormValues } from "@/features/meetings/schemas"
import { useMeetingMutations } from "@/hooks/use-meetings"
import { useWorkspaceUsers } from "@/hooks/use-projects"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import type { Meeting, MeetingType } from "@/types/meeting"

/** Converts an ISO instant into the value a datetime-local input expects. */
function toLocalInput(iso: string | undefined): string {
  if (!iso) return ""
  const date = new Date(iso)
  const pad = (value: number) => `${value}`.padStart(2, "0")
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

const emptyValues = (meeting?: Meeting | null): MeetingFormValues => ({
  title: meeting?.title ?? "",
  agenda: meeting?.agenda ?? "",
  description: meeting?.description ?? "",
  type: meeting?.type ?? "google_meet",
  starts_at: toLocalInput(meeting?.starts_at),
  ends_at: toLocalInput(meeting?.ends_at),
  meeting_link: meeting?.meeting_link ?? "",
  location: meeting?.location ?? "",
  reminder_minutes: meeting?.reminder_minutes != null ? String(meeting.reminder_minutes) : "",
  participant_ids: meeting?.participants?.map((participant) => participant.user_id) ?? [],
  guest_emails: meeting?.guests?.map((guest) => guest.email).join(", ") ?? "",
})

export function MeetingFormDialog({ meeting, onClose }: { meeting?: Meeting | null; onClose: () => void }) {
  const { toast } = useToast()
  const { create, update } = useMeetingMutations(meeting?.id)
  const { data: users } = useWorkspaceUsers()
  const editing = Boolean(meeting)
  const assignable = users ?? []

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
      // datetime-local has no zone, so treat it as local and send an instant.
      starts_at: new Date(values.starts_at).toISOString(),
      ends_at: new Date(values.ends_at).toISOString(),
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
      meeting_link: values.meeting_link || null,
      location: values.location || null,
      reminder_minutes: values.reminder_minutes ? Number(values.reminder_minutes) : null,
      participant_ids: values.participant_ids ?? [],
      guests: parseGuestEmails(values.guest_emails),
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

          <Controller
            name="participant_ids"
            control={control}
            render={({ field }) => (
              <fieldset className="grid gap-2">
                <legend className="text-sm font-medium">Participants</legend>
                {assignable.length === 0 ? (
                  <p className="text-xs text-muted-foreground">No colleagues with accounts yet. Invite staff to give them access.</p>
                ) : (
                  <div className="grid max-h-40 gap-1 overflow-y-auto rounded-lg border p-2">
                    {assignable.map((user) => {
                      const selected = field.value?.includes(user.id) ?? false
                      return (
                        <label key={user.id} className="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-muted">
                          <input
                            type="checkbox"
                            checked={selected}
                            onChange={(event) => {
                              const current = field.value ?? []
                              field.onChange(event.target.checked ? [...current, user.id] : current.filter((id) => id !== user.id))
                            }}
                            className="size-4 rounded border"
                          />
                          <span>{user.name}</span>
                          <span className="ml-auto text-xs text-muted-foreground">{user.email}</span>
                        </label>
                      )
                    })}
                  </div>
                )}
              </fieldset>
            )}
          />

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
