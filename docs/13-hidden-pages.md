# Hidden pages

As the Google Stitch redesigns ship, the old page they replace first stays in the menu labelled
"(OLD)" so people can compare. Once the new page is accepted, the old one is **hidden from the
menu for all users**. A hidden page is not deleted and not blocked: it still opens from a link or
its URL, because other screens may still link to it (for example its view, edit or create screens).

## Hidden now

| Old page (URL) | Class | Hidden since | Use instead |
|---|---|---|---|
| RFP Requests (`/hub/rfps`) | `App\Filament\Resources\Rfps\RfpResource` | 2026-10-29 | My requests (`/hub/rfp/my-requests`) |
| RFP Approvals (`/hub/rfp-approvals`) | `App\Filament\Resources\RfpApprovals\RfpApprovalResource` | 2026-10-29 | RFP Approvals (`/hub/rfp/approvals`) |
| Dashboard (`/hub`) | `App\Filament\Pages\Dashboard` | 2026-10-29 | Dashboard (`/hub/home`), AP Processor only so far |

The RFP view, edit and create screens (`/hub/rfps/{id}`, `/hub/rfps/{id}/edit`, `/hub/rfps/create`)
and the RFP approval screen (`/hub/rfp-approvals/{id}`) still work and are opened from the new pages.

The old Dashboard is hidden for **every** role, including the roles that have no redesigned Home yet
(Vendor, Requestor, AP Checker, Treasury and others): they have no dashboard link in the menu until
their Home is redesigned. `/hub` still opens and is still where everyone lands after logging in.

## To be hidden next

Old pages that still show in the menu, under a "(OLD)" label or group, next to their redesign.
Hide each one once its redesign is accepted.

| Old page (URL) | Class | Redesign |
|---|---|---|
| P2P (OLD) › P2P Approvals (`/hub/p2p/approvals`) | `App\Filament\Resources\P2p\Approvals\ApprovalResource` | P2P Approvals (`/hub/p2p/approval-queue`) |
| P2P (OLD) › P2P requests (`/hub/p2p/invoices`) | `App\Filament\Resources\P2p\Invoices\InvoiceResource` | Google Stitch design in progress |
| P2P (OLD) › Purchase Orders, Goods Receipts | `App\Filament\Resources\P2p\…` | Not designed yet |

Screens without a menu entry are replaced page by page as well, and their old version simply stops
being linked: Bulk Accounts Payable (`/hub/p2p/bulk-accounts-payable`) is replaced by the review at
`/hub/p2p/approval-queue/review`, opened from the new P2P Approvals.

## How to hide a page

Add this to the resource or page class, with a comment pointing here, then move its row from
"To be hidden next" to "Hidden now":

```php
/**
 * Hidden from the menu for all users: replaced by the redesigned pages. The pages still
 * open from links (view, edit, create). See docs/13-hidden-pages.md.
 */
public static function shouldRegisterNavigation(): bool
{
    return false;
}
```

A menu group whose pages are all hidden (for example "RFP (OLD)") disappears on its own. To show a
page again, remove the method.
