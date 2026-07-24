import { LoginForm } from "@/features/auth/login-form"
import type { Metadata } from "next"

export const metadata: Metadata = { title: "Sign in" }

export default function Page() { return <LoginForm /> }
