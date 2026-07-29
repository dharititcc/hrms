import { apiClient } from "@/lib/api-client"
import type {
  AttendanceListResponse, AttendanceLocation, AttendanceLocationInput,
  AttendanceLocationListResponse, AttendanceRecord, CapturedPosition, CheckInInput,
} from "@/types/attendance"

export const attendanceService = {
  async list(filters: { month?: string; employee_id?: number } = {}) {
    const { data } = await apiClient.get<AttendanceListResponse>("/auth/attendance", { params: filters })
    return data
  },
  /** The caller's own record for today, or null before they check in. */
  async today() {
    // Their today, not the server's: without this somebody a day ahead is
    // shown nothing after checking in.
    const { data } = await apiClient.get<{ data: AttendanceRecord | null }>("/auth/attendance/today", {
      params: { timezone: browserTimezone() },
    })
    return data.data
  },
  async checkIn(input: CheckInInput) {
    const { data } = await apiClient.post<{ data: AttendanceRecord }>("/auth/attendance/check-in", {
      ...input,
      timezone: browserTimezone(),
    })
    return data.data
  },
  async checkOut(id: number, position?: CapturedPosition) {
    const { data } = await apiClient.post<{ data: AttendanceRecord }>(`/auth/attendance/${id}/check-out`, {
      ...(position ?? {}),
      timezone: browserTimezone(),
    })
    return data.data
  },
  async approve(id: number) {
    const { data } = await apiClient.patch<{ data: AttendanceRecord }>(`/auth/attendance/${id}/approve`)
    return data.data
  },

  // Office locations, which geofenced check-ins are measured against.
  async locations() {
    const { data } = await apiClient.get<AttendanceLocationListResponse>("/auth/attendance-locations")
    return data
  },
  async createLocation(input: AttendanceLocationInput) {
    const { data } = await apiClient.post<{ data: AttendanceLocation }>("/auth/attendance-locations", input)
    return data.data
  },
  async updateLocation(id: number, input: AttendanceLocationInput) {
    const { data } = await apiClient.put<{ data: AttendanceLocation }>(`/auth/attendance-locations/${id}`, input)
    return data.data
  },
  async removeLocation(id: number) {
    await apiClient.delete(`/auth/attendance-locations/${id}`)
  },
}

/**
 * Asks the browser for a position.
 *
 * Resolves to null rather than rejecting when permission is refused or the
 * device cannot fix a location: attendance should still be recordable, just
 * without coordinates. The server accepts a check-in with none.
 */
export function capturePosition(timeoutMs = 8000): Promise<CapturedPosition | null> {
  if (typeof navigator === "undefined" || !navigator.geolocation) {
    return Promise.resolve(null)
  }

  return new Promise((resolve) => {
    navigator.geolocation.getCurrentPosition(
      (position) => resolve({ latitude: position.coords.latitude, longitude: position.coords.longitude }),
      () => resolve(null),
      { enableHighAccuracy: true, timeout: timeoutMs, maximumAge: 0 },
    )
  })
}

/**
 * The zone this browser is in, which is what the day should be recorded
 * against. Falls back to UTC on the rare engine that cannot report one.
 */
export function browserTimezone(): string {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || "UTC"
  } catch {
    return "UTC"
  }
}

/**
 * A recorded time, shown in the zone of whoever is looking at it.
 *
 * Prefers the absolute instant, so a manager in London reading an Indian
 * colleague's day sees it in London time with the zone named. Records made
 * before instants were stored have only a wall clock, which is shown as-is
 * and labelled with the zone it was recorded in.
 */
export function formatRecordedTime(
  instant: string | null | undefined,
  wallClock: string | null | undefined,
  recordedZone?: string | null,
): string {
  if (instant) {
    return new Intl.DateTimeFormat(undefined, {
      hour: "2-digit",
      minute: "2-digit",
      timeZoneName: "short",
    }).format(new Date(instant))
  }

  if (!wallClock) return "—"

  // No instant to convert, so the best that can be said is where it was taken.
  const zone = recordedZone ? ` (${recordedZone.split("/").pop()?.replace(/_/g, " ")})` : ""

  return `${wallClock.slice(0, 5)}${zone}`
}

/**
 * A zone in the width a table column allows: "Kolkata · GMT+5:30".
 *
 * The offset is read at the moment the record was made, not now, so a London
 * day in July reads BST and one in January reads GMT. The full identifier
 * belongs in a title attribute rather than the cell.
 */
export function compactTimezone(zone: string | null | undefined, at?: string | null): string {
  if (!zone) return "—"

  const city = zone.split("/").pop()?.replace(/_/g, " ") ?? zone

  try {
    const parts = new Intl.DateTimeFormat(undefined, { timeZone: zone, timeZoneName: "shortOffset" })
      .formatToParts(at ? new Date(at) : new Date())
    const offset = parts.find((part) => part.type === "timeZoneName")?.value

    return offset ? `${city} · ${offset}` : city
  } catch {
    // An identifier this browser does not know still names a place.
    return city
  }
}

/** A link rather than an embed: embedding Maps needs an API key. */
export function mapsLink(latitude: number, longitude: number): string {
  return `https://www.google.com/maps/search/?api=1&query=${latitude},${longitude}`
}
