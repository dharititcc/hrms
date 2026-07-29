"use client"

import { Building2, Crosshair, Edit3, MapPin, Plus, Trash2, X } from "lucide-react"
import { useState } from "react"
import { useForm } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { z } from "zod"
import { Button } from "@workspace/ui/components/button"
import { FormField } from "@/components/ui/form-fields"
import { useAttendanceLocationMutations, useAttendanceLocations } from "@/hooks/use-attendance"
import { usePermissions } from "@/hooks/use-permissions"
import { capturePosition, mapsLink } from "@/services/attendance-service"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import type { AttendanceLocation } from "@/types/attendance"

const locationSchema = z.object({
  name: z.string().trim().min(2, "Give the office a name"),
  address: z.string().max(255).optional(),
  latitude: z.string().refine((v) => v !== "" && Math.abs(Number(v)) <= 90, "Latitude must be between -90 and 90"),
  longitude: z.string().refine((v) => v !== "" && Math.abs(Number(v)) <= 180, "Longitude must be between -180 and 180"),
  // Matches the server's floor: consumer GPS is rarely better than 20m.
  radius_metres: z.string().refine((v) => Number(v) >= 20 && Number(v) <= 50000, "Radius must be between 20m and 50km"),
  is_active: z.boolean().optional(),
})

type LocationFormValues = z.infer<typeof locationSchema>

/**
 * Offices that geofenced check-ins are measured against.
 *
 * Only shown to those who can edit attendance: for everyone else the fences
 * are just something that happens.
 */
export function OfficeLocations() {
  const { can } = usePermissions()
  const { data, isLoading } = useAttendanceLocations()
  const { remove } = useAttendanceLocationMutations()
  const { toast } = useToast()
  const [editing, setEditing] = useState<AttendanceLocation | null | undefined>(undefined)

  if (!can("attendance.edit")) return null

  const locations = data?.data ?? []
  const enforcing = data?.meta.enforcement_enabled ?? false

  const confirmDelete = async (location: AttendanceLocation) => {
    if (!window.confirm(`Delete ${location.name}? Attendance already recorded there keeps its history.`)) return
    try {
      await remove.mutateAsync(location.id)
      toast({ tone: "success", title: "Office deleted" })
    } catch (error) {
      toast({ tone: "error", title: "Unable to delete office", description: getApiErrorMessage(error) })
    }
  }

  return (
    <section className="rounded-2xl border bg-background p-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="flex items-center gap-2 text-sm font-semibold"><Building2 className="size-4" />Office locations</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            {locations.length === 0
              ? "With no offices defined, check-ins are never geofenced."
              : enforcing
                ? "Check-ins outside every office are refused."
                : "Check-ins outside every office are allowed, but flagged for approval."}
          </p>
        </div>
        <Button size="sm" onPress={() => setEditing(null)}><Plus />Add office</Button>
      </div>

      {isLoading ? (
        <div className="mt-4 grid gap-2">{[1, 2].map((row) => <div key={row} className="h-14 animate-pulse rounded-xl bg-muted" />)}</div>
      ) : locations.length === 0 ? (
        <p className="mt-4 rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
          No offices yet. Add one to start measuring check-ins against it.
        </p>
      ) : (
        <ul className="mt-4 grid gap-2">
          {locations.map((location) => (
            <li key={location.id} className="flex flex-wrap items-center gap-3 rounded-xl border p-3">
              <div className="min-w-48 flex-1">
                <p className="text-sm font-medium">
                  {location.name}
                  {!location.is_active && <span className="ml-2 rounded-full bg-muted px-2 py-0.5 text-[0.7rem] text-muted-foreground">Inactive</span>}
                </p>
                <p className="mt-0.5 text-xs text-muted-foreground">{location.address ?? "No address"}</p>
              </div>

              <span className="text-xs text-muted-foreground">{location.radius_metres}m radius</span>

              <a
                href={mapsLink(location.latitude, location.longitude)}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
              >
                <MapPin className="size-3" />Map
              </a>

              <div className="flex gap-1">
                <Button variant="ghost" size="icon-sm" aria-label={`Edit ${location.name}`} onPress={() => setEditing(location)}><Edit3 /></Button>
                <Button variant="ghost" size="icon-sm" aria-label={`Delete ${location.name}`} onPress={() => void confirmDelete(location)}><Trash2 /></Button>
              </div>
            </li>
          ))}
        </ul>
      )}

      {editing !== undefined && (
        <OfficeDialog
          // Remounting per office is simpler than resetting the form on change.
          key={editing?.id ?? "new"}
          location={editing}
          defaultRadius={data?.meta.default_radius_metres ?? 200}
          onClose={() => setEditing(undefined)}
        />
      )}
    </section>
  )
}

