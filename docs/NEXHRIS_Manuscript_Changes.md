# What changed in the updated manuscript

Source: `docs/New NEXHRIS_Manuscript_Ch1-3.pdf` (28 pages)
Updated: `docs/NEXHRIS_Manuscript_Ch1-3_Updated.docx` (edit this one)
          `docs/NEXHRIS_Manuscript_Ch1-3_Updated.html` (the source it was built from)

Everything below was checked against the running system, not assumed.

## Corrections of fact

| # | Where | Original said | Corrected to | Why |
|---|---|---|---|---|
| 1 | Ch2, Cutover | "During development the system was hosted on Hostinger's shared hosting with cloud-based document storage" | Development on a local server (PHP 8.2 / MySQL); deployment target is cPanel shared hosting; uploaded records held on the server's own private storage | The claim was untrue. There is no cloud storage in the system; records are stored outside the web root and served through the application. |
| 2 | Ch3, Authentication | "an optional two-factor code sent by email" | The code challenges the HR Administrator, Deans and the Campus Director; employee accounts sign in with a password alone | `TwoFactorService` restricts the challenge to those three roles. It is not optional and not campus-wide. |
| 3 | Ch1, Hypothesis | Sentence was cut off mid-clause | Restated in full as H0 | Unreadable as printed. |
| 4 | Ch2, Slovin's | `n = 142 / [ 1 + 142(0.05)2 105` | Formula, substitution and result set out on separate lines | The exponent and the result had run together. |
| 5 | Ch3, Data Security | "role-based access control" only | Added: boundaries enforced at the query level; uploaded records held outside the web root; only blank forms served directly; passwords hashed and required to mix letters and numerals | Describes what the system actually does. |
| 6 | Ch3, Audit Trail | Sign-ins and record changes | Added ledger changes with the values a line previously held | Ledger edits are now logged with their before-values. |
| 7 | Ch1, Scope | Nothing on conversion fidelity | Added a paragraph: where no document converter is installed, the leave form is issued as the original workbook rather than a partially rendered PDF | An honest limitation, and better raised by you than by a panelist. |
| 8 | Ch2, Construction | Tooling only | Added the automated test suite and its size | Evidence of correctness, and it is defensible. |

## Modules the original manuscript did not mention

These exist and are now written up:

- **Publication of the official forms** — the HR Administrator uploads the blank Civil Service forms; each upload is a numbered version and the previous one is retired rather than overwritten, so a submission can be read against the version it was filled on. Previewable as PDF before issue.
- **Management reports** — leave balances of all personnel (PDF and spreadsheet), the employee directory as a PDF respecting the on-screen filter, and the leave calendar for a chosen month. Every generated document is named for the person or period it covers.
- **Notifications** — the bell and the notifications page, alongside email.
- **Colleges and departments** — a department belongs to exactly one college, and the college an employee is assigned to determines who signs their leave.
- **Leave calendar** — scoped to the role viewing it, printable for posting.

## Chapter 3 plates: 25 → 42

The original 25 plates were replaced with 42, grouped by module and each captioned
with the screen it shows and, where the screen differs by role, the role it is shown
for. The URL under each placeholder is the actual route, so you can open the screen,
capture it, and drop the image into the box.

| Group | Plates |
|---|---|
| Authentication and role-based access | 1–3 |
| Dashboards (one per role) | 4–7 |
| Employee accounts, colleges, profile | 8–12 |
| Publication of the official forms | 13 |
| Personal Data Sheet | 14–17 |
| Leave application and approval chain | 18–24 |
| Leave calendar | 25–26 |
| Ledger cards | 27–32 |
| Management reports | 33–34 |
| Policies, announcements, notifications | 35–39 |
| Digital identification card | 40–41 |
| Audit trail | 42 |

Three of these are worth capturing deliberately rather than casually:

- **Plate 3** — the refusal page a Dean gets on opening a record outside their
  college. It is the evidence for the query-level boundary claim in Chapter 1.
- **Plate 24** — a Dean's own application, showing the Dean stage marked not
  applicable. It demonstrates the rank-derived route better than any prose.
- **Plate 32** — the service credit card at three decimal places beside the leave
  card at two. It shows the system follows the campus's own forms.

## How to use the file

1. Open the `.docx` in Word. It is A4, double-spaced, Times New Roman 12, with a
   1.5-inch left margin and 1 inch elsewhere.
2. Each plate is a dashed grey box. Select the box, delete it, and paste the
   screenshot in its place. Leave the caption underneath — the captions are already
   numbered in sequence.
3. Tables 5, 6 and 7 at the end of Chapter 3 are placeholders. Fill them once the
   TAM questionnaire has been administered.
4. Figures 1, 2 and 3 (research paradigm, RAD model, Gantt chart) are placeholders
   for the diagrams already in your original manuscript — paste those back in.

## Left alone on purpose

- The literature review, the citations and the research gap argument in Chapter 1.
- The research design, RAD phases, population, Slovin's sample of 105, Table 1
  (project assignment), Tables 2–4, TAM and the Wilcoxon Signed-Rank Test.
- The four objectives, and the beneficiaries in the Importance of the Study.

These describe your research, not the system, and nothing in the code contradicts
them.
