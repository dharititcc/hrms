"use client"

import { Check, Moon, Sun } from "lucide-react"
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
  return <div className="mx-auto grid max-w-4xl gap-6"><div><p className="text-sm font-medium text-muted-foreground">Workspace</p><h1 className="mt-2 text-2xl font-semibold tracking-tight">Settings</h1><p className="mt-2 text-sm text-muted-foreground">Manage your account preferences.</p></div><section className="overflow-hidden rounded-2xl border bg-background"><div className="border-b p-5"><h2 className="font-semibold">Account</h2><p className="mt-1 text-sm text-muted-foreground">Update your personal account details.</p></div><form className="grid gap-4 p-5" onSubmit={handleSubmit(saveProfile)}><FormField label="Full name" autoComplete="name" error={errors.name?.message} {...register("name")} /><FormField label="Email address" type="email" autoComplete="email" error={errors.email?.message} {...register("email")} /><Button type="submit" className="w-fit" isDisabled={isSubmitting}>{isSubmitting ? "Saving…" : "Save profile"}</Button></form></section><section className="overflow-hidden rounded-2xl border bg-background"><div className="border-b p-5"><h2 className="font-semibold">Appearance</h2><p className="mt-1 text-sm text-muted-foreground">Choose how HRMS looks on this device.</p></div><div className="grid gap-3 p-5 sm:grid-cols-2"><Button variant={theme === "light" ? "secondary" : "outline"} className="h-auto justify-start gap-3 p-4" onPress={() => setTheme("light")}><Sun className="size-5" /><span className="grid text-left"><span className="font-medium">Light</span><span className="text-xs text-muted-foreground">Bright and clear</span></span>{theme === "light" && <Check className="ml-auto size-4" />}</Button><Button variant={theme === "dark" ? "secondary" : "outline"} className="h-auto justify-start gap-3 p-4" onPress={() => setTheme("dark")}><Moon className="size-5" /><span className="grid text-left"><span className="font-medium">Dark</span><span className="text-xs text-muted-foreground">Easy on the eyes</span></span>{theme === "dark" && <Check className="ml-auto size-4" />}</Button></div></section></div>
}
