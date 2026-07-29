import { apiClient } from "@/lib/api-client"
import type {
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
}

/** Percentages read as rates; fixed amounts read as money. */
export function formatComponentValue(component: SalaryComponent, currencySymbol: string): string {
  const value = Number(component.value)

  if (component.calculation === "manual") return "Per payslip"
  if (component.calculation === "fixed") return `${currencySymbol}${value.toFixed(2)}`

  return `${value}%`
}
