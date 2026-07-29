"use client"

import { Undo2, X } from "lucide-react"
import { useForm } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { z } from "zod"
import { Button } from "@workspace/ui/components/button"
import { FormField } from "@/components/ui/form-fields"
import { useSlipPaymentMutations, useSlipPayments } from "@/hooks/use-payroll-runs"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import { paymentMethodLabels, type PaymentMethod, type SalarySlip } from "@/types/payroll"

const paymentSchema = z.object({
  amount: z.string().refine((v) => Number(v) > 0, "Enter an amount greater than zero"),
  paid_at: z.string().optional(),
  method: z.enum(["bank_transfer", "cash", "cheque", "card", "other"]),
  reference: z.string().max(255).optional(),
  note: z.string().max(1000).optional(),
})

type PaymentFormValues = z.infer<typeof paymentSchema>

/**
 * Money recorded against one payslip.
 *
 * Per slip rather than per run because a failed transfer leaves one employee
 * unpaid while everybody else is settled, and because a payment can arrive in
 * instalments.
 */
export function PaymentDialog({ slip, runId, canPay, onClose }: {
  slip: SalarySlip
  runId: number
  canPay: boolean
  onClose: () => void
}) {
  const { data, isLoading } = useSlipPayments(slip.id)
  const { record, reverse } = useSlipPaymentMutations(slip.id, runId)
  const { toast } = useToast()

  const payments = data?.data ?? []
  const outstanding = data?.meta.outstanding ?? slip.outstanding
  const symbol = slip.currency_symbol

  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm<PaymentFormValues>({
    resolver: zodResolver(paymentSchema),
    defaultValues: { amount: "", paid_at: "", method: "bank_transfer", reference: "", note: "" },
  })

  const onSubmit = async (values: PaymentFormValues) => {
    try {
      await record.mutateAsync({
        amount: Number(values.amount),
        paid_at: values.paid_at || null,
        method: values.method as PaymentMethod,
        reference: values.reference || null,
        note: values.note || null,
      })
      reset({ amount: "", paid_at: "", method: values.method, reference: "", note: "" })
      toast({ tone: "success", title: "Payment recorded" })
    } catch (error) {
      toast({ tone: "error", title: "Unable to record payment", description: getApiErrorMessage(error) })
    }
  }

  const reversePayment = async (id: number) => {
    if (!window.confirm("Reverse this payment? The payslip and its run are restated.")) return
    try {
      await reverse.mutateAsync(id)
      toast({ tone: "success", title: "Payment reversed" })
    } catch (error) {
      toast({ tone: "error", title: "Unable to reverse payment", description: getApiErrorMessage(error) })
    }
  }

  return (
    <div
      className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4"
      role="presentation"
      onMouseDown={(event) => { if (event.target === event.currentTarget) onClose() }}
    >
      <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border bg-background p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="payment-dialog-title">
        <div className="flex items-start justify-between">
          <div>
            <h2 id="payment-dialog-title" className="text-lg font-semibold">Payments — {slip.employee_name ?? slip.slip_number}</h2>
            <p className="mt-1 text-sm text-muted-foreground">{slip.slip_number}</p>
          </div>
          <Button variant="ghost" size="icon-sm" aria-label="Close dialog" onPress={onClose}><X /></Button>
        </div>

        <div className="mt-6 grid grid-cols-3 gap-3 rounded-xl border p-4 text-sm">
          <div>
            <p className="text-xs text-muted-foreground">Net pay</p>
            <p className="mt-1 font-medium tabular-nums">{symbol}{Number(slip.net_salary).toLocaleString()}</p>
          </div>
          <div>
            <p className="text-xs text-muted-foreground">Paid</p>
            <p className="mt-1 font-medium tabular-nums">{symbol}{Number(data?.meta.paid_amount ?? slip.paid_amount).toLocaleString()}</p>
          </div>
          <div>
            <p className="text-xs text-muted-foreground">Outstanding</p>
            <p className={`mt-1 font-medium tabular-nums ${outstanding > 0 ? "text-amber-600 dark:text-amber-400" : "text-emerald-600 dark:text-emerald-400"}`}>
              {symbol}{outstanding.toLocaleString()}
            </p>
          </div>
        </div>

        {canPay && outstanding > 0 && (
          <form className="mt-6 grid gap-4" onSubmit={handleSubmit(onSubmit)} noValidate>
            <div className="grid gap-4 sm:grid-cols-2">
              <FormField label="Amount" type="number" step="0.01" min="0.01" error={errors.amount?.message} {...register("amount")} />
              <FormField label="Paid on" type="date" error={errors.paid_at?.message} {...register("paid_at")} />
            </div>
            <p className="-mt-2 text-xs text-muted-foreground">
              Leave the date blank for today. Backdating is fine — payroll is often recorded after the money has moved — but a future
              date is refused.
            </p>

            <div className="grid gap-4 sm:grid-cols-2">
              <label className="grid gap-2 text-sm font-medium">
                Method
                <select className="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20" {...register("method")}>
                  {(Object.keys(paymentMethodLabels) as PaymentMethod[]).map((method) => (
                    <option key={method} value={method}>{paymentMethodLabels[method]}</option>
                  ))}
                </select>
              </label>
              <FormField label="Reference" placeholder="Transaction ID" error={errors.reference?.message} {...register("reference")} />
            </div>

            <div className="flex justify-end">
              <Button type="submit" isDisabled={isSubmitting || record.isPending}>
                {isSubmitting || record.isPending ? "Recording…" : "Record payment"}
              </Button>
            </div>
          </form>
        )}

        {outstanding <= 0 && (
          <p className="mt-6 rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-3 text-sm text-muted-foreground">
            This payslip is settled in full.
          </p>
        )}

        <section className="mt-6">
          <h3 className="text-sm font-semibold">Recorded payments</h3>
          {isLoading ? (
            <div className="mt-3 h-12 animate-pulse rounded-xl bg-muted" />
          ) : payments.length === 0 ? (
            <p className="mt-3 rounded-xl border border-dashed p-4 text-center text-xs text-muted-foreground">Nothing paid yet.</p>
          ) : (
            <ul className="mt-3 grid gap-2">
              {payments.map((payment) => (
                <li key={payment.id} className="flex flex-wrap items-center justify-between gap-2 rounded-xl border px-3 py-2 text-sm">
                  <div>
                    <span className="font-medium tabular-nums">{symbol}{Number(payment.amount).toLocaleString()}</span>
                    <span className="ml-2 text-xs text-muted-foreground">
                      {new Date(payment.paid_at).toLocaleDateString()} · {paymentMethodLabels[payment.method]}
                      {payment.reference && ` · ${payment.reference}`}
                    </span>
                  </div>
                  {canPay && (
                    <Button variant="ghost" size="icon-sm" aria-label="Reverse this payment" onPress={() => void reversePayment(payment.id)}>
                      <Undo2 />
                    </Button>
                  )}
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>
    </div>
  )
}
