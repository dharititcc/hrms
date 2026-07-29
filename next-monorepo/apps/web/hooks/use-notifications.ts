"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { notificationService } from "@/services/notification-service"

/**
 * The unread badge polls, because the API has no push channel. A minute is
 * long enough to be cheap and short enough that a badge is never stale in a
 * way anyone notices.
 */
const POLL_MS = 60_000

export function useUnreadNotificationCount() {
  return useQuery({
    queryKey: ["notifications-unread-count"],
    queryFn: () => notificationService.unreadCount(),
    refetchInterval: POLL_MS,
    // Polling while the tab is hidden wakes the server for nobody.
    refetchIntervalInBackground: false,
  })
}

export function useNotifications(enabled: boolean) {
  return useQuery({
    queryKey: ["notifications"],
    queryFn: () => notificationService.list({ per_page: 15 }),
    // Only fetched once the panel is opened; the badge alone needs no list.
    enabled,
  })
}

export function useNotificationMutations() {
  const queryClient = useQueryClient()
  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ["notifications"] })
    queryClient.invalidateQueries({ queryKey: ["notifications-unread-count"] })
  }

  const markAsRead = useMutation({ mutationFn: (id: string) => notificationService.markAsRead(id), onSuccess: refresh })
  const markAllAsRead = useMutation({ mutationFn: () => notificationService.markAllAsRead(), onSuccess: refresh })

  return { markAsRead, markAllAsRead }
}
