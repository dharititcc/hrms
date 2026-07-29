"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { meetingService } from "@/services/meeting-service"
import type { MeetingFilters, MeetingInput, RsvpStatus } from "@/types/meeting"

export function useMeetings(filters: MeetingFilters) {
  return useQuery({
    queryKey: ["meetings", filters],
    queryFn: () => meetingService.list(filters),
    // Keeps the calendar populated while moving between months.
    placeholderData: (previous) => previous,
  })
}

export function useMeeting(id: number) {
  return useQuery({ queryKey: ["meeting", id], queryFn: () => meetingService.get(id), enabled: Number.isFinite(id) })
}

export function useMeetingMutations(meetingId?: number) {
  const queryClient = useQueryClient()

  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ["meetings"] })
    if (meetingId !== undefined) queryClient.invalidateQueries({ queryKey: ["meeting", meetingId] })
  }

  const create = useMutation({ mutationFn: (input: MeetingInput) => meetingService.create(input), onSuccess: refresh })
  const update = useMutation({ mutationFn: ({ id, input }: { id: number; input: MeetingInput }) => meetingService.update(id, input), onSuccess: refresh })
  const remove = useMutation({ mutationFn: (id: number) => meetingService.remove(id), onSuccess: refresh })
  const cancel = useMutation({ mutationFn: (id: number) => meetingService.cancel(id), onSuccess: refresh })
  const reschedule = useMutation({
    mutationFn: ({ id, ...input }: { id: number; starts_at: string; ends_at: string; timezone?: string | null }) =>
      meetingService.reschedule(id, input),
    onSuccess: refresh,
  })
  const duplicate = useMutation({
    mutationFn: ({ id, ...input }: { id: number; starts_at: string; ends_at: string }) => meetingService.duplicate(id, input),
    onSuccess: refresh,
  })
  const respond = useMutation({
    mutationFn: ({ id, rsvp }: { id: number; rsvp: RsvpStatus }) => meetingService.respond(id, rsvp),
    onSuccess: refresh,
  })
  const invite = useMutation({
    mutationFn: ({ id, ...input }: { id: number; participant_ids?: number[]; guests?: { email: string; name?: string | null }[] }) =>
      meetingService.invite(id, input),
    onSuccess: refresh,
  })
  const attendance = useMutation({
    mutationFn: ({ id, ...input }: { id: number; participants?: Record<number, boolean>; guests?: Record<number, boolean> }) =>
      meetingService.recordAttendance(id, input),
    onSuccess: refresh,
  })

  return { create, update, remove, cancel, reschedule, duplicate, respond, invite, attendance }
}
