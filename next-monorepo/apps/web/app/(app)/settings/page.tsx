import type { Metadata } from "next"
import { SettingsModule } from "@/features/settings/settings-module"

export const metadata: Metadata = { title: "Settings" }

export default function SettingsPage() { return <SettingsModule /> }
