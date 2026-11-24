# CGO Admin Resources

This document describes the Filament admin resources of the CGO (Continuous Growth Offering) demo module — reference software only; not an offer of securities.

> Concept and demo code for an investment-round workflow (tiers, payments, KYC, agreements, refunds). The CGO round is closed.

## Overview

The admin panel at `/admin` has two CGO resources. Both sit in the "Platform" navigation group.

- **CGO Investments** (`CgoInvestmentResource`) is a scaffold for the `cgo_investments` table.
- **CGO Early Access** (`CgoNotificationResource`) lists the email addresses people left on the `/cgo` concept page.

Both resources use the `RespectsModuleVisibility` trait. They drop out of the navigation when `ADMIN_MODULES` is set and does not list the "Platform" group. The production Zelta configuration in `.env.zelta.example` leaves "Platform" out of `ADMIN_MODULES`, so the two resources are hidden there. It also lists `Cgo` in `MODULES_DISABLED`, which switches off the CGO module itself.

There is no admin resource for pricing rounds, refunds or payment verification. There is no CGO stats widget.

## CGO Investments

The resource lives in `app/Filament/Admin/Resources/CgoInvestmentResource.php`. It has list, create and edit pages at `/admin/cgo-investments`.

The resource is a scaffold. Its form and its table define no fields or columns, and it has no filters. The edit page offers a delete action, and the list offers a bulk delete. It has no navigation badge, export, payment verification or document actions.

To inspect investment records, query the `cgo_investments` table. The GraphQL `investment` and `investments` queries return only the signed-in user's own investments, and they work only while the `Cgo` module is enabled.

## CGO Early Access

The resource lives in `app/Filament/Admin/Resources/CgoNotificationResource.php`. It is a single management page at `/admin/cgo-notifications`.

- The table shows the email address, the IP address and the sign-up time, newest first.
- The form shows the email address and, read-only, the IP address and user agent.
- Each row has a view action and a delete action.
- The bulk actions are delete and export.
- The navigation badge shows the number of sign-ups.

The records come from `POST /cgo/notify` and are stored in the `cgo_notifications` table.

## Related Commands

`php artisan cgo:verify-payments` checks pending investment payments. With `--expired` it also handles expired payments, and with `--investment=<uuid>` it checks one investment. The scheduler runs it only while the `Cgo` module is enabled.

## Related Documentation

- [CGO module overview](../16-CGO/README.md)
- [CGO refund processing](../16-CGO/CGO_REFUND_PROCESSING.md)
