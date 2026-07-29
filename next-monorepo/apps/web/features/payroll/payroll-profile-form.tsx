"use client"

import { AlertTriangle, Landmark, ShieldCheck } from "lucide-react"
import { useForm, useWatch } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { z } from "zod"
import { Button } from "@workspace/ui/components/button"
import { FormField } from "@/components/ui/form-fields"
import { useSalaryStructures } from "@/hooks/use-payroll-config"
import { usePayrollProfile, usePayrollProfileMutations } from "@/hooks/use-payroll-runs"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import type { PayrollProfile, PayrollProfileInput } from "@/types/payroll"

const profileSchema = z.object({
  country: z.string().min(2, "Choose a country"),
  bank_name: z.string().max(255).optional(),
  account_holder_name: z.string().max(255).optional(),
  account_number: z.string().max(64).optional(),
  iban: z.string().max(34).optional(),
  bank_code: z.string().max(40).optional(),
  swift_code: z.string().max(11).optional(),
  tax_identifier: z.string().max(64).optional(),
  tax_regime: z.string().max(100).optional(),
  tax_notes: z.string().max(2000).optional(),
})

type ProfileFormValues = z.infer<typeof profileSchema>

/**
 * Bank and tax details.
 *
 * The account number, IBAN and tax identifier only ever arrive masked, so
 * their fields start blank with the masked value as placeholder: leaving one
 * alone keeps what is stored, and typing replaces it. That is also why they
 * are not sent at all unless touched.
 */
export function PayrollProfileForm({ employeeId, employeeName, canEdit }: {
  employeeId: number
  employeeName: string
  canEdit: boolean
}) {
  const { data: profile, isLoading } = usePayrollProfile(employeeId)
  const { data: structureData } = useSalaryStructures()
  const { save } = usePayrollProfileMutations(employeeId)
  const { toast } = useToast()

  const countries = structureData?.meta.countries ?? []

  if (isLoading) return <div className="mt-6 h-40 animate-pulse rounded-xl bg-muted" />

  return (
    <Form
      // Remount once the stored profile arrives, so defaults come from it.
      key={profile?.id ?? "new"}
      profile={profile ?? null}
      employeeName={employeeName}
      countries={countries}
      canEdit={canEdit}
      onSave={async (input: PayrollProfileInput) => {
        try {
          await save.mutateAsync(input)
          toast({ tone: "success", title: "Payroll details saved" })
        } catch (error) {
          toast({ tone: "error", title: "Unable to save payroll details", description: getApiErrorMessage(error) })
        }
      }}
      saving={save.isPending}
    />
  )
}

