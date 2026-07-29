export type SalaryComponentType = "earning" | "deduction" | "employer_contribution"
export type SalaryCalculation = "fixed" | "percent_of_basic" | "percent_of_gross" | "manual"

export type SalaryComponent = {
  id: number
  salary_structure_id: number | null
  structure_name?: string | null
  code: string
  name: string
  type: SalaryComponentType
  calculation: SalaryCalculation
  /** Decimal string from the API; a percentage when the calculation is derived. */
  value: string
  is_taxable: boolean
  is_statutory: boolean
  is_active: boolean
  country: string | null
  sort_order: number
  created_at: string
}

export type SalaryStructure = {
  id: number
  name: string
  description: string | null
  country: string
  country_label: string
  currency_code: string
  currency_symbol: string
  is_active: boolean
  components?: SalaryComponent[]
  components_count?: number
  /** Non-zero means deleting is refused: historic slips reference it. */
  assignments_count?: number
  created_at: string
}

export type PayrollCountryOption = {
  value: string
  label: string
  currency_code: string
  currency_symbol: string
  /** How many statutory deductions the country defines, if any. */
  statutory_count: number
}

export type SalaryStructureListResponse = {
  data: SalaryStructure[]
  meta: { countries: PayrollCountryOption[] }
}

export type SalaryComponentListResponse = {
  data: SalaryComponent[]
  meta: { types: SalaryComponentType[]; calculations: SalaryCalculation[] }
}

export type SalaryStructureInput = {
  name: string
  description?: string | null
  country: string
  currency_code?: string | null
  is_active?: boolean
  /** Only honoured on create; re-seeding would duplicate corrected rates. */
  seed_statutory?: boolean
}

export type SalaryComponentInput = {
  salary_structure_id?: number | null
  code: string
  name: string
  type: SalaryComponentType
  calculation: SalaryCalculation
  value: number
  is_taxable?: boolean
  is_statutory?: boolean
  country?: string | null
  sort_order?: number
  is_active?: boolean
}

export type PayrollRunStatus = "draft" | "pending_approval" | "approved" | "paid" | "cancelled"

export type SalarySlipLine = {
  type: SalaryComponentType
  code: string
  name: string
  amount: string
  is_statutory: boolean
}

/** Frozen figures: nothing reads back through the structure once issued. */
export type SalarySlip = {
  id: number
  slip_number: string
  payroll_run_id: number
  period?: string | null
  period_start?: string | null
  period_end?: string | null
  staff_id: number
  staff_name?: string | null
  country: string
  currency_code: string
  currency_symbol: string
  basic_salary: string
  total_earnings: string
  total_deductions: string
  employer_contributions: string
  gross_salary: string
  net_salary: string
  paid_amount: string
  outstanding: number
  status: string
  lines?: SalarySlipLine[]
  created_at?: string
}

export type PayrollRun = {
  id: number
  title: string
  country: string
  currency_code: string
  currency_symbol: string
  period_start: string
  period_end: string
  pay_date: string | null
  status: PayrollRunStatus
  /** Draft runs can be recalculated; approved ones are committed. */
  is_editable: boolean
  is_locked: boolean
  slip_count: number
  total_earnings: string
  total_deductions: string
  total_net: string
  generated_by: number | null
  approved_by: number | null
  approved_at: string | null
  notes: string | null
  slips?: SalarySlip[]
  created_at: string
}

export type PayrollRunListResponse = {
  data: PayrollRun[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export type GeneratePayrollInput = {
  title: string
  country: string
  period_start: string
  period_end: string
  pay_date?: string | null
  notes?: string | null
  /** staff id => component code => amount, for progressive taxes. */
  manual_amounts?: Record<number, Record<string, number>>
}

export type SalaryAssignmentStatus = "active" | "superseded" | "ended"

export type SalaryAssignment = {
  id: number
  staff_id: number
  staff_name?: string | null
  salary_structure_id: number | null
  structure_name?: string | null
  basic_salary: string
  currency_code: string
  currency_symbol: string
  country: string
  effective_from: string
  effective_to: string | null
  status: SalaryAssignmentStatus
  revision_reason: string | null
  supersedes_id: number | null
  component_values?: { salary_component_id: number; code?: string | null; name?: string | null; value: string }[]
  created_at: string
}

export type SalaryAssignmentInput = {
  basic_salary: number
  country: string
  currency_code?: string | null
  effective_from: string
  revision_reason?: string | null
  salary_structure_id?: number | null
  /** Per-employee overrides, keyed by component id. */
  component_values?: Record<number, number>
}

export const payrollRunStatusLabels: Record<PayrollRunStatus, string> = {
  draft: "Draft",
  pending_approval: "Pending approval",
  approved: "Approved",
  paid: "Paid",
  cancelled: "Cancelled",
}

export const payrollRunStatusStyles: Record<PayrollRunStatus, string> = {
  draft: "bg-muted text-muted-foreground",
  pending_approval: "bg-amber-500/10 text-amber-600 dark:text-amber-400",
  approved: "bg-sky-500/10 text-sky-600 dark:text-sky-400",
  paid: "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400",
  cancelled: "bg-destructive/10 text-destructive",
}

export const salaryAssignmentStatusLabels: Record<SalaryAssignmentStatus, string> = {
  active: "Current",
  superseded: "Superseded",
  ended: "Ended",
}

export const salaryComponentTypeLabels: Record<SalaryComponentType, string> = {
  earning: "Earning",
  deduction: "Deduction",
  employer_contribution: "Employer contribution",
}

export const salaryCalculationLabels: Record<SalaryCalculation, string> = {
  fixed: "Fixed amount",
  percent_of_basic: "% of basic",
  percent_of_gross: "% of gross",
  manual: "Entered per payslip",
}
