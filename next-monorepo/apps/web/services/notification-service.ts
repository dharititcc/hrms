import { apiClient } from "@/lib/api-client"
import type { AppNotification, NotificationListResponse } from "@/types/notification"

export const notificationService = {
  async list(filters: { unread?: boolean; per_page?: number } = {}) {
    const { data } = await apiClient.get<NotificationListResponse>("/auth/notifications", { params: filters })
    return data
  },
  async unreadCount() {
    const { data } = await apiClient.get<{ count: number }>("/auth/notifications/unread-count")
    return data.count
  },
  async markAsRead(id: string) {
    await apiClient.patch(`/auth/notifications/${id}/read`)
  },
  async markAllAsRead() {
    await apiClient.patch("/auth/notifications/read-all")
  },
}

/**
 * Where a notification should take you, or null when it has no destination.
 *
 * Derived from the payload rather than the type, so a notification type added
 * server-side still links correctly if it carries a familiar id.
 */
export function notificationHref(notification: AppNotification): string | null {
  const { task_id, meeting_id } = notification.data

  if (task_id) return `/dashboard/tasks/${task_id}`
  if (meeting_id) return `/dashboard/meetings/${meeting_id}`

  return null
}

/** Compact relative time: the dropdown has no room for a full timestamp. */
export function relativeTime(iso: string): string {
  const seconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000)

  if (seconds < 60) return "just now"
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`
  if (seconds < 86_400) return `${Math.floor(seconds / 3600)}h ago`
  if (seconds < 604_800) return `${Math.floor(seconds / 86_400)}d ago`

  return new Date(iso).toLocaleDateString(undefined, { day: "numeric", month: "short" })
}
