"use client"

import { Check, CheckCircle2, Mail, Moon, ShieldCheck, Sun } from "lucide-react"
import { useTheme } from "next-themes"
import { useForm } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { Button } from "@workspace/ui/components/button"
import { useAuthStore } from "@/store/auth-store"
import { FormField } from "@/components/ui/form-fields"
import { authService } from "@/services/auth-service"
import { useToast } from "@/providers/toast-provider"
import { getApiErrorMessage } from "@/lib/api-error"
import { z } from "zod"

const profileSchema = z.object({ name: z.string().trim().min(2, "Enter your name"), email: z.email("Enter a valid email address") })

export function SettingsModule() {
  const { user, updateUser } = useAuthStore(); const { theme, setTheme } = useTheme(); const { toast } = useToast(); const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm<{name:string;email:string}>({ resolver: zodResolver(profileSchema), defaultValues: { name: user?.name ?? "", email: user?.email ?? "" } })
  const saveProfile = async (values: {name:string;email:string}) => { try { const updated = await authService.updateProfile(values); updateUser(updated); toast({ tone: "success", title: "Profile updated" }) } catch (error) { toast({ tone: "error", title: "Unable to update profile", description: getApiErrorMessage(error) }) } }
  const verified = Boolean(user?.email_verified_at)
  const resendVerification = async () => { try { await authService.sendVerificationNotification(); toast({ tone: "success", title: "Verification email sent", description: "Check your inbox for the latest link." }) } catch (error) { toast({ tone: "error", title: "Unable to send email", description: getApiErrorMessage(error) }) } }
  return <div className="mx-auto grid max-w-4xl gap-6"><div><p className="text-sm font-medium text-muted-foreground">Workspace</p><h1 className="mt-2 text-2xl font-semibold tracking-tight">Settings</h1><p className="mt-2 text-sm text-muted-foreground">Manage your account preferences.</p></div>
    {/* Account readiness, moved here from the dashboard so the overview can
        show workload rather than sign-in details. */}
    <div className="grid gap-4 md:grid-cols-3"><div className="rounded-2xl border bg-background p-5"><Mail className="size-5 text-muted-foreground"/><p className="mt-6 text-sm text-muted-foreground">Signed in as</p><p className="mt-1 truncate font-medium">{user?.email ?? "Loading account…"}</p></div><div className="rounded-2xl border bg-background p-5"><ShieldCheck className="size-5 text-muted-foreground"/><p className="mt-6 text-sm text-muted-foreground">Email status</p><p className="mt-1 font-medium">{verified ? "Verified" : "Verification required"}</p></div><div className="rounded-2xl border bg-background p-5"><CheckCircle2 className="size-5 text-muted-foreground"/><p className="mt-6 text-sm text-muted-foreground">Workspace status</p><p className="mt-1 font-medium">Ready to build</p></div></div>
    {!verified && <div className="flex flex-col gap-4 rounded-2xl border border-amber-500/30 bg-amber-500/5 p-5 sm:flex-row sm:items-center sm:justify-between"><div><p className="font-medium">Verify your email address</p><p className="mt-1 text-sm text-muted-foreground">Confirm your email to keep your account secure.</p></div><Button variant="outline" onPress={resendVerification}>Resend verification email</Button></div>}
    <section className="overflow-hidden rounded-2xl border bg-background"><div className="border-b p-5"><h2 className="font-semibold">Account</h2><p className="mt-1 text-sm text-muted-foreground">Update your personal account details.</p></div><form className="grid gap-4 p-5" onSubmit={handleSubmit(saveProfile)}><FormField label="Full name" autoComplete="name" error={errors.name?.message} {...register("name")} /><FormField label="Email address" type="email" autoComplete="email" error={errors.email?.message} {...register("email")} /><Button type="submit" className="w-fit" isDisabled={isSubmitting}>{isSubmitting ? "Saving…" : "Save profile"}</Button></form></section><section className="overflow-hidden rounded-2xl border bg-background"><div className="border-b p-5"><h2 className="font-semibold">Appearance</h2><p className="mt-1 text-sm text-muted-foreground">Choose how HRMS looks on this device.</p></div><div className="grid gap-3 p-5 sm:grid-cols-2"><Button variant={theme === "light" ? "secondary" : "outline"} className="h-auto justify-start gap-3 p-4" onPress={() => setTheme("light")}><Sun className="size-5" /><span className="grid text-left"><span className="font-medium">Light</span><span className="text-xs text-muted-foreground">Bright and clear</span></span>{theme === "light" && <Check className="ml-auto size-4" />}</Button><Button variant={theme === "dark" ? "secondary" : "outline"} className="h-auto justify-start gap-3 p-4" onPress={() => setTheme("dark")}><Moon className="size-5" /><span className="grid text-left"><span className="font-medium">Dark</span><span className="text-xs text-muted-foreground">Easy on the eyes</span></span>{theme === "dark" && <Check className="ml-auto size-4" />}</Button></div></section></div>
}
