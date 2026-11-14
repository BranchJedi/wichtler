export type ChristmasCountdown =
    | { state: 'counting'; days: number; hours: number; minutes: number }
    | { state: 'christmas' }

const MINUTE = 60_000

/**
 * Time left until Christmas Eve (24 December, 00:00 local time). From the 24th to the 26th it
 * is Christmas; after that the countdown runs towards next year.
 */
export function christmasCountdown(now: Date): ChristmasCountdown {
    const year = now.getFullYear()
    if (now.getMonth() === 11 && now.getDate() >= 24 && now.getDate() <= 26) {
        return {state: 'christmas'}
    }
    const thisYear = new Date(year, 11, 24)
    const target = now < thisYear ? thisYear : new Date(year + 1, 11, 24)
    const minutesLeft = Math.ceil((target.getTime() - now.getTime()) / MINUTE)
    return {
        state: 'counting',
        days: Math.floor(minutesLeft / 1440),
        hours: Math.floor((minutesLeft % 1440) / 60),
        minutes: minutesLeft % 60,
    }
}
