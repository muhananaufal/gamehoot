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
