import {Dialog} from 'radix-ui'
import type {ReactNode} from 'react'
import starUrl from '../assets/illustrations/stern.svg'
import {de} from '../i18n/de'

type OverlayProps = {
    open: boolean
    onOpenChange: (open: boolean) => void
    title: string
    description: string
    children: ReactNode
}

/** The "detail overlay": a modal dialog that is a bottom sheet on phones and a centred card on larger screens. */
export function Overlay({open, onOpenChange, title, description, children}: OverlayProps) {
    return (
        <Dialog.Root open={open} onOpenChange={onOpenChange}>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-40 bg-ink/50"/>
                <Dialog.Content
                    className="fixed inset-x-0 bottom-0 z-50 max-h-[92dvh] overflow-y-auto rounded-t-3xl border border-line bg-paper-000 p-6 shadow-overlay outline-none sm:inset-auto sm:top-1/2 sm:left-1/2 sm:w-full sm:max-w-lg sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-3xl">
                    <div aria-hidden="true" className="candy -mx-6 -mt-6 mb-5 h-2.5"/>
                    <div className="mb-4 flex items-start gap-4">
                        <div className="mr-auto">
                            <Dialog.Title className="flex items-center gap-2 font-display text-2xl font-semibold">
                                <img src={starUrl} alt="" className="size-6 shrink-0"/>
                                {title}
                            </Dialog.Title>
                            <Dialog.Description
                                className="mt-1 text-sm text-ink-muted">{description}</Dialog.Description>
                        </div>
                        <Dialog.Close
                            aria-label={de.dialog.close}
                            className="-mt-2 -mr-2 inline-flex size-11 shrink-0 items-center justify-center rounded-full text-2xl text-ink-muted hover:bg-paper-200"
                        >
                            ×
                        </Dialog.Close>
                    </div>
                    {children}
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    )
}
