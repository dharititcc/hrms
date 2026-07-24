import { VerificationStatus } from "@/features/auth/verification-status"
import { Suspense } from "react"
import type { Metadata } from "next"

export const metadata: Metadata = { title: "Email verification" }

export default function Page() { return <Suspense fallback={<div className="grid min-h-96 place-items-center text-sm text-muted-foreground">Checking verification…</div>}><VerificationStatus /></Suspense> }
