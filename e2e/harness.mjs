// Minimal harness: drives the real UI in headless Chrome over the DevTools protocol.
// No dependencies; needs Node 22+ (built-in WebSocket) and a local Chrome (CHROME_BIN overrides its path).
import { spawn } from 'node:child_process'
import { mkdirSync, rmSync, writeFileSync } from 'node:fs'

const CHROME = process.env.CHROME_BIN ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'

/**
 * @param {string} base address of the app, e.g. http://127.0.0.1:8089
 * @param {string} out directory for screenshots and the throwaway browser profile
 */
export async function start(base, out, port = 9334) {
  mkdirSync(out, { recursive: true })
  const profile = `${out}/.profile-${port}-${Date.now()}`
  const chrome = spawn(
    CHROME,
    ['--headless=new', '--disable-gpu', '--no-first-run', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, 'about:blank'],
    { stdio: 'ignore' },
  )
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms))
  let target
  for (let i = 0; i < 50 && !target; i++) {
    await sleep(200)
    try {
      target = (await (await fetch(`http://127.0.0.1:${port}/json`)).json()).find((t) => t.type === 'page')
    } catch {}
  }
  const ws = new WebSocket(target.webSocketDebuggerUrl)
  await new Promise((r) => (ws.onopen = r))
  let seq = 0
  const pending = new Map()
  ws.onmessage = (m) => {
    const msg = JSON.parse(m.data)
    if (msg.id && pending.has(msg.id)) {
      pending.get(msg.id)(msg)
      pending.delete(msg.id)
    }
  }
  const send = (method, params = {}) =>
    new Promise((resolve) => {
      const id = ++seq
      pending.set(id, resolve)
      ws.send(JSON.stringify({ id, method, params }))
    })
  const helpers = `
    window.__t = {
      byLabel(label) {
        const l = [...document.querySelectorAll('label')].find((x) => x.textContent.trim().startsWith(label))
        if (!l) throw new Error('no label ' + label)
        return l.htmlFor ? document.getElementById(l.htmlFor) : l.querySelector('input')
      },
      fill(label, value) {
        const el = this.byLabel(label)
        const proto = el.tagName === 'TEXTAREA' ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype
        Object.getOwnPropertyDescriptor(proto, 'value').set.call(el, value)
        el.dispatchEvent(new Event('input', { bubbles: true }))
        el.dispatchEvent(new Event('change', { bubbles: true }))
      },
      click(text, scope = document) {
        const el = [...scope.querySelectorAll('button, a')].find((x) => x.textContent.trim() === text)
        if (!el) throw new Error('no button/link ' + text)
        if (el.disabled) throw new Error('disabled: ' + text)
        el.click()
      },
      // the row (li) of the member list that mentions the given text
      row(text) {
        const li = [...document.querySelectorAll('li')].find((x) => x.textContent.includes(text))
        if (!li) throw new Error('no row ' + text)
        return li
      },
      isDisabled(text) {
        return [...document.querySelectorAll('button')].find((x) => x.textContent.trim() === text).disabled
      },
      has(text) { return document.body.innerText.includes(text) },
      text() { return document.body.innerText },
      dialog() { return document.querySelector('[role=dialog]') },
    }
  `
  const js = async (expression) => {
    const r = await send('Runtime.evaluate', { expression: `(async () => { ${helpers}; return (${expression}) })()`, awaitPromise: true, returnByValue: true })
    if (r.result.exceptionDetails) throw new Error(r.result.exceptionDetails.exception?.description ?? 'eval failed')
    return r.result.result.value
  }
  await send('Page.enable')
  // Anything the Content-Security-Policy blocks is recorded and fails the run.
  await send('Page.addScriptToEvaluateOnNewDocument', {
    source: `window.__csp = []; document.addEventListener('securitypolicyviolation', (e) => window.__csp.push(e.violatedDirective + ' blocked ' + (e.blockedURI || 'inline') + ' on ' + location.pathname))`,
  })
  // A phone by default; E2E_VIEWPORT=1600x2400 runs the same flows on a desktop-sized screen.
  const [width, height] = (process.env.E2E_VIEWPORT ?? '390x844').split('x').map(Number)
  const mobile = width < 600
  await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: mobile ? 2 : 1, mobile })

  const results = []
  const cspViolations = []
  const collectCsp = async () => {
    try {
      cspViolations.push(...(await js(`(() => { const found = window.__csp ?? []; window.__csp = []; return found })()`)))
    } catch {}
  }
  const api = {
    sleep,
    js,
    results,
    cspViolations,
    async goto(path) {
      await collectCsp()
      await send('Page.navigate', { url: base + path })
      await sleep(900)
    },
    async wait(label, predicate, timeout = 6000) {
      const end = Date.now() + timeout
      while (Date.now() < end) {
        try {
          if (await js(predicate)) return
        } catch {}
        await sleep(150)
      }
      throw new Error(`timeout waiting for: ${label} @ ${await js('location.pathname')}\n--- page text ---\n${await js('document.body.innerText')}`)
    },
    async shot(name) {
      const r = await send('Page.captureScreenshot', { format: 'png' })
      writeFileSync(`${out}/${name}.png`, Buffer.from(r.result.data, 'base64'))
    },
    async step(name, fn) {
      try {
        await fn()
        await collectCsp()
        results.push(`ok   ${name}`)
      } catch (e) {
        results.push(`FAIL ${name}: ${e.message.split('\n').slice(0, 30).join(' / ')}`)
        throw e
      }
    },
    /** Prints the outcome, closes the browser and sets the exit code. */
    async finish() {
      await collectCsp()
      for (const violation of new Set(cspViolations)) results.push(`FAIL Content-Security-Policy: ${violation}`)
      console.log(results.join('\n'))
      ws.close()
      chrome.kill()
      await sleep(300)
      rmSync(profile, { recursive: true, force: true })
      if (results.some((line) => line.startsWith('FAIL'))) process.exitCode = 1
    },
  }
  return api
}
