import { RegisterForm } from "@/features/auth/register-form"
import type { Metadata } from "next"

export const metadata: Metadata = { title: "Create account" }

export default function Page() { return <RegisterForm /> }
