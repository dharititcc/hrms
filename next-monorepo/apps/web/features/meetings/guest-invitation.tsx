"use client"

import { CalendarDays, Check, Clock, ExternalLink, MapPin } from "lucide-react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { Button } from "@workspace/ui/components/button"
import { BrandMark } from "@/components/brand-mark"
import { guestRsvpService } from "@/services/guest-rsvp-service"
import { rsvpLabels } from "@/features/meetings/labels"
import type { RsvpStatus } from "@/types/meeting"

const CHOICES: RsvpStatus[] = ["accepted", "tentative", "declined"]

export function GuestInvitation({ token }: { token: string }) {
  const queryClient = useQueryClient()
  const { data: invitation, isLoading, isError } = useQuery({
    queryKey: ["guest-invitation", token],
    queryFn: () => guestRsvpService.get(token),
    retry: false,
  })

  const respond = useMutation({
    mutationFn: (rsvp: RsvpStatus) => guestRsvpService.respond(token, rsvp),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["guest-invitation", token] }),
  })

  if (isLoading) {
    return <Shell><div className="h-56 animate-pulse rounded-2xl bg-muted" /></Shell>
  }

  // A bad or withdrawn token must not hint at whether a meeting exists.
  if (isError || !invitation) {
    return (
      <Shell>
        <div className="rounded-2xl border bg-background p-8 text-center">
          <h1 className="text-lg font-semibold">This invitation link isn’t valid</h1>
          <p className="mt-2 text-sm text-muted-foreground">
            It may have expired, or the meeting may have been removed. Please ask the organiser to send a new one.
          </p>
        </div>
      </Shell>
    )
  }

  const starts = new Date(invitation.starts_at)
  const ends = new Date(invitation.ends_at)
  const cancelled = invitation.status === "cancelled"
  const time = (date: Date) => date.toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit" })

  return (
    <Shell>
      <div className="overflow-hidden rounded-2xl border bg-background">
        <div className="border-b p-6">
          <p className="text-sm text-muted-foreground">
            {invitation.guest_name ? `Hello ${invitation.guest_name},` : "Hello,"} you’re invited to
          </p>
          <h1 className="mt-2 text-2xl font-semibold tracking-tight">{invitation.title}</h1>

          {cancelled && (
            <p className="mt-3 inline-flex rounded-full bg-destructive/10 px-3 py-1 text-sm font-medium text-destructive">
              This meeting has been cancelled
            </p>
          )}
        </div>

        <dl className="grid gap-4 border-b p-6 sm:grid-cols-2">
          <div>
            <dt className="text-xs font-medium text-muted-foreground">When</dt>
            <dd className="mt-1 inline-flex items-center gap-2 text-sm">
              <CalendarDays className="size-4 text-muted-foreground" />
              {starts.toLocaleDateString(undefined, { weekday: "long", day: "numeric", month: "long", year: "numeric" })}
            </dd>
            <dd className="mt-1 inline-flex items-center gap-2 text-sm">
              <Clock className="size-4 text-muted-foreground" />
              {time(starts)} – {time(ends)} ({invitation.duration_minutes} min)
            </dd>
            {/* Shown in the reader's own zone; the organiser's zone is named so
                nobody has to guess which one the times are in. */}
            <dd className="mt-1 text-xs text-muted-foreground">
              Your local time. Scheduled in {invitation.timezone}.
            </dd>
          </div>

          <div>
            <dt className="text-xs font-medium text-muted-foreground">{invitation.location ? "Where" : "Joining"}</dt>
            <dd className="mt-1 text-sm">
              {invitation.location ? (
                <span className="inline-flex items-center gap-2"><MapPin className="size-4 text-muted-foreground" />{invitation.location}</span>
              ) : invitation.meeting_link ? (
                <a href={invitation.meeting_link} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 text-primary hover:underline">
                  <ExternalLink className="size-4" />Join the meeting
                </a>
              ) : (
                <span className="text-muted-foreground">A joining link will follow.</span>
              )}
            </dd>
          </div>

          {invitation.agenda && (
            <div className="sm:col-span-2">
              <dt className="text-xs font-medium text-muted-foreground">Agenda</dt>
              <dd className="mt-1 text-sm whitespace-pre-wrap text-muted-foreground">{invitation.agenda}</dd>
            </div>
          )}
        </dl>

        <div className="p-6">
          {cancelled ? (
            <p className="text-sm text-muted-foreground">No reply is needed.</p>
          ) : (
            <>
              <p className="text-sm font-medium">Will you attend?</p>
              <div className="mt-3 flex flex-wrap gap-2">
                {CHOICES.map((choice) => (
                  <Button
                    key={choice}
                    variant={invitation.rsvp === choice ? "default" : "outline"}
                    isDisabled={respond.isPending}
                    onPress={() => respond.mutate(choice)}
                  >
                    {invitation.rsvp === choice && <Check />}
                    {rsvpLabels[choice]}
                  </Button>
                ))}
              </div>

              <p className="mt-3 text-xs text-muted-foreground">
                {invitation.rsvp === "pending"
                  ? "You haven’t replied yet. You can change your answer later using this link."
                  : `Recorded as ${rsvpLabels[invitation.rsvp].toLowerCase()}. You can change it any time.`}
              </p>

              {respond.isError && (
                <p className="mt-3 text-sm text-destructive">Your reply couldn’t be saved. Please try again.</p>
              )}
            </>
          )}
        </div>
      </div>

      <p className="mt-4 text-center text-xs text-muted-foreground">
        Replying needs no account. This link is personal to {invitation.guest_email}.
      </p>
    </Shell>
  )
}

function Shell({ children }: { children: React.ReactNode }) {
  return (
    <main className="min-h-svh bg-muted/20 px-4 py-10">
      <div className="mx-auto w-full max-w-2xl">
        <div className="mb-6 flex justify-center"><BrandMark /></div>
        {children}
      </div>
    </main>
  )
}
