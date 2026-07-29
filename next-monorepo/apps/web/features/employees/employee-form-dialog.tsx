"use client"

import { X } from "lucide-react"
import { useEffect } from "react"
import { useForm } from "react-hook-form"
import { Controller } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { Button } from "@workspace/ui/components/button"
import { FormField } from "@/components/ui/form-fields"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import { useEmployeeMutations } from "@/hooks/use-employees"
import { employeeSchema, type EmployeeFormValues } from "@/features/employees/schemas"
import type { Employee } from "@/types/employee"
import { SelectField } from "@/components/ui/select-field"
import { StatusSwitch } from "@/components/ui/status-switch"

export function EmployeeFormDialog({ employee, onClose }: { employee?: Employee | null; onClose: () => void }) {
  const { toast } = useToast(); const { create, update } = useEmployeeMutations(); const editing = Boolean(employee)
  const { register, control, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm<EmployeeFormValues>({ resolver: zodResolver(employeeSchema), defaultValues: { name: employee?.name ?? "", email: employee?.email ?? "", phone: employee?.phone ?? "", role: employee?.role ?? "employee", status: employee?.status ?? "active" } })
  useEffect(() => { reset({ name: employee?.name ?? "", email: employee?.email ?? "", phone: employee?.phone ?? "", role: employee?.role ?? "employee", status: employee?.status ?? "active" }) }, [reset, employee])
  const onSubmit = async (input: EmployeeFormValues) => { try { if (employee) await update.mutateAsync({ id: employee.id, input }); else await create.mutateAsync(input); toast({ tone: "success", title: editing ? "Employee updated" : "Employee added" }); onClose() } catch (error) { toast({ tone: "error", title: editing ? "Unable to update employee" : "Unable to add employee", description: getApiErrorMessage(error) }) } }
  return <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose() }}><div className="w-full max-w-lg rounded-2xl border bg-background p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="employee-dialog-title"><div className="flex items-start justify-between"><div><h2 id="employee-dialog-title" className="text-lg font-semibold">{editing ? "Edit employee" : "Add employee"}</h2><p className="mt-1 text-sm text-muted-foreground">{editing ? "Update this team member’s details." : "Add someone to your workspace directory."}</p></div><Button variant="ghost" size="icon-sm" aria-label="Close dialog" onPress={onClose}><X /></Button></div><form className="mt-6 grid gap-4" onSubmit={handleSubmit(onSubmit)} noValidate><FormField label="Full name" placeholder="Ada Lovelace" autoComplete="name" error={errors.name?.message} {...register("name")} /><FormField label="Email address" type="email" placeholder="ada@company.com" autoComplete="email" error={errors.email?.message} {...register("email")} /><FormField label="Phone number" type="tel" placeholder="Optional" autoComplete="tel" error={errors.phone?.message} {...register("phone")} /><Controller name="role" control={control} render={({ field }) => <SelectField label="Role" value={field.value} onChange={field.onChange} options={[{ id: "employee", label: "Employee" }, { id: "manager", label: "Manager" }, { id: "admin", label: "Admin" }]} />} /><Controller name="status" control={control} render={({ field }) => <div className="grid gap-2"><span className="text-sm font-medium">Status</span><StatusSwitch value={field.value} onChange={field.onChange} /></div>} />{errors.role && <p className="text-xs text-destructive">{errors.role.message}</p>}{errors.status && <p className="text-xs text-destructive">{errors.status.message}</p>}<div className="mt-2 flex justify-end gap-2"><Button type="button" variant="outline" onPress={onClose}>Cancel</Button><Button type="submit" isDisabled={isSubmitting || create.isPending || update.isPending}>{isSubmitting || create.isPending || update.isPending ? "Saving…" : editing ? "Save changes" : "Add employee"}</Button></div></form></div></div>
}
