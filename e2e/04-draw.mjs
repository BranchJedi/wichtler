// Exclusions, the draw, blur/reveal and who may see what.
// Run through e2e/run.sh; arguments: <base-url> <out-dir> <admin-user> <admin-password>
import { start } from './harness.mjs'

const [base, out, adminUser, adminPw] = process.argv.slice(2)
const t = await start(base, out, 9336)
const { js, goto, wait, shot, step } = t

const run = Date.now().toString(36)
const pw = 'e2e-' + Math.random().toString(36).slice(2) + '-pw'
const owner = `e2e.olga.${run}`
const ben = `e2e.ben.${run}`
const carla = `e2e.carla.${run}`
const dora = `e2e.dora.${run}`

const login = async (username, password = pw) => {
  await goto('/login')
  await wait('login form', `location.pathname === '/login'`)
  await js(`(__t.fill('Username', '${username}'), __t.fill('Passwort', '${password}'), __t.click('Anmelden'))`)
  await wait('signed in', `location.pathname === '/'`)
}
const logout = async () => {
  await js(`__t.click('Abmelden')`)
  await wait('logged out', `location.pathname === '/login'`)
}
const post = `const post = (url, body) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined })`
// the card (section) with the given heading
const card = (title) => `[...document.querySelectorAll('section')].find((s) => s.querySelector('h2')?.textContent === '${title}')`
const rowIn = (title, text) => `[...${card(title)}.querySelectorAll('li')].find((li) => li.textContent.includes('${text}'))`
const requested = (part) => `performance.getEntriesByType('resource').some((e) => e.name.includes('${part}'))`

let groupId, groupPath

