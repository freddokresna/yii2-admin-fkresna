# Phase 2 — Business User Agent Audit Report

**Project:** yii2-admin-fkresna (Yii2 RBAC Admin)
**Audit Date:** 2026-09-08
**Auditor:** AI (Phase 2 — Business User Agent)
**Scope:** Controllers (8 files), Views (32 files), Messages/Translations (2 locales), Models, JS, Module config
**Context Reference:** Phase 1 UI/UX Audit (`docs/phase1_audit.md`)

---

## Executive Summary

| Category | Issues Found | Severity |
|---|---|---|
| Workflow & Navigation Gaps | 9 | High |
| Form Usability & Field Design | 7 | High |
| Error Handling & Feedback | 8 | High/Medium |
| CTA (Call-to-Action) Clarity | 5 | Medium |
| Business Logic / Conceptual Clarity | 4 | High |
| Accessibility & Internationalization | 3 | Medium/Low |
| **Total** | **36** | |

---

## 1. WORKFLOW & NAVIGATION GAPS

### W-01. Assignment: No confirmation feedback after assign/revoke
- **Files:** `views/assignment/view.php` (L31 — loads `_script.js`), `views/assignment/_script.js` (L10-23)
- **Description:** When an admin clicks "Assign" or "Remove" on the assignment page, the operation completes via JSON POST but **no confirmation is shown**. The dropdowns update silently. If the operation fails (server error, network timeout), the user sees nothing — not even a spinner visible long enough.
- **Impact:** Admin cannot confirm that roles were actually granted. Silent failures lead to false confidence.
- **Fix:** After a successful assign/revoke, show a Bootstrap toast or flash alert: "Roles assigned to {username}." Also show a visible spinner during the request and handle 4xx/5xx errors.

### W-02. Role/Permission Create: Redirect to detail, not back to list
- **Files:** `components/ItemController.php` (L86), `views/item/create.php`
- **Description:** After creating a new role or permission, the user is redirected to the **detail view** of the newly created item, not the index/list. This forces the admin to manually click the breadcrumb or back button to create another item, disrupting batch-creation workflows.
- **Impact:** Creating 3+ items in a row requires excessive back-navigation clicks.
- **Fix:** Redirect to the list page after create (`redirect(['index'])`), or add a persistent "Create another" button on the detail page that clears the form.

### W-03. User activation: Success shown only via redirect (no visible confirmation)
- **Files:** `controllers/UserController.php` (L287-301), `views/user/view.php` (L22-29)
- **Description:** The `actionActivate` method calls `goHome()` after saving. The admin is redirected to the application's home page, losing all context of the RBAC admin panel. No flash message is set, so even on the home page the admin doesn't know the activation succeeded.
- **Impact:** Admin may think the activation failed and click "Activate" again, causing duplicate/conflicting behavior.
- **Fix:** Use a flash message (`setFlash('success', ...)`) and redirect back to the user's view page (`redirect(['view', 'id' => $id])`) instead of `goHome()`.

### W-04. User management: No edit functionality (only activate/delete)
- **Files:** `controllers/UserController.php` (no update/create), `views/user/view.php` (L20-42)
- **Description:** The user management module allows viewing and activating/deleting users, but there is **no edit form** to update username, email, or other fields. The `_form.php` exists but is never rendered by any controller action.
- **Impact:** Admins must use database tools or workarounds to change user attributes.
- **Fix:** Implement `actionUpdate` / `actionCreate` in `UserController` using the existing `_form.php`, or document this as an intentional limitation.

### W-05. Route refresh: "Generate Routes" action hidden in button, no progress indicator
- **Files:** `views/route/index.php` (L44-47), `views/route/_script.js` (L71-85)
- **Description:** The refresh button is a small icon button without text. Clicking it repopulates available routes but doesn't tell the user "Routes are being refreshed…" or how many were found.
- **Impact:** Admins don't know if they need to click again or if the system hung.
- **Fix:** Add text label "Refresh Routes" or a tooltip. After refresh, show a flash or toast: "Routes refreshed: {n} available, {m} assigned."

