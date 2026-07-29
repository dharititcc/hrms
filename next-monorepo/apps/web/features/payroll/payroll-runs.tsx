"use client"

import { AlertTriangle, ArrowLeft, Ban, CalendarRange, Check, Play, RefreshCw, Send, Trash2, Wallet } from "lucide-react"
import { useState } from "react"
import { Button } from "@workspace/ui/components/button"
import { GenerateRunDialog } from "@/features/payroll/generate-run-dialog"
import { PaymentDialog } from "@/features/payroll/payment-dialog"
import { useSalaryComponents } from "@/hooks/use-payroll-config"
import { usePayrollRun, usePayrollRunMutations, usePayrollRuns } from "@/hooks/use-payroll-runs"
import { usePermissions } from "@/hooks/use-permissions"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import {
  payrollRunStatusLabels, payrollRunStatusStyles, salarySlipStatusLabels,
  type PayrollRun, type SalarySlip,
} from "@/types/payroll"

export function PayrollRuns() {
  const [openRun, setOpenRun] = useState<number | null>(null)

  return openRun === null
    ? <RunList onOpen={setOpenRun} />
    : <RunDetail id={openRun} onBack={() => setOpenRun(null)} />
}

function RunList({ onOpen }: { onOpen: (id: number) => void }) {
  const { can } = usePermissions()
  const [page, setPage] = useState(1)
  const [generating, setGenerating] = useState(false)
  const { data, isLoading, isError, refetch, isPlaceholderData } = usePayrollRuns(page)
  const { remove } = usePayrollRunMutations()
  const { toast } = useToast()

  const runs = data?.data ?? []
  const meta = data?.meta

  const confirmDelete = async (run: PayrollRun) => {
    if (!window.confirm(`Delete ${run.title} and its ${run.slip_count} payslip(s)?`)) return
    try {
      await remove.mutateAsync(run.id)
      toast({ tone: "success", title: "Payroll run deleted" })
    } catch (error) {
      toast({ tone: "error", title: "Unable to delete run", description: getApiErrorMessage(error) })
    }
  }

  return (
    <div className="grid gap-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-muted-foreground">Each run produces a draft payslip per employee, calculated from their salary.</p>
        {can("payroll.generate") && <Button onPress={() => setGenerating(true)}><Play />Generate payroll</Button>}
      </div>

      {isError ? (
        <div className="grid place-items-center rounded-2xl border bg-background p-12 text-center">
          <p className="font-medium">Unable to load payroll runs</p>
          <Button className="mt-4" variant="outline" onPress={() => refetch()}>Retry</Button>
        </div>
      ) : (
        <div className={`overflow-hidden rounded-2xl border bg-background transition-opacity ${isPlaceholderData ? "opacity-60" : ""}`}>
          {isLoading ? (
            <div className="animate-pulse divide-y">
              {[1, 2, 3].map((row) => <div key={row} className="p-5"><div className="h-4 w-48 rounded bg-muted" /></div>)}
            </div>
          ) : runs.length === 0 ? (
            <div className="grid place-items-center p-12 text-center">
              <div className="grid size-12 place-items-center rounded-full bg-muted"><CalendarRange className="size-5 text-muted-foreground" /></div>
              <p className="mt-4 font-medium">No payroll runs yet</p>
              <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                Generating one produces a draft slip for every active employee who has a salary set.
              </p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[48rem] text-left text-sm">
                <thead className="border-b bg-muted/30 text-xs text-muted-foreground">
                  <tr>
                    <th className="px-5 py-3 font-medium">Run</th>
                    <th className="px-5 py-3 font-medium">Period</th>
                    <th className="px-5 py-3 font-medium">Slips</th>
                    <th className="px-5 py-3 font-medium">Net total</th>
                    <th className="px-5 py-3 font-medium">Status</th>
                    <th className="px-5 py-3"><span className="sr-only">Actions</span></th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {runs.map((run) => (
                    <tr key={run.id} className="transition-colors hover:bg-muted/20">
                      <td className="px-5 py-4">
                        <button type="button" className="font-medium hover:underline" onClick={() => onOpen(run.id)}>{run.title}</button>
                        <p className="mt-0.5 text-xs text-muted-foreground">{run.currency_code}</p>
                      </td>
                      <td className="px-5 py-4 text-muted-foreground">{run.period_start} → {run.period_end}</td>
                      <td className="px-5 py-4 tabular-nums">{run.slip_count}</td>
                      <td className="px-5 py-4 font-medium tabular-nums">{run.currency_symbol}{Number(run.total_net).toLocaleString()}</td>
                      <td className="px-5 py-4">
                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${payrollRunStatusStyles[run.status]}`}>
                          {payrollRunStatusLabels[run.status]}
                        </span>
                      </td>
                      <td className="px-5 py-4">
                        <div className="flex justify-end gap-1">
                          <Button variant="outline" size="sm" onPress={() => onOpen(run.id)}>Open</Button>
                          {can("payroll.delete") && !run.is_locked && (
                            <Button variant="ghost" size="icon-sm" aria-label={`Delete ${run.title}`} onPress={() => void confirmDelete(run)}>
                              <Trash2 />
                            </Button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" isDisabled={page <= 1} onPress={() => setPage((current) => Math.max(1, current - 1))}>Previous</Button>
          <span className="text-xs text-muted-foreground">Page {meta.current_page} of {meta.last_page}</span>
          <Button variant="outline" size="sm" isDisabled={page >= meta.last_page} onPress={() => setPage((current) => current + 1)}>Next</Button>
        </div>
      )}

      {generating && <GenerateRunDialog onClose={() => setGenerating(false)} onGenerated={(id) => { setGenerating(false); onOpen(id) }} />}
    </div>
  )
}

function RunDetail({ id, onBack }: { id: number; onBack: () => void }) {
  const { can } = usePermissions()
  const { data: run, isLoading } = usePayrollRun(id)
  const { data: componentData } = useSalaryComponents({})
  const { regenerate, submit, approve, cancel } = usePayrollRunMutations()
  const { toast } = useToast()

  // staff id => component code => amount, as the generator expects it.
  const [manual, setManual] = useState<Record<number, Record<string, string>>>({})
  const [paying, setPaying] = useState<SalarySlip | null>(null)

  // Which lines are entered per payslip rather than derived. Progressive taxes
  // land here and generate as zero, so they need surfacing or a run silently
  // under-deducts.
  const manualCodes = new Set(
    (componentData?.data ?? []).filter((component) => component.calculation === "manual").map((component) => component.code),
  )

  if (isLoading || !run) {
    return <div className="h-64 animate-pulse rounded-2xl bg-muted" />
  }

  const slips = run.slips ?? []
  const canRecalculate = can("payroll.generate") && run.is_editable
  const canPay = can("payroll.pay") && (run.status === "approved" || run.status === "paid")

  const unfilled = slips.reduce((total, slip) => total + (slip.lines ?? [])
    .filter((line) => manualCodes.has(line.code) && Number(line.amount) === 0).length, 0)

  const transition = async (
    action: "submit" | "approve" | "cancel",
    mutation: { mutateAsync: (id: number) => Promise<unknown> },
    title: string,
    confirmation?: string,
  ) => {
    if (confirmation && !window.confirm(confirmation)) return
    try {
      await mutation.mutateAsync(run.id)
      toast({ tone: "success", title })
    } catch (error) {
      toast({ tone: "error", title: `Unable to ${action} this payroll`, description: getApiErrorMessage(error) })
    }
  }

  const recalculate = async () => {
    const amounts: Record<number, Record<string, number>> = {}

    for (const [staffId, codes] of Object.entries(manual)) {
      const filled = Object.entries(codes)
        .filter(([, value]) => value !== "" && !Number.isNaN(Number(value)))
        .map(([code, value]) => [code, Number(value)] as const)

      if (filled.length > 0) amounts[Number(staffId)] = Object.fromEntries(filled)
    }

    try {
      await regenerate.mutateAsync({
        id: run.id,
        input: {
          // Regenerating revalidates the whole run, so its own details go back.
          title: run.title,
          country: run.country,
          period_start: run.period_start,
          period_end: run.period_end,
          pay_date: run.pay_date,
          notes: run.notes,
          manual_amounts: amounts,
        },
      })
      toast({ tone: "success", title: "Payroll recalculated" })
    } catch (error) {
      toast({ tone: "error", title: "Unable to recalculate", description: getApiErrorMessage(error) })
    }
  }

  return (
    <div className="grid gap-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <Button variant="ghost" size="sm" className="-ml-2" onPress={onBack}><ArrowLeft />All runs</Button>
          <h2 className="mt-2 text-xl font-semibold">{run.title}</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            {run.period_start} → {run.period_end}
            {run.pay_date && ` · paid ${run.pay_date}`}
            <span className={`ml-2 rounded-full px-2 py-0.5 text-xs font-medium ${payrollRunStatusStyles[run.status]}`}>
              {payrollRunStatusLabels[run.status]}
            </span>
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          {canRecalculate && (
            <Button variant="outline" isDisabled={regenerate.isPending} onPress={() => void recalculate()}>
              <RefreshCw />{regenerate.isPending ? "Recalculating…" : "Recalculate"}
            </Button>
          )}

          {can("payroll.edit") && run.status === "draft" && run.slip_count > 0 && (
            <Button variant="outline" isDisabled={submit.isPending} onPress={() => void transition("submit", submit, "Sent for approval")}>
              <Send />Send for approval
            </Button>
          )}

          {can("payroll.approve") && run.is_editable && run.slip_count > 0 && (
            <Button
              isDisabled={approve.isPending}
              onPress={() => void transition(
                "approve", approve, "Payroll approved",
                unfilled > 0
                  ? `${unfilled} line(s) entered per payslip are still zero. Approving commits these figures and they cannot be recalculated afterwards. Continue?`
                  : "Approving commits these figures. The run cannot be recalculated or deleted afterwards. Continue?",
              )}
            >
              <Check />Approve
            </Button>
          )}

          {can("payroll.approve") && run.status !== "cancelled" && run.status !== "paid" && (
            <Button
              variant="outline"
              isDisabled={cancel.isPending}
              onPress={() => void transition("cancel", cancel, "Payroll cancelled", `Cancel ${run.title}?`)}
            >
              <Ban />Cancel run
            </Button>
          )}
        </div>
      </div>

      <section className="grid gap-4 sm:grid-cols-4">
        <Stat label="Payslips" value={String(run.slip_count)} />
        <Stat label="Earnings" value={`${run.currency_symbol}${Number(run.total_earnings).toLocaleString()}`} />
        <Stat label="Deductions" value={`${run.currency_symbol}${Number(run.total_deductions).toLocaleString()}`} />
        <Stat label="Net" value={`${run.currency_symbol}${Number(run.total_net).toLocaleString()}`} />
      </section>

      {run.is_locked && (
        <p className="rounded-2xl border bg-muted/30 p-4 text-sm text-muted-foreground">
          This run has been approved, so its figures are committed and can no longer be recalculated or deleted. People may already have
          been told what they are being paid.
        </p>
      )}

      {run.status === "cancelled" && (
        <p className="rounded-2xl border bg-muted/30 p-4 text-sm text-muted-foreground">
          This run was cancelled. Its payslips are kept for the record but nothing will be paid against them.
        </p>
      )}

      {unfilled > 0 && run.is_editable && (
        <p className="flex items-start gap-2 rounded-2xl border border-amber-500/30 bg-amber-500/5 p-4 text-sm">
          <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
          <span className="text-muted-foreground">
            {unfilled} line{unfilled === 1 ? "" : "s"} entered per payslip {unfilled === 1 ? "is" : "are"} still zero — progressive taxes
            are not calculated from a single rate, so they have to be filled in below and the run recalculated. Approving as-is would
            under-deduct.
          </span>
        </p>
      )}

      {slips.length === 0 ? (
        <div className="grid place-items-center rounded-2xl border bg-background p-12 text-center">
          <p className="font-medium">No payslips in this run</p>
          <p className="mt-1 max-w-md text-sm text-muted-foreground">
            No active employee has a salary set for {run.country}. Set one from the staff list, then recalculate.
          </p>
        </div>
      ) : (
        <div className="grid gap-3">
          {slips.map((slip) => (
            <SlipCard
              key={slip.id}
              slip={slip}
              manualCodes={manualCodes}
              editable={canRecalculate}
              values={manual[slip.staff_id] ?? {}}
              onChange={(code, value) => setManual((current) => ({
                ...current,
                [slip.staff_id]: { ...current[slip.staff_id], [code]: value },
              }))}
              onPay={canPay ? () => setPaying(slip) : undefined}
            />
          ))}
        </div>
      )}

      {paying && <PaymentDialog slip={paying} runId={run.id} canPay={canPay} onClose={() => setPaying(null)} />}
    </div>
  )
}

function SlipCard({ slip, manualCodes, editable, values, onChange, onPay }: {
  slip: SalarySlip
  manualCodes: Set<string>
  editable: boolean
  values: Record<string, string>
  onChange: (code: string, value: string) => void
  onPay?: () => void
}) {
  const [open, setOpen] = useState(false)
  const lines = slip.lines ?? []
  const symbol = slip.currency_symbol
  const settled = slip.status === "paid"

  return (
    <div className="overflow-hidden rounded-2xl border bg-background">
      <div className="flex flex-wrap items-center gap-3 p-4">
        <button
          type="button"
          onClick={() => setOpen((current) => !current)}
          aria-expanded={open}
          className="flex min-w-0 flex-1 flex-wrap items-center justify-between gap-3 text-left"
        >
          <div className="min-w-40">
            <p className="font-medium">{slip.staff_name ?? `Staff #${slip.staff_id}`}</p>
            <p className="mt-0.5 font-mono text-xs text-muted-foreground">{slip.slip_number}</p>
          </div>
          <div className="flex flex-wrap items-center gap-5 text-sm tabular-nums">
            <span className="text-muted-foreground">Gross {symbol}{Number(slip.gross_salary).toLocaleString()}</span>
            <span className="text-muted-foreground">Deductions {symbol}{Number(slip.total_deductions).toLocaleString()}</span>
            <span className="font-semibold">Net {symbol}{Number(slip.net_salary).toLocaleString()}</span>
          </div>
        </button>

        {onPay && (
          <div className="flex items-center gap-2">
            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${settled ? "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400" : "bg-amber-500/10 text-amber-600 dark:text-amber-400"}`}>
              {salarySlipStatusLabels[slip.status]}
            </span>
            <Button variant="outline" size="sm" onPress={onPay}><Wallet />{settled ? "Payments" : "Pay"}</Button>
          </div>
        )}
      </div>

      {open && (
        <div className="border-t">
          <table className="w-full text-left text-sm">
            <tbody className="divide-y">
              <tr>
                <td className="px-5 py-2.5 text-muted-foreground">Basic salary</td>
                <td className="px-5 py-2.5 text-right tabular-nums">{symbol}{Number(slip.basic_salary).toLocaleString()}</td>
              </tr>
              {lines.map((line) => {
                const isManual = manualCodes.has(line.code)

                return (
                  <tr key={line.code} className={line.type === "deduction" ? "text-muted-foreground" : ""}>
                    <td className="px-5 py-2.5">
                      {line.name}
                      {line.is_statutory && <span className="ml-2 text-xs text-amber-600 dark:text-amber-400">Statutory</span>}
                      {isManual && Number(line.amount) === 0 && <span className="ml-2 text-xs text-amber-600 dark:text-amber-400">Not set</span>}
                    </td>
                    <td className="px-5 py-2.5 text-right tabular-nums">
                      {isManual && editable ? (
                        <input
                          type="number"
                          step="0.01"
                          min="0"
                          aria-label={`${line.name} for ${slip.staff_name ?? `staff ${slip.staff_id}`}`}
                          placeholder={Number(line.amount).toFixed(2)}
                          value={values[line.code] ?? ""}
                          onChange={(event) => onChange(line.code, event.target.value)}
                          className="h-8 w-28 rounded-lg border bg-background px-2 text-right text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
                        />
                      ) : (
                        <>{line.type === "deduction" ? "−" : ""}{symbol}{Number(line.amount).toLocaleString()}</>
                      )}
                    </td>
                  </tr>
                )
              })}
              <tr className="bg-muted/30 font-medium">
                <td className="px-5 py-2.5">Net pay</td>
                <td className="px-5 py-2.5 text-right tabular-nums">{symbol}{Number(slip.net_salary).toLocaleString()}</td>
              </tr>
            </tbody>
          </table>

          {Number(slip.employer_contributions) > 0 && (
            <p className="border-t px-5 py-2.5 text-xs text-muted-foreground">
              Employer contributions of {symbol}{Number(slip.employer_contributions).toLocaleString()} are paid on top and do not reduce net pay.
            </p>
          )}
        </div>
      )}
    </div>
  )
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-2xl border bg-background p-4">
      <p className="text-xs font-medium text-muted-foreground">{label}</p>
      <p className="mt-2 text-lg font-semibold tabular-nums">{value}</p>
    </div>
  )
}
