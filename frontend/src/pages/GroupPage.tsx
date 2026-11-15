import {useState} from 'react'
import {Link, useNavigate, useParams} from 'react-router'
import {useGetGroup} from '../api/generated/groups/groups'
import gnomeUrl from '../assets/illustrations/wichtel.svg'
import {Avatar, Badge, Button, Notice} from '../components/ui'
import {useSession} from '../auth/session'
import {AllAssignmentsCard, MyAssignmentCard} from '../groups/AssignmentCards'
import {DeleteGroupDialog} from '../groups/DeleteGroupDialog'
import {ExclusionsCard} from '../groups/ExclusionsCard'
import {CalendarLeaf} from '../groups/CalendarLeaf'
import {daysUntil, formatBudget, formatEventDate, groupRole, groupStatus} from '../groups/format'
import {GroupFormDialog} from '../groups/GroupFormDialog'
import {DrawStatusCard} from '../groups/DrawStatusCard'
import {MembersCard} from '../groups/MembersCard'
import {WishesCard} from '../groups/WishesCard'
import {de} from '../i18n/de'

const t = de.group

export function GroupPage() {
    const {id = ''} = useParams()
    const {user} = useSession()
    const navigate = useNavigate()
    const [editing, setEditing] = useState(false)
    const [deleting, setDeleting] = useState(false)
    const {data: group, isPending, isError} = useGetGroup(id, {query: {retry: false}})

    const back = (
        <Link to="/" className="inline-flex min-h-11 items-center rounded-full border border-line-strong bg-paper-000 px-4 text-sm font-semibold text-tanne-700">
            ← {t.back}
        </Link>
    )

    if (isPending) return <p role="status" className="text-ink-muted">{de.app.loading}</p>
    if (isError) {
        return (
            <div className="space-y-4">
                {back}
                <Notice tone="error">{t.notFound}</Notice>
            </div>
        )
    }

    const status = groupStatus(group)
    const role = groupRole(group)

    const facts: [string, string, string?][] = [
        [t.eventDate, formatEventDate(group.eventDate), de.group.until(daysUntil(group.eventDate))],
        [t.budget, formatBudget(group)],
        [t.minWishes, t.minWishesValue(group.minWishAmount)],
        [t.year, String(group.year)],
    ]

    return (
        <div className="space-y-6">
            {back}

            {/* The group at a glance: a dark banner that no feature card resembles. */}
            <header className="overflow-hidden rounded-3xl bg-tanne-700 text-on-dark shadow-card">
                <div className="flex flex-wrap items-start gap-x-6 gap-y-4 p-6 sm:p-8">
                    <CalendarLeaf isoDate={group.eventDate} className="mt-1"/>
                    <div className="min-w-0 flex-1 basis-72">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge tone={status.tone}>{status.label}</Badge>
                            <Badge tone={role.tone}>{role.label}</Badge>
                        </div>
                        <h1 className="mt-2 text-4xl font-semibold break-words text-balance sm:text-5xl">{group.title}</h1>
                        {group.description &&
                            <p className="mt-3 max-w-prose text-lg whitespace-pre-line">{group.description}</p>}
                        <p className="mt-4 flex flex-wrap items-center gap-x-3 gap-y-2">
                            <Avatar firstName={group.owner.firstName} lastName={group.owner.lastName}/>
                            <span>
                                <span className="block text-sm">{t.organizedBy}</span>
                                <span className="font-semibold">
                                    {group.owner.firstName} {group.owner.lastName} ({group.owner.username})
                                </span>
                            </span>
                        </p>
                    </div>
                    {group.canManage && (
                        <div className="flex flex-wrap gap-2">
                            <Button variant="ghost" onClick={() => setEditing(true)}>
                                {t.edit}
                            </Button>
                            <Button variant="danger" disabled={group.isDrawed} onClick={() => setDeleting(true)}>
                                {t.delete}
                            </Button>
                        </div>
                    )}
                </div>
                {group.canManage && group.isDrawed && <p className="px-6 pb-4 text-sm sm:px-8">{t.deleteLockedHint}</p>}
                <dl className="grid grid-cols-2 gap-px bg-tanne-900 lg:grid-cols-5">
                    {facts.map(([term, value, extra]) => (
                        <div key={term} className="bg-tanne-800 px-6 py-4 sm:px-8">
                            <dt className="text-sm">{term}</dt>
                            <dd className="font-display text-lg font-semibold sm:text-xl">{value}</dd>
                            {extra && <dd className="text-sm">{extra}</dd>}
                        </div>
                    ))}
                    <div className="col-span-2 flex items-center gap-3 bg-tanne-800 px-6 py-4 sm:px-8 lg:col-span-1">
                        <span className="inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-paper-000"><img src={gnomeUrl} alt="" className="h-9"/></span>
                        <dd className="font-display text-xl font-semibold">{de.group.participants(group.participantCount)}</dd>
                    </div>
                </dl>
            </header>

            {group.isDrawed && group.isMember && !group.isParticipant &&
                <Notice tone="info">{de.assignment.notParticipating}</Notice>}

            {/* Feature cards flow into as many columns as the screen holds. */}
            <div className="gap-6 lg:columns-2 min-[1700px]:columns-3 [&>*]:mb-6 [&>*]:break-inside-avoid">
                {group.isDrawed && group.isParticipant && <MyAssignmentCard group={group}/>}
                {group.isMember && <WishesCard group={group}/>}
                <MembersCard group={group}/>
                {group.canManage && <DrawStatusCard group={group}/>}
                {group.isParticipant && <ExclusionsCard group={group}/>}
                {user?.isAdmin && group.isDrawed && <AllAssignmentsCard group={group}/>}
            </div>

            <GroupFormDialog group={group} open={editing} onOpenChange={setEditing}/>
            <DeleteGroupDialog group={group} open={deleting} onOpenChange={setDeleting}
                               onDeleted={() => void navigate('/')}/>
        </div>
    )
}
