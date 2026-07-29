/**
 * Sections are optional: the API omits any module the caller lacks permission
 * for, so a Client receives no people or project figures at all.
 */
export type DashboardStats = {
  tasks?: {
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
  meetings?: {
    today: number
    this_week: number
    upcoming: number
    awaiting_my_reply: number
  }
  /**
   * The caller's own day is always present; the team figures only for someone
   * with attendance.view-all, so they are separately optional.
   */
  attendance?: {
    checked_in: boolean
    checked_out: boolean
    my_attendance_id: number | null
    my_status: string | null
    my_check_in: string | null
    active_employees?: number
    present_today?: number
    late_today?: number
    on_leave_today?: number
    /** Nobody recorded anything for them, which is not the same as absent. */
    not_recorded?: number
    awaiting_approval?: number
  }
  leave?: {
    my_pending: number
    balances: { id: number; name: string; entitlement: number; taken: number; remaining: number }[]
    awaiting_approval?: number
  }
  payroll?: {
    /** Only from an approved run: a draft's figures can still change. */
    latest_slip: {
      id: number
      slip_number: string
      period: string | null
      net_salary: string
      currency_symbol: string
      status: string
    } | null
    draft_runs?: number
    awaiting_approval?: number
    awaiting_payment?: number
  }
  people?: { employees: number; active: number; with_accounts: number }
  projects?: { total: number; active: number }
  recent_activity?: {
    id: number
    action: string
    entity: string
    entity_id: number | null
    user_name: string | null
    created_at: string
  }[]
}
