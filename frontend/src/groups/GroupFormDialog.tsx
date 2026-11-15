import {zodResolver} from '@hookform/resolvers/zod'
import {useQueryClient} from '@tanstack/react-query'
import {Slider} from 'radix-ui'
import {Controller, useForm, useWatch} from 'react-hook-form'
import {z} from 'zod'
import {applyServerErrors} from '../api/errors'
import {
    getGetGroupQueryKey,
    getListGroupsQueryKey,
    useCreateGroup,
    useUpdateGroup
} from '../api/generated/groups/groups'
import type {GroupRequest, GroupResponse} from '../api/generated/model'
import {GroupRequestCurrency} from '../api/generated/model'
import {ApiError} from '../api/http'
import {Overlay} from '../components/Overlay'
import {Button, Field, Notice, Select, TextArea} from '../components/ui'
import {de} from '../i18n/de'
import {formatMoney} from './format'

const t = de.groupForm
const BUDGET_STEP = 5
const DEFAULT_BUDGET: [number, number] = [20, 40]

const schema = z.object({
    title: z.string().trim().min(1, de.validation.required).max(120, t.validation.titleLength),
    description: z.string().max(2000),
    year: z.number({error: t.validation.year}).int(t.validation.year).min(2000, t.validation.year).max(2100, t.validation.year),
    eventDate: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, t.validation.eventDate),
    minWishAmount: z
        .number({error: t.validation.minWishAmount})
        .int(t.validation.minWishAmount)
        .min(0, t.validation.minWishAmount)
        .max(20, t.validation.minWishAmount),
    hasBudget: z.boolean(),
    budget: z.tuple([z.number(), z.number()]),
    currency: z.enum(GroupRequestCurrency),
})
type Values = z.infer<typeof schema>

function defaults(group?: GroupResponse): Values {
    if (!group) {
        const year = new Date().getFullYear()
        return {
            title: '',
            description: '',
            year,
            eventDate: `${year}-12-24`,
            minWishAmount: 3,
            hasBudget: false,
            budget: DEFAULT_BUDGET,
            currency: 'CHF',
        }
    }
    const hasBudget = group.budgetMin !== null || group.budgetMax !== null
    return {
        title: group.title,
        description: group.description ?? '',
        year: group.year,
        eventDate: group.eventDate,
        minWishAmount: group.minWishAmount,
        hasBudget,
        budget: hasBudget
            ? [group.budgetMin ?? group.budgetMax ?? 0, group.budgetMax ?? group.budgetMin ?? 0]
            : DEFAULT_BUDGET,
        currency: group.currency,
    }
}

function toRequest(values: Values): GroupRequest {
    return {
        title: values.title,
        description: values.description.trim() === '' ? null : values.description,
        year: values.year,
        eventDate: values.eventDate,
        minWishAmount: values.minWishAmount,
        budgetMin: values.hasBudget ? values.budget[0] : null,
        budgetMax: values.hasBudget ? values.budget[1] : null,
        currency: values.currency,
    }
}

type Props = {
    /** Omit to create a new group. */
    group?: GroupResponse
    open: boolean
    onOpenChange: (open: boolean) => void
    onSaved?: (group: GroupResponse) => void
}

export function GroupFormDialog({group, open, onOpenChange, onSaved}: Props) {
    return (
        <Overlay open={open} onOpenChange={onOpenChange} title={group ? t.editTitle : t.createTitle}
                 description={t.description}>
            {/* Mounted with the overlay, so every opening starts from the current group. */}
            <GroupForm group={group} onDone={() => onOpenChange(false)} onSaved={onSaved}/>
        </Overlay>
    )
}

