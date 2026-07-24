"use client"

import { useState } from "react"
import { useSearchParams, useRouter } from "next/navigation"
import { useForm } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { Button } from "@workspace/ui/components/button"
import { FormField } from "@/components/ui/form-fields"
import { AuthFormShell, AuthLink } from "@/features/auth/auth-form-shell"
import { emailSchema, resetSchema } from "@/features/auth/schemas"
import { useToast } from "@/providers/toast-provider"
import { authService } from "@/services/auth-service"
import { getApiErrorMessage } from "@/lib/api-error"
import { z } from "zod"

export function ForgotPasswordForm() {
  const { toast } = useToast(); const [sent, setSent] = useState(false); const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm<{ email: string }>({ resolver: zodResolver(emailSchema) })
  const onSubmit = async ({ email }: { email: string }) => { try { await authService.forgotPassword(email); setSent(true); toast({ tone: "success", title: "Reset email sent", description: "Check your inbox for the next step." }) } catch (error) { toast({ tone: "error", title: "Unable to send reset email", description: getApiErrorMessage(error) }) } }
  return <AuthFormShell title="Forgot your password?" description={sent ? "If an account exists for that address, we have sent reset instructions." : "Enter your work email and we’ll send you reset instructions."} footer={<>Remember your password? <AuthLink href="/login">Back to sign in</AuthLink></>}><form className="grid gap-5" onSubmit={handleSubmit(onSubmit)} noValidate><FormField label="Email address" type="email" autoComplete="email" placeholder="you@company.com" error={errors.email?.message} {...register("email")} /><Button type="submit" className="h-11 w-full" isDisabled={isSubmitting}>{isSubmitting ? "Sending…" : "Send reset link"}</Button></form></AuthFormShell>
}

export function ResetPasswordForm() {
  const searchParams = useSearchParams(); const router = useRouter(); const { toast } = useToast(); const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm<z.infer<typeof resetSchema>>({ resolver: zodResolver(resetSchema), defaultValues: { email: searchParams.get("email") ?? "", token: searchParams.get("token") ?? "", password: "", password_confirmation: "" } })
  const onSubmit = async (values: z.infer<typeof resetSchema>) => { try { await authService.resetPassword(values); toast({ tone: "success", title: "Password updated", description: "You can now sign in with your new password." }); router.replace("/login") } catch (error) { toast({ tone: "error", title: "Unable to reset password", description: getApiErrorMessage(error, "This reset link may have expired.") }) } }
  return <AuthFormShell title="Set a new password" description="Choose a strong password for your account." footer={<>Back to <AuthLink href="/login">sign in</AuthLink></>}><form className="grid gap-5" onSubmit={handleSubmit(onSubmit)} noValidate><FormField label="Email address" type="email" autoComplete="email" placeholder="you@company.com" error={errors.email?.message} {...register("email")} /><FormField label="New password" type="password" autoComplete="new-password" placeholder="At least 8 characters" error={errors.password?.message} {...register("password")} /><FormField label="Confirm password" type="password" autoComplete="new-password" placeholder="Repeat your password" error={errors.password_confirmation?.message} {...register("password_confirmation")} /><Button type="submit" className="h-11 w-full" isDisabled={isSubmitting}>{isSubmitting ? "Updating…" : "Reset password"}</Button></form></AuthFormShell>
}
