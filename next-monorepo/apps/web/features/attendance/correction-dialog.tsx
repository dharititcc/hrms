"use client"

import { AlertTriangle, X } from "lucide-react"
import { useEffect } from "react"
import { useForm } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { z } from "zod"
import { Button } from "@workspace/ui/components/button"
import { FormField } from "@/components/ui/form-fields"
import { useAttendanceMutations } from "@/hooks/use-attendance"
import { compactTimezone } from "@/services/attendance-service"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import type { AttendanceRecord } from "@/types/attendance"

const correctionSchema = z.object({
  check_in: z.string().min(1, "A day needs a check-in time"),
  check_out: z.string().optional(),
  notes: z.string().max(1000).optional(),
}).refine((values) => !values.check_out || values.check_out > values.check_in, {
  // Overnight shifts are not modelled, so this is a typo rather than a case.
  message: "Check-out must be later than check-in",
  path: ["check_out"],
})

type CorrectionFormValues = z.infer<typeof correctionSchema>

/** A time input wants "HH:MM"; the record stores "HH:MM:SS". */
function toInputTime(value: string | null): string {
  return value ? value.slice(0, 5) : ""
}

/** Module-level, so the reset effect below has no changing dependency. */
function valuesFor(record: AttendanceRecord): CorrectionFormValues {
  return {
    check_in: toInputTime(record.check_in),
    check_out: toInputTime(record.check_out),
    notes: record.notes ?? "",
  }
}

/**
 * Corrects the times on a day, most often to close one somebody forgot to
 * check out of.
 *
 * Times are entered as the employee's own wall clock, not the corrector's.
 * The record keeps the zone it was taken in, and reinterpreting a time in the
 * manager's zone would move somebody's day.
 */
export function CorrectionDialog({ record, onClose }: { record: AttendanceRecord; onClose: () => void }) {
  const { correct } = useAttendanceMutations()
  const { toast } = useToast()

  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm<CorrectionFormValues>({
    resolver: zodResolver(correctionSchema),
    defaultValues: valuesFor(record),
  })

  // react-aria's TextField does not read react-hook-form's defaultValues.
  useEffect(() => { reset(valuesFor(record)) }, [reset, record])

  const onSubmit = async (values: CorrectionFormValues) => {
    try {
      await correct.mutateAsync({
        id: record.id,
        input: {
          check_in: values.check_in,
          check_out: values.check_out || null,
          notes: values.notes || null,
        },
      })
      toast({ tone: "success", title: "Attendance corrected", description: "It has gone back for approval." })
      onClose()
    } catch (error) {
      toast({ tone: "error", title: "Unable to correct attendance", description: getApiErrorMessage(error) })
    }
  }

  const zone = record.timezone

  return (
    <div
      className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4"
      role="presentation"
      onMouseDown={(event) => { if (event.target === event.currentTarget) onClose() }}
    >
      <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border bg-background p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="correction-dialog-title">
        <div className="flex items-start justify-between">
          <div>
            <h2 id="correction-dialog-title" className="text-lg font-semibold">Correct attendance</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              {record.employee_name ?? "This employee"} on {new Date(record.work_date).toLocaleDateString(undefined, { day: "numeric", month: "long" })}
            </p>
          </div>
          <Button variant="ghost" size="icon-sm" aria-label="Close dialog" onPress={onClose}><X /></Button>
        </div>

        <form className="mt-6 grid gap-4" onSubmit={handleSubmit(onSubmit)} noValidate>
          <div className="grid gap-4 sm:grid-cols-2">
            <FormField label="Checked in" type="time" error={errors.check_in?.message} {...register("check_in")} />
            <FormField label="Checked out" type="time" error={errors.check_out?.message} {...register("check_out")} />
          </div>

          <p className="-mt-2 text-xs text-muted-foreground">
            {zone
              ? `Times are this employee's own clock in ${compactTimezone(zone, record.check_in_at)}, not yours.`
              : "Times are the employee's own clock, not yours."}
            {" "}Leaving check-out blank keeps the day open.
          </p>

          <label className="grid gap-2 text-sm font-medium">
            Reason
            <textarea
              className="min-h-20 rounded-lg border bg-background p-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
              placeholder="Why this is being changed"
              {...register("notes")}
            />
          </label>

          <p className="flex items-start gap-2 rounded-xl border border-amber-500/30 bg-amber-500/5 p-3 text-xs">
            <AlertTriangle className="mt-0.5 size-3.5 shrink-0 text-amber-600 dark:text-amber-400" />
            <span className="text-muted-foreground">
              Hours, overtime and status are recalculated from these times. The day is marked as entered by hand and goes back for
              approval, because a figure somebody typed should not count the same as one the system captured.
            </span>
          </p>

          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="outline" onPress={onClose}>Cancel</Button>
            <Button type="submit" isDisabled={isSubmitting || correct.isPending}>
              {isSubmitting || correct.isPending ? "Saving…" : "Save correction"}
            </Button>
          </div>
        </form>
      </div>
    </div>
  )
}
