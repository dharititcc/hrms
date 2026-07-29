"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { payrollService } from "@/services/payroll-service"
import type { GeneratePayrollInput, RecordPaymentInput, SalaryAssignmentInput } from "@/types/payroll"

export function usePayrollRuns(page = 1) {
  return useQuery({
    queryKey: ["payroll-runs", page],
    queryFn: () => payrollService.runs(page),
    placeholderData: (previous) => previous,
  })
}

export function usePayrollRun(id: number | null) {
  return useQuery({
    queryKey: ["payroll-run", id],
    queryFn: () => payrollService.run(id as number),
    enabled: id !== null,
  })
}

export function usePayrollRunMutations() {
  const queryClient = useQueryClient()
  // A run's slips are what the payslip list reads, so both change together.
  const refresh = (id?: number) => {
    queryClient.invalidateQueries({ queryKey: ["payroll-runs"] })
    queryClient.invalidateQueries({ queryKey: ["payroll"] })
    if (id !== undefined) queryClient.invalidateQueries({ queryKey: ["payroll-run", id] })
  }

  const generate = useMutation({
    mutationFn: (input: GeneratePayrollInput) => payrollService.generateRun(input),
    onSuccess: (run) => refresh(run.id),
  })
  const regenerate = useMutation({
    mutationFn: ({ id, input }: { id: number; input: GeneratePayrollInput }) => payrollService.regenerateRun(id, input),
    onSuccess: (run) => refresh(run.id),
  })
  const remove = useMutation({
    mutationFn: (id: number) => payrollService.removeRun(id),
    onSuccess: () => refresh(),
  })

  const submit = useMutation({ mutationFn: (id: number) => payrollService.submitRun(id), onSuccess: (run) => refresh(run.id) })
  const approve = useMutation({ mutationFn: (id: number) => payrollService.approveRun(id), onSuccess: (run) => refresh(run.id) })
  const cancel = useMutation({ mutationFn: (id: number) => payrollService.cancelRun(id), onSuccess: (run) => refresh(run.id) })

  return { generate, regenerate, remove, submit, approve, cancel }
}

export function useSlipPayments(slipId: number | null) {
  return useQuery({
    queryKey: ["slip-payments", slipId],
    queryFn: () => payrollService.payments(slipId as number),
    enabled: slipId !== null,
  })
}

export function useSlipPaymentMutations(slipId: number, runId: number) {
  const queryClient = useQueryClient()
  // A payment moves the slip and can settle the run, so both are restated.
  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ["slip-payments", slipId] })
    queryClient.invalidateQueries({ queryKey: ["payroll-run", runId] })
    queryClient.invalidateQueries({ queryKey: ["payroll-runs"] })
    queryClient.invalidateQueries({ queryKey: ["payroll"] })
  }

  const record = useMutation({ mutationFn: (input: RecordPaymentInput) => payrollService.recordPayment(slipId, input), onSuccess: refresh })
  const reverse = useMutation({ mutationFn: (id: number) => payrollService.reversePayment(id), onSuccess: refresh })

  return { record, reverse }
}

export function useSalary(staffId: number | null) {
  const current = useQuery({
    queryKey: ["salary", staffId],
    queryFn: () => payrollService.currentSalary(staffId as number),
    enabled: staffId !== null,
  })
  const history = useQuery({
    queryKey: ["salary-history", staffId],
    queryFn: () => payrollService.salaryHistory(staffId as number),
    enabled: staffId !== null,
  })

  return { current, history }
}

export function useSalaryMutations(staffId: number | null) {
  const queryClient = useQueryClient()
  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ["salary", staffId] })
    queryClient.invalidateQueries({ queryKey: ["salary-history", staffId] })
    // Assignment counts decide whether a structure can be deleted.
    queryClient.invalidateQueries({ queryKey: ["salary-structures"] })
  }

  const assign = useMutation({
    mutationFn: (input: SalaryAssignmentInput) => payrollService.assignSalary(staffId as number, input),
    onSuccess: refresh,
  })
  const end = useMutation({
    mutationFn: (effectiveTo: string) => payrollService.endSalary(staffId as number, effectiveTo),
    onSuccess: refresh,
  })

  return { assign, end }
}
