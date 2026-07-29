"use client"

import { LogIn, LogOut, MapPin, MapPinOff, Timer } from "lucide-react"
import { useState } from "react"
import { Button } from "@workspace/ui/components/button"
import { attendanceStatusLabels, attendanceStatusStyles, formatMinutes, workModeLabels } from "@/features/attendance/labels"
import { useAttendanceMutations, useTodayAttendance } from "@/hooks/use-attendance"
import { usePermissions } from "@/hooks/use-permissions"
import { capturePosition, formatRecordedTime, mapsLink } from "@/services/attendance-service"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import type { WorkMode } from "@/types/attendance"

const MODES: WorkMode[] = ["office", "remote", "field"]

/**
 * The employee's own check-in and check-out for today.
 *
 * Location is requested but never required: if the browser refuses or cannot
 * fix a position, attendance is still recorded without coordinates rather than
 * blocking somebody from starting work.
 */
export function CheckInCard({ employeeId }: { employeeId: number | null }) {
  const { data: today, isLoading } = useTodayAttendance()
  const { checkIn, checkOut } = useAttendanceMutations()
  const { can } = usePermissions()
  const { toast } = useToast()

  const [mode, setMode] = useState<WorkMode>("office")
  const [locating, setLocating] = useState(false)

  if (!can("attendance.create")) return null

  if (employeeId === null) {
    return (
      <section className="rounded-2xl border bg-background p-5">
        <p className="text-sm text-muted-foreground">
          This account isn&rsquo;t linked to an employee record, so there&rsquo;s nothing to check in against.
        </p>
      </section>
    )
  }

  const pending = checkIn.isPending || checkOut.isPending || locating

  const withPosition = async <T,>(action: (position: Awaited<ReturnType<typeof capturePosition>>) => Promise<T>) => {
    setLocating(true)
    try {
      const position = await capturePosition()
      return { result: await action(position), position }
    } finally {
      setLocating(false)
    }
  }

  const doCheckIn = async () => {
    try {
      const { position } = await withPosition((position) =>
        checkIn.mutateAsync({
          employee_id: employeeId,
          work_mode: mode,
          latitude: position?.latitude,
          longitude: position?.longitude,
        }),
      )

      toast({
        tone: "success",
        title: "Checked in",
        description: position ? undefined : "Recorded without a location, because your browser didn’t share one.",
      })
    } catch (error) {
      toast({ tone: "error", title: "Unable to check in", description: getApiErrorMessage(error) })
    }
  }

  const doCheckOut = async () => {
    if (!today) return
    try {
      await withPosition((position) => checkOut.mutateAsync({ id: today.id, position: position ?? undefined }))
      toast({ tone: "success", title: "Checked out" })
    } catch (error) {
      toast({ tone: "error", title: "Unable to check out", description: getApiErrorMessage(error) })
    }
  }

  return (
    <section className="rounded-2xl border bg-background p-5">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h2 className="text-sm font-semibold">Today</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            {new Date().toLocaleDateString(undefined, { weekday: "long", day: "numeric", month: "long" })}
          </p>
        </div>
        {today && (
          <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${attendanceStatusStyles[today.status]}`}>
            {attendanceStatusLabels[today.status]}
          </span>
        )}
      </div>

      {isLoading ? (
        <div className="mt-5 h-10 w-48 animate-pulse rounded bg-muted" />
      ) : (
        <>
          <dl className="mt-5 grid gap-4 sm:grid-cols-3">
            <Fact label="Checked in" value={formatRecordedTime(today?.check_in_at, today?.check_in, today?.timezone)} />
            <Fact label="Checked out" value={formatRecordedTime(today?.check_out_at, today?.check_out, today?.timezone)} />
            <Fact
              label="Worked"
              value={today?.check_out
                ? today.worked_hours
                : today?.is_open ? `${formatMinutes(today.elapsed_minutes)} so far` : "—"}
            />
          </dl>

          {today && (today.late_minutes > 0 || today.overtime_minutes > 0) && (
            <p className="mt-3 inline-flex items-center gap-2 text-xs text-muted-foreground">
              <Timer className="size-3.5" />
              {today.late_minutes > 0 && <span>{formatMinutes(today.late_minutes)} late</span>}
              {today.overtime_minutes > 0 && <span>{formatMinutes(today.overtime_minutes)} overtime</span>}
            </p>
          )}

          {today?.requires_approval && (
            <p className="mt-3 rounded-lg bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-400">
              Recorded away from a known office, so it needs approval.
            </p>
          )}

          {today?.check_in_location.latitude != null && today.check_in_location.longitude != null && (
            <a
              href={mapsLink(today.check_in_location.latitude, today.check_in_location.longitude)}
              target="_blank"
              rel="noopener noreferrer"
              className="mt-3 inline-flex items-center gap-1.5 text-xs text-primary hover:underline"
            >
              <MapPin className="size-3.5" />
              {today.check_in_location.office ?? "View check-in location"}
            </a>
          )}

          <div className="mt-5 flex flex-wrap items-center gap-2">
            {!today?.check_in ? (
              <>
                <div className="flex gap-1" role="group" aria-label="Work mode">
                  {MODES.map((option) => (
                    <Button
                      key={option}
                      size="sm"
                      variant={mode === option ? "default" : "outline"}
                      onPress={() => setMode(option)}
                    >
                      {workModeLabels[option]}
                    </Button>
                  ))}
                </div>
                <Button isDisabled={pending} onPress={() => void doCheckIn()}>
                  <LogIn />{locating ? "Finding you…" : "Check in"}
                </Button>
              </>
            ) : today.check_out ? (
              <p className="text-sm text-muted-foreground">You&rsquo;re done for the day.</p>
            ) : (
              <Button variant="destructive" isDisabled={pending} onPress={() => void doCheckOut()}>
                <LogOut />{locating ? "Finding you…" : "Check out"}
              </Button>
            )}
          </div>

          {!today?.check_in && (
            <p className="mt-3 inline-flex items-center gap-1.5 text-xs text-muted-foreground">
              <MapPinOff className="size-3.5" />
              Your browser may ask for your location. Declining still records attendance, without a location.
            </p>
          )}
        </>
      )}
    </section>
  )
}

function Fact({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-lg bg-muted/30 px-3 py-2">
      <dt className="text-xs text-muted-foreground">{label}</dt>
      <dd className="mt-0.5 font-medium tabular-nums">{value}</dd>
    </div>
  )
}
