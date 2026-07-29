"use client"

import { CalendarCheck, Check, MapPin, Monitor, Smartphone, Tablet } from "lucide-react"
import { useState } from "react"
import { Button } from "@workspace/ui/components/button"
import { CheckInCard } from "@/features/attendance/check-in-card"
import { attendanceStatusLabels, attendanceStatusStyles, formatMinutes, workModeLabels } from "@/features/attendance/labels"
import { OfficeLocations } from "@/features/attendance/office-locations"
import { useAttendance, useAttendanceMutations } from "@/hooks/use-attendance"
import { usePermissions } from "@/hooks/use-permissions"
import { useEmployees } from "@/hooks/use-employees"
import { compactTimezone, formatRecordedTime, mapsLink } from "@/services/attendance-service"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import type { AttendanceRecord, AttendanceStatus } from "@/types/attendance"

function currentMonth(): string {
  const now = new Date()
  return `${now.getFullYear()}-${`${now.getMonth() + 1}`.padStart(2, "0")}`
}

export function AttendanceModule() {
  const [month, setMonth] = useState(currentMonth)
  const [employeeFilter, setEmployeeFilter] = useState<number | "all">("all")

  const { can, employeeId: myEmployeeId } = usePermissions()
  const { toast } = useToast()
  const { approve } = useAttendanceMutations()

  const seesEveryone = can("attendance.view-all")
  // The employee picker only means anything to someone who can see others.
  const { data: employee } = useEmployees(seesEveryone ? { status: "active", per_page: 100 } : { per_page: 1 })

  const { data, isLoading, isError, refetch, isPlaceholderData } = useAttendance({
    month,
    employee_id: employeeFilter === "all" ? undefined : employeeFilter,
  })

  const records = data?.data ?? []

  const approveRecord = async (record: AttendanceRecord) => {
    try {
      await approve.mutateAsync(record.id)
      toast({ tone: "success", title: "Attendance approved" })
    } catch (error) {
      toast({ tone: "error", title: "Unable to approve", description: getApiErrorMessage(error) })
    }
  }

  return (
    <div className="mx-auto grid max-w-6xl gap-6">
      <div>
        <p className="text-sm font-medium text-muted-foreground">People</p>
        <h1 className="mt-2 text-2xl font-semibold tracking-tight">Attendance</h1>
        <p className="mt-2 text-sm text-muted-foreground">
          {seesEveryone ? "Check in, and review the team’s attendance." : "Check in and review your own attendance."}
        </p>
      </div>

      <CheckInCard employeeId={myEmployeeId} />

      <OfficeLocations />

      <div className="flex flex-wrap items-end gap-3 rounded-2xl border bg-background p-3">
        <label className="grid gap-1 text-xs font-medium">
          Month
          <input
            type="month"
            value={month}
            onChange={(event) => setMonth(event.target.value)}
            aria-label="Attendance month"
            className="h-9 rounded-lg border bg-background px-2 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
          />
        </label>

        {seesEveryone && (
          <label className="grid gap-1 text-xs font-medium">
            Employee
            <select
              value={employeeFilter}
              onChange={(event) => setEmployeeFilter(event.target.value === "all" ? "all" : Number(event.target.value))}
              aria-label="Filter by employee"
              className="h-9 rounded-lg border bg-background px-2 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
            >
              <option value="all">Everyone</option>
              {(employee?.data ?? []).map((member) => <option key={member.id} value={member.id}>{member.name}</option>)}
            </select>
          </label>
        )}
      </div>

      <StatRow records={records} />

      {isError ? (
        <div className="grid place-items-center rounded-2xl border bg-background p-12 text-center">
          <p className="font-medium">Unable to load attendance</p>
          <Button className="mt-4" variant="outline" onPress={() => refetch()}>Retry</Button>
        </div>
      ) : (
        <div className={`overflow-hidden rounded-2xl border bg-background transition-opacity ${isPlaceholderData ? "opacity-60" : ""}`}>
          {isLoading ? (
            <div className="animate-pulse divide-y">
              {[1, 2, 3].map((row) => <div key={row} className="p-5"><div className="h-4 w-40 rounded bg-muted" /></div>)}
            </div>
          ) : records.length === 0 ? (
            <div className="grid place-items-center p-12 text-center">
              <div className="grid size-12 place-items-center rounded-full bg-muted"><CalendarCheck className="size-5 text-muted-foreground" /></div>
              <p className="mt-4 font-medium">No attendance this month</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[60rem] text-left text-sm">
                <thead className="border-b bg-muted/30 text-xs text-muted-foreground">
                  <tr>
                    <th className="px-5 py-3 font-medium">Date</th>
                    {seesEveryone && <th className="px-5 py-3 font-medium">Employee</th>}
                    <th className="px-5 py-3 font-medium">In</th>
                    <th className="px-5 py-3 font-medium">Out</th>
                    {/* Where the employee was, as opposed to the In and Out
                        columns, which are in the reader's own zone. */}
                    <th className="px-5 py-3 font-medium">Timezone</th>
                    <th className="px-5 py-3 font-medium">Worked</th>
                    <th className="px-5 py-3 font-medium">Status</th>
                    <th className="px-5 py-3 font-medium">Where</th>
                    <th className="px-5 py-3"><span className="sr-only">Actions</span></th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {records.map((record) => (
                    <Row
                      key={record.id}
                      record={record}
                      showEmployee={seesEveryone}
                      onApprove={can("attendance.edit") && record.requires_approval ? () => void approveRecord(record) : undefined}
                    />
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}
    </div>
  )
}

function StatRow({ records }: { records: AttendanceRecord[] }) {
  const count = (status: AttendanceStatus) => records.filter((record) => record.status === status).length
  const remote = records.filter((record) => record.work_mode === "remote").length
  const worked = records.reduce((total, record) => total + record.worked_minutes, 0)

  return (
    <section className="grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
      <Stat label="Present" value={count("present")} />
      <Stat label="Late" value={count("late")} tone="warning" />
      <Stat label="Half day" value={count("half_day")} />
      <Stat label="Remote" value={remote} />
      <Stat label="Hours" value={formatMinutes(worked)} />
    </section>
  )
}

function Stat({ label, value, tone }: { label: string; value: number | string; tone?: "warning" }) {
  const highlight = tone === "warning" && value !== 0

  return (
    <div className="rounded-2xl border bg-background p-4">
      <p className="text-xs font-medium text-muted-foreground">{label}</p>
      <p className={`mt-2 text-xl font-semibold tabular-nums ${highlight ? "text-amber-600 dark:text-amber-400" : ""}`}>{value}</p>
    </div>
  )
}

function Row({ record, showEmployee, onApprove }: { record: AttendanceRecord; showEmployee: boolean; onApprove?: () => void }) {
  const DeviceIcon = record.device.type === "mobile" ? Smartphone : record.device.type === "tablet" ? Tablet : Monitor
  const { latitude, longitude, office } = record.check_in_location

  return (
    <tr className="transition-colors hover:bg-muted/20">
      <td className="px-5 py-4">
        <span className="font-medium">{new Date(record.work_date).toLocaleDateString(undefined, { day: "numeric", month: "short" })}</span>
        <span className="ml-2 text-xs text-muted-foreground">{workModeLabels[record.work_mode]}</span>
      </td>
      {showEmployee && <td className="px-5 py-4 text-muted-foreground">{record.employee_name ?? "—"}</td>}
      <td className="px-5 py-4 tabular-nums">{formatRecordedTime(record.check_in_at, record.check_in, record.timezone)}</td>
      <td className="px-5 py-4 tabular-nums">{formatRecordedTime(record.check_out_at, record.check_out, record.timezone)}</td>
      <td className="px-5 py-4 text-xs whitespace-nowrap text-muted-foreground">
        {/* The full identifier is too wide for the column but worth keeping. */}
        <span title={record.timezone ?? undefined}>{compactTimezone(record.timezone, record.check_in_at)}</span>
      </td>
      <td className="px-5 py-4 tabular-nums">
        {record.check_out ? record.worked_hours : "—"}
        {record.overtime_minutes > 0 && (
          <span className="ml-1 text-xs text-emerald-600 dark:text-emerald-400">+{formatMinutes(record.overtime_minutes)}</span>
        )}
      </td>
      <td className="px-5 py-4">
        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${attendanceStatusStyles[record.status]}`}>
          {attendanceStatusLabels[record.status]}
        </span>
        {record.late_minutes > 0 && <span className="ml-2 text-xs text-muted-foreground">{formatMinutes(record.late_minutes)}</span>}
      </td>
      <td className="px-5 py-4 text-xs text-muted-foreground">
        <span className="inline-flex items-center gap-1.5">
          <DeviceIcon className="size-3.5" />
          {record.device.browser ?? "Unknown"}
        </span>
        {latitude != null && longitude != null && (
          <a
            href={mapsLink(latitude, longitude)}
            target="_blank"
            rel="noopener noreferrer"
            className="ml-2 inline-flex items-center gap-1 text-primary hover:underline"
          >
            <MapPin className="size-3" />{office ?? "Map"}
          </a>
        )}
      </td>
      <td className="px-5 py-4">
        <div className="flex justify-end">
          {record.requires_approval && (
            onApprove
              ? <Button variant="outline" size="sm" onPress={onApprove}><Check />Approve</Button>
              : <span className="rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-600 dark:text-amber-400">Awaiting approval</span>
          )}
        </div>
      </td>
    </tr>
  )
}
