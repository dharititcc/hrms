"use client"

import { createContext, useCallback, useContext, useMemo, useState } from "react"
import { X } from "lucide-react"
import { Button } from "@workspace/ui/components/button"

type Toast = { id: number; title: string; description?: string; tone: "success" | "error" }
type ToastContextValue = { toast: (toast: Omit<Toast, "id">) => void }
const ToastContext = createContext<ToastContextValue | null>(null)

export function ToastProvider({ children }: { children: React.ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([])
  const toast = useCallback((input: Omit<Toast, "id">) => {
    const id = Date.now()
    setToasts((current) => [...current, { ...input, id }])
    window.setTimeout(() => setToasts((current) => current.filter((item) => item.id !== id)), 4500)
  }, [])
  const value = useMemo(() => ({ toast }), [toast])
  return <ToastContext.Provider value={value}>{children}<div className="fixed right-4 bottom-4 z-50 grid w-[min(24rem,calc(100vw-2rem))] gap-3" aria-live="polite">{toasts.map((item) => <div key={item.id} className="flex items-start gap-3 rounded-xl border bg-card p-4 shadow-xl"><div className="min-w-0 flex-1"><p className={item.tone === "error" ? "text-destructive" : "text-foreground"}>{item.title}</p>{item.description && <p className="mt-1 text-sm text-muted-foreground">{item.description}</p>}</div><Button aria-label="Dismiss notification" variant="ghost" size="icon-xs" onPress={() => setToasts((current) => current.filter((toast) => toast.id !== item.id))}><X /></Button></div>)}</div></ToastContext.Provider>
}

export function useToast() {
  const context = useContext(ToastContext)
  if (!context) throw new Error("useToast must be used within ToastProvider")
  return context
}
