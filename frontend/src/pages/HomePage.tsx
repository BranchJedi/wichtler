import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { useListGroups } from '../api/generated/groups/groups'
import type { GroupResponse } from '../api/generated/model'
import { useSession } from '../auth/session'
import { Badge, Button, Card, Notice } from '../components/ui'
import { formatBudget, formatEventDate, groupRole, groupStatus } from '../groups/format'
import { GroupFormDialog } from '../groups/GroupFormDialog'
import { PendingInvitations } from '../groups/PendingInvitations'
import { de } from '../i18n/de'

const t = de.home

/** One group in the overview. Shows the draw status only, never who drew whom. */
function GroupCard({ group }: { group: GroupResponse }) {
  const status = groupStatus(group)
  const role = groupRole(group)
  return (
    <li>
      <Link
        to={`/gruppen/${group.id}`}
        className="block rounded-2xl border border-stone-200 bg-white p-5 shadow-sm transition-colors hover:border-pine-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pine-600"
      >
        <div className="flex flex-wrap items-center gap-2">
          <h2 className="mr-auto text-lg font-semibold">{group.title}</h2>
          <Badge tone={status.tone}>{status.label}</Badge>
        </div>
        <p className="mt-1 text-stone-700">{formatEventDate(group.eventDate)}</p>
        <p className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-stone-500">
          <span>{de.group.participants(group.participantCount)}</span>
          <span aria-hidden="true">·</span>
          <span>{formatBudget(group)}</span>
          <span className="ml-auto">
            <Badge tone={role.tone}>{role.label}</Badge>
          </span>
        </p>
      </Link>
    </li>
  )
}

export function HomePage() {
  const { user } = useSession()
  const navigate = useNavigate()
  const [scope, setScope] = useState<'mine' | 'all'>('mine')
  const [creating, setCreating] = useState(false)
  const groups = useListGroups({ scope })

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end gap-3">
        <div className="mr-auto">
          {user && <p className="text-sm text-stone-500">{t.greeting(user.firstName)}</p>}
          <h1 className="text-2xl font-bold">{t.title}</h1>
        </div>
        <Button onClick={() => setCreating(true)}>{t.newGroup}</Button>
      </div>

      <PendingInvitations />

      {user?.isAdmin && (
        <div role="group" aria-label={t.scopeLabel} className="inline-flex rounded-lg border border-stone-300 bg-white p-1">
          {(['mine', 'all'] as const).map((value) => (
            <button
              key={value}
              type="button"
              aria-pressed={scope === value}
              onClick={() => setScope(value)}
              className={`min-h-9 rounded-md px-3 text-sm font-medium ${scope === value ? 'bg-pine-600 text-white' : 'text-stone-700 hover:bg-stone-100'}`}
            >
              {value === 'mine' ? t.scopeMine : t.scopeAll}
            </button>
          ))}
        </div>
      )}

      {groups.isPending && <p role="status" className="text-stone-500">{de.app.loading}</p>}
      {groups.isError && <Notice tone="error">{t.loadFailed}</Notice>}
      {groups.data?.length === 0 && (
        <Card>
          <p>{scope === 'all' ? t.emptyAll : t.empty}</p>
          {scope === 'mine' && <p className="mt-2 text-sm text-stone-500">{t.emptyHint}</p>}
        </Card>
      )}
      {groups.data && groups.data.length > 0 && (
        <ul className="grid gap-4 sm:grid-cols-2">
          {groups.data.map((group) => (
            <GroupCard key={group.id} group={group} />
          ))}
        </ul>
      )}

      <GroupFormDialog open={creating} onOpenChange={setCreating} onSaved={(group) => void navigate(`/gruppen/${group.id}`)} />
    </div>
  )
}
