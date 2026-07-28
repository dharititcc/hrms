export type DashboardStats = {
  tasks: {
    total: number
    pending: number
    in_progress: number
    review: number
    completed: number
    overdue: number
    due_today: number
    /** Open tasks assigned to the signed-in user. */
    mine: number
    unassigned: number
  }
  meetings: {
    today: number
    this_week: number
    upcoming: number
    awaiting_my_reply: number
  }
  people: { staff: number; active: number; with_accounts: number }
  projects: { total: number; active: number }
  recent_activity: {
    id: number
    action: string
    entity: string
    entity_id: number | null
    user_name: string | null
    created_at: string
  }[]
}
