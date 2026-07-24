import { ResetPasswordForm } from "@/features/auth/simple-auth-form"
import { Suspense } from "react"
import type { Metadata } from "next"

export const metadata: Metadata = { title: "Reset password" }

export default function Page() { return <Suspense fallback={<div className="grid min-h-96 place-items-center text-sm text-muted-foreground">Loading reset form…</div>}><ResetPasswordForm /></Suspense> }
