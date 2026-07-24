"use client"

import { Eye, EyeOff } from "lucide-react"
import { Input, Label, TextField, FieldError } from "react-aria-components"
import { useState } from "react"
import { cn } from "@workspace/ui/lib/utils"
import { Button } from "@workspace/ui/components/button"

export function FormField({ label, error, type = "text", ...props }: React.ComponentProps<typeof Input> & { label: string; error?: string }) {
  const [visible, setVisible] = useState(false)
  const isPassword = type === "password"
  return <TextField isRequired={props.required} isInvalid={Boolean(error)} className="grid gap-2">
    <Label className="text-sm font-medium">{label}</Label>
    <div className="relative">
      <Input {...props} type={isPassword && !visible ? "password" : isPassword ? "text" : type} className={cn("h-11 w-full rounded-lg border bg-background px-3 text-sm outline-none transition placeholder:text-muted-foreground focus:border-ring focus:ring-3 focus:ring-ring/20", isPassword && "pr-10", error && "border-destructive")}/>
      {isPassword && <Button type="button" variant="ghost" size="icon-sm" aria-label={visible ? "Hide password" : "Show password"} className="absolute top-1/2 right-1 -translate-y-1/2" onPress={() => setVisible((value) => !value)}>{visible ? <EyeOff /> : <Eye />}</Button>}
    </div>
    {error && <FieldError className="text-xs text-destructive">{error}</FieldError>}
  </TextField>
}
