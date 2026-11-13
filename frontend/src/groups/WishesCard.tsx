import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { applyServerErrors, errorMessage } from '../api/errors'
import { getGetDrawStatusQueryKey } from '../api/generated/draw/draw'
import { getGetGroupQueryKey, getListGroupsQueryKey } from '../api/generated/groups/groups'
import type { GroupResponse, WishResponse } from '../api/generated/model'
import { getListMyWishesQueryKey, useCreateWish, useDeleteWish, useListMyWishes, useUpdateWish } from '../api/generated/wishes/wishes'
import { ApiError } from '../api/http'
import { ConfirmDialog } from '../components/ConfirmDialog'
import { Overlay } from '../components/Overlay'
import { Button, Card, Field, Notice, TextArea } from '../components/ui'
import { de } from '../i18n/de'

const t = de.wishes

const schema = z.object({
  title: z.string().trim().min(1, de.validation.required).max(160, t.validation.titleLength),
  description: z.string().max(2000),
  url: z
    .string()
    .trim()
    .max(500)
    .refine((value) => value === '' || /^https?:\/\/\S+\.\S+/i.test(value), t.validation.url),
})
type Values = z.infer<typeof schema>

/** A wish changes the participant's progress and with it whether the group can be drawn. */
function useRefreshWishes(groupId: string) {
  const queryClient = useQueryClient()
  return () => {
    void queryClient.invalidateQueries({ queryKey: getListMyWishesQueryKey(groupId) })
    void queryClient.invalidateQueries({ queryKey: getGetGroupQueryKey(groupId) })
    void queryClient.invalidateQueries({ queryKey: getGetDrawStatusQueryKey(groupId) })
    void queryClient.invalidateQueries({ queryKey: getListGroupsQueryKey() })
  }
}

export function WishesCard({ group }: { group: GroupResponse }) {
  const groupId = String(group.id)
  const refresh = useRefreshWishes(groupId)
  const wishes = useListMyWishes(groupId)
  // undefined: closed, null: new wish, otherwise the wish being edited
  const [editing, setEditing] = useState<WishResponse | null | undefined>(undefined)
  const [deleting, setDeleting] = useState<WishResponse | null>(null)
  const remove = useDeleteWish({
    mutation: {
      onSuccess: () => {
        refresh()
        setDeleting(null)
      },
    },
  })

  const count = wishes.data?.length ?? 0
  const missing = Math.max(0, group.minWishAmount - count)

  return (
    <Card title={t.title}>
      <p className="text-sm text-stone-500">{t.intro}</p>

      {wishes.isPending && <p role="status" className="mt-3 text-stone-500">{de.app.loading}</p>}
      {wishes.isError && <div className="mt-3"><Notice tone="error">{t.loadFailed}</Notice></div>}

      {wishes.data && (
        <>
          <div className="mt-4">
            <Notice tone={missing > 0 ? 'info' : 'success'}>
              {group.minWishAmount > 0 ? t.progress(count, group.minWishAmount) : t.progressDone(count)}
              {missing > 0 && ` – ${t.missing(missing)}`}
            </Notice>
          </div>

          {count === 0 ? (
            <p className="mt-4 text-stone-700">{t.empty}</p>
          ) : (
            <ul className="mt-2 divide-y divide-stone-200">
              {wishes.data.map((wish) => (
                <li key={wish.id} className="py-3">
                  <p className="font-medium">{wish.title}</p>
                  {wish.description && <p className="mt-1 whitespace-pre-line text-sm text-stone-700">{wish.description}</p>}
                  {wish.url && (
                    <a
                      href={wish.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="mt-1 inline-block text-sm font-medium break-all text-pine-700 underline"
                    >
                      {t.link}
                    </a>
                  )}
                  <div className="mt-2 flex gap-2">
                    <Button variant="ghost" onClick={() => setEditing(wish)}>
                      {t.edit}
                    </Button>
                    <Button
                      variant="ghost"
                      onClick={() => {
                        remove.reset()
                        setDeleting(wish)
                      }}
                    >
                      {t.delete}
                    </Button>
                  </div>
                </li>
              ))}
            </ul>
          )}

          <div className="mt-4">
            <Button onClick={() => setEditing(null)}>{t.add}</Button>
          </div>
        </>
      )}

      <Overlay
        open={editing !== undefined}
        onOpenChange={(open) => !open && setEditing(undefined)}
        title={editing ? t.editTitle : t.addTitle}
        description={t.formDescription}
      >
        <WishForm
          groupId={groupId}
          wish={editing ?? undefined}
          onDone={() => {
            refresh()
            setEditing(undefined)
          }}
          onCancel={() => setEditing(undefined)}
        />
      </Overlay>

      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={t.deleteTitle}
        description={deleting ? t.deleteDescription(deleting.title) : ''}
        confirmLabel={t.delete}
        cancelLabel={t.cancel}
        danger
        busy={remove.isPending}
        error={remove.error}
        onConfirm={() => deleting && remove.mutate({ id: groupId, wishId: String(deleting.id) })}
      />
    </Card>
  )
}

function WishForm({ groupId, wish, onDone, onCancel }: { groupId: string; wish?: WishResponse; onDone: () => void; onCancel: () => void }) {
  const { register, handleSubmit, setError, formState } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { title: wish?.title ?? '', description: wish?.description ?? '', url: wish?.url ?? '' },
  })
  const handlers = {
    onSuccess: onDone,
    onError: (error: unknown) => {
      if (error instanceof ApiError && error.status === 409) {
        setError('root', { type: 'server', message: errorMessage(error) })
        return
      }
      applyServerErrors(error, setError, ['title', 'description', 'url'])
    },
  }
  const create = useCreateWish({ mutation: handlers })
  const update = useUpdateWish({ mutation: handlers })
  const { errors } = formState

  const submit = handleSubmit((values) => {
    const data = { title: values.title, description: values.description.trim() || null, url: values.url || null }
    if (wish) {
      update.mutate({ id: groupId, wishId: String(wish.id), data })
    } else {
      create.mutate({ id: groupId, data })
    }
  })

  return (
    <form className="space-y-4" noValidate onSubmit={submit}>
      {errors.root && <Notice tone="error">{errors.root.message}</Notice>}
      <Field label={t.fieldTitle} error={errors.title?.message} {...register('title')} />
      <TextArea label={t.fieldDescription} error={errors.description?.message} {...register('description')} />
      <Field
        label={t.fieldUrl}
        type="url"
        inputMode="url"
        autoCapitalize="none"
        autoCorrect="off"
        spellCheck={false}
        placeholder={t.urlPlaceholder}
        error={errors.url?.message}
        {...register('url')}
      />
      <div className="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
        <Button type="button" variant="ghost" onClick={onCancel}>
          {t.cancel}
        </Button>
        <Button type="submit" busy={create.isPending || update.isPending}>
          {t.save}
        </Button>
      </div>
    </form>
  )
}
