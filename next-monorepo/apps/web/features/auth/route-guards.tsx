"use client"

import { useEffect } from "react"
import { usePathname, useRouter } from "next/navigation"
import { useAuthStore } from "@/store/auth-store"

export function PublicRoute({ children }: { children: React.ReactNode }) {
  const router = useRouter(); const pathname = usePathname(); const { isInitialized, isAuthenticated } = useAuthStore()
  useEffect(() => { if (isInitialized && isAuthenticated) router.replace("/") }, [isAuthenticated, isInitialized, pathname, router])
  if (!isInitialized || isAuthenticated) return <div className="grid min-h-svh place-items-center text-sm text-muted-foreground">Loading…</div>
  return children
}

export function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const router = useRouter(); const { isInitialized, isAuthenticated } = useAuthStore()
  useEffect(() => { if (isInitialized && !isAuthenticated) router.replace("/login") }, [isAuthenticated, isInitialized, router])
  if (!isInitialized || !isAuthenticated) return <div className="grid min-h-svh place-items-center text-sm text-muted-foreground">Loading…</div>
  return children
}
