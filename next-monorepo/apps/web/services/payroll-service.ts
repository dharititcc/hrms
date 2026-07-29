import { apiClient } from "@/lib/api-client"
import type {
  GeneratePayrollInput, PayrollRun, PayrollRunListResponse,
  SalaryAssignment, SalaryAssignmentInput,
  SalaryComponent, SalaryComponentInput, SalaryComponentListResponse,
  SalaryStructure, SalaryStructureInput, SalaryStructureListResponse,
} from "@/types/payroll"

/** Payroll configuration: the templates and lines a payslip is built from. */
export const payrollService = {
  async structures() {
    const { data } = await apiClient.get<SalaryStructureListResponse>("/auth/salary-structures")
    return data
  },
  async structure(id: number) {
    const { data } = await apiClient.get<{ data: SalaryStructure }>(`/auth/salary-structures/${id}`)
    return data.data
  },
  async createStructure(input: SalaryStructureInput) {
    const { data } = await apiClient.post<{ data: SalaryStructure }>("/auth/salary-structures", input)
    return data.data
  },
  async updateStructure(id: number, input: SalaryStructureInput) {
    const { data } = await apiClient.put<{ data: SalaryStructure }>(`/auth/salary-structures/${id}`, input)
    return data.data
  },
  async removeStructure(id: number) {
    await apiClient.delete(`/auth/salary-structures/${id}`)
  },

  async components(filters: { salary_structure_id?: number; global_only?: boolean } = {}) {
    const { data } = await apiClient.get<SalaryComponentListResponse>("/auth/salary-components", { params: filters })
    return data
  },
  async createComponent(input: SalaryComponentInput) {
    const { data } = await apiClient.post<{ data: SalaryComponent }>("/auth/salary-components", input)
    return data.data
  },
  async updateComponent(id: number, input: SalaryComponentInput) {
    const { data } = await apiClient.put<{ data: SalaryComponent }>(`/auth/salary-components/${id}`, input)
    return data.data
  },
  async removeComponent(id: number) {
    await apiClient.delete(`/auth/salary-components/${id}`)
  },

  // An employee's salary, and the revisions behind it.
  async currentSalary(staffId: number) {
    const { data } = await apiClient.get<{ data: SalaryAssignment | null }>(`/auth/staff/${staffId}/salary`)
    return data.data
  },
  async salaryHistory(staffId: number) {
    const { data } = await apiClient.get<{ data: SalaryAssignment[] }>(`/auth/staff/${staffId}/salary/history`)
    return data.data
  },
  async assignSalary(staffId: number, input: SalaryAssignmentInput) {
    const { data } = await apiClient.post<{ data: SalaryAssignment }>(`/auth/staff/${staffId}/salary`, input)
    return data.data
  },
  async endSalary(staffId: number, effectiveTo: string) {
    const { data } = await apiClient.patch<{ data: SalaryAssignment }>(`/auth/staff/${staffId}/salary/end`, { effective_to: effectiveTo })
    return data.data
  },

  // Payroll runs and the slips they produce.
  async runs(page = 1) {
    const { data } = await apiClient.get<PayrollRunListResponse>("/auth/payroll-runs", { params: { page } })
    return data
  },
  async run(id: number) {
    const { data } = await apiClient.get<{ data: PayrollRun }>(`/auth/payroll-runs/${id}`)
    return data.data
  },
  async generateRun(input: GeneratePayrollInput) {
    const { data } = await apiClient.post<{ data: PayrollRun }>("/auth/payroll-runs", input)
    return data.data
  },
  async regenerateRun(id: number, input: GeneratePayrollInput) {
    const { data } = await apiClient.post<{ data: PayrollRun }>(`/auth/payroll-runs/${id}/regenerate`, input)
    return data.data
  },
  async removeRun(id: number) {
    await apiClient.delete(`/auth/payroll-runs/${id}`)
  },
}

/**
 * Lines whose amount is entered per payslip rather than derived.
 *
 * Progressive taxes land here, and they generate as zero until someone fills
 * them in — so surfacing them is what stops a run silently under-deducting.
 */
export function manualLines(slip: { lines?: { code: string; name: string; amount: string }[] }, manualCodes: Set<string>) {
  return (slip.lines ?? []).filter((line) => manualCodes.has(line.code))
}

/** Percentages read as rates; fixed amounts read as money. */
export function formatComponentValue(component: SalaryComponent, currencySymbol: string): string {
  const value = Number(component.value)

  if (component.calculation === "manual") return "Per payslip"
  if (component.calculation === "fixed") return `${currencySymbol}${value.toFixed(2)}`

  return `${value}%`
}
