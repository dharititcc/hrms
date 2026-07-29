"use client"

import { CalendarDays, ChevronLeft, ChevronRight, MapPin, Video } from "lucide-react"
import Link from "next/link"
import { useState } from "react"
import { Button } from "@workspace/ui/components/button"
import { meetingStatusStyles } from "@/features/meetings/labels"
import {
  addDays, addMonths, dayKey, formatRange, formatTime, groupByDay, isSameDay,
  monthGrid, startOfDay, startOfMonth, startOfWeek, toLocal, weekDays,
  type CalendarView,
} from "@/features/meetings/calendar-utils"
import type { Meeting } from "@/types/meeting"

const WEEKDAYS = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"]

export function MeetingCalendar({ view, anchor, meetings, isLoading, onMove }: {
  view: CalendarView
  anchor: Date
  meetings: Meeting[]
  isLoading: boolean
  /** Omitted when the viewer may not reschedule; the grid then reads only. */
  onMove?: (meeting: Meeting, target: Date) => void
}) {
  const byDay = groupByDay(meetings)

  if (isLoading) {
    return <div className="h-[32rem] animate-pulse rounded-2xl bg-muted" />
  }

  if (view === "month") return <MonthView anchor={anchor} byDay={byDay} onMove={onMove} draggable={Boolean(onMove)} />
  if (view === "week") return <WeekView anchor={anchor} byDay={byDay} />
  if (view === "day") return <DayView anchor={anchor} byDay={byDay} />
  return <AgendaView meetings={meetings} />
}

