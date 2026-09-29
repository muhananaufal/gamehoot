# Changelog

All notable changes to Pentahoot are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