try {
  await step('setup through the API: group of four, everyone with three wishes', async () => {
    await goto('/login')
    const result = await js(`(async () => {
      ${post}
      await post('/api/auth/register', { firstName: 'Olga', lastName: 'E2E', username: '${owner}', password: '${pw}' })
      const group = await (await post('/api/groups', { title: 'E2E Auslosung', year: 2026, eventDate: '2026-12-24', minWishAmount: 3, currency: 'CHF' })).json()
      for (let i = 1; i <= 3; i++) await post('/api/groups/' + group.id + '/wishes', { title: 'Geheimwunsch Olga ' + i })
      const token = (await (await fetch('/api/groups/' + group.id + '/invite-link')).json()).token
      await post('/api/auth/logout')
      for (const [first, username] of [['Ben', '${ben}'], ['Carla', '${carla}'], ['Dora', '${dora}']]) {
        await post('/api/auth/register', { firstName: first, lastName: 'E2E', username, password: '${pw}' })
        await post('/api/invite-links/' + token + '/accept')
        for (let i = 1; i <= 3; i++) await post('/api/groups/' + group.id + '/wishes', { title: 'Geheimwunsch ' + first + ' ' + i })
        await post('/api/auth/logout')
      }
      return group.id
    })()`)
    if (!Number.isInteger(result)) throw new Error('setup failed: ' + result)
    groupId = result
    groupPath = '/gruppen/' + groupId
  })

  await step('participant excludes one person; the limit of one is then used up', async () => {
    await login(ben)
    await goto(groupPath)
    await wait('exclusion card', `${card('Meine Ausschlüsse')}.textContent.includes('Du kannst noch 1 von 1 Person ausschliessen.') && !__t.has('Dein Wichtel')`)
    await js(`__t.click('Ausschliessen', ${rowIn('Meine Ausschlüsse', carla)})`)
    await wait('excluded', `${rowIn('Meine Ausschlüsse', carla)}.textContent.includes('Ausgeschlossen') && ${card('Meine Ausschlüsse')}.textContent.includes('Du kannst noch 0 von 1 Person ausschliessen.')`)
    const others = await js(`[...${card('Meine Ausschlüsse')}.querySelectorAll('button')].filter((b) => b.textContent === 'Ausschliessen').every((b) => b.disabled)`)
    if (!others) throw new Error('further exclusions should be disabled')
    await shot('exclusions-mobile')
  })

  await step('a person cannot be excluded by a second participant; the refusal stays vague', async () => {
    await logout()
    await login(dora)
    await goto(groupPath)
    await wait('card', `${card('Meine Ausschlüsse')}.textContent.includes('1 von 1')`)
    if (await js(`${card('Meine Ausschlüsse')}.textContent.includes('Ausgeschlossen')`)) throw new Error('dora sees an exclusion that is not hers')
    await js(`__t.click('Ausschliessen', ${rowIn('Meine Ausschlüsse', carla)})`)
    await wait('refusal', `${card('Meine Ausschlüsse')}.textContent.includes('Diese Person kann nicht weiter ausgeschlossen werden.')`)
  })

  await step('group admin starts the draw; the overlay warns about small groups and exclusions', async () => {
    await logout()
    await login(owner)
    await goto(groupPath)
    await wait('ready', `__t.has('Alles bereit: Die Gruppe kann ausgelost werden.')`)
    if (await js(`${card('Meine Ausschlüsse')}.textContent.includes('Ausgeschlossen')`)) throw new Error('group admin sees exclusions of others')
    await js(`__t.click('Auslosung starten')`)
    await wait('overlay', `!!__t.dialog() && __t.dialog().textContent.includes('4 Teilnehmer werden einander zufällig zugelost') && __t.dialog().textContent.includes('nur wenige mögliche Zuteilungen') && __t.dialog().textContent.includes('Es wurden Ausschlüsse gesetzt') && __t.dialog().textContent.includes('kann nicht gelöscht werden')`)
    await shot('draw-start-overlay-mobile')
    await js(`__t.click('Jetzt auslosen', __t.dialog())`)
    await wait('drawn', `!__t.dialog() && __t.has('Ausgelost') && __t.has('Dein Wichtel') && __t.has('Ausgelost am')`)
  })

  await step('result is blurred and not even loaded until asked for', async () => {
    await goto(groupPath)
    await wait('hidden', `!!document.querySelector('[data-testid=assignment-dummy]') && !document.querySelector('[data-testid=assignment-revealed]')`)
    if (await js(requested('my-assignment'))) throw new Error('assignment was requested before the reveal')
    const text = await js(`${card('Dein Wichtel')}.innerText`)
    for (const name of ['Ben', 'Carla', 'Dora', 'Geheimwunsch']) if (text.includes(name)) throw new Error('hidden card mentions ' + name)
    const blurred = await js(`getComputedStyle(document.querySelector('[data-testid=assignment-dummy]')).filter`)
    if (!blurred.includes('blur')) throw new Error('dummy is not blurred: ' + blurred)
    await shot('assignment-hidden-mobile')
  })

  let ownersReceiver
  await step('reveal shows name and wishes together; hide and reload conceal them again', async () => {
    await js(`__t.click('Wichtel aufdecken')`)
    await wait('revealed', `!!document.querySelector('[data-testid=assignment-revealed]') && !document.querySelector('[data-testid=assignment-dummy]')`)
    const text = await js(`document.querySelector('[data-testid=assignment-revealed]').innerText`)
    ownersReceiver = ['Ben', 'Carla', 'Dora'].find((n) => text.includes(n + ' E2E'))
    if (!ownersReceiver) throw new Error('no receiver in: ' + text)
    for (let i = 1; i <= 3; i++) if (!text.includes('Geheimwunsch ' + ownersReceiver + ' ' + i)) throw new Error('wish ' + i + ' missing: ' + text)
    for (const other of ['Ben', 'Carla', 'Dora'].filter((n) => n !== ownersReceiver)) if (text.includes('Geheimwunsch ' + other)) throw new Error('wishes of ' + other + ' shown')
    await shot('assignment-revealed-mobile')
    await js(`__t.click('Verbergen')`)
    await wait('hidden again', `!document.querySelector('[data-testid=assignment-revealed]') && !__t.has('Geheimwunsch ' + '${'${ownersReceiver}'}')`.replace('${ownersReceiver}', ownersReceiver))
    await js(`__t.click('Wichtel aufdecken')`)
    await wait('revealed', `!!document.querySelector('[data-testid=assignment-revealed]')`)
    await goto(groupPath)
    await wait('hidden after reload', `!!document.querySelector('[data-testid=assignment-dummy]') && !document.querySelector('[data-testid=assignment-revealed]')`)
  })

  await step('group admin has no way to the results of others; the group is locked', async () => {
    if (await js(`__t.has('Alle Zuteilungen')`)) throw new Error('group admin sees the admin card')
    const statuses = await js(`(async () => {
      const members = await (await fetch('/api/groups/${groupId}/members')).json()
      const out = []
      for (const m of members) out.push((await fetch('/api/groups/${groupId}/draw/assignments/' + m.id)).status)
      return out.join(',')
    })()`)
    if (statuses !== '403,403,403,403') throw new Error('assignment endpoint for group admin: ' + statuses)
    await wait('locks', `__t.has('Die Teilnehmerliste ist gesperrt.') && __t.has('Ausschlüsse können nicht mehr geändert werden.') && __t.has('Auch du als Gruppenadmin siehst nur deinen eigenen Wichtel.') && !__t.has('Per Username einladen')`)
    const deleteDisabled = await js(`[...${card('Verwaltung')}.querySelectorAll('button')].find((b) => b.textContent === 'Löschen').disabled`)
    if (!deleteDisabled) throw new Error('group can still be deleted')
  })

  await step('the participant with an exclusion did not draw the excluded person', async () => {
    await logout()
    await login(ben)
    await goto(groupPath)
    await wait('card', `__t.has('Dein Wichtel')`)
    await js(`__t.click('Wichtel aufdecken')`)
    await wait('revealed', `!!document.querySelector('[data-testid=assignment-revealed]')`)
    const text = await js(`document.querySelector('[data-testid=assignment-revealed]').innerText`)
    if (text.includes('Carla E2E')) throw new Error('ben drew the excluded carla')
    if (text.includes('Ben E2E')) throw new Error('ben drew himself')
    if (await js(`__t.has('Auslosung löschen') || __t.has('Alle Zuteilungen')`)) throw new Error('participant sees admin controls')
  })

  await step('Wichtler admin sees all assignments, each blurred and revealed on its own', async () => {
    await logout()
    await login(adminUser, adminPw)
    await js(`__t.click('Alle Gruppen')`)
    await wait('all groups', `__t.has('E2E Auslosung') && __t.has('Admin-Ansicht')`)
    await goto(groupPath)
    await wait('admin card', `!!${card('Alle Zuteilungen')} && ${card('Alle Zuteilungen')}.querySelectorAll('[data-testid=assignment-dummy]').length === 4`)
    if (await js(`__t.has('Dein Wichtel') || __t.has('Meine Wünsche')`)) throw new Error('admin is shown participant cards without taking part')
    if (await js(requested('/draw/assignments/'))) throw new Error('assignments requested before a reveal')
    await js(`__t.click('Aufdecken', ${rowIn('Alle Zuteilungen', ben)})`)
    await wait('one revealed', `document.querySelectorAll('[data-testid=assignment-revealed]').length === 1 && document.querySelectorAll('[data-testid=assignment-dummy]').length === 3`)
    const text = await js(`${rowIn('Alle Zuteilungen', ben)}.innerText`)
    if (!text.includes('Ben beschenkt') || text.includes('Carla E2E')) throw new Error('unexpected row: ' + text)
    await shot('admin-assignments-mobile')
  })

  await step('deleting the draw needs confirmation and unlocks the group', async () => {
    await logout()
    await login(owner)
    await goto(groupPath)
    await wait('drawn', `__t.has('Auslosung löschen')`)
    await js(`__t.click('Auslosung löschen')`)
    await wait('confirm', `!!__t.dialog() && __t.dialog().textContent.includes('Alle Zuteilungen werden gelöscht') && __t.dialog().textContent.includes('kennt die bisherige Zuteilung')`)
    await js(`__t.click('Auslosung löschen', __t.dialog())`)
    await wait('undone', `!__t.dialog() && __t.has('Bereit zur Auslosung') && !__t.has('Dein Wichtel') && __t.has('Per Username einladen') && __t.has('Auslosung starten')`)
    const status = await js(`fetch('/api/groups/${groupId}/draw/my-assignment').then((r) => r.status)`)
    if (status !== 409) throw new Error('assignment still available: ' + status)
  })
} catch {
  await shot('failure-04')
} finally {
  await t.finish()
}
