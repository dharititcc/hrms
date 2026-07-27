"use client"

import Link from "next/link"
import { Controller, useForm } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { Checkbox } from "react-aria-components"
import { Button } from "@workspace/ui/components/button"
import { FormField } from "@/components/ui/form-fields"
import { AuthFormShell, AuthLink } from "@/features/auth/auth-form-shell"
import { loginSchema, type LoginValues } from "@/features/auth/schemas"
import { getApiErrorMessage } from "@/lib/api-error"
import { useAuthStore } from "@/store/auth-store"
import { useToast } from "@/providers/toast-provider"
import { useRouter } from "next/navigation"

export function LoginForm() {
  const router = useRouter(); const { toast } = useToast(); const login = useAuthStore((state) => state.login); const isLoading = useAuthStore((state) => state.isLoading)
  const { register, control, handleSubmit, formState: { errors } } = useForm<LoginValues>({ resolver: zodResolver(loginSchema), defaultValues: { email: "", password: "", remember: true } })
  const onSubmit = async (values: LoginValues) => { try { await login(values); toast({ tone: "success", title: "Welcome back", description: "You are now signed in." }); router.replace("/dashboard") } catch (error) { toast({ tone: "error", title: "Unable to sign in", description: getApiErrorMessage(error, "Check your email and password.") }) } }
  return <AuthFormShell title="Welcome back" description="Sign in to continue to your workspace." footer={<>New to HRMS? <AuthLink href="/register">Create an account</AuthLink></>}><form className="grid gap-5" onSubmit={handleSubmit(onSubmit)} noValidate><FormField label="Email address" type="email" autoComplete="email" placeholder="you@company.com" error={errors.email?.message} {...register("email")} /><FormField label="Password" type="password" autoComplete="current-password" placeholder="Enter your password" error={errors.password?.message} {...register("password")} /><div className="flex items-center justify-between"><Controller name="remember" control={control} render={({ field }) => <Checkbox isSelected={field.value} onChange={field.onChange} className="flex cursor-pointer items-center gap-2 text-sm text-muted-foreground"><span className="grid size-4 place-items-center rounded border border-input bg-background data-[selected]:border-primary data-[selected]:bg-primary data-[selected]:text-primary-foreground">{field.value && "✓"}</span>Remember me</Checkbox>} /><Link href="/forgot-password" className="text-sm font-medium text-foreground hover:underline">Forgot password?</Link></div><Button type="submit" className="h-11 w-full" isDisabled={isLoading}>{isLoading ? "Signing in…" : "Sign in"}</Button></form></AuthFormShell>
}
