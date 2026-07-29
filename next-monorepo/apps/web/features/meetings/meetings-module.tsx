"use client"

import { CalendarDays, List, Plus, Search } from "lucide-react"
import Link from "next/link"
import { useState } from "react"
import { Button } from "@workspace/ui/components/button"
import { CalendarNav, MeetingCalendar } from "@/features/meetings/meeting-calendar"
import { MeetingFormDialog } from "@/features/meetings/meeting-form-dialog"
import { meetingStatusLabels, meetingStatusStyles, meetingTypeLabels } from "@/features/meetings/labels"
import { formatRange, shiftToDay, toLocal, viewRange, type CalendarView } from "@/features/meetings/calendar-utils"
import { useMeetings, useMeetingMutations } from "@/hooks/use-meetings"
import { usePermissions } from "@/hooks/use-permissions"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import type { Meeting, MeetingStatus, MeetingType } from "@/types/meeting"

const VIEWS: { id: CalendarView; label: string }[] = [
  { id: "month", label: "Month" },
  { id: "week", label: "Week" },
  { id: "day", label: "Day" },
  { id: "agenda", label: "Agenda" },
]

export function MeetingsModule() {
  const [mode, setMode] = useState<"calendar" | "list">("calendar")
  const [view, setView] = useState<CalendarView>("month")
  const [anchor, setAnchor] = useState(() => new Date())
  const [search, setSearch] = useState("")
  const [status, setStatus] = useState<MeetingStatus | "all">("all")
  const [type, setType] = useState<MeetingType | "all">("all")
  const [mine, setMine] = useState(false)
  const [page, setPage] = useState(1)
  const [dialogOpen, setDialogOpen] = useState(false)

  const { toast } = useToast()
  const { can } = usePermissions()
  const { reschedule } = useMeetingMutations()
  // Dragging reschedules, so the grid is only interactive for those who may edit.
  const canReschedule = can("meetings.edit")

  const range = viewRange(view, anchor)
  const filters = {
    search: search || undefined,
    status: status === "all" ? undefined : [status],
    type: type === "all" ? undefined : [type],
    mine: mine || undefined,
    ...(mode === "calendar"
      // The calendar needs the whole visible window in one page.
      ? { from: range.from.toISOString(), to: range.to.toISOString(), per_page: 100 }
      : { page, per_page: 25 }),
  }

  const { data, isLoading, isError, refetch, isPlaceholderData } = useMeetings(filters)
  const meetings = data?.data ?? []
  const meta = data?.meta
  if (mode === "list" && meta && page > meta.last_page) setPage(meta.last_page)

  /**
   * Dragging a meeting to another day reschedules it, which clears every RSVP.
   * That is significant enough to confirm rather than do silently.
   */
  const moveMeeting = async (meeting: Meeting, target: Date) => {
    const shifted = shiftToDay(meeting, target)
    const when = toLocal(shifted.starts_at).toLocaleString(undefined, { weekday: "short", day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" })

    if (!window.confirm(`Move “${meeting.title}” to ${when}?\n\nEveryone's RSVP will be cleared and attendees will be asked to respond again.`)) return

    try {
      await reschedule.mutateAsync({ id: meeting.id, ...shifted })
      toast({ tone: "success", title: "Meeting rescheduled", description: "Attendees have been notified and asked to respond again." })
    } catch (error) {
      toast({ tone: "error", title: "Unable to reschedule", description: getApiErrorMessage(error) })
    }
  }

  return (
    <div className="mx-auto grid max-w-7xl gap-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-sm font-medium text-muted-foreground">Schedule</p>
          <h1 className="mt-2 text-2xl font-semibold tracking-tight">Meetings</h1>
          <p className="mt-2 text-sm text-muted-foreground">Times are shown in your local timezone.</p>
        </div>
        {can("meetings.create") && <Button onPress={() => setDialogOpen(true)}><Plus />New meeting</Button>}
      </div>

      <div className="flex flex-wrap items-center gap-3 rounded-2xl border bg-background p-3">
        <div className="flex gap-1" role="group" aria-label="Display mode">
          <Button size="sm" variant={mode === "calendar" ? "default" : "outline"} onPress={() => setMode("calendar")}><CalendarDays />Calendar</Button>
          <Button size="sm" variant={mode === "list" ? "default" : "outline"} onPress={() => { setMode("list"); setPage(1) }}><List />List</Button>
        </div>

        {mode === "calendar" && (
          <>
            <div className="flex gap-1" role="group" aria-label="Calendar view">
              {VIEWS.map((entry) => (
                <Button key={entry.id} size="sm" variant={view === entry.id ? "secondary" : "ghost"} onPress={() => setView(entry.id)}>{entry.label}</Button>
              ))}
            </div>
            <CalendarNav view={view} anchor={anchor} onAnchor={setAnchor} />
          </>
        )}

        <div className="relative ml-auto min-w-48 flex-1 sm:max-w-64">
          <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
          <input
            aria-label="Search meetings"
            value={search}
            onChange={(event) => { setSearch(event.target.value); setPage(1) }}
            placeholder="Search meetings"
            className="h-9 w-full rounded-lg border bg-background pr-3 pl-9 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
          />
        </div>

        <select
          aria-label="Filter by status"
          value={status}
          onChange={(event) => { setStatus(event.target.value as MeetingStatus | "all"); setPage(1) }}
          className="h-9 rounded-lg border bg-background px-2 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
        >
          <option value="all">All statuses</option>
          {(Object.keys(meetingStatusLabels) as MeetingStatus[]).map((value) => <option key={value} value={value}>{meetingStatusLabels[value]}</option>)}
        </select>

        <select
          aria-label="Filter by type"
          value={type}
          onChange={(event) => { setType(event.target.value as MeetingType | "all"); setPage(1) }}
          className="h-9 rounded-lg border bg-background px-2 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
        >
          <option value="all">All types</option>
          {(Object.keys(meetingTypeLabels) as MeetingType[]).map((value) => <option key={value} value={value}>{meetingTypeLabels[value]}</option>)}
        </select>

        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" checked={mine} onChange={(event) => { setMine(event.target.checked); setPage(1) }} className="size-4 rounded border" />
          Only mine
        </label>
      </div>

      {isError ? (
        <div className="grid place-items-center rounded-2xl border bg-background p-12 text-center">
          <p className="font-medium">Unable to load meetings</p>
          <Button className="mt-4" variant="outline" onPress={() => refetch()}>Retry</Button>
        </div>
      ) : mode === "calendar" ? (
        <div className={isPlaceholderData ? "opacity-60 transition-opacity" : "transition-opacity"}>
          <MeetingCalendar
            view={view}
            anchor={anchor}
            meetings={meetings}
            isLoading={isLoading}
            onMove={canReschedule ? (meeting, target) => void moveMeeting(meeting, target) : undefined}
          />
        </div>
      ) : (
        <MeetingList meetings={meetings} isLoading={isLoading} meta={meta} onPage={setPage} />
      )}

      {dialogOpen && <MeetingFormDialog onClose={() => setDialogOpen(false)} />}
    </div>
  )
}

function MeetingList({ meetings, isLoading, meta, onPage }: {
  meetings: Meeting[]
  isLoading: boolean
  meta?: { current_page: number; last_page: number; per_page: number; total: number }
  onPage: (updater: (current: number) => number) => void
}) {
  if (isLoading) {
    return <div className="grid gap-2">{[1, 2, 3].map((row) => <div key={row} className="h-16 animate-pulse rounded-2xl bg-muted" />)}</div>
  }

  if (meetings.length === 0) {
    return (
      <div className="grid place-items-center rounded-2xl border bg-background p-12 text-center">
        <div className="grid size-12 place-items-center rounded-full bg-muted"><CalendarDays className="size-5 text-muted-foreground" /></div>
        <p className="mt-4 font-medium">No meetings match these filters</p>
      </div>
    )
  }

  return (
    <>
      <div className="overflow-hidden rounded-2xl border bg-background">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[48rem] text-left text-sm">
            <thead className="border-b bg-muted/30 text-xs text-muted-foreground">
              <tr>
                <th className="px-5 py-3 font-medium">Meeting</th>
                <th className="px-5 py-3 font-medium">When</th>
                <th className="px-5 py-3 font-medium">Type</th>
                <th className="px-5 py-3 font-medium">Status</th>
                <th className="px-5 py-3 font-medium">Attendees</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {meetings.map((meeting) => {
                const attendees = (meeting.participants?.length ?? 0) + (meeting.guests?.length ?? 0)
                return (
                  <tr key={meeting.id} className="transition-colors hover:bg-muted/20">
                    <td className="px-5 py-4">
                      <Link href={`/meetings/${meeting.id}`} className="font-medium hover:underline">{meeting.title}</Link>
                      {meeting.host_name && <p className="mt-0.5 text-xs text-muted-foreground">Hosted by {meeting.host_name}</p>}
                    </td>
                    <td className="px-5 py-4 text-muted-foreground">
                      {toLocal(meeting.starts_at).toLocaleDateString()} <span className="text-xs">{formatRange(meeting)}</span>
                    </td>
                    <td className="px-5 py-4 text-muted-foreground">{meetingTypeLabels[meeting.type]}</td>
                    <td className="px-5 py-4">
                      <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${meetingStatusStyles[meeting.status]}`}>{meetingStatusLabels[meeting.status]}</span>
                    </td>
                    <td className="px-5 py-4 text-muted-foreground">{attendees === 0 ? "—" : attendees}</td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      </div>

      {meta && meta.last_page > 1 && (
        <div className="flex flex-col gap-3 rounded-2xl border bg-background px-5 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
          <p className="text-muted-foreground">
            Showing <span className="font-medium text-foreground">{(meta.current_page - 1) * meta.per_page + 1}</span>–
            <span className="font-medium text-foreground">{Math.min(meta.current_page * meta.per_page, meta.total)}</span> of{" "}
            <span className="font-medium text-foreground">{meta.total}</span>
          </p>
          <div className="flex items-center justify-end gap-2">
            <Button variant="outline" size="sm" isDisabled={meta.current_page <= 1} onPress={() => onPage((current) => Math.max(1, current - 1))}>Previous</Button>
            <span className="text-xs text-muted-foreground">Page {meta.current_page} of {meta.last_page}</span>
            <Button variant="outline" size="sm" isDisabled={meta.current_page >= meta.last_page} onPress={() => onPage((current) => current + 1)}>Next</Button>
          </div>
        </div>
      )}
    </>
  )
}
