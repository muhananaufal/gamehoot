# Changelog

All notable changes to Pentahoot are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- Moving a game or a question with the keyboard keeps the focus on its Move button after the new order is saved, so it can be moved again right away.
- The Activity log, the Results page and the replay cards of Live control stay quick for a long event; they no longer slow down with every entry or question.
- A CSV import in New event that gets no answer, and a personal link copy the browser refuses, now say so instead of failing silently.
- Help texts on the Names, Games and Tebak Live control pages name the buttons that are actually there.
- Phones waiting in the lobby no longer run a countdown clock four times a second, which saves battery during a long event.
- Searching for a name on the phone also ignores accents, letter case and letter width, so "jose" finds José.
- Row menus open and move with the keyboard, and games in Up next can be reordered with Move up and Move down buttons instead of dragging only.
- Form fields and the focus ring are clearly visible in both themes, buttons and links are easy to tap, and the live countdown no longer talks over screen readers every second.
- Host pages start with a Skip to content link for keyboard users.
- The realtime server can hold thousands of phones, projectors and host tabs at once instead of refusing connections past about a thousand.
- A deploy against a database that does not answer now fails its health check instead of going live.
- Names that differ only in accents, letter case or letter width (José and Jose) are treated as the same name everywhere: they are marked before saving, and New event no longer fails with an error when both are typed. Searching for "jose" also finds José.
- Reviewing a long imported name list stays smooth while typing; with hundreds of rows each keystroke used to freeze the page for about a second.
- The event links /app and /apps are refused, since the realtime server answers them before the event could.
- A whole venue sharing one internet connection can claim names in the same minute without being told to wait.
- Phones no longer receive a message for every vote and every name claimed by other players, so a full room keeps the venue WiFi and the realtime server much quieter; the projector and Live control still count live.
- Closing an event while a game runs updates the screens once instead of twice.
- Reset votes, Pick winner, Surrender, Finish game and Close event are refused when another host changed the screen a moment before, so nobody wipes or decides a result they have not seen.
- A host whose account is disabled sees why on the sign in page.
- The Names page stops refreshing itself in the background after the host moves to another page.

- Every host page shows its title, also Settings, Games, Activity log and Deleted events.
- Delete game uses the red style of other destructive actions.
- The name buttons on the phone join page match the search box.
- Every form checks its simple rules before sending, including passwords, time to vote and the rename dialog, so nothing that would be refused is sent; sign in still leaves the checks to the server.
- Delete event stays disabled until the event name is typed exactly.
- The link field in Restore event shows the site address in front, like New event.
- Icons in the narrowed sidebar are centred.
- After the last question, Live control points to Finish game instead of asking to pick a question that does not exist.
- Live control no longer reloads the whole page when another game starts or ends.
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
- A refused CSV upload stays in the Import modal and explains why in plain words.
- A dialog no longer lingers for a moment over the page after its form is sent.
- Every submit button in the host panel now spins and locks while its form is sent, so nothing is sent twice.
- After a change, other pages no longer show the old data for a few seconds before updating.
- Word Guess answers with unsupported symbols, or with every box open, are caught before the form is sent.
- The Names page no longer offers to add or import names once the list is locked, and says why players cannot join an open event without names.

### Added

