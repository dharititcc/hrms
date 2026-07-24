import { z } from "zod"

export const loginSchema = z.object({ email: z.email("Enter a valid email address"), password: z.string().min(1, "Enter your password"), remember: z.boolean() })
export const registerSchema = z.object({ name: z.string().trim().min(2, "Enter your full name"), email: z.email("Enter a valid email address"), password: z.string().min(8, "Use at least 8 characters"), password_confirmation: z.string().min(1, "Confirm your password") }).refine((data) => data.password === data.password_confirmation, { path: ["password_confirmation"], message: "Passwords do not match" })
export const emailSchema = z.object({ email: z.email("Enter a valid email address") })
export const resetSchema = z.object({ email: z.email("Enter a valid email address"), token: z.string().min(1, "Your reset link is invalid"), password: z.string().min(8, "Use at least 8 characters"), password_confirmation: z.string().min(1, "Confirm your password") }).refine((data) => data.password === data.password_confirmation, { path: ["password_confirmation"], message: "Passwords do not match" })
export type LoginValues = z.infer<typeof loginSchema>
export type RegisterValues = z.infer<typeof registerSchema>
