import type { ProjectStatus, TaskPriority, TaskStatus } from "@/types/project"

export const projectStatusLabels: Record<ProjectStatus, string> = {
  planning: "Planning",
  active: "Active",
  on_hold: "On hold",
  completed: "Completed",
  cancelled: "Cancelled",
}

export const projectStatusStyles: Record<ProjectStatus, string> = {
  planning: "bg-sky-500/10 text-sky-600 dark:text-sky-400",
  active: "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400",
  on_hold: "bg-amber-500/10 text-amber-600 dark:text-amber-400",
  completed: "bg-muted text-muted-foreground",
  cancelled: "bg-destructive/10 text-destructive",
}

export const taskStatusLabels: Record<TaskStatus, string> = {
  pending: "Pending",
  in_progress: "In progress",
  review: "Review",
  completed: "Completed",
  on_hold: "On hold",
  cancelled: "Cancelled",
}

/** Column order for the board, left to right. */
export const taskStatusOrder: TaskStatus[] = ["pending", "in_progress", "review", "completed", "on_hold", "cancelled"]

export const taskPriorityLabels: Record<TaskPriority, string> = {
  low: "Low",
  medium: "Medium",
  high: "High",
  urgent: "Urgent",
}

export const taskPriorityStyles: Record<TaskPriority, string> = {
  low: "bg-muted text-muted-foreground",
  medium: "bg-sky-500/10 text-sky-600 dark:text-sky-400",
  high: "bg-amber-500/10 text-amber-600 dark:text-amber-400",
  urgent: "bg-destructive/10 text-destructive",
}
