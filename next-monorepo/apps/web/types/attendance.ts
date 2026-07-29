export type AttendanceStatus = "present" | "late" | "half_day" | "absent" | "on_leave" | "holiday"
export type WorkMode = "office" | "remote" | "field"

export type AttendanceRecord = {
  id: number
  employee_id: number
  employee_name?: string | null
  work_date: string
  /** Wall clock where the employee was, e.g. "09:12:00". */
  check_in: string | null
  check_out: string | null
  /** The same moments as absolute instants, for showing in the viewer's zone. */
  check_in_at: string | null
  check_out_at: string | null
  /** The IANA zone the employee's browser reported when recording. */
  timezone: string | null
  status: AttendanceStatus
  work_mode: WorkMode
  /** Checked in but not yet out, so nothing has been derived for it. */
  is_open: boolean
  /** Minutes since check-in on a day still running. */
  elapsed_minutes: number
  worked_minutes: number
  worked_hours: string
  break_minutes: number
  late_minutes: number
  overtime_minutes: number
  check_in_location: { latitude: number | null; longitude: number | null; address: string | null; office?: string | null }
  check_out_location: { latitude: number | null; longitude: number | null; address: string | null }
  device: { type: string | null; os: string | null; browser: string | null; ip_address: string | null }
  requires_approval: boolean
  is_manual: boolean
  approved_at: string | null
  notes: string | null
}

export type AttendanceListResponse = {
  data: AttendanceRecord[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

/** What the browser managed to capture, if the user allowed it. */
export type CapturedPosition = {
  latitude: number
  longitude: number
}

export type AttendanceLocation = {
  id: number
  name: string
  address: string | null
  latitude: number
  longitude: number
  radius_metres: number
  is_active: boolean
  created_at: string
}

export type AttendanceLocationListResponse = {
  data: AttendanceLocation[]
  meta: {
    /** Whether a fence blocks a check-in or merely flags it for approval. */
    enforcement_enabled: boolean
    default_radius_metres: number
  }
}

export type AttendanceLocationInput = {
  name: string
  address?: string | null
  latitude: number
  longitude: number
  radius_metres: number
  is_active?: boolean
}

/**
 * Times are the employee's own wall clock, not the corrector's: the record
 * already knows which zone it was taken in, and reinterpreting it in the
 * manager's would move somebody's day.
 */
export type AttendanceCorrectionInput = {
  /** As "HH:MM", in the zone on the record. */
  check_in?: string | null
  check_out?: string | null
  notes?: string | null
}

/** The service adds the browser's timezone, so callers never pass it. */
export type CheckInInput = {
  employee_id: number
  work_mode?: WorkMode
  latitude?: number
  longitude?: number
  address?: string | null
}

/** Office hours: what "late" and "a full day" are measured against. */
export type WorkShift = {
  id: number
  name: string
  starts_at: string
  ends_at: string
  grace_minutes: number
  break_minutes: number
  is_default: boolean
  is_active: boolean
  /** The shift's length less its unpaid break. */
  paid_minutes: number
  created_at: string
}

export type WorkShiftListResponse = {
  data: WorkShift[]
  meta: {
    /** What a workspace with no hours of its own is judged against. */
    fallback: { starts_at: string; ends_at: string; grace_minutes: number; break_minutes: number }
    half_day_threshold_minutes: number
    overtime_after_minutes: number
    break_after_minutes: number
  }
}

export type WorkShiftInput = {
  name: string
  starts_at: string
  ends_at: string
  grace_minutes: number
  break_minutes: number
  is_default?: boolean
  is_active?: boolean
}
