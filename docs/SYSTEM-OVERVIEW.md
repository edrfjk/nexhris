# NexHRIS — System Overview

Human Resource Information System for **ISPSC Tagudin Campus**.
Reference document for writing the capstone manuscript.

Everything below is taken from the working codebase, not from notes.

---

## 1. What the system is for

The campus HR office kept its records on paper and in Excel workbooks: a
Personal Data Sheet per employee, a leave form per application, and two
handwritten ledger cards per person. Leave approval meant walking a printed
form between three offices. NexHRIS moves that flow online while keeping the
Civil Service forms exactly as the campus knows them.

**Scope.** Employee records, Personal Data Sheet submission and review, the
leave application chain, the leave and service credit ledger cards, HR
policies, announcements, digital ID, dashboards and an audit trail.

---

## 2. Technology

| Layer | Choice |
|---|---|
| Framework | Laravel 12 (PHP 8.2) |
| Database | MySQL |
| Front end | Blade templates, Tailwind CSS 3.4, Alpine.js |
| Spreadsheets | PhpSpreadsheet |
| PDF | Dompdf, with LibreOffice used when available |
| Charts | Chart.js |
| Icons | Heroicons (Blade components) |
| Testing | PHPUnit (Laravel feature tests, SQLite in memory) |

**Size:** 111 routes · 47 migrations · 41 tables · 33 models · 12 services ·
71 Blade views · 30 test files.

---

## 3. Roles and what each may do

Four roles. They share one interface; only their capabilities differ.

| | HR Administrator | Campus Director | Dean | Employee |
|---|---|---|---|---|
| Dashboard | campus-wide | campus-wide | own college | own records |
| Employee accounts | create, edit | — | — | — |
| Colleges & departments | manage | — | — | — |
| Leave approval | second signature | final signature | first signature | files leave |
| Ledger cards | maintains all | reads all | — | reads own |
| PDS | reviews all | own only | own only | own only |
| Policies & announcements | publishes | reads | reads | reads |
| Audit trail | reads | — | — | — |

The HR Administrator is a **system account**, not a member of staff: it holds
no leave, ledger or PDS of its own.

**Data boundaries are enforced in the query, not the interface.** A Dean's
visibility is limited by `scopeVisibleTo()` on the User model, so a Dean who
edits a URL to another college's employee gets a 403 rather than data.

---

## 4. The leave approval chain

The chain is derived from **who is applying**, because nobody signs their own
leave.

| Applicant | Route | Final signature |
|---|---|---|
| Employee | Dean → HR → Campus Director | Campus Director |
| Dean | HR → Campus Director | Campus Director |
| HR Administrator | Dean → Campus Director | Campus Director |
| Campus Director | HR | HR |

The Campus Director's own leave goes to HR **alone** — a Dean reports to the
Campus Director, so asking a Dean to sign would invert the reporting line, and
nobody outranks the Campus Director for a final signature. This was the
client's decision.

Skipped stages still appear on the printed trail, marked N/A, so an absent
signature is explained rather than merely missing.

Implemented in `app/Services/LeaveChain.php`.

### The flow end to end

1. HR publishes the blank leave form.
2. The employee downloads it, fills it in Excel, and uploads the workbook.
3. Each reviewer in turn reads it **as a PDF in the browser** and approves or
   returns it with remarks. A returned form restarts from the first stage.
4. Once fully approved, HR records the days against the ledger — choosing
   which of the two cards it is written on.
5. The employee may then print the approval sheet.

---

## 5. The two ledger cards

The campus keeps **two separate cards**, both on the same official form.

| | Leave ledger | Service credit ledger |
|---|---|---|
| A day taken charges | vacation or sick balance | **service credits** — even when the leave was sick or vacation |
| Decimal places | 2 | 3 |
| Lines shown | that card only | that card only |

HR decides which card an approved leave is written on. A line on one card
never appears on the other.

Each line stores the balance as it stood after it, so correcting a line in the
middle **replays every balance below it** (`LeaveLedgerService::recalculate`).

---

## 6. Documents and how they are produced

