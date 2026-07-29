"use client"

import Link from "next/link"
import { CheckCircle2, CircleAlert } from "lucide-react"
import { AuthFormShell } from "@/features/auth/auth-form-shell"
import { useSearchParams } from "next/navigation"

export function VerificationStatus() {
  const success = useSearchParams().get("status") === "success"
  return <AuthFormShell title={success ? "Email verified" : "Verification link expired"} description={success ? "Your email address is verified. You can now continue to your workspace." : "This verification link is invalid or has expired. Sign in to request a new one."}><div className="grid gap-5"><div className="grid place-items-center rounded-xl border bg-muted/30 p-8">{success ? <CheckCircle2 className="size-12 text-emerald-500" /> : <CircleAlert className="size-12 text-amber-500" />}</div><Link href={success ? "/" : "/login"} className="inline-flex h-11 items-center justify-center rounded-lg bg-primary px-4 text-sm font-medium text-primary-foreground">{success ? "Continue to workspace" : "Back to sign in"}</Link></div></AuthFormShell>
}
