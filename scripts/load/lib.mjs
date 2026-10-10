// F19: shared parts of the load test. An HTTP client with its own cookie jar (one per phone, as
// a browser would have) and a raw Pusher-protocol socket to Reverb that counts what it receives.
// Node 24 or newer: fetch and WebSocket are built in, so the script needs no packages.

export const BASE = (process.env.BASE ?? 'http://localhost:8000').replace(/\/$/, '');
export const WS = (process.env.WS ?? BASE.replace(/^http/, 'ws').replace(/:\d+$/, ':8080')).replace(/\/$/, '');

export const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/** A value from the environment that the run cannot do without. */
export function required(name) {
    const value = process.env[name];

    if (!value) {
        console.error(`Set ${name}; see the header of scripts/load/f19.mjs.`);
        process.exit(2);
    }

    return value;
}

export class Client {
    constructor(name) {
        this.name = name;
        this.jar = new Map();
        this.csrf = '';
    }

    cookieHeader() {
        return [...this.jar].map(([key, value]) => `${key}=${value}`).join('; ');
    }

    store(response) {
        for (const cookie of response.headers.getSetCookie?.() ?? []) {
            const [pair] = cookie.split(';');
            const at = pair.indexOf('=');
            const key = pair.slice(0, at);
            const value = pair.slice(at + 1);

            if (/Max-Age=0|expires=Thu, 01 Jan 1970/i.test(cookie) || value === '' || value === 'deleted') {
                this.jar.delete(key);
            } else {
                this.jar.set(key, value);
            }
        }
    }

    async req(method, path, { body, json } = {}) {
        const headers = { Cookie: this.cookieHeader() };
        let payload;

        if (json !== undefined) {
            Object.assign(headers, {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': this.csrf,
            });
            payload = JSON.stringify(json);
        } else if (body !== undefined) {
            headers['Content-Type'] = 'application/x-www-form-urlencoded';
            payload = new URLSearchParams({ _token: this.csrf, ...body }).toString();
        }

        const started = performance.now();
        const response = await fetch(BASE + path, { method, headers, body: payload, redirect: 'manual' });
        const ms = performance.now() - started;
        this.store(response);
        const text = await response.text();
        const token = text.match(/name="csrf-token" content="([^"]+)"/) ?? text.match(/name="_token" value="([^"]+)"/);

        if (token) {
            this.csrf = token[1];
        }

        let data = null;

        try {
            data = JSON.parse(text);
        } catch {
            // An HTML page.
        }

        return { status: response.status, text, data, ms, location: response.headers.get('location') };
    }

    async login(login, password) {
        await this.req('GET', '/login');
        const response = await this.req('POST', '/login', { body: { login, password } });

        if (response.status !== 302 || !String(response.location).includes('/host')) {
            throw new Error(`Sign in failed (${response.status}); check HOST_LOGIN and HOST_PASSWORD.`);
        }

        await this.req('GET', '/host');
    }
}

/**
 * A Pusher-protocol socket that joins the given public channels and counts the messages and
 * bytes it receives per event name. Sends the Origin Reverb allows, and answers its pings.
 */
export function socket(key, channels) {
    return new Promise((resolve, reject) => {
        const ws = new WebSocket(`${WS}/app/${key}?protocol=7&client=js&version=8.4.0&flash=false`, {
            headers: { Origin: BASE },
        });
        const stats = { ws, messages: 0, bytes: 0, byEvent: {}, errors: [] };
        const timer = setTimeout(() => reject(new Error('The socket did not connect within 15 s.')), 15_000);

        ws.onmessage = (message) => {
            const raw = typeof message.data === 'string' ? message.data : '';
            const event = JSON.parse(raw);

            if (event.event === 'pusher:connection_established') {
                for (const channel of channels) {
                    ws.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel } }));
                }

                clearTimeout(timer);
                resolve(stats);

                return;
            }

            if (event.event === 'pusher:ping') {
                ws.send(JSON.stringify({ event: 'pusher:pong', data: {} }));

                return;
            }

            if (event.event === 'pusher_internal:subscription_succeeded') {
                return;
            }

            if (event.event?.startsWith('pusher')) {
                stats.errors.push(raw);

                return;
            }

            stats.messages++;
            stats.bytes += raw.length;
            stats.byEvent[event.event] = (stats.byEvent[event.event] ?? 0) + 1;
        };
        ws.onerror = (error) => reject(error);
    });
}

/** Percentiles of a list of milliseconds, rounded. */
export function percentiles(values) {
    const sorted = [...values].sort((a, b) => a - b);
    const at = (share) => Math.round(sorted[Math.min(sorted.length - 1, Math.floor(sorted.length * share))] ?? 0);

    return { p50: at(0.5), p95: at(0.95), max: at(1) };
}