The campus forms carry merged cells, column widths and print areas that a
rebuilt document cannot reproduce, so **PDS and leave form exports are
conversions of the actual uploaded workbook**, never re-rendered from database
values.

Two renderers, chosen automatically:

1. **LibreOffice** (`soffice --headless`) when the binary is present.
2. **PhpSpreadsheet + Dompdf** otherwise — pure PHP, so it runs on shared
   hosting with no shell access.

Both force **A4** (the supplied templates are US Letter or a custom size, which
crops the right-hand columns), preserve each sheet's own orientation and
margins, honour print areas, skip hidden helper sheets, and scale each sheet to
fit as the template asks.

**The ledger card is the exception.** Its layout is fixed and does not change,
so it is built as HTML from the campus template's own measurements — which lets
a long remark wrap and grow its row instead of being clipped by a fixed cell.
The campus seal is taken from the published template itself.

---

## 7. Modules

- **Authentication** — email and password, optional two-factor code by email,
  role-based redirect after sign-in.
- **Employee accounts** — HR creates and maintains staff, assigning a college
  and department; the college decides who approves that person's leave.
- **Colleges & departments** — departments belong to exactly one college.
- **Personal Data Sheet** — HR publishes CS Form 212; employees download, fill
  and upload; HR approves or returns with remarks; every version is kept.
- **Leave** — as in section 4, with a shared calendar scoped per role.
- **Ledger cards** — as in section 5.
- **HR policies** — published with acknowledgement tracking.
- **Announcements** — campus notices with a notification bell.
- **Digital ID** — printable card with a QR code that resolves to a public
  verification page.
- **Dashboards** — a different set of figures per role.
- **Audit trail** — every sign-in, approval and record change with the account
  and IP behind it.
- **My Profile** — everyone edits their own name, email, contact number, photo
  and password. Employee number, position, college, department and role stay
  with HR, because the college decides the approver.

---

## 8. Design decisions worth writing up

Each of these was a real fork in the road, and the reasoning belongs in the
manuscript more than the code does.

**Conversion over reconstruction.** Exports are conversions of the real
workbook so the printed document is the campus's own form. The one exception,
the ledger card, is rebuilt precisely because its layout is fixed and because a
handwritten card needs cells that grow.

**Two renderers.** The deployment target is shared hosting where LibreOffice
cannot be installed, so a pure-PHP path had to produce the same A4 output. The
system detects what is available rather than being configured for one.

**Per-owner PDF cache keys.** Documents copied from one blank template are
byte-identical, so a cache keyed on content alone served the first person's
document to everyone. The key carries the owner as well.

**Approval derived from role, not hard-coded.** One state machine serves all
four applicant types instead of four branches.

**Scoping in the query.** A Dean's boundary is enforced where the data is
fetched, so it cannot be bypassed by changing a URL.

**Two ledger cards kept apart.** The client's own cards behave differently —
different balances charged, different precision — so they are separate records
rather than one filtered view.

**A design system, not per-page styling.** One set of components (`.btn`,
`.card`, `.chip`, `.icon-btn`, `.table`, `.filter-bar`) with a palette sampled
from the ISPSC seal, guarded by tests that read the Blade sources.

---

## 9. Testing

30 feature-test files. The suite covers the approval chain per role, data
boundaries, PDF fidelity (A4, orientation, page count, no column dropped),
cache isolation, ledger arithmetic and recalculation, the design system, and
that every screen renders for every role.

Notable: several tests exist because a specific defect reached the browser —
a ledger card served to the wrong person, icons rendering at the wrong size,
a form control placed outside its own `<form>`. Each is written to fail on the
original bug.

---

## 10. Deployment

Shared hosting (cPanel), PHP 8.2, MySQL. Requires the `gd`, `zip`, `mbstring`,
`dom`, `xml`, `iconv` and `fileinfo` extensions, and a writable `storage/`.

`PDF_RENDERER=php` forces the pure-PHP renderer; `auto` uses LibreOffice where
it exists. Set `TWO_FACTOR_ENABLED=true` before the defence.
