"use client"

import { Switch } from "react-aria-components"
import { cn } from "@workspace/ui/lib/utils"

export function StatusSwitch({ value, onChange }: { value: "active" | "inactive"; onChange: (value: "active" | "inactive") => void }) {
  const isActive = value === "active"
  return <Switch isSelected={isActive} onChange={(selected) => onChange(selected ? "active" : "inactive")} className="group flex cursor-pointer items-center gap-3 outline-none"><span className={cn("relative h-6 w-11 rounded-full p-0.5 transition-colors focus-visible:ring-3 focus-visible:ring-ring/30", isActive ? "bg-emerald-500" : "bg-muted-foreground/30")}><span className={cn("block size-5 rounded-full bg-white shadow-sm transition-transform", isActive ? "translate-x-5" : "translate-x-0")} /></span><span className="grid gap-0.5"><span className="text-sm font-medium">{isActive ? "Active" : "Inactive"}</span><span className="text-xs text-muted-foreground">{isActive ? "Can access the workspace" : "Access is paused"}</span></span></Switch>
}
