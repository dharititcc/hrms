import { apiClient } from "@/lib/api-client"
import type { AttendanceListResponse, AttendanceRecord, CapturedPosition, CheckInInput } from "@/types/attendance"

export const attendanceService = {
  async list(filters: { month?: string; staff_id?: number } = {}) {
    const { data } = await apiClient.get<AttendanceListResponse>("/auth/attendance", { params: filters })
    return data
  },
  /** The caller's own record for today, or null before they check in. */
  async today() {
    const { data } = await apiClient.get<{ data: AttendanceRecord | null }>("/auth/attendance/today")
    return data.data
  },
  async checkIn(input: CheckInInput) {
    const { data } = await apiClient.post<{ data: AttendanceRecord }>("/auth/attendance/check-in", input)
    return data.data
  },
  async checkOut(id: number, position?: CapturedPosition) {
    const { data } = await apiClient.post<{ data: AttendanceRecord }>(`/auth/attendance/${id}/check-out`, position ?? {})
    return data.data
  },
  async approve(id: number) {
    const { data } = await apiClient.patch<{ data: AttendanceRecord }>(`/auth/attendance/${id}/approve`)
    return data.data
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

/** A link rather than an embed: embedding Maps needs an API key. */
export function mapsLink(latitude: number, longitude: number): string {
  return `https://www.google.com/maps/search/?api=1&query=${latitude},${longitude}`
}
