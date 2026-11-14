import starUrl from '../assets/illustrations/stern.svg'

const sparkles = [
    'left-0 top-6 size-5',
    'left-8 -top-1 size-7 [animation-delay:320ms]',
    'right-6 top-0 size-6 [animation-delay:260ms]',
    'right-0 top-10 size-4 [animation-delay:420ms]',
    'left-1/2 -top-3 size-4 [animation-delay:500ms]',
]

/**
 * The present of the draw. Closed while the result is hidden; when opened the lid lifts once
 * and gold stars sparkle. Purely decorative, so hidden from assistive technology.
 */
export function GiftBox({open}: { open: boolean }) {
    return (
        <div aria-hidden="true" className="relative w-28 shrink-0 sm:w-32">
            {open && sparkles.map((position) => (
                <img key={position} src={starUrl} alt=""
                     className={`absolute animate-sparkle opacity-0 ${position}`}/>
            ))}
            <svg viewBox="0 0 160 170" className="block w-full overflow-visible">
                <ellipse cx="80" cy="158" rx="58" ry="6" fill="#2a1f1a" opacity=".10"/>
                <rect x="28" y="80" width="104" height="76" rx="6" fill="#1f4d3a"/>
                <rect x="28" y="80" width="104" height="12" fill="#173b2c"/>
                <rect x="71" y="80" width="18" height="76" fill="#c9a04a"/>
                <g className={open ? 'origin-bottom-left animate-lid [transform-box:fill-box]' : undefined}>
                    <rect x="20" y="60" width="120" height="26" rx="6" fill="#28614a"/>
                    <rect x="69" y="60" width="22" height="26" fill="#d9b566"/>
                    <path d="M80 60C66 32 40 30 38 44s22 18 42 16zM80 60c14-28 40-30 42-16s-22 18-42 16z"
                          fill="#c9a04a"/>
                    <path d="M80 60C70 44 52 40 50 47s14 12 30 13zM80 60c10-16 28-20 30-13s-14 12-30 13z"
                          fill="#f8f1e3"/>
                    <circle cx="80" cy="59" r="7" fill="#8a6417"/>
                </g>
            </svg>
        </div>
    )
}
