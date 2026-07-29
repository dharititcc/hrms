import { EmployeesModule } from "@/features/employees/employees-module"
import type { Metadata } from "next"

export const metadata: Metadata = { title: "Employees" }

export default function EmployeesPage() { return <EmployeesModule /> }
