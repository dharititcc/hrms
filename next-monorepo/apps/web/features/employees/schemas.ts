import { z } from "zod"

export const employeeSchema = z.object({
  name: z.string().trim().min(2, "Enter a name"),
  email: z.email("Enter a valid email address"),
  phone: z.string().max(40, "Phone number is too long").optional(),
  role: z.enum(["admin", "manager", "employee"]),
  status: z.enum(["active", "inactive"]),
})

export type EmployeeFormValues = z.infer<typeof employeeSchema>
