{{-- The one copy of the terms. Included by the standalone page at /terms and
     by the dialog on the sign-in screen, so the wording a user agrees to at
     sign-in and the wording they can read afterwards can never drift apart.

     Every claim here is one the system actually keeps: the access boundaries,
     the private storage, the audit trail and the hashed passwords are all
     enforced in code, not policy. Do not add a promise the system does not
     already keep. --}}

<div class="space-y-6 text-[13.5px] leading-relaxed text-sand-600">

    <p class="text-[12px] uppercase tracking-[0.14em] text-sand-400">
        {{-- Bump this whenever the wording below changes. --}}
        Last updated September 2026
    </p>

    <p>
        NexHRIS is the Human Resource Information System of Ilocos Sur Polytechnic
        State College, Tagudin Campus. It is operated by the Human Resource
        Management Office. By signing in you confirm that you have read and accept
        the terms below.
    </p>

    {{-- 1 --------------------------------------------------------------- --}}
    <section>
        <h3 class="mb-1.5 font-semibold text-sand-900">1. Who may use this system</h3>
        <p>
            Accounts are issued by the HR Office to the employees, Deans, Campus
            Director and HR staff of ISPSC Tagudin Campus. An account belongs to
            one person. You may not share it, sign in on someone else's behalf, or
            let another person use a session you have opened.
        </p>
    </section>

    {{-- 2 --------------------------------------------------------------- --}}
    <section>
        <h3 class="mb-1.5 font-semibold text-sand-900">2. What information the system collects</h3>
        <p class="mb-2">
            The HR Office collects and keeps the following through this system:
        </p>
        <ul class="ml-5 list-disc space-y-1.5">
            <li>
                <strong class="font-medium text-sand-800">Your Personal Data Sheet.</strong>
                The completed Civil Service form you upload, including every earlier
                version you submitted, is stored and read by the HR Administrator.
            </li>
            <li>
                <strong class="font-medium text-sand-800">Your leave records.</strong>
                The leave forms you file, the dates and type of leave, the remarks
                any reviewer writes, and the running balances on your leave and
                service credit ledger cards.
            </li>
            <li>
                <strong class="font-medium text-sand-800">Your employment details.</strong>
                Name, employee number, position, college and department, first day
                of government service, work email address, contact number and
                photograph.
            </li>
            <li>
                <strong class="font-medium text-sand-800">Your activity in the system.</strong>
                Sign-ins and failed sign-in attempts with the IP address behind
                them, approvals you give, policies you acknowledge, and changes made
                to records — including the values a record held before the change.
            </li>
        </ul>
    </section>

    {{-- 3 --------------------------------------------------------------- --}}
    <section>
        <h3 class="mb-1.5 font-semibold text-sand-900">3. Why it is collected, and who can see it</h3>
        <p class="mb-2">
            The information is used to carry out the HR transactions of the campus:
            maintaining your 201 file, routing and approving leave, keeping your
            ledger cards, issuing your digital identification card, and producing
            the official reports the HR Office is required to keep. It is not used
            for any other purpose and is not sold or disclosed to any outside party
            except where a law or a lawful order requires it.
        </p>
        <p class="mb-2">Access follows your role, and nothing wider:</p>
        <ul class="ml-5 list-disc space-y-1.5">
            <li>The <strong class="font-medium text-sand-800">HR Administrator</strong> reads and maintains all personnel records.</li>
            <li>The <strong class="font-medium text-sand-800">Campus Director</strong> reads campus-wide records for approval.</li>
            <li>A <strong class="font-medium text-sand-800">Dean</strong> reads only the records of their own college.</li>
            <li>An <strong class="font-medium text-sand-800">employee</strong> reads only their own records.</li>
        </ul>
        <p class="mt-2">
            These boundaries are enforced when records are read from the database,
            not merely hidden from the screen.
        </p>
    </section>

    {{-- 4 --------------------------------------------------------------- --}}
    <section>
        <h3 class="mb-1.5 font-semibold text-sand-900">4. How it is protected</h3>
        <p>
            Uploaded documents — Personal Data Sheets, leave forms and policy
            attachments — are held outside the publicly reachable part of the server
            and are released only through the system, after your role has been
            checked. Passwords are stored hashed and are never readable, by the HR
            Office or by anyone else. Accounts that administer or approve on behalf
            of others must also clear a verification code sent to their registered
            email address. Repeated failed sign-in attempts lock the account
            temporarily, and both the failures and the lock are recorded.
        </p>
    </section>

    {{-- 5 --------------------------------------------------------------- --}}
    <section>
        <h3 class="mb-1.5 font-semibold text-sand-900">5. Your responsibilities</h3>
        <ul class="ml-5 list-disc space-y-1.5">
            <li>Keep your password private, and change it if you believe someone else knows it.</li>
            <li>Never share a verification code. HR staff will never ask you for one.</li>
            <li>Make sure the information you submit, above all in your Personal Data Sheet, is true and complete. You remain responsible for what you declare on an official form.</li>
            <li>Report anything that looks wrong in your records to the HR Office rather than working around it.</li>
            <li>Do not attempt to reach records that are not yours. Attempts are refused and recorded.</li>
        </ul>
    </section>

    {{-- 6 --------------------------------------------------------------- --}}
    <section>
        <h3 class="mb-1.5 font-semibold text-sand-900">6. Your rights under the Data Privacy Act</h3>
        <p>
            Republic Act No. 10173, the Data Privacy Act of 2012, gives you the
            right to be informed of how your personal data is processed, to have
            access to it, to have inaccurate entries corrected, to object to
            processing in the cases the law allows, and to lodge a complaint with
            the National Privacy Commission. Requests to see or correct what the
            system holds about you should be made to the HR Management Office, which
            is the personal information controller for these records.
        </p>
    </section>

    {{-- 7 --------------------------------------------------------------- --}}
    <section>
        <h3 class="mb-1.5 font-semibold text-sand-900">7. How long records are kept</h3>
        <p>
            Personnel records form part of your 201 file and are retained for as
            long as the Civil Service Commission and the college's records
            retention rules require, which is generally beyond the end of your
            service. Accounts of employees who leave are deactivated rather than
            deleted, so that the approvals and entries attached to them remain
            complete and auditable.
        </p>
    </section>

    {{-- 8 --------------------------------------------------------------- --}}
    <section>
        <h3 class="mb-1.5 font-semibold text-sand-900">8. Availability and changes</h3>
        <p>
            The system may be unavailable during maintenance or upgrades. Where the
            system is unavailable, the HR Office continues to accept transactions on
            paper. These terms may be revised; the current version is always the one
            shown on this page, and material changes will be announced through the
            system.
        </p>
    </section>

    {{-- 9 --------------------------------------------------------------- --}}
    <section>
        <h3 class="mb-1.5 font-semibold text-sand-900">9. Contact</h3>
        <p>
            Human Resource Management Office, Ilocos Sur Polytechnic State College,
            Tagudin Campus, Tagudin, Ilocos Sur. Questions about your records,
            your account or these terms should be addressed there.
        </p>
    </section>
</div>