function GroupForm({group, onDone, onSaved}: {
    group?: GroupResponse;
    onDone: () => void;
    onSaved?: (group: GroupResponse) => void
}) {
    const queryClient = useQueryClient()
    const locked = group?.isDrawed ?? false
    const {register, handleSubmit, control, setError, formState} = useForm<Values>({
        resolver: zodResolver(schema),
        defaultValues: defaults(group),
    })
    const {errors} = formState
    const [hasBudget, budget, currency] = useWatch({control, name: ['hasBudget', 'budget', 'currency']})
    // The slider grows with an existing budget above its usual range.
    const sliderMax = Math.max(200, Math.ceil(budget[1] / 50) * 50)

    const handlers = {
        onSuccess: (saved: GroupResponse) => {
            queryClient.setQueryData(getGetGroupQueryKey(String(saved.id)), saved)
            void queryClient.invalidateQueries({queryKey: getListGroupsQueryKey()})
            onSaved?.(saved)
            onDone()
        },
        onError: (error: unknown) => {
            if (error instanceof ApiError && error.status === 409 && error.body && 'detail' in error.body && error.body.detail) {
                setError('root', {type: 'server', message: error.body.detail})
                return
            }
            applyServerErrors(error, setError, ['title', 'description', 'year', 'eventDate', 'minWishAmount', 'currency'])
        },
    }
    const create = useCreateGroup({mutation: handlers})
    const update = useUpdateGroup({mutation: handlers})
    const busy = create.isPending || update.isPending

    const submit = handleSubmit((values) => {
        const data = toRequest(values)
        if (group) {
            update.mutate({id: String(group.id), data})
        } else {
            create.mutate({data})
        }
    })

    return (
        <form className="space-y-4" noValidate onSubmit={submit}>
            {errors.root && <Notice tone="error">{errors.root.message}</Notice>}
            {locked && <Notice tone="info">{t.lockedHint}</Notice>}
            <Field label={t.title} error={errors.title?.message} {...register('title')} />
            <TextArea label={t.details} error={errors.description?.message} {...register('description')} />
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label={t.eventDate} type="date" error={errors.eventDate?.message} {...register('eventDate')} />
                <Field
                    label={t.year}
                    type="number"
                    inputMode="numeric"
                    disabled={locked}
                    error={errors.year?.message}
                    {...register('year', {valueAsNumber: true})}
                />
            </div>
            <Field
                label={t.minWishAmount}
                type="number"
                inputMode="numeric"
                min={0}
                max={20}
                disabled={locked}
                error={errors.minWishAmount?.message}
                {...register('minWishAmount', {valueAsNumber: true})}
            />

            <fieldset className="rounded-2xl border-2 border-dashed border-gold-700/50 bg-gold-100/50 p-4" disabled={locked}>
                <label className="flex min-h-11 items-center gap-3 text-sm font-medium text-ink">
                    <input type="checkbox" className="size-5 accent-tanne-700" {...register('hasBudget')} />
                    {t.hasBudget}
                </label>
                {hasBudget && (
                    <div className="mt-2 space-y-4">
                        <p className="text-center font-display text-2xl font-semibold text-wein-700" aria-live="polite">
                            {t.budgetRange(formatMoney(budget[0], currency), formatMoney(budget[1], currency))}
                        </p>
                        <Controller
                            control={control}
                            name="budget"
                            render={({field}) => (
                                <Slider.Root
                                    className="relative flex h-11 touch-none items-center select-none"
                                    min={0}
                                    max={sliderMax}
                                    step={BUDGET_STEP}
                                    minStepsBetweenThumbs={0}
                                    value={field.value}
                                    onValueChange={(value) => field.onChange([value[0], value[1]])}
                                    disabled={locked}
                                >
                                    <Slider.Track className="relative h-2.5 grow rounded-full bg-paper-000 ring-1 ring-line-strong">
                                        <Slider.Range className="absolute h-full rounded-full bg-tanne-700"/>
                                    </Slider.Track>
                                    {[t.budgetMin, t.budgetMax].map((label) => (
                                        <Slider.Thumb
                                            key={label}
                                            aria-label={label}
                                            className="block size-8 rounded-full border-[3px] border-paper-000 bg-wein-700 shadow-card focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-tanne-700"
                                        />
                                    ))}
                                </Slider.Root>
                            )}
                        />
                        <Select label={t.currency} error={errors.currency?.message} {...register('currency')}>
                            {Object.values(GroupRequestCurrency).map((code) => (
                                <option key={code} value={code}>
                                    {code}
                                </option>
                            ))}
                        </Select>
                    </div>
                )}
            </fieldset>

            <div className="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                <Button type="button" variant="ghost" onClick={onDone}>
                    {t.cancel}
                </Button>
                <Button type="submit" busy={busy}>
                    {group ? t.save : t.create}
                </Button>
            </div>
        </form>
    )
}
