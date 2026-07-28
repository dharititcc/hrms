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

export const taskSchema = z.object({
  title: z.string().trim().min(2, "Enter a task title"),
  description: z.string().max(5000, "Description is too long").optional(),
  status: z.enum(["todo", "in_progress", "done"]),
  priority: z.enum(["low", "medium", "high", "urgent"]),
  due_date: z.string().optional(),
  /** Held as a string because the select emits strings; "" means unassigned. */
  staff_id: z.string().optional(),
})

export type TaskFormValues = z.infer<typeof taskSchema>
