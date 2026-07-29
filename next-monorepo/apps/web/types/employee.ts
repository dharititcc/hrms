export type EmployeeRole = "admin" | "manager" | "member"
export type EmployeeStatus = "active" | "inactive"

export type Employee = {
  id: number
  name: string
  email: string
  phone: string | null
  role: EmployeeRole
  status: EmployeeStatus
  /** True once invited and linked to a login account. */
  has_account: boolean
  created_at: string
  updated_at: string
}

export type EmployeeListResponse = {
  data: Employee[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export type EmployeeInput = { name: string; email: string; phone?: string | null; role: EmployeeRole; status: EmployeeStatus }