- A load test script for staging plays a full room of phones claiming, voting and reconnecting, and reports what each kind of screen received.
- Host pages keep the session alive while they are open, so a Live control left untouched during a long break still works, and warn ten minutes before the 8 hour session ends.
- Games whose pack changed since they were added are marked, and Reload from pack shows only for them and asks first, since it replaces edits made to the copied questions.
- Phones follow the host's changes to the name list at once: a released or deleted name goes back to picking a name, a renamed one shows the new name.
- The Games page adds games from the same pack list as Question packs, with search, type filter and folding groups.
- Deleted events can be filtered by the date they were deleted and by who deleted them.
- The Games page of an event works like Question packs: games grouped by Up next, Running and Played, filtered by game type, with questions reordered, edited, added or deleted in place, and several packs added at once.
- Opening, reopening, locking claims, starting any game and skipping a question now ask for confirmation first.
- Every text field shows an example of what to type.
- Long lists offer "Go to page" and show their totals with thousands separators.
- New event is set up in four steps (Name and link, Names, Games, Review), saved together at the end; the host then goes on in Live control. Names can be typed or imported from a CSV file, games are picked from the packs, and leaving after typing asks first.
- Modals no longer have a close button in the corner, like the other dialogs; Cancel, Escape or a click outside close them.
- The Activity log also lists skipped questions, reloads from a pack, deleted questions of a game, finished games and added names.
- Names can be added several at once, one per line, with duplicates and too-long names marked while typing.
- Import CSV opens in a two-step modal (Upload, Review) with the supported format, a template to download, a status filter and a search in the review, and Back that keeps the modal. Leaving after a change asks first.
- Hosts can replay past moments on the projector from Live control, during the event or after it closed: Pentahoot categories, Word and Picture Guess answers with their winners, leaderboards after each win and final podiums, then go back to live.
- A recent event in the sidebar expands its pages in place, so Settings and the other pages are one click away.
- `php artisan db:seed --class=DemoSeeder` builds a demo event with 50 names and one game of each type; `LoadTestSeeder` builds an open event with 500 names for load tests. Neither runs in production.
- The specification gains a day-to-day guide with every command from branch to release, a debugging chapter with a note format (`docs/debugging/`), and execution lessons in the project blueprint.
- Friendly error pages (page not found, no access, expired page, too many tries, server error, maintenance) with a way to reload or go back.
- Penta, the Pentahoot logo, comes alive: it blinks, looks around and reacts with a mood on the phones, the Public View lobby, empty pages, status screens, error pages and sign in, where it follows the typing, covers its eyes for the password and peeks when it is shown.
- A Reduce motion switch beside the theme switch (and in the phone header) calms every animation on that device; holding Penta for a moment does the same. Devices that ask for less motion start calm.
- The Public View changes scenes smoothly: a countdown ring that beats in the last five seconds and turns red-orange in the last three, letter boxes that turn as they open, pictures that fade in, an answer picture that opens like a curtain, and a short "Back online" after a dropped connection.
- Rank 1 of a question, a Word or Picture Guess winner and a final podium are celebrated with confetti and a cheering Penta, on the projector and on phones, once per moment.
- Settings, Screens has a Screen animations switch that stops Penta's idle moves and the confetti on the Public View, for a formal event or a slow projector laptop.
- Phones slide between screens, mark the chosen name, stamp "Sent!" and ring the player's own name on the podium.
- Live control moves with the game: the main action fades in when it changes, the answered count beats, finished steps fill the timeline, a winner gets a little confetti and an outdated action shakes its message. New event slides between its steps, a field that fails its check shakes once, switches move with a spring and copied links show a check.
- Icon buttons in the host panel show a Pentahoot tooltip on hover and keyboard focus, which Escape closes.

### Security

- Wrong passwords typed on one device no longer lock the host out on another; failed sign ins count per account and per device address.
- Images much taller than the projector are refused before the server opens them, so one upload cannot exhaust its memory.
- Exported CSV files also neutralise the full-width = + - @ signs that Excel reads as formulas.
- A co-host who is removed, or an account that is disabled or deleted, stops receiving the host's live data (including Word Guess answers) on a Live control page that is still open.
- A host session ends 8 hours after signing in, even while it is in use.
- The database seeder no longer creates a test account with a known password.
- Every page now sends security headers, including a Content Security Policy, and cannot be embedded in other sites.
- Behind a reverse proxy, each phone is rate limited by its own address and links use HTTPS.
- Production refuses to start with debug output on or with cookies allowed over plain HTTP.
- Server images are scanned for known critical vulnerabilities on every change, and the web server image takes the latest Debian security fixes.
- Uploaded images are re-encoded on the server, so camera details such as the GPS position never get stored.
- Database errors no longer write participant names or personal link tokens into the server log.

- Production-ready images and an automatic pipeline: every merge to develop deploys staging, and every version tag deploys production after approval. A failed deploy returns to the previous release by itself.
- A step-by-step guide for preparing the staging and production servers on Google Cloud.

### Changed

