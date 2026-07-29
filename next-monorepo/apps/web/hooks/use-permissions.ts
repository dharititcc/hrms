"use client"

import { useQuery } from "@tanstack/react-query"
import { apiClient } from "@/lib/api-client"
import type { Permission, WorkspacePermissions } from "@/types/permission"

/**
 * The caller's permissions, used to hide actions that would be refused.
 *
 * This is presentation only — the API is still the enforcement point. Hiding a
 * button stops a pointless 403; it does not make anything safe on its own.
 */
export function usePermissions() {
  const { data, isLoading } = useQuery({
    queryKey: ["permissions"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: WorkspacePermissions }>("/auth/permissions")
      return data.data
    },
    staleTime: 5 * 60 * 1000,
  })

  return {
    role: data?.role,
    isWorkspaceOwner: data?.is_workspace_owner ?? false,
    staffId: data?.staff_id ?? null,
    isLoading,
    /**
     * False until permissions have loaded, so a control never appears and then
     * vanishes — briefly missing is better than briefly wrong.
     */
    can: (permission: Permission) => data?.permissions.includes(permission) ?? false,
  }
}
