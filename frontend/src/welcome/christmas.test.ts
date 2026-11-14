import {describe, expect, it} from 'vitest'
import {christmasCountdown} from './christmas'

describe('christmasCountdown', () => {
    it('counts days, hours and minutes until Christmas Eve', () => {
        expect(christmasCountdown(new Date(2026, 10, 14, 17, 30))).toEqual({
            state: 'counting',
            days: 39,
            hours: 6,
            minutes: 30,
        })
    })

    it('reaches zero days on the evening before', () => {
        expect(christmasCountdown(new Date(2026, 11, 23, 23, 59))).toEqual({
            state: 'counting',
            days: 0,
            hours: 0,
            minutes: 1,
        })
    })

    it.each([24, 25, 26])('is Christmas on 2%i December', (day) => {
        expect(christmasCountdown(new Date(2026, 11, day, 12))).toEqual({state: 'christmas'})
    })

    it('counts towards next year after Christmas', () => {
        expect(christmasCountdown(new Date(2026, 11, 27))).toEqual({
            state: 'counting',
            days: 362,
            hours: 0,
            minutes: 0,
        })
    })
})
