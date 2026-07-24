import { z } from "zod"

export const staffSchema = z.object({
  name: z.string().trim().min(2, "Enter a name"),
  email: z.email("Enter a valid email address"),
  phone: z.string().max(40, "Phone number is too long").optional(),
  role: z.enum(["admin", "manager", "member"]),
  status: z.enum(["active", "inactive"]),
})

export type StaffFormValues = z.infer<typeof staffSchema>
