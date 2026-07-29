import { ArrowUpRight, Check } from "lucide-react"
import { BrandMark } from "@/components/brand-mark"

/**
 * The dark panel beside the sign-in form.
 *
 * Copy describes this product rather than the generic software-team pitch it
 * shipped with: nobody signing in here is building or shipping anything, they
 * are recording a day's attendance or looking for a payslip.
 */
export function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <main className="grid min-h-svh lg:grid-cols-[minmax(22rem,0.8fr)_minmax(30rem,1.2fr)]">
      <section className="relative hidden overflow-hidden bg-zinc-950 p-10 text-white lg:flex lg:flex-col">
        <BrandMark className="[&_span:last-child]:text-white" />

        <div className="relative z-10 mt-auto max-w-lg pb-10">
          <p className="mb-5 text-sm text-zinc-400">Human resources, without the spreadsheets.</p>
          <h1 className="text-4xl leading-tight font-medium tracking-tight xl:text-5xl">
            Your people, payroll and time in one place.
          </h1>

          <div className="mt-8 grid gap-3 text-sm text-zinc-300">
            <p className="flex items-center gap-2">
              <Check className="size-4 text-zinc-500" /> Attendance, leave and payroll that agree with each other
            </p>
            <p className="flex items-center gap-2">
              <Check className="size-4 text-zinc-500" /> Salary details encrypted, and every change recorded
            </p>
          </div>
        </div>

        <div className="absolute -right-24 -bottom-32 size-96 rounded-full bg-white/10 blur-3xl" />
        <ArrowUpRight className="absolute right-10 bottom-10 size-5 text-zinc-600" />
      </section>

      <section className="flex min-h-svh flex-col bg-background px-5 py-6 sm:px-10 lg:px-16">
        <div className="lg:hidden"><BrandMark /></div>
        <div className="flex flex-1 items-center justify-center py-12">
          <div className="w-full max-w-sm">{children}</div>
        </div>
        <p className="text-center text-xs text-muted-foreground">
          © {new Date().getFullYear()} HRMS
        </p>
      </section>
    </main>
  )
}
