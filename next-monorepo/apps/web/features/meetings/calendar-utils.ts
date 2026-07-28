import type { Meeting } from "@/types/meeting"

export type CalendarView = "month" | "week" | "day" | "agenda"

/**
 * Times arrive as UTC ISO strings and are rendered in the viewer's own zone,
 * which is what a calendar should do: a 09:00 London meeting reads as 10:00 to
 * someone in Paris. The meeting's scheduling zone is shown on the detail page
 * so the original intent is never lost.
 */
export function toLocal(iso: string): Date {
  return new Date(iso)
}

/** Local YYYY-MM-DD, used as a stable key for grouping by day. */
export function dayKey(date: Date): string {
  const month = `${date.getMonth() + 1}`.padStart(2, "0")
  const day = `${date.getDate()}`.padStart(2, "0")
  return `${date.getFullYear()}-${month}-${day}`
}

export function startOfDay(date: Date): Date {
  const copy = new Date(date)
  copy.setHours(0, 0, 0, 0)
  return copy
}

export function addDays(date: Date, days: number): Date {
  const copy = new Date(date)
  copy.setDate(copy.getDate() + days)
  return copy
}

/** Weeks run Monday to Sunday. */
export function startOfWeek(date: Date): Date {
  const copy = startOfDay(date)
  const weekday = (copy.getDay() + 6) % 7
  return addDays(copy, -weekday)
}

export function startOfMonth(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth(), 1)
}

export function addMonths(date: Date, months: number): Date {
  return new Date(date.getFullYear(), date.getMonth() + months, 1)
}

/** The six-week grid a month view needs, always starting on a Monday. */
export function monthGrid(anchor: Date): Date[] {
  const first = startOfWeek(startOfMonth(anchor))
  return Array.from({ length: 42 }, (_, index) => addDays(first, index))
}

export function weekDays(anchor: Date): Date[] {
  const first = startOfWeek(anchor)
  return Array.from({ length: 7 }, (_, index) => addDays(first, index))
}

/** Inclusive range covering the whole visible period, for the API query. */
export function viewRange(view: CalendarView, anchor: Date): { from: Date; to: Date } {
  if (view === "month") {
    const grid = monthGrid(anchor)
    return { from: grid[0]!, to: addDays(grid[41]!, 1) }
  }
  if (view === "week") {
    const days = weekDays(anchor)
    return { from: days[0]!, to: addDays(days[6]!, 1) }
  }
  if (view === "day") {
    const from = startOfDay(anchor)
    return { from, to: addDays(from, 1) }
  }
  // Agenda looks a month ahead from today rather than around the anchor.
  const from = startOfDay(anchor)
  return { from, to: addDays(from, 30) }
}

export function groupByDay(meetings: Meeting[]): Map<string, Meeting[]> {
  const grouped = new Map<string, Meeting[]>()

  for (const meeting of meetings) {
    const key = dayKey(toLocal(meeting.starts_at))
    const bucket = grouped.get(key)
    if (bucket) bucket.push(meeting)
    else grouped.set(key, [meeting])
  }

  for (const bucket of grouped.values()) {
    bucket.sort((a, b) => a.starts_at.localeCompare(b.starts_at))
  }

  return grouped
}

export function formatTime(date: Date): string {
  return date.toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit" })
}

export function formatRange(meeting: Meeting): string {
  return `${formatTime(toLocal(meeting.starts_at))} – ${formatTime(toLocal(meeting.ends_at))}`
}

export function isSameDay(a: Date, b: Date): boolean {
  return dayKey(a) === dayKey(b)
}

/**
 * Shifts a meeting to a new day, keeping its time of day and duration.
 * Returns ISO strings ready for the reschedule endpoint.
 */
export function shiftToDay(meeting: Meeting, target: Date): { starts_at: string; ends_at: string } {
  const start = toLocal(meeting.starts_at)
  const newStart = new Date(target)
  newStart.setHours(start.getHours(), start.getMinutes(), 0, 0)
  const newEnd = new Date(newStart.getTime() + meeting.duration_minutes * 60_000)

  return { starts_at: newStart.toISOString(), ends_at: newEnd.toISOString() }
}
