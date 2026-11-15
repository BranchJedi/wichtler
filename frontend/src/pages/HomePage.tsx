import giftUrl from '../assets/illustrations/geschenk.svg'
import parcelUrl from '../assets/illustrations/paeckchen.svg'
import gnomeUrl from '../assets/illustrations/wichtel.svg'
import listUrl from '../assets/illustrations/wunschzettel.svg'
import {useState} from 'react'
import {Link, useNavigate} from 'react-router'
import {useListGroups} from '../api/generated/groups/groups'
import type {GroupResponse} from '../api/generated/model'
import {useSession} from '../auth/session'
import {Badge, Button, Notice} from '../components/ui'
import {CalendarLeaf} from '../groups/CalendarLeaf'
import {daysUntil, formatBudget, formatEventDate, groupRole, groupStatus} from '../groups/format'
import {GroupFormDialog} from '../groups/GroupFormDialog'
import {PendingInvitations} from '../groups/PendingInvitations'
import {de} from '../i18n/de'

const t = de.home

/** How a group is wrapped tells its state: loose paper, ribbon laid out, or tied up with a bow. */
const wrapping = {
    neutral: {
        lid: 'bg-[repeating-linear-gradient(135deg,var(--color-paper-200)_0_14px,var(--color-line)_14px_28px)]',
        ribbon: false,
        bow: false,
    },
    ready: {
        lid: 'bg-[repeating-linear-gradient(135deg,var(--color-gold-100)_0_14px,var(--color-gold-500)_14px_28px)]',
        ribbon: false,
        bow: false,
    },
    done: {lid: 'bg-tanne-700', ribbon: true, bow: true},
} as const

/** One group in the overview, as a parcel. Shows the draw status only, never who drew whom. */
function GroupCard({group}: { group: GroupResponse }) {
    const status = groupStatus(group)
    const role = groupRole(group)
    const wrap = wrapping[status.tone]
    return (
        <li>
            <Link
                to={`/gruppen/${group.id}`}
                className="group block h-full overflow-hidden rounded-3xl border border-line bg-paper-000 shadow-card transition-transform hover:-translate-y-1 hover:-rotate-1 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-tanne-700"
            >
                <div className={`relative flex h-16 items-start justify-end gap-2 border-b border-dashed border-line-strong/50 p-3 ${wrap.lid}`}>
                    {wrap.ribbon && <span aria-hidden="true" className="absolute inset-y-0 left-13 w-4 bg-gold-500"/>}
                    {wrap.bow && <span className="absolute -bottom-4 left-28 inline-flex size-14 rotate-6 items-center justify-center rounded-full bg-paper-000 shadow-card"><img src={giftUrl} alt="" className="size-10"/></span>}
                    <span className="relative rounded-full border border-line-strong bg-paper-000 px-3 py-0.5 text-xs font-bold tracking-wide text-ink">
                        {status.label}
                    </span>
                </div>
                <div className="px-5 pb-5">
                    <CalendarLeaf isoDate={group.eventDate} className="relative -mt-9 transition-transform group-hover:rotate-2"/>
                    <h2 className="mt-3 text-2xl font-semibold break-words">{group.title}</h2>
                    <p className="mt-1 text-ink">
                        {formatEventDate(group.eventDate)}
                        <span className="text-ink-muted"> · {de.group.until(daysUntil(group.eventDate))}</span>
                    </p>
                    <p className="mt-4 flex flex-wrap items-center gap-2 text-sm font-semibold">
                        <span className="rounded-full bg-tanne-100 px-3 py-1 text-tanne-900">{de.group.participants(group.participantCount)}</span>
                        <span className="rounded-full bg-gold-100 px-3 py-1 text-ink">{formatBudget(group)}</span>
                        <span className="ml-auto">
                            <Badge tone={role.tone}>{role.label}</Badge>
                        </span>
                    </p>
                </div>
            </Link>
        </li>
    )
}

export function HomePage() {
    const {user} = useSession()
    const navigate = useNavigate()
    const [scope, setScope] = useState<'mine' | 'all'>('mine')
    const [creating, setCreating] = useState(false)
    const groups = useListGroups({scope})

    return (
        <div className="space-y-6">
            <section className="relative overflow-hidden rounded-3xl bg-tanne-700 text-on-dark shadow-card">
                <div className="flex flex-wrap items-end gap-x-10 gap-y-4 p-6 sm:p-8">
                    <div className="min-w-0 flex-1 basis-80">
                        {user && <p className="text-lg font-semibold">{t.greeting(user.firstName)}</p>}
                        <h1 className="mt-1 text-4xl font-semibold text-balance sm:text-5xl">{t.title}</h1>
                        {scope === 'mine' && groups.data && <p className="mt-3 text-lg">{t.summary(groups.data.length)}</p>}
                        <div className="mt-6">
                            <Button variant="festive" onClick={() => setCreating(true)}>{t.newGroup}</Button>
                        </div>
                    </div>
                    {/* The figures stand on a mound of snow so that they read on the green banner. */}
                    <div aria-hidden="true"
                         className="relative -mb-6 flex items-end gap-1 px-6 before:absolute before:inset-x-0 before:-bottom-8 before:h-20 before:rounded-[50%] before:bg-paper-100 sm:-mb-8">
                        <img src={parcelUrl} alt="" className="relative w-16 sm:w-24"/>
                        <img src={gnomeUrl} alt="" className="relative w-28 sm:w-40"/>
                        <img src={listUrl} alt="" className="relative mb-2 w-14 rotate-6 sm:w-20"/>
                    </div>
                </div>
            </section>

            <PendingInvitations/>

            {user?.isAdmin && (
                <div role="group" aria-label={t.scopeLabel}
                     className="inline-flex rounded-full border border-line-strong bg-paper-000 p-1">
                    {(['mine', 'all'] as const).map((value) => (
                        <button
                            key={value}
                            type="button"
                            aria-pressed={scope === value}
                            onClick={() => setScope(value)}
                            className={`min-h-9 rounded-full px-4 text-sm font-semibold ${scope === value ? 'bg-tanne-700 text-on-dark' : 'text-ink hover:bg-paper-200'}`}
                        >
                            {value === 'mine' ? t.scopeMine : t.scopeAll}
                        </button>
                    ))}
                </div>
            )}

            {groups.isPending && <p role="status" className="text-ink-muted">{de.app.loading}</p>}
            {groups.isError && <Notice tone="error">{t.loadFailed}</Notice>}
            {groups.data?.length === 0 && (
                <div className="rounded-3xl border-2 border-dashed border-line-strong/60 bg-paper-000/70 p-8 text-center">
                    <img src={listUrl} alt="" className="mx-auto w-20 -rotate-6"/>
                    <p className="mt-4 font-display text-2xl font-semibold">{scope === 'all' ? t.emptyAll : t.empty}</p>
                    {scope === 'mine' && <p className="mt-2 text-ink-muted">{t.emptyHint}</p>}
                </div>
            )}
            {groups.data && groups.data.length > 0 && (
                <ul className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3 min-[1700px]:grid-cols-4">
                    {groups.data.map((group) => (
                        <GroupCard key={group.id} group={group}/>
                    ))}
                </ul>
            )}

            <GroupFormDialog open={creating} onOpenChange={setCreating}
                             onSaved={(group) => void navigate(`/gruppen/${group.id}`)}/>
        </div>
    )
}
