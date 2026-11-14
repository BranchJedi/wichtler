import {useState} from 'react'
import {Link, useNavigate, useParams} from 'react-router'
import {useGetGroup} from '../api/generated/groups/groups'
import {Badge, Button, Card, Notice} from '../components/ui'
import {useSession} from '../auth/session'
import {AllAssignmentsCard, MyAssignmentCard} from '../groups/AssignmentCards'
import {DeleteGroupDialog} from '../groups/DeleteGroupDialog'
import {ExclusionsCard} from '../groups/ExclusionsCard'
import {formatBudget, formatEventDate, groupRole, groupStatus} from '../groups/format'
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
        <Link to="/" className="inline-flex min-h-11 items-center text-sm font-medium text-tanne-700 underline">
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

    return (
        <div className="space-y-6">
            {back}
            <div>
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="mr-auto text-3xl font-semibold">{group.title}</h1>
                    <Badge tone={status.tone}>{status.label}</Badge>
                    <Badge tone={role.tone}>{role.label}</Badge>
                </div>
                {group.description && <p className="mt-2 whitespace-pre-line text-ink">{group.description}</p>}
            </div>

            {group.isDrawed && group.isParticipant && <MyAssignmentCard group={group}/>}
            {group.isDrawed && group.isMember && !group.isParticipant &&
                <Notice tone="info">{de.assignment.notParticipating}</Notice>}

            <Card>
                <dl className="grid gap-4 sm:grid-cols-2">
                    {[
                        [t.eventDate, formatEventDate(group.eventDate)],
                        [t.year, String(group.year)],
                        [t.budget, formatBudget(group)],
                        [t.minWishes, t.minWishesValue(group.minWishAmount)],
                        [t.owner, `${group.owner.firstName} ${group.owner.lastName} (${group.owner.username})`],
                        [de.group.participants(group.participantCount), ''],
                    ].map(([term, value]) => (
                        <div key={term}>
                            <dt className="text-sm text-ink-muted">{value === '' ? '' : term}</dt>
                            <dd className="font-medium">{value === '' ? term : value}</dd>
                        </div>
                    ))}
                </dl>
            </Card>

            {group.isMember && <WishesCard group={group}/>}
            {group.isParticipant && <ExclusionsCard group={group}/>}
            <MembersCard group={group}/>
            {group.canManage && <DrawStatusCard group={group}/>}
            {user?.isAdmin && group.isDrawed && <AllAssignmentsCard group={group}/>}

            {group.canManage && (
                <Card title={t.manageTitle}>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="ghost" onClick={() => setEditing(true)}>
                            {t.edit}
                        </Button>
                        <Button variant="danger" disabled={group.isDrawed} onClick={() => setDeleting(true)}>
                            {t.delete}
                        </Button>
                    </div>
                    {group.isDrawed && <p className="mt-3 text-sm text-ink-muted">{t.deleteLockedHint}</p>}
                </Card>
            )}

            <GroupFormDialog group={group} open={editing} onOpenChange={setEditing}/>
            <DeleteGroupDialog group={group} open={deleting} onOpenChange={setDeleting}
                               onDeleted={() => void navigate('/')}/>
        </div>
    )
}
