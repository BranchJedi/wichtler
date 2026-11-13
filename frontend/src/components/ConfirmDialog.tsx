import { errorMessage } from '../api/errors'
import { Overlay } from './Overlay'
import { Button, Notice } from './ui'

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  title: string
  description: string
  confirmLabel: string
  cancelLabel: string
  danger?: boolean
  busy: boolean
  /** The failed attempt, if any. Its explanation from the server is shown. */
  error: unknown
  onConfirm: () => void
}

/** A detail overlay that only asks "really?". */
export function ConfirmDialog({ open, onOpenChange, title, description, confirmLabel, cancelLabel, danger, busy, error, onConfirm }: Props) {
  return (
    <Overlay open={open} onOpenChange={onOpenChange} title={title} description={description}>
      <div className="space-y-4">
        {error ? <Notice tone="error">{errorMessage(error)}</Notice> : null}
        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
            {cancelLabel}
          </Button>
          <Button type="button" variant={danger ? 'danger' : 'primary'} busy={busy} onClick={onConfirm}>
            {confirmLabel}
          </Button>
        </div>
      </div>
    </Overlay>
  )
}
