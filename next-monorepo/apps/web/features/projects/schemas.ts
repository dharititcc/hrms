import { z } from "zod"

export const projectSchema = z
  .object({
    name: z.string().trim().min(2, "Enter a project name"),
    client: z.string().max(255, "Client name is too long").optional(),
    description: z.string().max(5000, "Description is too long").optional(),
    status: z.enum(["planning", "active", "on_hold", "completed", "cancelled"]),
    start_date: z.string().optional(),
    end_date: z.string().optional(),
    budget: z
      .string()
      .optional()
      .refine((value) => !value || !Number.isNaN(Number(value)), "Enter a valid amount")
      .refine((value) => !value || Number(value) >= 0, "Budget cannot be negative"),
    member_ids: z.array(z.number()).optional(),
  })
  .refine((values) => !values.start_date || !values.end_date || values.end_date >= values.start_date, {
    message: "End date cannot be before the start date",
    path: ["end_date"],
  })

export type ProjectFormValues = z.infer<typeof projectSchema>

export const taskSchema = z
  .object({
    subject: z.string().trim().min(2, "Enter a task subject"),
    description: z.string().max(20000, "Description is too long").optional(),
    status: z.enum(["pending", "in_progress", "review", "completed", "cancelled", "on_hold"]),
    priority: z.enum(["low", "medium", "high", "urgent"]),
    is_billable: z.boolean().optional(),
    estimated_hours: z
      .string()
      .optional()
      .refine((value) => !value || (!Number.isNaN(Number(value)) && Number(value) >= 0), "Enter a valid number of hours"),
    start_date: z.string().optional(),
    due_date: z.string().optional(),
    assignee_ids: z.array(z.number()).optional(),
  })
  .refine((values) => !values.start_date || !values.due_date || values.due_date >= values.start_date, {
    message: "Due date cannot be before the start date",
    path: ["due_date"],
  })

export type TaskFormValues = z.infer<typeof taskSchema>
