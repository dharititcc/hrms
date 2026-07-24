"use client"

import { Check, ChevronsUpDown } from "lucide-react"
import { Button } from "@workspace/ui/components/button"
import { ComboBox, Input, Label, ListBox, ListBoxItem, Popover } from "react-aria-components"

type SelectOption = { id: string; label: string }

export function SelectField({ label, value, options, onChange }: { label: string; value: string; options: SelectOption[]; onChange: (value: string) => void }) {
  return <ComboBox selectedKey={value} onSelectionChange={(key) => { if (key) onChange(String(key)) }} className="grid gap-2"><Label className="text-sm font-medium">{label}</Label><div className="relative"><Input className="h-11 w-full rounded-lg border bg-background px-3 pr-10 text-sm outline-none transition placeholder:text-muted-foreground focus:border-ring focus:ring-3 focus:ring-ring/20"/><Button type="button" variant="ghost" size="icon-sm" aria-label={`Open ${label.toLowerCase()} options`} className="absolute top-1/2 right-1 -translate-y-1/2"><ChevronsUpDown /></Button></div><Popover className="w-[--trigger-width] overflow-hidden rounded-xl border bg-popover p-1 shadow-xl"><ListBox className="max-h-56 overflow-auto outline-none">{options.map((option) => <ListBoxItem key={option.id} id={option.id} textValue={option.label} className="flex cursor-pointer items-center justify-between rounded-lg px-3 py-2.5 text-sm outline-none data-[focused]:bg-muted"><span>{option.label}</span>{value === option.id && <Check className="size-4" />}</ListBoxItem>)}</ListBox></Popover></ComboBox>
}
