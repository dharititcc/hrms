"use client"

import { ArrowLeft, Ban, CalendarDays, Edit3, ExternalLink, MapPin, Users } from "lucide-react"
import Link from "next/link"
import { useState } from "react"
import { Button } from "@workspace/ui/components/button"
import { MeetingFormDialog } from "@/features/meetings/meeting-form-dialog"
import { meetingStatusLabels, meetingStatusStyles, meetingTypeLabels, rsvpLabels, rsvpStyles } from "@/features/meetings/labels"
import { formatRange, toLocal } from "@/features/meetings/calendar-utils"
import { useMeeting, useMeetingMutations } from "@/hooks/use-meetings"
import { usePermissions } from "@/hooks/use-permissions"
import { useAuthStore } from "@/store/auth-store"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import type { RsvpStatus } from "@/types/meeting"

const RSVP_CHOICES: RsvpStatus[] = ["accepted", "tentative", "declined"]

export function MeetingDetail({ meetingId }: { meetingId: number }) {
  const { data: meeting, isLoading, isError, refetch } = useMeeting(meetingId)
  const { respond, cancel, attendance } = useMeetingMutations(meetingId)
  const { user } = useAuthStore()
  const { can } = usePermissions()
  const { toast } = useToast()
  const [editing, setEditing] = useState(false)

  if (isLoading) {
    return (
      <div className="mx-auto grid max-w-5xl gap-6">
        <div className="h-8 w-64 animate-pulse rounded bg-muted" />
        <div className="h-64 animate-pulse rounded-2xl bg-muted" />
      </div>
    )
  }

  if (isError || !meeting) {
    return (
      <div className="mx-auto grid max-w-5xl place-items-center rounded-2xl border bg-background p-12 text-center">
        <p className="font-medium">Unable to load this meeting</p>
        <p className="mt-1 text-sm text-muted-foreground">It may have been deleted, or you may not have access.</p>
        <div className="mt-4 flex gap-2">
          <Button variant="outline" onPress={() => refetch()}>Retry</Button>
          <Link href="/dashboard/meetings"><Button variant="ghost">Back to meetings</Button></Link>
        </div>
      </div>
    )
  }

  const myRsvp = meeting.participants?.find((participant) => participant.user_id === user?.id)
  const starts = toLocal(meeting.starts_at)
  const isPast = new Date(meeting.ends_at) < new Date()
  /*
   * The policy lets the host or organiser manage their own meeting regardless
   * of role, so gating on meetings.edit alone would hide the controls from the
   * very person running it.
   */
  const isHostOrOrganizer = user?.id === meeting.host_id || user?.id === meeting.organizer_id
  const canManage = can("meetings.edit") || isHostOrOrganizer

  const sendRsvp = async (rsvp: RsvpStatus) => {
    try {
      await respond.mutateAsync({ id: meeting.id, rsvp })
      toast({ tone: "success", title: `You replied ${rsvpLabels[rsvp].toLowerCase()}` })
    } catch (error) {
      toast({ tone: "error", title: "Unable to send your reply", description: getApiErrorMessage(error) })
    }
  }

  const cancelMeeting = async () => {
    if (!window.confirm(`Cancel “${meeting.title}”? Everyone invited will be notified.`)) return
    try {
      await cancel.mutateAsync(meeting.id)
      toast({ tone: "success", title: "Meeting cancelled", description: "Attendees have been notified." })
    } catch (error) {
      toast({ tone: "error", title: "Unable to cancel", description: getApiErrorMessage(error) })
    }
  }

  const markAttendance = async (userId: number, attended: boolean) => {
    try {
      await attendance.mutateAsync({ id: meeting.id, participants: { [userId]: attended } })
    } catch (error) {
      toast({ tone: "error", title: "Unable to record attendance", description: getApiErrorMessage(error) })
    }
  }

  return (
    <div className="mx-auto grid max-w-5xl gap-6">
      <div>
        <Link href="/dashboard/meetings" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
          <ArrowLeft className="size-4" />Meetings
        </Link>
        <div className="mt-3 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <div className="flex flex-wrap items-center gap-3">
              <h1 className="text-2xl font-semibold tracking-tight">{meeting.title}</h1>
              <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${meetingStatusStyles[meeting.status]}`}>{meetingStatusLabels[meeting.status]}</span>
            </div>
            <p className="mt-2 text-sm text-muted-foreground">
              {starts.toLocaleDateString(undefined, { weekday: "long", day: "numeric", month: "long", year: "numeric" })} · {formatRange(meeting)}
            </p>
            {/* The zone it was booked in, which may differ from the reader's. */}
            <p className="mt-1 text-xs text-muted-foreground">
              Shown in your local time. Scheduled in {meeting.timezone}. {meeting.duration_minutes} minutes.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            {canManage && <Button variant="outline" onPress={() => setEditing(true)}><Edit3 />Edit</Button>}
            {canManage && meeting.status !== "cancelled" && <Button variant="destructive" onPress={() => void cancelMeeting()}><Ban />Cancel</Button>}
          </div>
        </div>
      </div>

      {meeting.agenda && (
        <section className="rounded-2xl border bg-background p-5">
          <h2 className="text-sm font-semibold">Agenda</h2>
          <p className="mt-2 text-sm whitespace-pre-wrap text-muted-foreground">{meeting.agenda}</p>
        </section>
      )}

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Fact label="Type"><span className="text-sm">{meetingTypeLabels[meeting.type]}</span></Fact>
        <Fact label={meeting.type === "offline" ? "Location" : "Joining link"}>
          {meeting.type === "offline" ? (
            <span className="inline-flex items-center gap-1 text-sm"><MapPin className="size-3.5" />{meeting.location ?? "—"}</span>
          ) : meeting.meeting_link ? (
            <a href={meeting.meeting_link} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 text-sm text-primary hover:underline">
              <ExternalLink className="size-3.5" />Join
            </a>
          ) : (
            <span className="text-sm text-muted-foreground">Not set</span>
          )}
        </Fact>
        <Fact label="Host"><span className="text-sm">{meeting.host_name ?? "—"}</span></Fact>
        <Fact label="Reminder">
          <span className="text-sm">{meeting.reminder_minutes ? `${meeting.reminder_minutes} min before` : "—"}</span>
        </Fact>
      </div>

      {myRsvp && meeting.status !== "cancelled" && (
        <section className="rounded-2xl border bg-background p-5">
          <h2 className="text-sm font-semibold">Your response</h2>
          <div className="mt-3 flex flex-wrap items-center gap-2">
            {RSVP_CHOICES.map((choice) => (
              <Button
                key={choice}
                size="sm"
                variant={myRsvp.rsvp === choice ? "default" : "outline"}
                isDisabled={respond.isPending}
                onPress={() => void sendRsvp(choice)}
              >
                {rsvpLabels[choice]}
              </Button>
            ))}
            {myRsvp.rsvp === "pending" && <span className="text-xs text-muted-foreground">You have not replied yet.</span>}
          </div>
        </section>
      )}

      <section className="rounded-2xl border bg-background p-5">
        <h2 className="flex items-center gap-2 text-sm font-semibold">
          <Users className="size-4" />Attendees
          <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-normal text-muted-foreground">
            {(meeting.participants?.length ?? 0) + (meeting.guests?.length ?? 0)}
          </span>
        </h2>

        <ul className="mt-4 grid gap-1">
          {(meeting.participants ?? []).map((participant) => (
            <li key={participant.id} className="flex flex-wrap items-center gap-3 rounded-lg px-2 py-2 hover:bg-muted/40">
              <span className="text-sm font-medium">{participant.name ?? "Removed user"}</span>
              <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${rsvpStyles[participant.rsvp]}`}>{rsvpLabels[participant.rsvp]}</span>
              {/* Attendance is only meaningful once the meeting has happened. */}
              {isPast && canManage && (
                <label className="ml-auto flex items-center gap-2 text-xs text-muted-foreground">
                  <input
                    type="checkbox"
                    checked={participant.attended}
                    onChange={(event) => void markAttendance(participant.user_id, event.target.checked)}
                    className="size-4 rounded border"
                  />
                  Attended
                </label>
              )}
            </li>
          ))}

          {(meeting.guests ?? []).map((guest) => (
            <li key={`guest-${guest.id}`} className="flex flex-wrap items-center gap-3 rounded-lg px-2 py-2 hover:bg-muted/40">
              <span className="text-sm font-medium">{guest.name ?? guest.email}</span>
              <span className="text-xs text-muted-foreground">{guest.email}</span>
              <span className="rounded-full bg-muted px-2 py-0.5 text-[0.7rem] text-muted-foreground">Guest</span>
              <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${rsvpStyles[guest.rsvp]}`}>{rsvpLabels[guest.rsvp]}</span>
            </li>
          ))}

          {(meeting.participants?.length ?? 0) + (meeting.guests?.length ?? 0) === 0 && (
            <li className="px-2 py-2 text-sm text-muted-foreground">Nobody invited yet.</li>
          )}
        </ul>
      </section>

      {(meeting.notes || meeting.recording_url) && (
        <section className="rounded-2xl border bg-background p-5">
          <h2 className="text-sm font-semibold">After the meeting</h2>
          {meeting.notes && <p className="mt-2 text-sm whitespace-pre-wrap text-muted-foreground">{meeting.notes}</p>}
          {meeting.recording_url && (
            <a href={meeting.recording_url} target="_blank" rel="noopener noreferrer" className="mt-3 inline-flex items-center gap-1 text-sm text-primary hover:underline">
              <CalendarDays className="size-3.5" />Recording
            </a>
          )}
        </section>
      )}

      {editing && <MeetingFormDialog meeting={meeting} onClose={() => setEditing(false)} />}
    </div>
  )
}

function Fact({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="rounded-2xl border bg-background p-4">
      <p className="text-xs font-medium text-muted-foreground">{label}</p>
      <div className="mt-2">{children}</div>
    </div>
  )
}
