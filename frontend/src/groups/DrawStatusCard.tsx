import {useQueryClient} from '@tanstack/react-query'
import {useState} from 'react'
import {getGetDrawStatusQueryKey, useDeleteDraw, useGetDrawStatus, useStartDraw} from '../api/generated/draw/draw'
import {getGetMyExclusionsQueryKey} from '../api/generated/exclusions/exclusions'
import {getGetGroupQueryKey, getListGroupsQueryKey} from '../api/generated/groups/groups'
import {getListInvitationsQueryKey} from '../api/generated/invitations/invitations'
import {getGetInviteLinkQueryKey, getListMembersQueryKey} from '../api/generated/members/members'
import type {DrawStatusResponse, GroupResponse} from '../api/generated/model'
import {ConfirmDialog} from '../components/ConfirmDialog'
import {Overlay} from '../components/Overlay'
import {errorMessage} from '../api/errors'
import {Button, Card, Notice} from '../components/ui'
import {de} from '../i18n/de'

const t = de.drawStatus
const dateTime = new Intl.DateTimeFormat('de-CH', {dateStyle: 'long', timeStyle: 'short'})

function Check({done, children}: { done: boolean; children: string }) {
    return (
        <li className="flex items-start gap-3 py-2">
      <span
          aria-hidden="true"
          className={`mt-0.5 inline-flex size-6 shrink-0 items-center justify-center rounded-full text-sm font-bold ${done ? 'bg-tanne-700 text-on-dark' : 'border border-dashed border-line-strong bg-paper-100 text-ink-muted'}`}
      >
        {done ? '✓' : '…'}
      </span>
            <span>
        {children} <span className="sr-only">({done ? t.done : t.open})</span>
      </span>
        </li>
    )
}

/** Few valid assignments let participants infer the others' results from their own. */
function inferenceWarnings(status: DrawStatusResponse): string[] {
    const warnings: string[] = []
    if (status.participantCount <= 4) warnings.push(t.warnSmall(status.participantCount))
    if (status.hasExclusions) warnings.push(t.warnExclusions)
    return warnings
}

/**
 * The group admin's side of the draw: the checklist before it, starting it, deleting it.
 * It never shows a result. The group admin sees their own like every participant, and no other.
 */
export function DrawStatusCard({group}: { group: GroupResponse }) {
    const groupId = String(group.id)
    const queryClient = useQueryClient()
    const status = useGetDrawStatus(groupId)
    const [starting, setStarting] = useState(false)
    const [undoing, setUndoing] = useState(false)

    // A draw (or its deletion) changes what nearly every part of the page may show or do.
    const applied = (fresh: GroupResponse) => {
        queryClient.setQueryData(getGetGroupQueryKey(groupId), fresh)
        for (const queryKey of [
            getGetDrawStatusQueryKey(groupId),
            getListMembersQueryKey(groupId),
            getGetMyExclusionsQueryKey(groupId),
            getGetInviteLinkQueryKey(groupId),
            getListGroupsQueryKey(),
            getListInvitationsQueryKey(),
        ]) {
            void queryClient.invalidateQueries({queryKey})
        }
        setStarting(false)
        setUndoing(false)
    }
    const start = useStartDraw({mutation: {onSuccess: applied}})
    const undo = useDeleteDraw({mutation: {onSuccess: applied}})

    return (
        <Card title={t.title} feature="draw">
            {status.isPending && <p role="status" className="text-ink-muted">{de.app.loading}</p>}
            {status.isError && <Notice tone="error">{t.loadFailed}</Notice>}

            {status.data?.isDrawed && (
                <div className="space-y-4">
                    <Notice
                        tone="success">{group.drawnAt ? t.drawnAt(dateTime.format(new Date(group.drawnAt))) : t.drawn}</Notice>
                    <p className="text-sm text-ink-muted">{t.secrecy}</p>
                    <Button
                        variant="danger"
                        onClick={() => {
                            undo.reset()
                            setUndoing(true)
                        }}
                    >
                        {t.undo}
                    </Button>
                </div>
            )}

            {status.data && !status.data.isDrawed && (
                <div className="space-y-4">
                    <Notice
                        tone={status.data.drawable ? 'success' : 'info'}>{status.data.drawable ? t.ready : t.notReady}</Notice>
                    <ul>
                        <Check done={status.data.participantCount >= status.data.minParticipants}>
                            {t.participants(status.data.participantCount, status.data.minParticipants)}
                        </Check>
                        <Check
                            done={status.data.missingWishes.length === 0}>{t.wishes(status.data.minWishAmount)}</Check>
                    </ul>
                    {status.data.missingWishes.length > 0 && (
                        <div>
                            <h3 className="text-sm font-semibold text-ink">{t.missingTitle}</h3>
                            <ul className="mt-1 divide-y divide-line">
                                {status.data.missingWishes.map((member) => (
                                    <li key={member.memberId} className="flex items-center gap-3 py-2">
                    <span className="mr-auto">
                      {member.firstName} {member.lastName} <span
                        className="text-sm text-ink-muted">({member.username})</span>
                    </span>
                                        <span
                                            className="text-sm font-medium text-ink">{t.missingRow(member.wishCount, status.data.minWishAmount)}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                    {status.data.pendingInvitations > 0 &&
                        <p className="text-sm text-ink-muted">{t.pendingInvitations(status.data.pendingInvitations)}</p>}
                    <Button
                        variant="festive"
                        disabled={!status.data.drawable}
                        onClick={() => {
                            start.reset()
                            setStarting(true)
                        }}
                    >
                        {t.start}
                    </Button>
                </div>
            )}

            <Overlay
                open={starting}
                onOpenChange={setStarting}
                title={t.startTitle}
                description={status.data ? t.startDescription(status.data.participantCount) : ''}
            >
                {status.data && (
                    <div className="space-y-4">
                        <p className="text-sm text-ink">{t.startLocks}</p>
                        {status.data.pendingInvitations > 0 &&
                            <Notice tone="info">{t.startPending(status.data.pendingInvitations)}</Notice>}
                        {inferenceWarnings(status.data).map((warning) => (
                            <Notice key={warning} tone="info">
                                {warning}
                            </Notice>
                        ))}
                        {start.error ? <Notice tone="error">{errorMessage(start.error)}</Notice> : null}
                        <div className="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                            <Button type="button" variant="ghost" onClick={() => setStarting(false)}>
                                {t.cancel}
                            </Button>
                            <Button type="button" busy={start.isPending} onClick={() => start.mutate({id: groupId})}>
                                {t.startConfirm}
                            </Button>
                        </div>
                    </div>
                )}
            </Overlay>

            <ConfirmDialog
                open={undoing}
                onOpenChange={setUndoing}
                title={t.undoTitle}
                description={`${t.undoDescription} ${t.undoWarning}`}
                confirmLabel={t.undoConfirm}
                cancelLabel={t.cancel}
                danger
                busy={undo.isPending}
                error={undo.error}
                onConfirm={() => undo.mutate({id: groupId})}
            />
        </Card>
    )
}
