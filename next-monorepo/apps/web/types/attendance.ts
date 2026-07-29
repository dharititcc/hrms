export type AttendanceStatus = "present" | "late" | "half_day" | "absent" | "on_leave" | "holiday"
export type WorkMode = "office" | "remote" | "field"

export type AttendanceRecord = {
  id: number
  staff_id: number
  staff_name?: string | null
  work_date: string
  check_in: string | null
  check_out: string | null
  status: AttendanceStatus
  work_mode: WorkMode
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

export type CheckInInput = {
  staff_id: number
  work_mode?: WorkMode
  latitude?: number
  longitude?: number
  address?: string | null
}
