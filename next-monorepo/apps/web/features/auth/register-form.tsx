"use client"

import { useForm } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { Button } from "@workspace/ui/components/button"
import { FormField } from "@/components/ui/form-fields"
import { AuthFormShell, AuthLink } from "@/features/auth/auth-form-shell"
import { registerSchema, type RegisterValues } from "@/features/auth/schemas"
import { useAuthStore } from "@/store/auth-store"
import { useToast } from "@/providers/toast-provider"
import { getApiErrorMessage } from "@/lib/api-error"
import { useRouter } from "next/navigation"

export function RegisterForm() {
  const router = useRouter(); const { toast } = useToast(); const registerUser = useAuthStore((state) => state.register); const isLoading = useAuthStore((state) => state.isLoading)
  const { register, handleSubmit, formState: { errors } } = useForm<RegisterValues>({ resolver: zodResolver(registerSchema), defaultValues: { name: "", email: "", password: "", password_confirmation: "" } })
  const onSubmit = async (values: RegisterValues) => { try { await registerUser(values); toast({ tone: "success", title: "Account created", description: "Welcome to HRMS." }); router.replace("/dashboard") } catch (error) { toast({ tone: "error", title: "Unable to create account", description: getApiErrorMessage(error) }) } }
  return <AuthFormShell title="Create your account" description="Start building a calmer, more focused workspace." footer={<>Already have an account? <AuthLink href="/login">Sign in</AuthLink></>}><form className="grid gap-5" onSubmit={handleSubmit(onSubmit)} noValidate><FormField label="Full name" autoComplete="name" placeholder="Ada Lovelace" error={errors.name?.message} {...register("name")} /><FormField label="Email address" type="email" autoComplete="email" placeholder="you@company.com" error={errors.email?.message} {...register("email")} /><FormField label="Password" type="password" autoComplete="new-password" placeholder="At least 8 characters" error={errors.password?.message} {...register("password")} /><FormField label="Confirm password" type="password" autoComplete="new-password" placeholder="Repeat your password" error={errors.password_confirmation?.message} {...register("password_confirmation")} /><p className="text-xs leading-5 text-muted-foreground">By continuing, you agree to our Terms of Service and Privacy Policy.</p><Button type="submit" className="h-11 w-full" isDisabled={isLoading}>{isLoading ? "Creating account…" : "Create account"}</Button></form></AuthFormShell>
}
