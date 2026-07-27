"use client"

import { Megaphone, Package, Plus, TrendingUp } from "lucide-react"
import { useState } from "react"
import { Button } from "@workspace/ui/components/button"
import { usePhaseFourFive, usePhaseFourFiveCreate, useSummary } from "@/hooks/use-phase-four-five"
import { useToast } from "@/providers/toast-provider"
import { getApiErrorMessage } from "@/lib/api-error"

type Tab = "assets" | "announcements" | "reports"

export function PhaseFiveModule() {
  const { toast } = useToast()
  const [tab, setTab] = useState<Tab>("assets")
  const data = usePhaseFourFive(tab === "assets" ? "assets" : "announcements")
  const summary = useSummary()
  const create = usePhaseFourFiveCreate(tab === "assets" ? "assets" : "announcements")
  const [title, setTitle] = useState("")
  const [category, setCategory] = useState("")
  const submit = async () => {
    try {
      await create.mutateAsync(tab === "assets" ? { name: title, category } : { title, body: category, status: "published" })
      toast({ tone: "success", title: "Saved successfully" })
      setTitle(""); setCategory("")
    } catch (error) { toast({ tone: "error", title: "Unable to save", description: getApiErrorMessage(error) }) }
  }
  return <div className="mx-auto grid max-w-6xl gap-6"><div><p className="text-sm font-medium text-muted-foreground">Operations</p><h1 className="mt-2 text-2xl font-semibold tracking-tight">Assets, announcements & reports</h1><p className="mt-2 text-sm text-muted-foreground">Keep company resources and operational insights in one place.</p></div><div className="flex flex-wrap gap-2"><Button variant={tab === "assets" ? "secondary" : "outline"} onPress={() => setTab("assets")}><Package />Assets</Button><Button variant={tab === "announcements" ? "secondary" : "outline"} onPress={() => setTab("announcements")}><Megaphone />Announcements</Button><Button variant={tab === "reports" ? "secondary" : "outline"} onPress={() => setTab("reports")}><TrendingUp />Reports</Button></div>{tab === "reports" ? <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">{Object.entries(summary.data ?? {}).map(([key, value]) => <div key={key} className="rounded-2xl border bg-background p-5"><p className="text-xs uppercase text-muted-foreground">{key.replace("_", " ")}</p><p className="mt-2 text-2xl font-semibold">{String(value)}</p></div>)}</section> : <><section className="rounded-2xl border bg-background p-5"><div className="flex flex-col gap-3 sm:flex-row"><input value={title} onChange={(event) => setTitle(event.target.value)} placeholder={tab === "assets" ? "Asset name" : "Announcement title"} className="h-10 flex-1 rounded-lg border px-3 text-sm" /><input value={category} onChange={(event) => setCategory(event.target.value)} placeholder={tab === "assets" ? "Category" : "Announcement body"} className="h-10 flex-1 rounded-lg border px-3 text-sm" /><Button isDisabled={!title || !category} onPress={submit}><Plus />Create</Button></div></section><section className="grid gap-4 md:grid-cols-2">{(data.data ?? []).map((item) => { const record = item as { id: number; name?: string; title?: string; category?: string; status?: string }; return <div key={record.id} className="rounded-2xl border bg-background p-5"><p className="font-medium">{tab === "assets" ? record.name : record.title}</p><p className="mt-2 text-sm text-muted-foreground">{tab === "assets" ? `${record.category} · ${record.status}` : record.status}</p></div> })}</section></>}</div>
}