function Form({ profile, employeeName, countries, canEdit, onSave, saving }: {
  profile: PayrollProfile | null
  employeeName: string
  countries: { value: string; label: string; currency_code: string }[]
  canEdit: boolean
  onSave: (input: PayrollProfileInput) => Promise<void>
  saving: boolean
}) {
  const { register, handleSubmit, control, formState: { errors, isSubmitting, dirtyFields } } = useForm<ProfileFormValues>({
    resolver: zodResolver(profileSchema),
    defaultValues: {
      country: profile?.country ?? countries[0]?.value ?? "IN",
      bank_name: profile?.bank_name ?? "",
      account_holder_name: profile?.account_holder_name ?? "",
      bank_code: profile?.bank_code ?? "",
      swift_code: profile?.swift_code ?? "",
      tax_regime: profile?.tax_regime ?? "",
      tax_notes: profile?.tax_notes ?? "",
      // Never prefilled: the server does not send them back.
      account_number: "",
      iban: "",
      tax_identifier: "",
    },
  })

  const country = useWatch({ control, name: "country" })
  const selected = countries.find((option) => option.value === country)

  // Labels follow the selected country before it is even saved, so the fields
  // are named the way the person filling them in would name them.
  const labels = profile && profile.country === country
    ? profile.labels
    : { tax_identifier: "Tax identifier", bank_code: "Bank code" }

  const submit = async (values: ProfileFormValues) => {
    const input: PayrollProfileInput = {
      country: values.country,
      bank_name: values.bank_name ?? "",
      account_holder_name: values.account_holder_name ?? "",
      bank_code: values.bank_code ?? "",
      swift_code: values.swift_code ?? "",
      tax_regime: values.tax_regime ?? "",
      tax_notes: values.tax_notes ?? "",
    }

    /*
    | Only send a secret the user actually typed into. Sending an untouched
    | empty field would clear a stored value that was never shown, which is
    | the one mistake this form must not make.
    */
    for (const key of ["account_number", "iban", "tax_identifier"] as const) {
      if (dirtyFields[key]) input[key] = values[key] ?? ""
    }

    await onSave(input)
  }

  return (
    <form className="mt-6 grid gap-4 rounded-xl border p-4" onSubmit={handleSubmit(submit)} noValidate>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="flex items-center gap-2 text-sm font-medium"><Landmark className="size-4" />Bank &amp; tax details</p>
        {profile && (
          profile.is_payable ? (
            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
              <ShieldCheck className="size-3" />Payable
            </span>
          ) : (
            <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-600 dark:text-amber-400">
              <AlertTriangle className="size-3" />Not payable yet
            </span>
          )
        )}
      </div>

      {profile && !profile.is_payable && (
        <p className="text-xs text-muted-foreground">
          A payslip can still be generated, but there is no account to send the money to. An account holder name and either an account
          number or an IBAN are what make {employeeName} payable.
        </p>
      )}

      <label className="grid gap-2 text-sm font-medium">
        Country
        <select
          className="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
          disabled={!canEdit}
          {...register("country")}
        >
          {countries.map((option) => <option key={option.value} value={option.value}>{option.label} ({option.currency_code})</option>)}
        </select>
      </label>

      <div className="grid gap-4 sm:grid-cols-2">
        <FormField label="Bank name" placeholder="State Bank" readOnly={!canEdit} error={errors.bank_name?.message} {...register("bank_name")} />
        <FormField label="Account holder" placeholder={employeeName} readOnly={!canEdit} error={errors.account_holder_name?.message} {...register("account_holder_name")} />
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <FormField
          label="Account number"
          placeholder={profile?.account_number_masked ?? "Not set"}
          readOnly={!canEdit}
          error={errors.account_number?.message}
          {...register("account_number")}
        />
        <FormField
          label="IBAN"
          placeholder={profile?.iban_masked ?? "Not set"}
          readOnly={!canEdit}
          error={errors.iban?.message}
          {...register("iban")}
        />
      </div>
      {(profile?.has_account_number || profile?.has_iban) && (
        <p className="-mt-2 text-xs text-muted-foreground">
          Stored values are shown only as their last four digits and are never sent back to the browser. Leave a field blank to keep it,
          or type to replace it.
        </p>
      )}

      <div className="grid gap-4 sm:grid-cols-2">
        <FormField label={labels.bank_code} placeholder="Optional" readOnly={!canEdit} error={errors.bank_code?.message} {...register("bank_code")} />
        <FormField label="SWIFT / BIC" placeholder="Optional" readOnly={!canEdit} error={errors.swift_code?.message} {...register("swift_code")} />
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <FormField
          label={labels.tax_identifier}
          placeholder={profile?.tax_identifier_masked ?? "Not set"}
          readOnly={!canEdit}
          error={errors.tax_identifier?.message}
          {...register("tax_identifier")}
        />
        <FormField label="Tax regime" placeholder="Optional" readOnly={!canEdit} error={errors.tax_regime?.message} {...register("tax_regime")} />
      </div>

      <label className="grid gap-2 text-sm font-medium">
        Notes
        <textarea
          className="min-h-20 rounded-lg border bg-background p-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
          placeholder="Anything payroll needs to know about this person's tax position"
          readOnly={!canEdit}
          {...register("tax_notes")}
        />
      </label>

      {canEdit && (
        <div className="flex items-center justify-between gap-3">
          <p className="text-xs text-muted-foreground">
            Changes are recorded on the activity timeline. {selected?.label ?? "This country"} sets the currency payroll uses.
          </p>
          <Button type="submit" isDisabled={isSubmitting || saving}>
            {isSubmitting || saving ? "Saving…" : "Save details"}
          </Button>
        </div>
      )}
    </form>
  )
}
