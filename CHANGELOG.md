# Changelog

All notable changes to Pentahoot are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- Pressing Enter in a dialog field (such as Add name or New pack) sends the form instead of closing the dialog.
- Name lists saved as CSV by Excel on Windows keep their accented names instead of silently dropping them.
- Exported CSV files (results and personal links) open in Excel with accents and emoji intact.
- A phone that picks a name the host just removed sees a clear screen with a way back to the list.
- Error messages name form fields the way the form labels them, explain a failed image upload, and match when an event link is already taken.
- Reordering pack questions now confirms the change.
- The projector podium never lists more than ten names; a big tie below the steps becomes one summary line. Scores show their unit, and emoji in names no longer break the initials.
- Long questions shrink to fit the projector, so the countdown and the answered count stay visible.
- A phone that lost the answer to its vote shows the pick it already sent, and a phone whose name was released goes back to the name list.
- After a reload, a phone that already voted shows its pick at once instead of "Time is up".
- Live control updates right after its own actions instead of waiting for the realtime message.
- The "Show questions on player phones" switch can be clicked or tapped directly.
- Word Guess and Picture Guess phones show the question counter once.
- The image picker shows the picked file name, goes back to the stored image when a pick fails, and asks to pick images again after a form error.
- The name import marks names longer than 100 characters, says how many more names fit in the event, and shows the real reason when saving fails.
- Starting the first game, which locks the name list, now asks for confirmation.
- The Names page says whether new claims are locked and can lock or unlock them directly.
- Forms show that they are being sent and cannot be sent twice.
- Outdated texts on the Games and Results pages were corrected, and the duplicate-name hint uses the actual name.
- The Activity log now also lists opening the event, starting a game, revealing a question, new personal links, deleted names and games, link changes and co-host changes.
- Live control and phones get a clear message for actions that are not available, instead of a generic error.
- A stuck realtime server no longer slows every host action and vote for up to 30 seconds.
- Refused actions such as late votes no longer fill the server log.

### Added

- `php artisan db:seed --class=DemoSeeder` builds a demo event with 50 names and one game of each type; `LoadTestSeeder` builds an open event with 500 names for load tests. Neither runs in production.
- The specification gains a day-to-day guide with every command from branch to release, a debugging chapter with a note format (`docs/debugging/`), and execution lessons in the project blueprint.
- Friendly error pages (page not found, no access, expired page, too many tries, server error, maintenance) with a way to reload or go back.

### Security

- The database seeder no longer creates a test account with a known password.
- Every page now sends security headers, including a Content Security Policy, and cannot be embedded in other sites.
- Behind a reverse proxy, each phone is rate limited by its own address and links use HTTPS.
- Production refuses to start with debug output on or with cookies allowed over plain HTTP.
- Server images are scanned for known critical vulnerabilities on every change, and the web server image takes the latest Debian security fixes.
- Uploaded images are re-encoded on the server, so camera details such as the GPS position never get stored.

- Production-ready images and an automatic pipeline: every merge to develop deploys staging, and every version tag deploys production after approval. A failed deploy returns to the previous release by itself.
- A step-by-step guide for preparing the staging and production servers on Google Cloud.

### Changed

- The sidebar follows the light and dark theme, lists your five most recent events with the open event's pages under its name, scrolls on its own so theme and sign out stay in view, and can be narrowed to icons on wide screens.
- Confirmations such as "Settings saved" appear as short toasts instead of a box at the top of the page; copying a personal link confirms the same way.
- Links are easier to read in the dark theme, link-style buttons are easier to tap, and everything clickable shows the hand cursor.
- The link of an open event can no longer be changed, because printed QR codes and open phones use it. Close the event first to change it.
- The phone join page is much lighter for long name lists, and phones checking for updates no longer create server sessions.
- An image upload that fails to save leaves no file behind, and hosting staff can clear older leftovers with `php artisan pentahoot:prune-media`.
- The Names page can be searched, filtered by status and sorted without reloading, shows when each name joined, and keeps Rename, Release, New link and Delete in one menu per row. Add name sits in the top bar, deleting a name asks first, and a list of 480 names loads about three times lighter.
- The event list can be searched, filtered and sorted without reloading, and shows when each event was created and last changed and who owns a co-hosted event. An event name opens its Live control.
- Questions in a pack can be dragged into a new order; the arrow buttons stay. New pack and Add question sit in the top bar, and Delete pack moves into a menu with a confirmation.
- Empty pages (no events, no packs, a pack without questions) explain the next step with the mascot instead of a blank panel.
- The Names, Games and Activity log pages update by themselves when phones join, a game starts or finishes, or a co-host acts, without reloading and without losing your search or filter.
- The Activity log can be searched and sorted by time.
- Host accounts can be searched and filtered by status, show when each account was added, and Disable asks for confirmation first.

## [0.5.0] - 2026-10-01

Tebak Gambar stage: the picture-guessing game with private answer images.

### Added

