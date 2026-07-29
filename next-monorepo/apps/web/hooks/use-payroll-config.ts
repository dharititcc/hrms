"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { payrollService } from "@/services/payroll-service"
import type { SalaryComponentInput, SalaryStructureInput } from "@/types/payroll"

export function useSalaryStructures(enabled = true) {
  return useQuery({
    queryKey: ["salary-structures"],
    queryFn: () => payrollService.structures(),
    enabled,
  })
}

export function useSalaryComponents(filters: { salary_structure_id?: number; global_only?: boolean }, enabled = true) {
  return useQuery({
    queryKey: ["salary-components", filters],
    queryFn: () => payrollService.components(filters),
    enabled,
  })
}

export function useSalaryStructureMutations() {
  const queryClient = useQueryClient()
  // Components carry a structure name and structures carry a component count,
  // so either changing invalidates both.
  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ["salary-structures"] })
    queryClient.invalidateQueries({ queryKey: ["salary-components"] })
  }

  const create = useMutation({ mutationFn: (input: SalaryStructureInput) => payrollService.createStructure(input), onSuccess: refresh })
  const update = useMutation({
    mutationFn: ({ id, input }: { id: number; input: SalaryStructureInput }) => payrollService.updateStructure(id, input),
    onSuccess: refresh,
  })
  const remove = useMutation({ mutationFn: (id: number) => payrollService.removeStructure(id), onSuccess: refresh })

  return { create, update, remove }
}

export function useSalaryComponentMutations() {
  const queryClient = useQueryClient()
  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ["salary-components"] })
    queryClient.invalidateQueries({ queryKey: ["salary-structures"] })
  }

  const create = useMutation({ mutationFn: (input: SalaryComponentInput) => payrollService.createComponent(input), onSuccess: refresh })
  const update = useMutation({
    mutationFn: ({ id, input }: { id: number; input: SalaryComponentInput }) => payrollService.updateComponent(id, input),
    onSuccess: refresh,
  })
  const remove = useMutation({ mutationFn: (id: number) => payrollService.removeComponent(id), onSuccess: refresh })

  return { create, update, remove }
}