- Removing a co-host in Settings asks first, like the other changes on that page.
- Phones load less than half the JavaScript they used to, so the game opens faster on a busy venue WiFi.
- Text on the projector is large enough to read from the back of the room, and every screen still fits a 16:9 or 4:3 projector without scrolling.
- The lobby on the projector shows the join link and QR code beside the names, so both stay readable with a long list.
- Host panel and phone text is a little larger, with captions no smaller than 13 px on the panel and 15 px on phones.
- The theme, motion and log out buttons sit in an evenly spaced row above the account at the foot of the sidebar, so the host's name and role always fit beside the avatar.
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
- The Games page shows when each game was added or started.
- Joining on a phone, ranking rows and the final podium now have short animations; they stay off when the device asks for reduced motion.
- Empty Names, Games, Results and Deleted events pages explain the next step.
- The host panel moves between pages without reloading: the sidebar keeps its place and scroll, a thin bar shows a slow page, and live pages switch to the right event as you go.
- New event and Add host open as a modal over the list; errors stay in the modal, and once created the new page opens with its message. A refresh while the modal is open stays on the list.
- Page titles and their buttons sit at the top of the page instead of the top bar, and every button that makes something new shows a plus icon.
- Question packs are edited on one page: opening, adding, editing, saving and reordering questions no longer reload the page. Each question has edit and delete icon buttons, delete asks first, and the pack list can be searched and stays in view.
- Lists remember their search, filter, sort and page size in the browser instead of the address, show 10, 25, 50 or 100 rows per page, and offer Reset filters while something narrows them.
- The event list sorts by creation date by default, shows when each event last changed under its name, and can be filtered by a date range from a calendar.
- Host accounts show when each account was added in a sortable column, mark your own account next to your name, and reset passwords in a dialog.
- Restoring a deleted event asks first, with the link to restore under chosen in the dialog.
- Buttons show a spinner while they save, including the buttons on Live control.
- Page changes, modals and dialogs have short animations, off when the device asks for reduced motion.
- The theme switch shows a sun or a moon for where it goes, the sign in page has the theme switch, and every password field can be shown and hidden.
- Every table looks the same: rows per page and filters above it, the date range and the search box on the right, numbered pages below, and every column sorts. Search boxes say what they search.
- Row actions are icon buttons in the row when there are one or two, instead of a menu. On Names, copy link, new link, release, rename and delete are icons, and renaming opens a dialog.
- Export links (CSV) sits at the top of the Names page and warns before the download. Joined names read "Playing".
- The event list shows your role (Owner or Co-host) as a pill next to each event name.
- The Activity log can be filtered by who did it and by date. Results and the Activity log no longer open with an explanation paragraph.
- Settings shows the event status next to General, makes Close event and Transfer ownership red, asks before a transfer, and lists the owner first and then each co-host with the date they were added. A field that cannot change right now looks switched off, and Close event is off with its reason while a game runs.
- Deleted events are listed in a table with Restore as an icon button.
- Every dialog closes on a click outside it.
- Form fields are checked as you leave them, so mistakes such as a link already in use show before you save.
- Pages, modals, dialogs, table rows and ranking changes move with a soft spring; nothing moves when the device asks for reduced motion.
- Hosts sign in with their username or their email. Every account has a lower case username; existing accounts got one made from their name. The super-admin command and Add host ask for it, and Host accounts shows and searches it.
- Co-hosts can be added, and events transferred, by username as well as by email.
- Super-admins can edit an account's name, username and email, reset their own password, and delete an account. A deleted account can no longer sign in and leaves the list, while events and the Activity log keep its name; an account that still owns events cannot be deleted until they are transferred.
- Host accounts actions (Edit, Reset password, Disable or Enable, Delete) sit in a "⋯" menu; Edit and Reset password open over the list.
- On Names, Copy stays beside the personal link and the other actions move to a "⋯" menu in an Actions column. Tables title their action column.
- The Add host modal has a title and fields as wide as the box.
- The question pack list stays quick with many packs: switching packs no longer reloads the list, groups per game type can be folded, and the list can be filtered by game type and ordered by name or by recent change, remembered per browser.
- Sorted columns show their direction (the active arrow solid, the other faint), table heads have rounded corners, and action columns are centred.
- Every dropdown in the host panel is a themed list that works with the keyboard, shows a count per choice for status filters, gets a search box with many choices, and is never cut off inside a scrolling list. Status filters on Events, Names and Host accounts are dropdowns; Host accounts can also filter by role and by the date an account was added.
- Forms point out empty required fields, too long text, a wrong email or username before sending, without waiting for the server.
- New event and Add host open at once; Edit and Reset password load while the menu is open.
- A new event's link fills in from its name until you type your own, and says at once if another event uses it.
- In a question pack, the title heads the card with Rename and Delete in a menu; the arrows move a question at once and save the order once; Time to vote has quick choices and a Custom option.

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
