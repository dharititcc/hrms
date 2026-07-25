export type Attendance = { id: number; staff_id: number; staff_name: string; work_date: string; check_in: string | null; check_out: string | null; status: "present" | "late" | "absent"; notes: string | null }
export type LeaveType = { id: number; name: string; days_per_year: number; is_active: boolean }
export type LeaveRequest = { id: number; staff_id: number; staff_name: string; leave_type_id: number; leave_type: string; start_date: string; end_date: string; reason: string | null; status: "pending" | "approved" | "rejected" | "cancelled" }
