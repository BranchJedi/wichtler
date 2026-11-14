import {Dialog} from 'radix-ui'
import type {ReactNode} from 'react'
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
                    className="fixed inset-x-0 bottom-0 z-50 max-h-[92dvh] overflow-y-auto rounded-t-2xl border border-line bg-paper-000 p-6 shadow-overlay outline-none sm:inset-auto sm:top-1/2 sm:left-1/2 sm:w-full sm:max-w-lg sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-2xl">
                    <div className="mb-4 flex items-start gap-4">
                        <div className="mr-auto">
                            <Dialog.Title className="font-display text-xl font-semibold">{title}</Dialog.Title>
                            <Dialog.Description
                                className="mt-1 text-sm text-ink-muted">{description}</Dialog.Description>
                        </div>
                        <Dialog.Close
                            aria-label={de.dialog.close}
                            className="-mt-2 -mr-2 inline-flex size-11 shrink-0 items-center justify-center rounded-lg text-xl text-ink-muted hover:bg-paper-200"
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