function OfficeDialog({ location, defaultRadius, onClose }: {
  location: AttendanceLocation | null
  defaultRadius: number
  onClose: () => void
}) {
  const { create, update } = useAttendanceLocationMutations()
  const { toast } = useToast()
  const [locating, setLocating] = useState(false)
  const editingExisting = Boolean(location)

  const { register, handleSubmit, setValue, formState: { errors, isSubmitting } } = useForm<LocationFormValues>({
    resolver: zodResolver(locationSchema),
    defaultValues: {
      name: location?.name ?? "",
      address: location?.address ?? "",
      latitude: location ? String(location.latitude) : "",
      longitude: location ? String(location.longitude) : "",
      radius_metres: String(location?.radius_metres ?? defaultRadius),
      is_active: location?.is_active ?? true,
    },
  })

  /** Typing coordinates by hand is error-prone, so offer the browser's fix. */
  const fillFromCurrentPosition = async () => {
    setLocating(true)
    try {
      const position = await capturePosition()
      if (position === null) {
        toast({ tone: "error", title: "Couldn’t get your location", description: "Enter the coordinates manually instead." })
        return
      }
      setValue("latitude", String(position.latitude), { shouldValidate: true })
      setValue("longitude", String(position.longitude), { shouldValidate: true })
    } finally {
      setLocating(false)
    }
  }

  const onSubmit = async (values: LocationFormValues) => {
    const input = {
      name: values.name,
      address: values.address || null,
      latitude: Number(values.latitude),
      longitude: Number(values.longitude),
      radius_metres: Number(values.radius_metres),
      is_active: values.is_active ?? true,
    }

    try {
      if (location) await update.mutateAsync({ id: location.id, input })
      else await create.mutateAsync(input)
      toast({ tone: "success", title: editingExisting ? "Office updated" : "Office added" })
      onClose()
    } catch (error) {
      toast({ tone: "error", title: "Unable to save office", description: getApiErrorMessage(error) })
    }
  }

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose() }}>
      <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border bg-background p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="office-dialog-title">
        <div className="flex items-start justify-between">
          <div>
            <h2 id="office-dialog-title" className="text-lg font-semibold">{editingExisting ? "Edit office" : "Add office"}</h2>
            <p className="mt-1 text-sm text-muted-foreground">Check-ins within the radius count as being at this office.</p>
          </div>
          <Button variant="ghost" size="icon-sm" aria-label="Close dialog" onPress={onClose}><X /></Button>
        </div>

        <form className="mt-6 grid gap-4" onSubmit={handleSubmit(onSubmit)} noValidate>
          <FormField label="Office name" placeholder="London HQ" error={errors.name?.message} {...register("name")} />
          <FormField label="Address" placeholder="Optional" error={errors.address?.message} {...register("address")} />

          <div className="grid gap-4 sm:grid-cols-2">
            <FormField label="Latitude" placeholder="51.5074" error={errors.latitude?.message} {...register("latitude")} />
            <FormField label="Longitude" placeholder="-0.1278" error={errors.longitude?.message} {...register("longitude")} />
          </div>

          <Button type="button" variant="outline" size="sm" className="w-fit" isDisabled={locating} onPress={() => void fillFromCurrentPosition()}>
            <Crosshair />{locating ? "Finding you…" : "Use my current location"}
          </Button>

          <FormField
            label="Radius in metres"
            type="number"
            min="20"
            max="50000"
            error={errors.radius_metres?.message}
            {...register("radius_metres")}
          />
          <p className="-mt-2 text-xs text-muted-foreground">
            Allow for GPS drift: a phone indoors is often 20–50m out, so a tight radius will reject people who are actually here.
          </p>

          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" className="size-4 rounded border" {...register("is_active")} />
            Active
          </label>

          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="outline" onPress={onClose}>Cancel</Button>
            <Button type="submit" isDisabled={isSubmitting || create.isPending || update.isPending}>
              {isSubmitting || create.isPending || update.isPending ? "Saving…" : editingExisting ? "Save changes" : "Add office"}
            </Button>
          </div>
        </form>
      </div>
    </div>
  )
}
