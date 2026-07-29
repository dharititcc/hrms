import type { AttendanceStatus, WorkMode } from "@/types/attendance"

export const attendanceStatusLabels: Record<AttendanceStatus, string> = {
  present: "Present",
  late: "Late",
  half_day: "Half day",
  absent: "Absent",
  on_leave: "On leave",
  holiday: "Holiday",
}

export const attendanceStatusStyles: Record<AttendanceStatus, string> = {
  present: "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400",
  late: "bg-amber-500/10 text-amber-600 dark:text-amber-400",
  half_day: "bg-sky-500/10 text-sky-600 dark:text-sky-400",
  absent: "bg-destructive/10 text-destructive",
  on_leave: "bg-muted text-muted-foreground",
  holiday: "bg-muted text-muted-foreground",
}

export const workModeLabels: Record<WorkMode, string> = {
  office: "Office",
  remote: "Remote",
  field: "Field",
}

/** 90 -> "1h 30m" */
export function formatMinutes(minutes: number): string {
  if (minutes <= 0) return "0m"
  const hours = Math.floor(minutes / 60)
  const rest = minutes % 60
  if (hours === 0) return `${rest}m`
  return rest === 0 ? `${hours}h` : `${hours}h ${rest}m`
}
