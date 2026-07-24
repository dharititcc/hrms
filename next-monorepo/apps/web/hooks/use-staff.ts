"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { staffService, type StaffFilters } from "@/services/staff-service"
import type { StaffInput } from "@/types/staff"

export function useStaff(filters: StaffFilters) {
  return useQuery({ queryKey: ["staff", filters], queryFn: () => staffService.list(filters) })
}

export function useStaffMutations() {
  const queryClient = useQueryClient()
  const refresh = () => queryClient.invalidateQueries({ queryKey: ["staff"] })
  const create = useMutation({ mutationFn: (input: StaffInput) => staffService.create(input), onSuccess: refresh })
  const update = useMutation({ mutationFn: ({ id, input }: { id: number; input: StaffInput }) => staffService.update(id, input), onSuccess: refresh })
  const remove = useMutation({ mutationFn: (id: number) => staffService.remove(id), onSuccess: refresh })
  return { create, update, remove }
}
