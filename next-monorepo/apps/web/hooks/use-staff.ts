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

  // Inviting changes who can be assigned work or invited to meetings, so the
  // workspace-user list must be refetched too.
  const refreshWithUsers = () => {
    refresh()
    queryClient.invalidateQueries({ queryKey: ["workspace-users"] })
  }

  const invite = useMutation({ mutationFn: (id: number) => staffService.invite(id), onSuccess: refreshWithUsers })
  const revokeAccess = useMutation({ mutationFn: (id: number) => staffService.revokeAccess(id), onSuccess: refreshWithUsers })

  return { create, update, remove, invite, revokeAccess }
}
