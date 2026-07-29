"use client"

import { Clock, Edit3, Plus, Star, Trash2, X } from "lucide-react"
import { useEffect, useState } from "react"
import { useForm } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { z } from "zod"
import { Button } from "@workspace/ui/components/button"
import { FormField } from "@/components/ui/form-fields"
import { formatMinutes } from "@/features/attendance/labels"
import { useWorkShiftMutations, useWorkShifts } from "@/hooks/use-attendance"
import { usePermissions } from "@/hooks/use-permissions"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import type { WorkShift } from "@/types/attendance"

const shiftSchema = z.object({
  name: z.string().trim().min(2, "Name the shift"),
  starts_at: z.string().min(1, "Choose a start time"),
  ends_at: z.string().min(1, "Choose an end time"),
  grace_minutes: z.string(),
  break_minutes: z.string(),
  is_default: z.boolean().optional(),
  is_active: z.boolean().optional(),
}).refine((values) => values.ends_at > values.starts_at, {
  // Overnight shifts are not modelled elsewhere in attendance.
  message: "The shift must end after it starts",
  path: ["ends_at"],
})

type ShiftFormValues = z.infer<typeof shiftSchema>

/** Module-level, so the reset effect below has no changing dependency. */
function valuesFor(shift: WorkShift | null): ShiftFormValues {
  return {
    name: shift?.name ?? "",
    starts_at: shift?.starts_at ?? "09:00",
    ends_at: shift?.ends_at ?? "18:00",
    grace_minutes: String(shift?.grace_minutes ?? 15),
    break_minutes: String(shift?.break_minutes ?? 60),
    is_default: shift?.is_default ?? false,
    is_active: shift?.is_active ?? true,
  }
}

/**
 * Office hours: what "late" and "a full day" are measured against.
 *
 * Attendance always read these, falling back to configuration when a
 * workspace had defined none, so every workspace was silently judged against
 * the same nine to six with nowhere to change it.
 */
export function OfficeHours() {
  const { can } = usePermissions()
  const { data, isLoading } = useWorkShifts()
  const { remove } = useWorkShiftMutations()
  const { toast } = useToast()
  const [editing, setEditing] = useState<WorkShift | null | undefined>(undefined)

  if (!can("attendance.edit")) return null

  const shifts = data?.data ?? []
  const fallback = data?.meta.fallback

  const confirmDelete = async (shift: WorkShift) => {
    if (!window.confirm(`Delete ${shift.name}? Attendance already recorded keeps the hours it was judged with.`)) return
    try {
      await remove.mutateAsync(shift.id)
      toast({ tone: "success", title: "Shift deleted" })
    } catch (error) {
      toast({ tone: "error", title: "Unable to delete shift", description: getApiErrorMessage(error) })
    }
  }

  return (
    <section className="rounded-2xl border bg-background p-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="flex items-center gap-2 text-sm font-semibold"><Clock className="size-4" />Office hours</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            {shifts.length === 0
              ? `No hours set, so everybody is judged against ${fallback?.starts_at ?? "09:00"}–${fallback?.ends_at ?? "18:00"} with ${fallback?.grace_minutes ?? 15} minutes' grace.`
              : "What counts as late, and what a full day comes to."}
          </p>
        </div>
        <Button size="sm" onPress={() => setEditing(null)}><Plus />Add hours</Button>
      </div>

      {isLoading ? (
        <div className="mt-4 grid gap-2">{[1, 2].map((row) => <div key={row} className="h-14 animate-pulse rounded-xl bg-muted" />)}</div>
      ) : shifts.length === 0 ? (
        <p className="mt-4 rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
          Set your hours so lateness is measured against your day rather than the default.
        </p>
      ) : (
        <ul className="mt-4 grid gap-2">
          {shifts.map((shift) => (
            <li key={shift.id} className="flex flex-wrap items-center gap-3 rounded-xl border p-3">
              <div className="min-w-40 flex-1">
                <p className="flex items-center gap-2 text-sm font-medium">
                  {shift.name}
                  {shift.is_default && (
                    <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-[0.7rem] font-medium text-primary">
                      <Star className="size-2.5" />Default
                    </span>
                  )}
                  {!shift.is_active && <span className="rounded-full bg-muted px-2 py-0.5 text-[0.7rem] text-muted-foreground">Inactive</span>}
                </p>
                <p className="mt-0.5 text-xs text-muted-foreground">
                  {shift.starts_at}–{shift.ends_at} · {shift.grace_minutes}m grace · {shift.break_minutes}m break
                </p>
              </div>

              <span className="text-xs text-muted-foreground">Full day {formatMinutes(shift.paid_minutes)}</span>

              <div className="flex gap-1">
                <Button variant="ghost" size="icon-sm" aria-label={`Edit ${shift.name}`} onPress={() => setEditing(shift)}><Edit3 /></Button>
                <Button variant="ghost" size="icon-sm" aria-label={`Delete ${shift.name}`} onPress={() => void confirmDelete(shift)}><Trash2 /></Button>
              </div>
            </li>
          ))}
        </ul>
      )}

      {editing !== undefined && (
        <ShiftDialog key={editing?.id ?? "new"} shift={editing} onClose={() => setEditing(undefined)} />
      )}
    </section>
  )
}

