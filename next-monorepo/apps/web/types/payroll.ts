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
