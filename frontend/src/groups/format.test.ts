import {describe, expect, it} from 'vitest'
import {formatBudget, formatEventDate, groupRole, groupStatus} from './format'

// Intl output uses narrow no-break spaces in places; compare on plain spaces.
const plain = (text: string) => text.replace(/[  ]/g, ' ')

describe('formatBudget', () => {
    it('shows a range in the group currency', () => {
        expect(plain(formatBudget({budgetMin: 20, budgetMax: 40, currency: 'CHF'}))).toBe('CHF 20 bis CHF 40')
        expect(plain(formatBudget({budgetMin: 20, budgetMax: 40, currency: 'EUR'}))).toBe('EUR 20 bis EUR 40')
    })

    it('collapses equal bounds and tolerates a single bound', () => {
        expect(plain(formatBudget({budgetMin: 30, budgetMax: 30, currency: 'CHF'}))).toBe('CHF 30')
        expect(plain(formatBudget({budgetMin: null, budgetMax: 50, currency: 'CHF'}))).toBe('CHF 50')
    })

    it('says so when there is no budget', () => {
        expect(formatBudget({budgetMin: null, budgetMax: null, currency: 'CHF'})).toBe('Kein Budget festgelegt')
    })
})

describe('formatEventDate', () => {
    it('keeps the calendar day whatever the time zone', () => {
        expect(formatEventDate('2026-12-24')).toContain('24. Dezember 2026')
        expect(formatEventDate('2027-01-01')).toContain('1. Januar 2027')
    })
})

describe('groupStatus', () => {
    it('prefers "drawn" over "ready" over "open"', () => {
        expect(groupStatus({isDrawed: true, drawable: false}).label).toBe('Ausgelost')
        expect(groupStatus({isDrawed: false, drawable: true}).label).toBe('Bereit zur Auslosung')
        expect(groupStatus({isDrawed: false, drawable: false}).label).toBe('Offen')
    })
})

describe('groupRole', () => {
    it('marks the Wichtler admin looking at a foreign group', () => {
        expect(groupRole({isOwner: true, isMember: true}).label).toBe('Gruppenadmin')
        expect(groupRole({isOwner: false, isMember: true}).label).toBe('Teilnehmer')
        expect(groupRole({isOwner: false, isMember: false})).toEqual({label: 'Admin-Ansicht', tone: 'admin'})
    })
})