function ShiftDialog({ shift, onClose }: { shift: WorkShift | null; onClose: () => void }) {
  const { create, update } = useWorkShiftMutations()
  const { toast } = useToast()
  const editing = Boolean(shift)

  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm<ShiftFormValues>({
    resolver: zodResolver(shiftSchema),
    defaultValues: valuesFor(shift),
  })

  // react-aria's TextField does not read react-hook-form's defaultValues.
  useEffect(() => { reset(valuesFor(shift)) }, [reset, shift])

  const onSubmit = async (values: ShiftFormValues) => {
    const input = {
      name: values.name,
      starts_at: values.starts_at,
      ends_at: values.ends_at,
      grace_minutes: Number(values.grace_minutes),
      break_minutes: Number(values.break_minutes),
      is_default: values.is_default ?? false,
      is_active: values.is_active ?? true,
    }

    try {
      if (shift) await update.mutateAsync({ id: shift.id, input })
      else await create.mutateAsync(input)
      toast({ tone: "success", title: editing ? "Hours updated" : "Hours added" })
      onClose()
    } catch (error) {
      toast({ tone: "error", title: "Unable to save hours", description: getApiErrorMessage(error) })
    }
  }

  return (
    <div
      className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4"
      role="presentation"
      onMouseDown={(event) => { if (event.target === event.currentTarget) onClose() }}
    >
      <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border bg-background p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="shift-dialog-title">
        <div className="flex items-start justify-between">
          <div>
            <h2 id="shift-dialog-title" className="text-lg font-semibold">{editing ? "Edit office hours" : "Add office hours"}</h2>
            <p className="mt-1 text-sm text-muted-foreground">Check-ins are judged late against the start of these hours.</p>
          </div>
          <Button variant="ghost" size="icon-sm" aria-label="Close dialog" onPress={onClose}><X /></Button>
        </div>

        <form className="mt-6 grid gap-4" onSubmit={handleSubmit(onSubmit)} noValidate>
          <FormField label="Name" placeholder="Day shift" error={errors.name?.message} {...register("name")} />

          <div className="grid gap-4 sm:grid-cols-2">
            <FormField label="Starts at" type="time" error={errors.starts_at?.message} {...register("starts_at")} />
            <FormField label="Ends at" type="time" error={errors.ends_at?.message} {...register("ends_at")} />
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <FormField label="Grace, in minutes" type="number" min="0" max="240" error={errors.grace_minutes?.message} {...register("grace_minutes")} />
            <FormField label="Unpaid break, in minutes" type="number" min="0" max="480" error={errors.break_minutes?.message} {...register("break_minutes")} />
          </div>
          <p className="-mt-2 text-xs text-muted-foreground">
            Grace forgives lateness; it does not move the start of the day, so arriving inside it is on time but arriving after it is
            counted from the start, not from the end of the grace.
          </p>

          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" className="size-4 rounded border" {...register("is_default")} />
            Use these hours for everyone
          </label>
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" className="size-4 rounded border" {...register("is_active")} />
            Active
          </label>

          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="outline" onPress={onClose}>Cancel</Button>
            <Button type="submit" isDisabled={isSubmitting || create.isPending || update.isPending}>
              {isSubmitting || create.isPending || update.isPending ? "Saving…" : editing ? "Save changes" : "Add hours"}
            </Button>
          </div>
        </form>
      </div>
    </div>
  )
}