function MonthView({ anchor, byDay, onMove, draggable }: { anchor: Date; byDay: Map<string, Meeting[]>; onMove?: (meeting: Meeting, target: Date) => void; draggable: boolean }) {
  const [dragOver, setDragOver] = useState<string | null>(null)
  const days = monthGrid(anchor)
  const currentMonth = startOfMonth(anchor).getMonth()
  const today = startOfDay(new Date())

  return (
    <div className="overflow-hidden rounded-2xl border bg-background">
      <div className="grid grid-cols-7 border-b bg-muted/30 text-xs font-medium text-muted-foreground">
        {WEEKDAYS.map((label) => <div key={label} className="px-2 py-2 text-center">{label}</div>)}
      </div>

      <div className="grid grid-cols-7">
        {days.map((day) => {
          const key = dayKey(day)
          const dayMeetings = byDay.get(key) ?? []
          const outside = day.getMonth() !== currentMonth

          return (
            <div
              key={key}
              onDragOver={draggable ? (event) => { event.preventDefault(); setDragOver(key) } : undefined}
              onDragLeave={draggable ? () => setDragOver((current) => (current === key ? null : current)) : undefined}
              onDrop={draggable ? (event) => {
                event.preventDefault()
                setDragOver(null)
                const id = Number(event.dataTransfer.getData("text/plain"))
                const meeting = [...byDay.values()].flat().find((item) => item.id === id)
                if (meeting) onMove?.(meeting, day)
              } : undefined}
              className={`min-h-28 border-b border-r p-1.5 transition-colors ${outside ? "bg-muted/20" : ""} ${dragOver === key ? "bg-primary/5 ring-1 ring-ring ring-inset" : ""}`}
            >
              <div className="flex items-center justify-between px-1">
                <span className={`text-xs ${isSameDay(day, today) ? "grid size-5 place-items-center rounded-full bg-primary font-semibold text-primary-foreground" : outside ? "text-muted-foreground/60" : "text-muted-foreground"}`}>
                  {day.getDate()}
                </span>
              </div>

              <div className="mt-1 grid gap-1">
                {dayMeetings.slice(0, 3).map((meeting) => <MonthChip key={meeting.id} meeting={meeting} draggable={draggable} />)}
                {dayMeetings.length > 3 && (
                  <span className="px-1 text-[0.7rem] text-muted-foreground">+{dayMeetings.length - 3} more</span>
                )}
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}

function MonthChip({ meeting, draggable }: { meeting: Meeting; draggable: boolean }) {
  return (
    <Link
      href={`/dashboard/meetings/${meeting.id}`}
      draggable={draggable}
      onDragStart={draggable ? (event) => event.dataTransfer.setData("text/plain", String(meeting.id)) : undefined}
      className={`block truncate rounded px-1.5 py-0.5 text-[0.7rem] ${draggable ? "cursor-grab active:cursor-grabbing" : ""} ${meetingStatusStyles[meeting.status]}`}
      title={`${formatRange(meeting)} — ${meeting.title}`}
    >
      {formatTime(toLocal(meeting.starts_at))} {meeting.title}
    </Link>
  )
}

function WeekView({ anchor, byDay }: { anchor: Date; byDay: Map<string, Meeting[]> }) {
  const days = weekDays(anchor)
  const today = startOfDay(new Date())

  return (
    <div className="overflow-x-auto rounded-2xl border bg-background">
      <div className="grid min-w-[56rem] grid-cols-7">
        {days.map((day) => {
          const dayMeetings = byDay.get(dayKey(day)) ?? []

          return (
            <div key={dayKey(day)} className="min-h-[24rem] border-r p-2 last:border-r-0">
              <div className={`mb-2 rounded-lg px-2 py-1 text-xs font-medium ${isSameDay(day, today) ? "bg-primary text-primary-foreground" : "text-muted-foreground"}`}>
                {WEEKDAYS[(day.getDay() + 6) % 7]} {day.getDate()}
              </div>
              <div className="grid gap-1.5">
                {dayMeetings.length === 0
                  ? <p className="px-2 text-[0.7rem] text-muted-foreground">—</p>
                  : dayMeetings.map((meeting) => <MeetingCard key={meeting.id} meeting={meeting} />)}
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}

function DayView({ anchor, byDay }: { anchor: Date; byDay: Map<string, Meeting[]> }) {
  const dayMeetings = byDay.get(dayKey(anchor)) ?? []

  return (
    <div className="rounded-2xl border bg-background p-4">
      <h3 className="text-sm font-semibold">{anchor.toLocaleDateString(undefined, { weekday: "long", day: "numeric", month: "long", year: "numeric" })}</h3>
      <div className="mt-4 grid gap-2">
        {dayMeetings.length === 0
          ? <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">Nothing scheduled.</p>
          : dayMeetings.map((meeting) => <MeetingCard key={meeting.id} meeting={meeting} expanded />)}
      </div>
    </div>
  )
}

function AgendaView({ meetings }: { meetings: Meeting[] }) {
  const grouped = groupByDay(meetings)
  const keys = [...grouped.keys()].sort()

  if (keys.length === 0) {
    return (
      <div className="grid place-items-center rounded-2xl border bg-background p-12 text-center">
        <div className="grid size-12 place-items-center rounded-full bg-muted"><CalendarDays className="size-5 text-muted-foreground" /></div>
        <p className="mt-4 font-medium">Nothing in the next 30 days</p>
      </div>
    )
  }

  return (
    <div className="grid gap-4">
      {keys.map((key) => {
        const first = toLocal(grouped.get(key)![0]!.starts_at)
        return (
          <section key={key} className="rounded-2xl border bg-background p-4">
            <h3 className="text-sm font-semibold">{first.toLocaleDateString(undefined, { weekday: "long", day: "numeric", month: "long" })}</h3>
            <div className="mt-3 grid gap-2">
              {grouped.get(key)!.map((meeting) => <MeetingCard key={meeting.id} meeting={meeting} expanded />)}
            </div>
          </section>
        )
      })}
    </div>
  )
}

function MeetingCard({ meeting, expanded }: { meeting: Meeting; expanded?: boolean }) {
  return (
    <Link href={`/dashboard/meetings/${meeting.id}`} className="block rounded-xl border p-2.5 transition-colors hover:bg-muted/40">
      <div className="flex items-start justify-between gap-2">
        <p className="text-sm font-medium">{meeting.title}</p>
        <span className={`shrink-0 rounded-full px-2 py-0.5 text-[0.7rem] font-medium ${meetingStatusStyles[meeting.status]}`}>
          {meeting.duration_minutes}m
        </span>
      </div>
      <p className="mt-1 text-xs text-muted-foreground">{formatRange(meeting)}</p>
      {expanded && (
        <p className="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground">
          {meeting.type === "offline"
            ? <><MapPin className="size-3" />{meeting.location ?? "No location"}</>
            : <><Video className="size-3" />{meeting.meeting_link ? "Joining link set" : "No link yet"}</>}
        </p>
      )}
    </Link>
  )
}

/** Period navigation shared by the calendar toolbar. */
export function CalendarNav({ view, anchor, onAnchor }: { view: CalendarView; anchor: Date; onAnchor: (date: Date) => void }) {
  const step = (direction: 1 | -1) => {
    if (view === "month") return onAnchor(addMonths(anchor, direction))
    if (view === "week") return onAnchor(addDays(startOfWeek(anchor), direction * 7))
    return onAnchor(addDays(anchor, direction))
  }

  const label = view === "month"
    ? anchor.toLocaleDateString(undefined, { month: "long", year: "numeric" })
    : view === "week"
      ? `Week of ${startOfWeek(anchor).toLocaleDateString(undefined, { day: "numeric", month: "short" })}`
      : anchor.toLocaleDateString(undefined, { day: "numeric", month: "long", year: "numeric" })

  return (
    <div className="flex items-center gap-2">
      <Button variant="outline" size="icon-sm" aria-label="Previous period" onPress={() => step(-1)}><ChevronLeft /></Button>
      <Button variant="outline" size="sm" onPress={() => onAnchor(new Date())}>Today</Button>
      <Button variant="outline" size="icon-sm" aria-label="Next period" onPress={() => step(1)}><ChevronRight /></Button>
      <span className="ml-1 text-sm font-medium">{view === "agenda" ? "Next 30 days" : label}</span>
    </div>
  )
}
