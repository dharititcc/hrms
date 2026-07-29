"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { employeeService, type EmployeeFilters } from "@/services/employee-service"
import type { EmployeeInput } from "@/types/employee"

export function useEmployees(filters: EmployeeFilters) {
  return useQuery({ queryKey: ["employee", filters], queryFn: () => employeeService.list(filters) })
}

export function useEmployeeMutations() {
  const queryClient = useQueryClient()
  const refresh = () => queryClient.invalidateQueries({ queryKey: ["employee"] })
  const create = useMutation({ mutationFn: (input: EmployeeInput) => employeeService.create(input), onSuccess: refresh })
  const update = useMutation({ mutationFn: ({ id, input }: { id: number; input: EmployeeInput }) => employeeService.update(id, input), onSuccess: refresh })
  const remove = useMutation({ mutationFn: (id: number) => employeeService.remove(id), onSuccess: refresh })

  // Inviting changes who can be assigned work or invited to meetings, so the
  // workspace-user list must be refetched too.
  const refreshWithUsers = () => {
    refresh()
    queryClient.invalidateQueries({ queryKey: ["workspace-users"] })
  }

  const invite = useMutation({ mutationFn: (id: number) => employeeService.invite(id), onSuccess: refreshWithUsers })
  const revokeAccess = useMutation({ mutationFn: (id: number) => employeeService.revokeAccess(id), onSuccess: refreshWithUsers })

  return { create, update, remove, invite, revokeAccess }
}
