"use client"

import { CheckCircle2, Mail, ShieldCheck } from "lucide-react"
import { Button } from "@workspace/ui/components/button"
import { useToast } from "@/providers/toast-provider"
import { useCurrentUser } from "@/hooks/use-current-user"
import { authService } from "@/services/auth-service"
import { getApiErrorMessage } from "@/lib/api-error"

export function OverviewModule() {
  const { toast } = useToast(); const { data: user } = useCurrentUser()
  const resend = async () => { try { await authService.sendVerificationNotification(); toast({ tone: "success", title: "Verification email sent", description: "Check your inbox for the latest link." }) } catch (error) { toast({ tone: "error", title: "Unable to send email", description: getApiErrorMessage(error) }) } }
  const verified = Boolean(user?.email_verified_at)
  return <div className="mx-auto grid max-w-6xl gap-6"><div><p className="text-sm font-medium text-muted-foreground">Workspace</p><h1 className="mt-2 text-2xl font-semibold tracking-tight">Good to see you.</h1><p className="mt-2 text-sm text-muted-foreground">Here’s a quick look at your account readiness.</p></div><div className="grid gap-4 md:grid-cols-3"><div className="rounded-2xl border bg-background p-5"><Mail className="size-5 text-muted-foreground"/><p className="mt-6 text-sm text-muted-foreground">Signed in as</p><p className="mt-1 truncate font-medium">{user?.email ?? "Loading account…"}</p></div><div className="rounded-2xl border bg-background p-5"><ShieldCheck className="size-5 text-muted-foreground"/><p className="mt-6 text-sm text-muted-foreground">Email status</p><p className="mt-1 font-medium">{verified ? "Verified" : "Verification required"}</p></div><div className="rounded-2xl border bg-background p-5"><CheckCircle2 className="size-5 text-muted-foreground"/><p className="mt-6 text-sm text-muted-foreground">Workspace status</p><p className="mt-1 font-medium">Ready to build</p></div></div>{!verified && <div className="flex flex-col gap-4 rounded-2xl border border-amber-500/30 bg-amber-500/5 p-5 sm:flex-row sm:items-center sm:justify-between"><div><p className="font-medium">Verify your email address</p><p className="mt-1 text-sm text-muted-foreground">Confirm your email to keep your account secure.</p></div><Button variant="outline" onPress={resend}>Resend verification email</Button></div>}</div>
}
