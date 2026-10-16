# Session records (IPDR) for ISPGrid

An add-on for [ISPGrid](https://github.com/ispgrid/ispgrid) that keeps every customer session for two years and
answers "who had this IP at this time". Indian ISPs must keep these records (IPDR) and hand them over on request;
ISPs elsewhere can use it too.

> [!WARNING]
> **Preview: not ready for production yet.** Like ISPGrid itself, it can bring breaking changes until v1.0.0. Read the
> release notes before you upgrade.

## What it does

- **Archive:** every 15 minutes, each session that has ended is copied from FreeRADIUS's `radacct` into its own
  database, `ispgrid_ipdr`: username, device MAC, IP (IPv4 and IPv6), NAS, start, end, duration and data. A session
  that stays open is copied once it closes.
- **Keeping:** 730 days (two years) by default, never less; set it on the Session records page (staff with the
  "App settings" permission). Older records are removed every night.
- **Core's cleanup waits for it:** ISPGrid removes old sessions from `radacct` only after this add-on has archived
  them.
- **Search:** menu "Session records". Search by an IP at a date and time (who had it then, sessions still online
  included), or by username, device MAC or NAS over a date range. Results show the customer's current name, mobile
  and address, or "Deleted customer". Export the results as CSV.
- **Accountability:** every search and export asks for a reference number and a reason, and is written to the
  Activity log.
- **Permission:** "Search session records" on the Roles screen (Admin has it).
- English and Hindi.

Not yet: NAT logs (customers behind CGNAT), a PDF export.

## Install

It needs ISPGrid 0.1.x. On a server set up with the ISPGrid installer, as root:

```bash
sudo /opt/ispgrid/deploy/install.sh --addon=ispgrid/ipdr
```

The installer keeps the add-on on every upgrade and backs up the archive database with the nightly ISPGrid backup.

### Why the installer, and why root

The panel runs as its own user, `ispgrid`, and that user cannot change the panel's code: `/opt/ispgrid` belongs to
root, and `ispgrid` can write only `storage/` and `bootstrap/cache`. If someone ever broke into the panel, they could
not rewrite its code or plant anything in `vendor/`. So adding a package needs root, and the installer does it
safely:

- Composer runs as root without its scripts, so root never runs the panel's code.
- Package discovery and the database migration run as `ispgrid`; the migration creates the archive database
  `ispgrid_ipdr`.
- The add-on is recorded in `addons.json`, so every upgrade puts it back. A package added with Composer by hand is
  removed at the next upgrade.
- The caches are rebuilt, so the Session records page shows up at once.

Running `composer require` as `ispgrid` fails because that user cannot write `composer.json` or `vendor/`. Don't
give it ownership of `/opt/ispgrid` to get around that: it removes the protection.

## Remove

```bash
sudo /opt/ispgrid/deploy/install.sh --remove-addon=ispgrid/ipdr
```

The page and the scheduled copy and purge go away, but the archive database `ispgrid_ipdr` is kept: the records may
be ones you must still hand over, and installing the add-on again picks them up where they were. The nightly backup
keeps dumping it. To delete the records too, now or after an earlier removal:

```bash
sudo /opt/ispgrid/deploy/install.sh --remove-addon=ispgrid/ipdr --delete-data
```

The backup taken at the start of that run keeps a copy (`/var/backups/ispgrid/ispgrid_ipdr-*.sql.gz`, for 14 days);
after that the records cannot be brought back. Don't drop the database by hand: the panel would still count the
add-on's migration as done, and installing it again would not create the database.

## Development

In an ISPGrid checkout:

```bash
composer config repositories.ispgrid-ipdr '{"type": "path", "url": "../path/to/ispgrid-ipdr", "options": {"symlink": true}}'
composer require ispgrid/ipdr:@dev
ISPGRID_ADDONS=on php vendor/bin/pest --test-directory=vendor/ispgrid/ipdr/tests vendor/ispgrid/ipdr/tests
```

Do not commit the changed `composer.json` and `composer.lock` in the ISPGrid checkout. The tests use the database
`ispgrid_ipdr_test` next to ISPGrid's `radius_test`.

## License

MIT
