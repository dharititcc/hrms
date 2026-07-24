import Link from "next/link"
import { cn } from "@workspace/ui/lib/utils"

export function AuthFormShell({ title, description, footer, children, className }: { title: string; description: string; footer?: React.ReactNode; children: React.ReactNode; className?: string }) {
  return <div className={cn("animate-in fade-in slide-in-from-bottom-2 duration-500", className)}><div className="mb-8"><h2 className="text-2xl font-semibold tracking-tight">{title}</h2><p className="mt-2 text-sm leading-6 text-muted-foreground">{description}</p></div>{children}{footer && <div className="mt-8 text-center text-sm text-muted-foreground">{footer}</div>}</div>
}

export function AuthLink({ href, children }: { href: string; children: React.ReactNode }) { return <Link href={href} className="font-medium text-foreground underline underline-offset-4 transition-colors hover:text-muted-foreground">{children}</Link> }
