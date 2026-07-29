"use client"

import { AtSign, Bell, CalendarDays, CalendarX, CheckCheck, CheckSquare, Clock, Mail } from "lucide-react"
import Link from "next/link"
import { useEffect, useRef, useState } from "react"
import { Button } from "@workspace/ui/components/button"
import { useNotificationMutations, useNotifications, useUnreadNotificationCount } from "@/hooks/use-notifications"
import { notificationHref, relativeTime } from "@/services/notification-service"
import type { AppNotification, NotificationType } from "@/types/notification"

/**
 * Elements rather than component references, so nothing looks like a component
 * being defined during render.
 */
const ICONS: Record<string, React.ReactNode> = {
  TaskAssignedNotification: <CheckSquare className="size-3.5" />,
  TaskDueReminderNotification: <Clock className="size-3.5" />,
  TaskMentionNotification: <AtSign className="size-3.5" />,
  MeetingInvitationNotification: <CalendarDays className="size-3.5" />,
  MeetingChangedNotification: <CalendarX className="size-3.5" />,
  MeetingReminderNotification: <Clock className="size-3.5" />,
  EmployeeInvitationNotification: <Mail className="size-3.5" />,
}

function iconFor(type: NotificationType): React.ReactNode {
  // Falls back rather than breaking on a type the server added later.
  return ICONS[type] ?? <Bell className="size-3.5" />
}

/**
 * In-app notifications.
 *
 * Eight notification classes and four endpoints already existed with no
 * frontend, so nothing was ever surfaced in the app — only by email.
 */
export function NotificationBell() {
  const [open, setOpen] = useState(false)
  const containerRef = useRef<HTMLDivElement>(null)

  const { data: unread = 0 } = useUnreadNotificationCount()
  const { data, isLoading } = useNotifications(open)
  const { markAsRead, markAllAsRead } = useNotificationMutations()

  const notifications = data?.data ?? []

  // A dropdown that survives a click elsewhere on the page reads as stuck.
  useEffect(() => {
    if (!open) return

    const close = (event: MouseEvent) => {
      if (!containerRef.current?.contains(event.target as Node)) setOpen(false)
    }
    const escape = (event: KeyboardEvent) => {
      if (event.key === "Escape") setOpen(false)
    }

    document.addEventListener("mousedown", close)
    document.addEventListener("keydown", escape)

    return () => {
      document.removeEventListener("mousedown", close)
      document.removeEventListener("keydown", escape)
    }
  }, [open])

  const openNotification = (notification: AppNotification) => {
    if (!notification.read_at) markAsRead.mutate(notification.id)
    setOpen(false)
  }

  return (
    <div className="relative" ref={containerRef}>
      <Button
        variant="ghost"
        size="icon-sm"
        aria-label={unread > 0 ? `Notifications, ${unread} unread` : "Notifications"}
        aria-expanded={open}
        onPress={() => setOpen((current) => !current)}
      >
        <Bell />
        {unread > 0 && (
          <span
            aria-hidden
            className="absolute -top-0.5 -right-0.5 grid min-w-4 place-items-center rounded-full bg-destructive px-1 text-[0.625rem] font-medium text-destructive-foreground"
          >
            {unread > 9 ? "9+" : unread}
          </span>
        )}
      </Button>

      {open && (
        <div className="absolute right-0 z-30 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border bg-popover shadow-xl">
          <div className="flex items-center justify-between border-b px-4 py-2.5">
            <p className="text-sm font-semibold">Notifications</p>
            {unread > 0 && (
              <Button
                variant="ghost"
                size="sm"
                isDisabled={markAllAsRead.isPending}
                onPress={() => markAllAsRead.mutate()}
              >
                <CheckCheck />Mark all read
              </Button>
            )}
          </div>

          <div className="max-h-96 overflow-y-auto">
            {isLoading ? (
              <div className="animate-pulse divide-y">
                {[1, 2, 3].map((row) => (
                  <div key={row} className="p-4"><div className="h-3 w-40 rounded bg-muted" /></div>
                ))}
              </div>
            ) : notifications.length === 0 ? (
              <div className="grid place-items-center p-8 text-center">
                <div className="grid size-10 place-items-center rounded-full bg-muted"><Bell className="size-4 text-muted-foreground" /></div>
                <p className="mt-3 text-sm font-medium">Nothing yet</p>
                <p className="mt-1 text-xs text-muted-foreground">Assignments, mentions and meeting invitations land here.</p>
              </div>
            ) : (
              <ul className="divide-y">
                {notifications.map((notification) => (
                  <Row key={notification.id} notification={notification} onOpen={() => openNotification(notification)} />
                ))}
              </ul>
            )}
          </div>
        </div>
      )}
    </div>
  )
}

function Row({ notification, onOpen }: { notification: AppNotification; onOpen: () => void }) {
  const href = notificationHref(notification)
  const isUnread = notification.read_at === null

  const body = (
    <div className="flex gap-3 px-4 py-3 text-left">
      <span className={`mt-0.5 grid size-7 shrink-0 place-items-center rounded-full ${isUnread ? "bg-primary/10 text-primary" : "bg-muted text-muted-foreground"}`}>
        {iconFor(notification.type)}
      </span>
      <div className="min-w-0 flex-1">
        <p className={`truncate text-sm ${isUnread ? "font-medium" : ""}`}>{notification.data.title}</p>
        <p className="mt-0.5 text-xs text-muted-foreground">{notification.data.message}</p>
        <time className="mt-1 block text-[0.7rem] text-muted-foreground" dateTime={notification.created_at}>
          {relativeTime(notification.created_at)}
        </time>
      </div>
      {isUnread && <span aria-hidden className="mt-1.5 size-2 shrink-0 rounded-full bg-primary" />}
    </div>
  )

  return (
    <li className={isUnread ? "bg-primary/[0.03]" : ""}>
      {href ? (
        <Link href={href} onClick={onOpen} className="block transition-colors hover:bg-muted/40">{body}</Link>
      ) : (
        // A workspace invitation has nowhere to go — the recipient is already
        // here — so it is only dismissible.
        <button type="button" onClick={onOpen} className="block w-full transition-colors hover:bg-muted/40">{body}</button>
      )}
    </li>
  )
}
