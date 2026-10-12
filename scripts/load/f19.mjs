// F19 (audit OPS-2): load test before the dress rehearsal, on staging, never on production.
// PHONES phones claim a name in waves (a room scanning the QR code), follow the event on the
// socket like a phone does, vote on one Pentahoot question all at once and then all ask /state at
// once (a reconnect storm). It prints what every kind of client received and the HTTP times, and
// exits 1 when the F25 message budget or an HTTP answer is broken.
//
// Prepare an open event with at least PHONES unclaimed names and a Pentahoot game that has not
// started (php artisan db:seed --class=LoadTestSeeder makes "load-test" on staging), then:
//
//   BASE=https://staging.example REVERB_APP_KEY=... HOST_LOGIN=... HOST_PASSWORD=... \
//   EVENT=<event uuid> SLUG=load-test PHONES=300 WAVE=50 node scripts/load/f19.mjs
//
// WS defaults to BASE on port 8080 (set it when the socket goes through the proxy, e.g.
// wss://staging.example). Credentials only come from the environment (O3).
import { Client, percentiles, required, sleep, socket } from './lib.mjs';

const key = required('REVERB_APP_KEY');
const event = required('EVENT');
const slug = required('SLUG');
const phonesWanted = Number(process.env.PHONES ?? 200);
const wave = Number(process.env.WAVE ?? 50);
const failures = [];
const statusCount = (responses) =>
    responses.reduce((counts, r) => ({ ...counts, [r.status]: (counts[r.status] ?? 0) + 1 }), {});

const host = new Client('host');
await host.login(required('HOST_LOGIN'), required('HOST_PASSWORD'));
await host.req('POST', `/host/${event}/open`, { body: {} });

// The Public View follows the public and the counter channel (F25).
const screen = await socket(key, [`event.${event}.public`, `event.${event}.screen`]);

const join = await new Client('probe').req('GET', `/${slug}`);
const names = [...join.text.matchAll(/name="person" value="([0-9a-f-]{36})"/g)].map((m) => m[1]);
const phonesCount = Math.min(phonesWanted, names.length);
console.log(
    `join page: ${join.text.length} bytes, ${names.length} free names, ${Math.round(join.ms)} ms; ${phonesCount} phones`,
);

// Claims in waves, like a room scanning the QR code.
const phones = [];
const claims = [];
for (let start = 0; start < phonesCount; start += wave) {
    const batch = names.slice(start, Math.min(start + wave, phonesCount));
    const joined = await Promise.all(
        batch.map(async (person, i) => {
            const phone = new Client(`phone${start + i}`);
            await phone.req('GET', `/${slug}`);
            const claim = await phone.req('POST', `/${slug}/claim`, { body: { person } });
            claims.push(claim);
            await phone.req('GET', `/${slug}/play`);
            phone.person = person;
            // A phone follows the public channel only (F25).
            phone.sock = await socket(key, [`event.${event}.public`]);

            return phone;
        }),
    );
    phones.push(...joined);
}
await sleep(2_000);

const claimCodes = statusCount(claims);
console.log('claims', JSON.stringify(claimCodes), JSON.stringify(percentiles(claims.map((c) => c.ms))));
const phoneCounters = phones.reduce((sum, p) => sum + (p.sock.byEvent.joined ?? 0) + (p.sock.byEvent.answered ?? 0), 0);
console.log(`during joins: counters at phones ${phoneCounters} (budget 0), screen ${JSON.stringify(screen.byEvent)}`);
if (phoneCounters > 0) failures.push(`phones received ${phoneCounters} counter messages while joining`);
if (Object.keys(claimCodes).some((code) => code !== '302'))
    failures.push(`claims answered ${JSON.stringify(claimCodes)}`);

// Start the first game that has not started and its first question.
const liveControl = await host.req('GET', `/host/${event}`);
const start = liveControl.text.match(/action="[^"]*?(\/host\/[0-9a-f-]{36}\/games\/[0-9a-f-]{36}\/start)"/);
if (!start) throw new Error('No game to start: add a Pentahoot game that has not started.');
await host.req('POST', start[1], { body: {} });
const afterStart = await host.req('GET', `/host/${event}`);
const ids = afterStart.text
    .slice(afterStart.text.indexOf('questionIds:'))
    .match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/);
if (!ids) throw new Error('The started game has no question.');
const question = ids[0];
await host.req('POST', `/host/${event}/questions/${question}/start`, { json: {} });
await sleep(1_000);

for (const phone of phones) Object.assign(phone.sock, { byEvent: {}, bytes: 0, messages: 0 });
screen.byEvent = {};

const state = await phones[0].req('GET', `/${slug}/state`);
const attempt = state.data?.game?.question?.attempt ?? 1;

// Every phone votes at once.
const voteStarted = performance.now();
const votes = await Promise.all(
    phones.map((phone, i) =>
        phone.req('POST', `/${slug}/questions/${question}/vote`, {
            json: { target: names[(i * 7) % phonesCount], attempt },
        }),
    ),
);
const voteWall = Math.round(performance.now() - voteStarted);
await sleep(2_000);
const voteCodes = statusCount(votes);
const answeredAtPhones = phones.reduce((sum, p) => sum + (p.sock.byEvent.answered ?? 0), 0);
console.log(
    'votes',
    JSON.stringify(voteCodes),
    `wall ${voteWall} ms`,
    JSON.stringify(percentiles(votes.map((v) => v.ms))),
);
console.log(
    `while voting: answered at phones ${answeredAtPhones} (budget 0), bytes at phone 1 ${phones[0].sock.bytes}, screen ${JSON.stringify(screen.byEvent)}`,
);
if (answeredAtPhones > 0) failures.push(`phones received ${answeredAtPhones} answered messages`);
if (Object.keys(voteCodes).some((code) => code !== '201')) failures.push(`votes answered ${JSON.stringify(voteCodes)}`);

// A reconnect storm: every phone asks /state at once.
const states = await Promise.all(phones.map((phone) => phone.req('GET', `/${slug}/state`)));
const stateCodes = statusCount(states);
console.log('reconnect /state', JSON.stringify(stateCodes), JSON.stringify(percentiles(states.map((s) => s.ms))));
if (Object.keys(stateCodes).some((code) => code !== '200'))
    failures.push(`/state answered ${JSON.stringify(stateCodes)}`);

const socketErrors = [screen, ...phones.map((p) => p.sock)].flatMap((s) => s.errors);
if (socketErrors.length > 0) failures.push(`socket errors: ${socketErrors.slice(0, 3).join(' | ')}`);

for (const phone of phones) phone.sock.ws.close();
screen.ws.close();

console.log(
    failures.length === 0 ? 'F19: within budget' : `F19: ${failures.length} problem(s)\n- ${failures.join('\n- ')}`,
);
process.exit(failures.length === 0 ? 0 : 1);
