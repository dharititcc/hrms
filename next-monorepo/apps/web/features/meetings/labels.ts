import type { MeetingStatus, MeetingType, RsvpStatus } from "@/types/meeting"

export const meetingStatusLabels: Record<MeetingStatus, string> = {
  scheduled: "Scheduled",
  ongoing: "Ongoing",
  completed: "Completed",
  cancelled: "Cancelled",
  rescheduled: "Rescheduled",
}

export const meetingStatusStyles: Record<MeetingStatus, string> = {
  scheduled: "bg-sky-500/10 text-sky-600 dark:text-sky-400",
  ongoing: "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400",
  completed: "bg-muted text-muted-foreground",
  cancelled: "bg-destructive/10 text-destructive",
  rescheduled: "bg-amber-500/10 text-amber-600 dark:text-amber-400",
}

export const meetingTypeLabels: Record<MeetingType, string> = {
  google_meet: "Google Meet",
  zoom: "Zoom",
  teams: "Microsoft Teams",
  offline: "In person",
}

export const rsvpLabels: Record<RsvpStatus, string> = {
  pending: "Awaiting reply",
  accepted: "Accepted",
  declined: "Declined",
  tentative: "Tentative",
}

export const rsvpStyles: Record<RsvpStatus, string> = {
  pending: "bg-muted text-muted-foreground",
  accepted: "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400",
  declined: "bg-destructive/10 text-destructive",
  tentative: "bg-amber-500/10 text-amber-600 dark:text-amber-400",
}
