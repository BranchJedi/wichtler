import type { GroupResponse } from '../api/generated/model'
import { de } from '../i18n/de'

const dateFormat = new Intl.DateTimeFormat('de-CH', { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' })

/** Formats an ISO date (YYYY-MM-DD) without shifting it through time zones. */
export function formatEventDate(isoDate: string): string {
  const [year, month, day] = isoDate.split('-').map(Number)
  return dateFormat.format(new Date(year, month - 1, day))
}

export function formatMoney(amount: number, currency: string): string {
  return new Intl.NumberFormat('de-CH', { style: 'currency', currency, maximumFractionDigits: 0 }).format(amount)
}

export function formatBudget(group: { budgetMin: number | null; budgetMax: number | null; currency: string }): string {
  const { budgetMin, budgetMax, currency } = group
  if (budgetMin === null && budgetMax === null) return de.group.noBudget
  if (budgetMin !== null && budgetMax !== null) {
    return budgetMin === budgetMax
      ? formatMoney(budgetMin, currency)
      : de.groupForm.budgetRange(formatMoney(budgetMin, currency), formatMoney(budgetMax, currency))
  }
  return formatMoney((budgetMin ?? budgetMax) as number, currency)
}

export function groupStatus(group: Pick<GroupResponse, 'isDrawed' | 'drawable'>): { label: string; tone: 'neutral' | 'ready' | 'done' } {
  if (group.isDrawed) return { label: de.group.status.drawn, tone: 'done' }
  if (group.drawable) return { label: de.group.status.ready, tone: 'ready' }
  return { label: de.group.status.open, tone: 'neutral' }
}

export function groupRole(group: Pick<GroupResponse, 'isOwner' | 'isMember'>): { label: string; tone: 'neutral' | 'admin' } {
  if (group.isOwner) return { label: de.group.role.owner, tone: 'neutral' }
  if (group.isMember) return { label: de.group.role.member, tone: 'neutral' }
  return { label: de.group.role.admin, tone: 'admin' }
}
