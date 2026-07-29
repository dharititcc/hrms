/**
 * Short class name rather than the PHP namespace, as the API sends it.
 * Unknown values are tolerated: a notification added server-side should still
 * render, just without a bespoke icon or link.
 */
export type NotificationType =
  | "TaskAssignedNotification"
  | "TaskDueReminderNotification"
  | "TaskMentionNotification"
  | "MeetingInvitationNotification"
  | "MeetingChangedNotification"
  | "MeetingReminderNotification"
  | "EmployeeInvitationNotification"
  | (string & {})

/** Every notification carries a title and message; the rest varies by type. */
export type NotificationPayload = {
  title: string
  message: string
  task_id?: number
  meeting_id?: number
  comment_id?: number
  due_date?: string | null
  starts_at?: string | null
  overdue?: boolean
  priority?: string
  excerpt?: string
  workspace?: string
}

export type AppNotification = {
  id: string
  type: NotificationType
  data: NotificationPayload
  read_at: string | null
  created_at: string
}

export type NotificationListResponse = {
  data: AppNotification[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}
