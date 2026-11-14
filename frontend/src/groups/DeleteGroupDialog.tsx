import {useQueryClient} from '@tanstack/react-query'
import {useState} from 'react'
import {getGetGroupQueryKey, getListGroupsQueryKey, useDeleteGroup} from '../api/generated/groups/groups'
import type {GroupResponse} from '../api/generated/model'
import {Overlay} from '../components/Overlay'
import {Button, Field, Notice} from '../components/ui'
import {de} from '../i18n/de'

const t = de.deleteGroup

type Props = { group: GroupResponse; open: boolean; onOpenChange: (open: boolean) => void; onDeleted: () => void }

export function DeleteGroupDialog({group, open, onOpenChange, onDeleted}: Props) {
    return (
        <Overlay open={open} onOpenChange={onOpenChange} title={t.title} description={t.description(group.title)}>
            <DeleteGroupForm group={group} onCancel={() => onOpenChange(false)} onDeleted={onDeleted}/>
        </Overlay>
    )
}

function DeleteGroupForm({group, onCancel, onDeleted}: {
    group: GroupResponse;
    onCancel: () => void;
    onDeleted: () => void
}) {
    const queryClient = useQueryClient()
    const [typed, setTyped] = useState('')
    const remove = useDeleteGroup({
        mutation: {
            onSuccess: () => {
                queryClient.removeQueries({queryKey: getGetGroupQueryKey(String(group.id))})
                void queryClient.invalidateQueries({queryKey: getListGroupsQueryKey()})
                onDeleted()
            },
        },
    })
    const confirmed = typed.trim() === group.title.trim()
    const failure = remove.error ? ((remove.error.body && 'detail' in remove.error.body && remove.error.body.detail) || de.errors.generic) : null

    return (
        <form
            className="space-y-4"
            onSubmit={(event) => {
                event.preventDefault()
                if (confirmed) remove.mutate({id: String(group.id)})
            }}
        >
            {failure && <Notice tone="error">{failure}</Notice>}
            <Field
                label={t.confirmLabel(group.title)}
                value={typed}
                onChange={(event) => setTyped(event.target.value)}
                autoComplete="off"
                autoCapitalize="none"
                spellCheck={false}
            />
            <div className="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                <Button type="button" variant="ghost" onClick={onCancel}>
                    {t.cancel}
                </Button>
                <Button type="submit" variant="danger" disabled={!confirmed} busy={remove.isPending}>
                    {t.confirm}
                </Button>
            </div>
        </form>
    )
}
