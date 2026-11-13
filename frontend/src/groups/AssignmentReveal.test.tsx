import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import type { AssignmentResponse } from '../api/generated/model'
import { AssignmentReveal } from './AssignmentReveal'

const assignment: AssignmentResponse = {
  receiverFirstName: 'Carla',
  receiverLastName: 'Beispiel',
  receiverUsername: 'carla',
  wishes: [
    { id: 1, title: 'Wollsocken', description: 'Grösse 43', url: 'https://example.com/socken' },
    { id: 2, title: 'Ein gutes Buch', description: null, url: null },
  ],
}

function setup(load = vi.fn(() => Promise.resolve(assignment))) {
  render(<AssignmentReveal load={load} revealLabel="Wichtel aufdecken" lead="Du beschenkst" />)
  return { load, user: userEvent.setup() }
}

describe('AssignmentReveal', () => {
  it('does not load or show the result before the user asks for it', () => {
    const { load } = setup()

    expect(load).not.toHaveBeenCalled()
    expect(screen.queryByText(/Carla/)).toBeNull()
    expect(screen.queryByText('Wollsocken')).toBeNull()
    // What sits behind the blur is a dummy, hidden from assistive technology.
    expect(screen.getByTestId('assignment-dummy').getAttribute('aria-hidden')).toBe('true')
    expect(screen.getByTestId('assignment-dummy').className).toContain('blur')
  })

  it('reveals the name together with the wishes', async () => {
    const { load, user } = setup()

    await user.click(screen.getByRole('button', { name: 'Wichtel aufdecken' }))

    expect(load).toHaveBeenCalledTimes(1)
    expect(await screen.findByText('Carla Beispiel')).toBeTruthy()
    expect(screen.getByText('Wollsocken')).toBeTruthy()
    expect(screen.getByText('Grösse 43')).toBeTruthy()
    expect(screen.getByText('Ein gutes Buch')).toBeTruthy()
    expect(screen.queryByTestId('assignment-dummy')).toBeNull()
  })

  it('opens wish links in a new tab without handing over the opener', async () => {
    const { user } = setup()
    await user.click(screen.getByRole('button', { name: 'Wichtel aufdecken' }))

    const link = await screen.findByRole('link', { name: 'Link öffnen' })

    expect(link.getAttribute('href')).toBe('https://example.com/socken')
    expect(link.getAttribute('target')).toBe('_blank')
    expect(link.getAttribute('rel')).toBe('noopener noreferrer')
  })

  it('hides name and wishes together and forgets them', async () => {
    const { load, user } = setup()
    await user.click(screen.getByRole('button', { name: 'Wichtel aufdecken' }))
    await screen.findByText('Carla Beispiel')

    await user.click(screen.getByRole('button', { name: 'Verbergen' }))

    expect(screen.queryByText('Carla Beispiel')).toBeNull()
    expect(screen.queryByText('Wollsocken')).toBeNull()
    expect(document.body.innerHTML).not.toContain('Carla')
    expect(screen.getByTestId('assignment-dummy')).toBeTruthy()

    // Revealing again asks the server again: nothing was kept.
    await user.click(screen.getByRole('button', { name: 'Wichtel aufdecken' }))
    await screen.findByText('Carla Beispiel')
    expect(load).toHaveBeenCalledTimes(2)
  })

  it('says so when a participant has no wishes', async () => {
    const { user } = setup(vi.fn(() => Promise.resolve({ ...assignment, wishes: [] })))

    await user.click(screen.getByRole('button', { name: 'Wichtel aufdecken' }))

    expect(await screen.findByText('Keine Wünsche erfasst.')).toBeTruthy()
  })

  it('stays hidden and explains when loading fails', async () => {
    const { user } = setup(vi.fn(() => Promise.reject(new Error('offline'))))

    await user.click(screen.getByRole('button', { name: 'Wichtel aufdecken' }))

    expect(await screen.findByRole('alert')).toBeTruthy()
    expect(screen.getByTestId('assignment-dummy')).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Wichtel aufdecken' })).toBeTruthy()
  })
})