### W-06. Role/Permission detail: "Create" button does not indicate type
- **Files:** `views/item/view.php` (L46), `components/ItemController.php` (L190-192)
- **Description:** The "Create" button on the detail page is generic — it doesn't say "Create Permission" or "Create Role". The actual button text depends on the controller (RoleController vs PermissionController) but the view doesn't convey which type was just viewed.
- **Impact:** After viewing a Role detail and clicking "Create", the admin might not realize they're creating another Role (vs. a Permission).
- **Fix:** Render `$context->labels()['Create']` instead of hardcoded 'Create'. Add breadcrumb: "Roles > {name} > Create" to confirm context.

### W-07. Menu management: No "back to list" on create/update pages
- **Files:** `views/menu/create.php` (doesn't exist — uses shared view), `views/menu/view.php` (L17-27)
- **Description:** Menu create/update follow the same pattern as Role/Permission: no explicit "Back to Menus" button. The only navigation is the breadcrumb.
- **Impact:** Users who don't read breadcrumbs get lost.
- **Fix:** Add a "Cancel" / "Back" button styled as `btn-outline-secondary` to all create/update forms.

### W-08. Signup: Post-signup redirect to `goHome()` with no account details shown
- **Files:** `controllers/UserController.php` (L183-186)
- **Description:** After a successful signup, the controller calls `goHome()`, which redirects to the application's home URL. The new user doesn't see their username or any confirmation that the account was created successfully.
- **Impact:** Admins can't verify which account was just created, especially if multiple accounts are created in sequence.
- **Fix:** Show a confirmation flash with the username on the home page, or redirect to the user's view page.

### W-09. Password reset: Generic success message leaks information (security-UX tradeoff)
- **Files:** `controllers/UserController.php` (L231), `messages/en/rbac-admin.php` (L100)
- **Description:** The password reset page shows "If your email is registered, the reset link has been sent" regardless of whether the email exists. While this prevents user-enumeration (a valid security measure), it creates UX confusion: admins who mistype their email don't know if they need to try again.
- **Impact:** Admins may retry multiple times, triggering rate limits unnecessarily.
- **Fix:** Add a secondary hint below the form: "If you don't receive the email within a few minutes, please check your spam folder or contact the system administrator."

---

## 2. FORM USABILITY & FIELD DESIGN

### F-01. Name field: No character counter despite maxlength=64
- **Files:** `views/item/_form.php` (L31), `views/rule/_form.php` (L15), `views/menu/_form.php` (L26)
- **Description:** All name/text inputs have a maxlength attribute (64 or 128) but show no character counter. Users type a long name, hit submit, and only then discover the name was truncated — or worse, the server rejects it with a cryptic error.
- **Impact:** Wasted effort, confusion.
- **Fix:** Add a `data-counter-target` element and a small JS snippet that updates "42/64 characters" in real time.

### F-02. Rule Name autocomplete: No placeholder or guidance text
- **Files:** `views/item/_form.php` (L18-24), `views/menu/_form.php` (L13-18)
- **Description:** The `rule_name` input uses jQuery UI Autocomplete, but there's no placeholder like "Type to search rules…" or helper text explaining that this field is optional. Users don't know what values are valid.
- **Impact:** Trial-and-error input, errors from typos.
- **Fix:** Add `placeholder="<?= Yii::t('rbac-admin', 'Type to search rules…') ?>"` and a `form-text` hint: "This field is optional. Select a rule to apply business logic to this item."

### F-03. "Data" field: Ambiguous label, no format hint
- **Files:** `views/item/_form.php` (L38)
- **Description:** `$form->field($model, 'data')->textarea(['rows' => 6])` — the field is labeled simply "Data" in translations. Users have no idea what format is expected (JSON? PHP array? serialized data?).
- **Impact:** Users enter invalid data that only fails on save, with a generic validation error.
- **Fix:** Change label to "Data (JSON format)" and add a `form-text` hint: "Enter additional data as JSON. Example: `{\"param1\": \"value1\"}`."

### F-04. Menu form: Parent Name autocomplete has no guidance
- **Files:** `views/menu/_form.php` (L28)
- **Description:** The `parent_name` autocomplete works similarly to `rule_name` but has no placeholder, no helper text, and no indication that the field is optional.
- **Impact:** Confusion about what value to type.
- **Fix:** Add `placeholder="e.g. Dashboard"` and helper text: "Enter the parent menu name to create a hierarchy. Leave empty for top-level."

### F-05. Route input: No validation hint on expected format
- **Files:** `views/route/index.php` (L29)
- **Description:** The "New route(s)" input accepts comma-separated route names (e.g., `site/index, user/profile`), but the placeholder just says "New route(s)". Users may enter one route at a time, trying to submit repeatedly.
- **Impact:** Wasted clicks, confusion.
- **Fix:** Change placeholder to: "Enter route(s) — comma-separated (e.g., site/index, user/profile)" and add a helper message.

### F-06. User status field in `_form.php`: Plain text input for a numeric enum
- **Files:** `views/user/_form.php` (L15) — referenced from Phase 1 audit item F-06
- **Description:** `$form->field($model, 'status')->textInput()` renders a free-text number input. Status is an enum (0 = Inactive, 10 = Active) but the form allows any integer input.
- **Impact:** Data integrity risk — a user could enter "99" or "-1".
- **Fix:** Use `dropDownList([0 => 'Inactive', 10 => 'Active'])`. (This was already flagged in Phase 1.)

### F-07. No cancel/back button on ANY create/update form
- **Files:** All `_form.php` files: `item/_form.php` (L43-45), `menu/_form.php` (L41-42), `rule/_form.php` (L21-22)
- **Description:** After starting to fill out a form, there's no way to cancel and return to the list without using the browser back button.
- **Impact:** Frustrating UX for accidental form entry.
- **Fix:** Add a `<a class="btn btn-outline-secondary" href="...">Cancel</a>` button next to the submit button on all forms.

---

## 3. ERROR HANDLING & FEEDBACK

### E-01. Route creation errors shown in a hidden div — easy to miss
- **Files:** `views/route/index.php` (L25), `views/route/_script.js` (L7-18)
- **Description:** When a route name exceeds 64 characters, the error is rendered inside `#route-alert` which starts as `display:none`. The JS toggles `show()`, but the error appears at the top of the page above the form input, and users might not scroll up to see it.
- **Impact:** Users think the route was added when it wasn't.
- **Fix:** Add a flash message or show the error inline next to the input field. Also add a small red border to the input on error.

### E-02. No error handling for failed AJAX assign/revoke operations
- **Files:** `views/assignment/_script.js` (L17-21), `views/item/_script.js` (L36-40), `views/route/_script.js` (L52-67)
- **Description:** The AJAX `.post()` calls have no `.fail()` handler. If the server returns a 500 error or the network fails, the user sees no error message — just a spinner that eventually stops.
- **Impact:** Users don't know the operation failed and may click the button multiple times.
- **Fix:** Add `.fail(function(jqXHR) { showNotification('Operation failed: ' + jqXHR.statusText); })`.

### E-03. Activation failure throws a UserException with technical message
- **Files:** `controllers/UserController.php` (L296-298)
- **Description:** When `save()` fails on activation, a `UserException` is thrown with `$user->firstErrors`. This produces a Yii-generated error page with stack traces, not a user-friendly message.
- **Impact:** Sensitive technical error exposed to the user; poor UX.
- **Fix:** Catch the exception and redirect back with a flash message: "Could not activate user. Please try again."

### E-04. Menu delete: Error message lacks link to submenu management
- **Files:** `controllers/MenuController.php` (L122-129)
- **Description:** When trying to delete a menu with children, the error says "Menu X still has N submenu(s). Delete the submenus first." but doesn't provide a link to navigate to those submenus or bulk-delete them.
- **Impact:** Admin must manually find the submenus to delete them first.
- **Fix:** Include a link in the error: "Menu X still has N submenu(s). Delete the submenus first. [View Submenus]" — where the link goes to `menu/index` filtered by parent.

### E-05. Rule deletion: Error message is clear but doesn't show which items use the rule
- **Files:** `controllers/RuleController.php` (L115-122)
- **Description:** The error says "Rule X is still used by N item(s). It cannot be deleted." but doesn't list which roles/permissions use it.
- **Impact:** Admin can't easily identify which items to modify.
- **Fix:** Include item names in the message: "Rule X is used by: admin, editor, viewer. Remove from these items first."

### E-06. Login failed: Error message is generic but good for security
- **Files:** `messages/en/rbac-admin.php` (L102)
- **Description:** "Incorrect username or password." is a standard secure message. However, it doesn't help users diagnose whether the issue is a typo, a locked account, or a wrong password — which can cause repeated lockouts.
- **Impact:** Minor — users might get locked out from repeated attempts.
- **Fix:** After 3 consecutive failures, show a more specific hint: "Note: If you've reset your password recently, try logging in again."

### E-07. Bulk search on multi-select: No indication of filtered count
- **Files:** `views/assignment/_script.js` (L31-52), `views/item/_script.js` (L51-72)
- **Description:** When the user types in the search box, options are filtered client-side. But the select shows no indication of how many items match (e.g., "Showing 3 of 45").
- **Impact:** Users don't know if the search is working or if an item was excluded.
- **Fix:** Add a small indicator: `Showing X of Y items` below the search input.

### E-08. DetailView `data` field shows raw JSON without formatting
- **Files:** `views/item/view.php` (L57)
- **Description:** The `data` field renders as `ntext` — raw, unformatted JSON or PHP serialized data. Long data blobs are unreadable in a table cell.
- **Impact:** Admins can't quickly understand what data an item has.
- **Fix:** If data is valid JSON, pretty-print it with syntax highlighting. Otherwise, show a truncated version with a "Show full" expand link.

---

## 4. CTA (CALL-TO-ACTION) CLARITY

### C-01. "Assign" / "Remove" buttons: Icon-only, no visible text labels
- **Files:** `views/assignment/view.php` (L46-55), `views/item/view.php` (L87-98), `views/route/index.php` (L52-61)
- **Description:** All assign/remove buttons use only Bootstrap Icons (`bi-chevron-double-right` / `bi-chevron-double-left`) inside anchor tags. The text label is in `visually-hidden` span or `title` attribute only. Non-technical admins may not understand what the arrows do.
- **Impact:** New admins hesitate to click; incorrect clicks have security implications.
- **Fix:** Add visible text labels: "Assign →" and "← Remove" next to the icons.

### C-02. Delete buttons: No soft-delete or undo option
- **Files:** All controllers with `actionDelete`: `ItemController` (L115-122), `MenuController` (L111-136), `RuleController` (L106-129), `UserController` (L112-141)
- **Description:** All delete actions are hard deletes with no undo mechanism. The confirmation dialog asks "Are you sure?" but doesn't explain the consequences.
- **Impact:** Accidental deletions of roles, permissions, or users can't be recovered.
- **Fix:** At minimum, show more detail in the confirmation: "Deleting role 'admin' will remove it from all users and break any permissions inherited from it. This cannot be undone."

### C-03. "Create" button on role/permission detail: No type context
- **Files:** `views/item/view.php` (L46)
- **Description:** A generic "Create" button doesn't indicate which type of item will be created (Role or Permission). The controller determines this but the view doesn't expose it.
- **Impact:** Confusion when creating items from the detail page.
- **Fix:** Use context-aware label: `<?= Yii::t('rbac-admin', $labels['Create Item']) ?>` where `$labels['Create Item']` = "Create Permission" or "Create Role".

### C-04. Refresh button: No text label, ambiguous purpose
- **Files:** `views/route/index.php` (L44-47)
- **Description:** A small refresh icon button with only a `visually-hidden` "Refresh" span. Without reading the source, it's not obvious whether this refreshes the list, scans for new routes, or both.
- **Impact:** Admins may not know to click it after adding new routes to the application.
- **Fix:** Show text: "Refresh Routes" or add a descriptive tooltip.

### C-05. Activate button: Only shows on inactive users, but no "Deactivate" option
- **Files:** `views/user/index.php` (L40-52)
- **Description:** The ActionColumn shows an activate button only for inactive users (status != 10). There is no "Deactivate" button for active users.
- **Impact:** Admins cannot easily deactivate users — they must go to the view page and potentially use the edit form (which doesn't exist) or delete the user.
- **Fix:** Add a "Deactivate" button for active users, or use a toggle button that switches between "Activate" and "Deactivate".

---

## 5. BUSINESS LOGIC & CONCEPTUAL CLARITY

### B-01. Terminology: "Rule" = BizRule class name, not business logic
- **Files:** `views/rule/index.php`, `messages/id/rbac-admin.php` (L62-64)
- **Description:** In Yii2 RBAC, "Rule" refers to a PHP class that evaluates conditions (BizRule). The Indonesian translation calls this "Peraturan" which means "Regulation/Policy" — this is misleading. Users expect "Rule" to mean a menu-level access rule, not a PHP class.
- **Impact:** Non-technical admins are confused about what a "Rule" is and how it differs from a "Permission".
- **Fix:** Change terminology: "Rule (Kondisi)" or "Rule (BizRule)" in Indonesian. Add a tooltip: "A PHP class that evaluates conditions for permissions."

### B-02. No visual distinction between Role hierarchy vs Permission chain
- **Files:** `views/item/view.php` (L78-106) — same dual-select UI for roles and permissions
- **Description:** Roles have a parent-child hierarchy (role A extends role B), while permissions can be assigned to roles and users independently. But the UI uses the same dual-select `<select>` UI for both, making it unclear whether the admin is building a hierarchy or a flat assignment.
- **Impact:** Admins may incorrectly assign permissions as parent roles, creating a broken hierarchy.
- **Fix:** Add a visual label on the view page: "Role Hierarchy" or "Permission Assignments" to clarify the context.

### B-03. No RBAC documentation tooltip or inline help
- **Files:** All views, `Module.php` (L105 — "Help" menu item)
- **Description:** The "Help" menu item points to the README, which is a Markdown document. RBAC concepts (roles, permissions, rules, routes) are complex and admins may not understand them. There is no inline help on any form or page.
- **Impact:** New admins spend excessive time learning the concepts through trial-and-error.
- **Fix:** Add `title` tooltips and small `?` icons on key fields explaining: "What is a role?", "What is a route?", "What is a rule?".

### B-04. No overview/dashboard for RBAC health
- **Files:** `controllers/DefaultController.php` (L19) — the "default" page is just a README renderer
- **Description:** There is no dashboard showing: number of users, number of roles, orphaned permissions, roles with no users assigned, etc. Admins must navigate to each page individually.
- **Impact:** Inefficient admin workflow; no quick overview of RBAC system health.
- **Fix:** Create a `dashboard` index action showing: total users, total roles, total permissions, assignments count, and quick-access cards.

---

## 6. ACCESSIBILITY & INTERNATIONALIZATION

### A-01. English-only UI on auth pages (login, signup, reset)
- **Files:** `views/user/login.php` (L24-25), `views/user/signup.php` (L25), `views/user/change-password.php` (L15)
- **Description:** Several auth pages use hardcoded English strings: "If you forgot your password you can reset it", "For new user you can signup", "Please fill out the following fields to change password". These are not wrapped in `Yii::t()`.
- **Impact:** Indonesian-speaking users see mixed English/Indonesian UI.
- **Fix:** Wrap all user-facing text in `Yii::t('rbac-admin', '…')` and add to message files.

### A-02. Indonesian translations missing some messages
- **Files:** `messages/id/rbac-admin.php`
- **Description:** Several keys used in English but missing in Indonesian (e.g., 'Application', 'Admin' — L80-81 in English, not present in Indonesian). Duplicate keys exist: 'Rules' appears twice (L62-63).
- **Impact:** Falls back to English for some terms, inconsistent experience.
- **Fix:** Add missing translations and remove duplicates.

### A-03. Breadcrumb language inconsistent
- **Files:** `views/menu/view.php` (L23 — 'Are you sure you want to delete this item?' not translated in Indonesian)
- **Description:** The delete confirmation on the menu view page uses hardcoded English text, not wrapped in `Yii::t()`.
- **Impact:** Inconsistent localization within the same page flow.
- **Fix:** Wrap in `Yii::t('rbac-admin', 'Are you sure you want to delete this item?')`.

---

## Summary of Priority Recommendations

| Priority | ID | Category | Issue | Quick Fix |
|---|---|---|---|---|
| **HIGH** | W-01 | Workflow | No confirm feedback on assign/revoke | Add toast/flash + spinner |
| **HIGH** | W-03 | Workflow | Activation redirects to `goHome()` | Flash message + redirect to view |
| **HIGH** | W-04 | Workflow | No user edit functionality | Implement update action |
| **HIGH** | F-01 | Form | No character counter on name fields | Add real-time counter JS |
| **HIGH** | F-02 | Form | No placeholder on autocomplete | Add placeholder + helper text |
| **HIGH** | F-03 | Form | Ambiguous "Data" field label | Label as "Data (JSON format)" |
| **HIGH** | E-02 | Error | No error handling on AJAX calls | Add `.fail()` handlers |
| **HIGH** | E-03 | Error | Technical UserException on failure | Catch and show user-friendly flash |
| **HIGH** | B-01 | Business | "Rule" terminology confusing | Add tooltip: "BizRule class" |
| **HIGH** | B-02 | Business | No distinction between hierarchy vs assignment | Add section label on view |
| **HIGH** | B-04 | Business | No RBAC overview dashboard | Create dashboard action |
| **MEDIUM** | W-02 | Workflow | Post-create redirect to detail, not list | Redirect to index |
| **MEDIUM** | W-05 | Workflow | Refresh button hidden, no progress | Add text label + toast |
| **MEDIUM** | W-06 | Workflow | "Create" button no type context | Use dynamic label |
| **MEDIUM** | W-07 | Workflow | No cancel button on forms | Add Cancel button |
| **MEDIUM** | F-04 | Form | Parent Name autocomplete no guidance | Add placeholder + hint |
| **MEDIUM** | F-05 | Form | Route input no format hint | Update placeholder |
| **MEDIUM** | F-07 | Form | No cancel on any form | Add Cancel button everywhere |
| **MEDIUM** | E-01 | Error | Route errors in hidden div, easy to miss | Inline error + highlight |
| **MEDIUM** | E-04 | Error | Menu delete error no submenu link | Add link in error message |
| **MEDIUM** | E-05 | Error | Rule deletion error doesn't list items | Show item names in message |
| **MEDIUM** | E-07 | Error | Search count not shown | Show "X of Y items" |
| **MEDIUM** | C-01 | CTA | Icon-only assign/remove buttons | Add visible text labels |
| **MEDIUM** | C-02 | CTA | Delete confirmation too generic | Add detail in confirmation |
| **MEDIUM** | C-05 | CTA | No deactivate button | Add toggle activate/deactivate |
| **LOW** | W-09 | Workflow | Password reset generic message | Add secondary hint text |
| **LOW** | E-06 | Error | Login error no diagnostic hint | Add hint after 3 failures |
| **LOW** | E-08 | Error | Raw JSON in DetailView | Pretty-print + syntax highlight |
| **LOW** | A-01 | i18n | Hardcoded English on auth pages | Wrap in `Yii::t()` |
| **LOW** | A-02 | i18n | Missing/duplicate translations | Complete Indonesian catalog |
| **LOW** | A-03 | i18n | Hardcoded English on menu delete | Use `Yii::t()` |

---

## Phase 2 vs Phase 1 Alignment

Phase 2 issues focus on **business logic, workflow design, and form usability** — complementing Phase 1's presentation-layer audit. Many Phase 2 issues (F-01, F-02, F-04, F-05, F-06, F-07, A-01, C-01) overlap with Phase 1 findings (F-01 through F-06, A-02/A-03). Phase 2 extends these by:

- Explaining the **business impact** (admin confusion, data integrity risks, security implications)
- Identifying **workflow gaps** not visible in a visual audit (missing actions, confusing navigation flows)
- Evaluating **error messages** from the user's diagnostic perspective
- Assessing **CTA clarity** and whether calls-to-action communicate consequences

---

## Suggested Implementation Order (Phase 3 prep)

1. **Sprint 1 (Quick Wins — 1 day):** Add cancel buttons, character counters, placeholder text, visible CTA labels, fix hardcoded English
2. **Sprint 2 (Workflow Fixes — 2 days):** Fix redirect flows, add AJAX error handling, improve confirmation messages
3. **Sprint 3 (Feature Additions — 3 days):** User edit form, deactivate toggle, RBAC dashboard, inline help tooltips
4. **Sprint 4 (Polish — 1 day):** i18n completion, error message enrichment, JSON pretty-printing, search count indicators

---

*This audit was performed by reading 8 controller files, 32 view files, 2 message translation catalogs (en + id), the Module class, and all JavaScript assets. The audit simulated the perspective of a non-technical business administrator using the RBAC admin panel to manage users, roles, permissions, routes, rules, and menus.*
