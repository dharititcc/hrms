import { ForgotPasswordForm } from "@/features/auth/simple-auth-form"
import type { Metadata } from "next"

export const metadata: Metadata = { title: "Forgot password" }

export default function Page() { return <ForgotPasswordForm /> }
