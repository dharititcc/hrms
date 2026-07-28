export type StaffRole = "admin" | "manager" | "member"
export type StaffStatus = "active" | "inactive"

export type Staff = {
  id: number
  name: string
  email: string
  phone: string | null
  role: StaffRole
  status: StaffStatus
  /** True once invited and linked to a login account. */
  has_account: boolean
  created_at: string
  updated_at: string
}

export type StaffListResponse = {
  data: Staff[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export type StaffInput = { name: string; email: string; phone?: string | null; role: StaffRole; status: StaffStatus }
