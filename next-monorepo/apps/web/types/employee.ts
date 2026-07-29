export type EmployeeRole = "admin" | "manager" | "employee"
export type EmployeeStatus = "active" | "inactive"

export type Employee = {
  id: number
  name: string
  email: string
  phone: string | null
  role: EmployeeRole
  status: EmployeeStatus
  /** The office they normally work from, null for remote and field workers. */
  attendance_location_id: number | null
  office_name?: string | null
  /** True once invited and linked to a login account. */
  has_account: boolean
  created_at: string
  updated_at: string
}

export type EmployeeListResponse = {
  data: Employee[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export type EmployeeInput = {
  name: string
  email: string
  phone?: string | null
  role: EmployeeRole
  status: EmployeeStatus
  attendance_location_id?: number | null
}
