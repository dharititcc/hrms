"use client"

import { DollarSign, Info } from "lucide-react"
import { Button } from "@workspace/ui/components/button"
import { usePayroll } from "@/hooks/use-phase-three"

/**
 * Salary slips produced by payroll runs.
 *
 * Read-only. The previous form posted figures straight into a flat table,
 * which left no salary history and no breakdown. Slips now come from an
 * employee's salary assignment, so the create path returns with payroll run
 * generation rather than being restored here.
 */
export function PayrollModule() {
  const { data, isLoading, isError, refetch } = usePayroll()
  const slips = data?.data ?? []

  return (
    <div className="mx-auto grid max-w-6xl gap-6">
      <div>
        <p className="text-sm font-medium text-muted-foreground">Finance</p>
        <h1 className="mt-2 text-2xl font-semibold tracking-tight">Payroll</h1>
        <p className="mt-2 text-sm text-muted-foreground">Salary slips issued by payroll runs.</p>
      </div>

      <div className="flex items-start gap-3 rounded-2xl border border-sky-500/30 bg-sky-500/5 p-4 text-sm">
        <Info className="mt-0.5 size-4 shrink-0 text-sky-600 dark:text-sky-400" />
        <p className="text-muted-foreground">
          Payroll now runs from each employee&rsquo;s salary structure, so slips carry a full earnings and
          deductions breakdown and a complete salary history. Generating a run is not built yet, so this
          list is read-only for now.
        </p>
      </div>

      {isError ? (
        <div className="grid place-items-center rounded-2xl border bg-background p-12 text-center">
          <p className="font-medium">Unable to load salary slips</p>
          <Button className="mt-4" variant="outline" onPress={() => refetch()}>Retry</Button>
        </div>
      ) : (
        <div className="overflow-hidden rounded-2xl border bg-background">
          {isLoading ? (
            <div className="animate-pulse divide-y">
              {[1, 2, 3].map((row) => <div key={row} className="h-16 p-5"><div className="h-4 w-48 rounded bg-muted" /></div>)}
            </div>
          ) : slips.length === 0 ? (
            <div className="grid place-items-center p-12 text-center">
              <div className="grid size-12 place-items-center rounded-full bg-muted"><DollarSign className="size-5 text-muted-foreground" /></div>
              <p className="mt-4 font-medium">No salary slips yet</p>
              <p className="mt-1 text-sm text-muted-foreground">They appear here once a payroll run is generated.</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[48rem] text-left text-sm">
                <thead className="border-b bg-muted/30 text-xs text-muted-foreground">
                  <tr>
                    <th className="px-5 py-3 font-medium">Slip</th>
                    <th className="px-5 py-3 font-medium">Employee</th>
                    <th className="px-5 py-3 font-medium">Period</th>
                    <th className="px-5 py-3 font-medium">Gross</th>
                    <th className="px-5 py-3 font-medium">Deductions</th>
                    <th className="px-5 py-3 font-medium">Net</th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {slips.map((slip) => (
                    <tr key={slip.id} className="transition-colors hover:bg-muted/20">
                      <td className="px-5 py-4 font-medium">{slip.slip_number}</td>
                      <td className="px-5 py-4 text-muted-foreground">{slip.staff_name ?? "—"}</td>
                      <td className="px-5 py-4 text-muted-foreground">{slip.period ?? "—"}</td>
                      <td className="px-5 py-4 tabular-nums">{slip.currency_symbol}{slip.gross_salary}</td>
                      <td className="px-5 py-4 tabular-nums text-muted-foreground">{slip.currency_symbol}{slip.total_deductions}</td>
                      <td className="px-5 py-4 font-medium tabular-nums">{slip.currency_symbol}{slip.net_salary}</td>
                    </tr>
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
