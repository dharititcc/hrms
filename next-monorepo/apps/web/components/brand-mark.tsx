import { cn } from "@workspace/ui/lib/utils"

export function BrandMark({ className }: { className?: string }) {
  return <div className={cn("flex items-center gap-2.5", className)}><div className="grid size-8 place-items-center rounded-lg bg-primary text-primary-foreground shadow-sm"><span className="text-sm font-semibold">N</span></div><span className="text-sm font-semibold tracking-tight">Nucleus</span></div>
}