- Hosts can write Tebak Gambar packs: each question has a question image and an answer image, picked as JPG or PNG and made smaller in the browser before upload. iPhone HEIC photos are refused with a clear message.
- Live control runs Tebak Gambar questions: show any queued question, reveal the answer image after a confirmation, skip before Reveal, pick the winner or surrender, and show the leaderboard. Hosts see the answer and both images from the start.
- The Public View shows the question image, then the answer image beside it after Reveal, with the winner, the leaderboard and a final podium. Phones that mirror the screen get smaller images.
- The Results page and CSV export list the winner and answer of every Tebak Gambar question and the final board.

### Security

- Answer images stay private until Reveal: they are only reachable through signed links that expire after 12 hours, and question image file names never give the answer away.

## [0.4.0] - 2026-10-01

Tebak Kata stage: the word-guessing game, editing copied questions and a shorter projector reveal.

### Added

- Hosts can add Tebak Kata games to an event from their Tebak Kata packs.
- Live control runs Tebak Kata questions: show any queued question, open letter boxes as hints, skip a question once, pick the winner from the name list after a confirmation, surrender, and show the leaderboard between questions. Hosts see the answer and a live board.
- The Public View shows Tebak Kata letter boxes, the winner or "no winner", the leaderboard with how names moved, and a final podium. Phones mirror the screen when the event allows it and always show the final results.
- Hosts can edit the copied questions of a game until each one has been on screen, without changing the pack.
- The Results page and CSV export list the winner of every Tebak Kata question and the final board.

### Changed

- A finished game stays on the Public View and phones until the next game starts.
- The projector reveal shows at most 10 rows: when many names tie, the tied group is summed up in one line, and the full list stays on phones, the Results page and the export.

## [0.3.0] - 2026-09-30

Pentahoot stage: games in an event, voting from phones, the reveal and the results.

### Added

- Hosts add games to an event from their Pentahoot question packs, reload a game from its pack before it starts, and delete games that never ran.
- Hosts start and finish games; starting the first game locks the name list, and finishing early leaves unfinished questions out and is logged.
- Live control runs Pentahoot questions: open any question that is not done, stop the countdown, reveal once late votes are in, move on, or reset a question's votes. Hosts see a live count per name that players never see.
- Players vote from their phones by searching the name list, until the countdown ends; they see the name they sent, then the same results and podium as the projector.
- The Public View shows the question with a countdown and how many answered, then reveals the top 5 from the lowest rank up and ends on a podium.
- The Public View lobby shows a QR code with the join link.
- A Results page per event shows the top 5 of every question, with a CSV export, and an Activity log lists the actions that cannot be undone.

## [0.2.0] - 2026-09-30

Realtime stage: live screens over Reverb, the Public View lobby and Live control.

### Added

- Screens update live: phones, the Public View and the host's Live control page follow every change to the event without a reload.
- The Public View (`/{event}/screen`) shows a lobby with the join link and a live count of players who joined, in the theme chosen for the event.
- Hosts get a Live control page per event with the event status, how many names have been claimed, and the connection state.
- Phones switch to the right screen by themselves when an event is closed, reopened or removed.
- When the live connection drops, screens show a warning and keep updating every few seconds until it is back.
- The app refuses to serve pages while a required realtime setting is missing, with a message naming it.

## [0.1.0] - 2026-09-29

Foundation stage: accounts, events, question packs, name lists and joining from phones.

### Added

- Application foundation: Laravel 13 on PHP 8.4 in Docker, backed by MySQL.
- Data model for events, co-hosts, participant names, question packs, games, votes and frozen results.
- Hosts sign in with accounts created by a super-admin; there is no self sign-up or email reset.
- Super-admins can add host accounts, reset passwords and disable or enable accounts, and each action is logged.
- The first super-admin is created with `php artisan pentahoot:create-super-admin`.
- Hosts see the events they own or co-host, filtered by status.
- Host pages follow the system light or dark theme, with a switch in the sidebar.
- Hosts can create events with a link, Public View theme and phone display setting, and open them when ready. A link left empty is made from the event name and is always free; after creating an event the host goes straight to adding names.
- Event owners can add co-hosts, transfer ownership, and delete events; deleted events can be restored by their owner or a super-admin.
- Hosts can close and reopen events and lock new name claims.
- Hosts can write Pentahoot and Word Guess question packs, reorder questions, and preview the answer boxes while writing.
- Hosts manage each event's name list: add, rename and delete names, or import a CSV through a preview that flags duplicates before anything is saved.
- Hosts can release a claimed name, make a new personal link, and export all personal links as CSV.
- Players join from the event link by picking their name, or straight from their personal link, without an account; the phone is remembered for reconnecting.
- Phones show clear screens when an event is not found, not open yet, ended, locked for new players, or when a name is already in use.

[Unreleased]: https://github.com/devivace-groups/pentahoot/compare/v0.5.0...develop
[0.5.0]: https://github.com/devivace-groups/pentahoot/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/devivace-groups/pentahoot/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/devivace-groups/pentahoot/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/devivace-groups/pentahoot/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/devivace-groups/pentahoot/releases/tag/v0.1.0
