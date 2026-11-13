import { useQueryClient } from '@tanstack/react-query'
import { errorMessage } from '../api/errors'
import { getGetDrawStatusQueryKey } from '../api/generated/draw/draw'
import {
  getGetMyExclusionsQueryKey,
  useDismissExclusionNotice,
  useExcludeMember,
  useGetMyExclusions,
  useIncludeMember,
} from '../api/generated/exclusions/exclusions'
import type { ExclusionStateResponse, GroupResponse } from '../api/generated/model'
import { Badge, Button, Card, Notice } from '../components/ui'
import { de } from '../i18n/de'

const t = de.exclusions

/** "I do not want to draw this person." Shows and changes only the current user's own choices. */
export function ExclusionsCard({ group }: { group: GroupResponse }) {
  const groupId = String(group.id)
  const queryClient = useQueryClient()
  const state = useGetMyExclusions(groupId)

  // Every change answers with the new state, so no second request is needed.
  const onSuccess = (fresh: ExclusionStateResponse) => {
    queryClient.setQueryData(getGetMyExclusionsQueryKey(groupId), fresh)
    void queryClient.invalidateQueries({ queryKey: getGetDrawStatusQueryKey(groupId) })
  }
  const exclude = useExcludeMember({ mutation: { onSuccess } })
  const include = useIncludeMember({ mutation: { onSuccess } })
  const dismiss = useDismissExclusionNotice({ mutation: { onSuccess } })
  const busyId = exclude.isPending ? exclude.variables?.memberId : include.isPending ? include.variables?.memberId : undefined
  const failure = exclude.error ?? include.error

  return (
    <Card title={t.title}>
      <p className="text-sm text-stone-500">{t.intro}</p>

      {state.isPending && <p role="status" className="mt-3 text-stone-500">{de.app.loading}</p>}
      {state.isError && <div className="mt-3"><Notice tone="error">{t.loadFailed}</Notice></div>}

      {state.data && (
        <div className="mt-4 space-y-4">
          {state.data.prunedNotice && (
            <div className="space-y-2 rounded-lg border border-amber-300 bg-amber-50 p-3">
              <p role="status" className="text-sm text-amber-900">
                {t.pruned}
              </p>
              <Button variant="ghost" busy={dismiss.isPending} onClick={() => dismiss.mutate({ id: groupId })}>
                {t.dismiss}
              </Button>
            </div>
          )}

          {!state.data.editable ? (
            <Notice tone="info">{t.locked}</Notice>
          ) : state.data.limit === 0 ? (
            <Notice tone="info">{t.none}</Notice>
          ) : (
            <Notice tone="info">{t.remaining(state.data.remaining, state.data.limit)}</Notice>
          )}
          {failure ? <Notice tone="error">{errorMessage(failure)}</Notice> : null}

          {(state.data.limit > 0 || state.data.candidates.some((candidate) => candidate.excluded)) && (
            <ul className="divide-y divide-stone-200">
              {state.data.candidates.map((candidate) => {
                const memberId = String(candidate.memberId)
                return (
                  <li key={candidate.memberId} className="flex flex-wrap items-center gap-3 py-3">
                    <div className="mr-auto">
                      <p className="font-medium">
                        {candidate.firstName} {candidate.lastName}
                      </p>
                      <p className="text-sm text-stone-500">{candidate.username}</p>
                    </div>
                    {candidate.excluded && <Badge tone="ready">{t.excluded}</Badge>}
                    {state.data.editable &&
                      (candidate.excluded ? (
                        <Button
                          variant="ghost"
                          busy={busyId === memberId}
                          onClick={() => {
                            exclude.reset()
                            include.mutate({ id: groupId, memberId })
                          }}
                        >
                          {t.include}
                        </Button>
                      ) : (
                        <Button
                          variant="ghost"
                          busy={busyId === memberId}
                          disabled={state.data.remaining === 0}
                          onClick={() => {
                            include.reset()
                            exclude.mutate({ id: groupId, memberId })
                          }}
                        >
                          {t.exclude}
                        </Button>
                      ))}
                  </li>
                )
              })}
            </ul>
          )}

          {state.data.editable && state.data.limit > 0 && <p className="text-sm text-stone-500">{t.inference}</p>}
        </div>
      )}
    </Card>
  )
}
