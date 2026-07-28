"use client"

import { useQuery } from "@tanstack/react-query"
import { apiClient } from "@/lib/api-client"
import type { DashboardStats } from "@/types/dashboard"

export function useDashboardStats() {
  return useQuery({
    queryKey: ["dashboard-stats"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: DashboardStats }>("/auth/dashboard/stats")
      return data.data
    },
    // Counts drift as work happens elsewhere; a short staleness keeps the
    // dashboard honest without hammering the endpoint.
    staleTime: 60_000,
  })
}
