import {getAssignmentOfMember, getMyAssignment} from '../api/generated/draw/draw'
import {useListMembers} from '../api/generated/members/members'
import type {GroupResponse} from '../api/generated/model'
import {Card} from '../components/ui'
import {de} from '../i18n/de'
import {AssignmentReveal} from './AssignmentReveal'

const t = de.assignment

/** The participant's own draw result, hidden until asked for. */
export function MyAssignmentCard({group}: { group: GroupResponse }) {
    return (
        <Card title={t.title} feature="assignment">
            <p className="mb-4 text-sm text-ink-muted">{t.intro}</p>
            <AssignmentReveal load={() => getMyAssignment(String(group.id))} revealLabel={t.reveal} lead={t.givesTo} festive/>
        </Card>
    )
}

/** Wichtler admin only: every participant's result, each behind its own blur. */
export function AllAssignmentsCard({group}: { group: GroupResponse }) {
    const groupId = String(group.id)
    const members = useListMembers(groupId)
    const givers = members.data?.filter((member) => member.status === 'active') ?? []

    return (
        <Card title={t.allTitle} feature="admin">
            <p className="text-sm text-ink-muted">{t.allIntro}</p>
            <ul className="mt-2 divide-y divide-line">
                {givers.map((giver) => (
                    <li key={giver.id} className="space-y-3 py-4">
                        <p className="font-medium">
                            {giver.firstName} {giver.lastName} <span
                            className="text-sm font-normal text-ink-muted">({giver.username})</span>
                        </p>
                        <AssignmentReveal
                            load={() => getAssignmentOfMember(groupId, String(giver.id))}
                            revealLabel={t.revealOne}
                            lead={`${giver.firstName} ${t.gives}`}
                        />
                    </li>
                ))}
            </ul>
        </Card>
    )
}
