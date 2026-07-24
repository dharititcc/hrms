"use client"

import { useQuery } from "@tanstack/react-query"
import { authService } from "@/services/auth-service"
import { useAuthStore } from "@/store/auth-store"

export function useCurrentUser() {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated)
  return useQuery({ queryKey: ["current-user"], queryFn: authService.me, enabled: isAuthenticated, staleTime: 5 * 60_000 })
}
