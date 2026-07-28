"use client"

import { Bell, BriefcaseBusiness, CalendarCheck, CalendarDays, CheckSquare, ChevronDown, ClipboardList, DollarSign, FolderKanban, LayoutDashboard, LogOut, Menu, Moon, Receipt, Settings, Sun, Users, Wrench, X } from "lucide-react"
import Link from "next/link"
import { usePathname } from "next/navigation"
import { useState } from "react"
import { useTheme } from "next-themes"
import { Button } from "@workspace/ui/components/button"
import { BrandMark } from "@/components/brand-mark"
import { ProtectedRoute } from "@/features/auth/route-guards"
import { useAuthStore } from "@/store/auth-store"

const NAV = [
  { href: "/dashboard", label: "Overview", icon: LayoutDashboard },
  { href: "/dashboard/staff", label: "Staff", icon: Users },
  { href: "/dashboard/attendance", label: "Attendance", icon: CalendarCheck },
  { href: "/dashboard/leave", label: "Leave", icon: ClipboardList },
  { href: "/dashboard/tasks", label: "Tasks", icon: CheckSquare },
  { href: "/dashboard/meetings", label: "Meetings", icon: CalendarDays },
  { href: "/dashboard/projects", label: "Projects", icon: FolderKanban },
  { href: "/dashboard/payroll", label: "Payroll", icon: DollarSign },
  { href: "/dashboard/expenses", label: "Expenses", icon: Receipt },
  { href: "/dashboard/recruitment", label: "Recruitment", icon: BriefcaseBusiness },
  { href: "/dashboard/operations", label: "Operations", icon: Wrench },
  { href: "/dashboard/settings", label: "Settings", icon: Settings },
] as const

/**
 * Overview matches only its exact path; every other entry also matches its
 * detail pages, so /dashboard/tasks/12 keeps Tasks highlighted.
 */
function isActive(pathname: string, href: string): boolean {
  return href === "/dashboard" ? pathname === href : pathname === href || pathname.startsWith(`${href}/`)
}

export function DashboardLayout({ children }: { children: React.ReactNode }) {
  return <ProtectedRoute><DashboardShell>{children}</DashboardShell></ProtectedRoute>
}

function DashboardShell({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useState(false)
  const [menuOpen, setMenuOpen] = useState(false)
  const { theme, setTheme } = useTheme()
  const { user, logout } = useAuthStore()
  const pathname = usePathname() ?? ""
  const current = NAV.find((entry) => isActive(pathname, entry.href))

  return (
    <div className="min-h-svh bg-muted/20">
      <aside className={`fixed inset-y-0 left-0 z-40 w-64 border-r bg-background p-4 transition-transform md:translate-x-0 ${open ? "translate-x-0" : "-translate-x-full"}`}>
        <div className="flex items-center justify-between">
          <BrandMark />
          <Button variant="ghost" size="icon-sm" className="md:hidden" aria-label="Close navigation" onPress={() => setOpen(false)}><X /></Button>
        </div>

        <nav className="mt-8 grid gap-1">
          {NAV.map(({ href, label, icon: Icon }) => {
            const active = isActive(pathname, href)
            return (
              <Link
                key={href}
                href={href}
                // aria-current is what a screen reader announces; the styling
                // alone would leave the active page invisible to one.
                aria-current={active ? "page" : undefined}
                onClick={() => setOpen(false)}
                className={`flex h-9 items-center gap-2 rounded-lg px-2.5 text-sm transition-colors ${
                  active
                    ? "bg-secondary font-medium text-secondary-foreground"
                    : "text-muted-foreground hover:bg-muted hover:text-foreground"
                }`}
              >
                <Icon className="size-4" />
                {label}
              </Link>
            )
          })}
        </nav>
      </aside>

      {open && <button aria-label="Close navigation overlay" className="fixed inset-0 z-30 bg-black/30 md:hidden" onClick={() => setOpen(false)} />}

      <div className="md:pl-64">
        <header className="sticky top-0 z-20 flex h-16 items-center justify-between border-b bg-background/80 px-4 backdrop-blur sm:px-6">
          <Button variant="ghost" size="icon-sm" className="md:hidden" aria-label="Open navigation" onPress={() => setOpen(true)}><Menu /></Button>
          {/* Tracks the page rather than being permanently "Workspace overview". */}
          <div className="hidden text-sm text-muted-foreground md:block">{current?.label ?? "Workspace"}</div>
          <div className="ml-auto flex items-center gap-2">
            <Button variant="ghost" size="icon-sm" aria-label="Toggle theme" onPress={() => setTheme(theme === "dark" ? "light" : "dark")}>
              {theme === "dark" ? <Sun /> : <Moon />}
            </Button>
            <Button variant="ghost" size="icon-sm" aria-label="Notifications"><Bell /></Button>
            <div className="relative">
              <Button variant="ghost" className="gap-2" onPress={() => setMenuOpen((value) => !value)}>
                <span className="grid size-7 place-items-center rounded-full bg-primary text-xs text-primary-foreground">{user?.name.slice(0, 1).toUpperCase()}</span>
                <span className="hidden max-w-28 truncate sm:block">{user?.name}</span>
                <ChevronDown />
              </Button>
              {menuOpen && (
                <div className="absolute right-0 mt-2 w-48 rounded-xl border bg-popover p-1 shadow-lg">
                  <p className="truncate px-3 py-2 text-xs text-muted-foreground">{user?.email}</p>
                  <Button variant="ghost" className="w-full justify-start" onPress={() => logout()}><LogOut />Sign out</Button>
                </div>
              )}
            </div>
          </div>
        </header>
        <main className="p-4 sm:p-6">{children}</main>
      </div>
    </div>
  )
}
