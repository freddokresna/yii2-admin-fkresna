# Phase 1 UI/UX Audit Report — yii2-admin-fkresna (Yii2 RBAC Admin)

**Audit Date:** 2026-09-08
**Auditor:** AI (Phase 1 — UI/UX Designer)
**Scope:** `views/layouts/`, `views/**/*.php`, `assets/*.css`, `assets/*.js`, `views/**/_script.js`
**Framework:** Yii2 + Bootstrap 5 + Bootstrap Icons + jQuery UI Autocomplete
**Total files analyzed:** 32 PHP views, 4 layout templates, 3 CSS files, 4 JS files

---

## Executive Summary

| Category | Issues Found | Severity |
|---|---|---|
| Layout & Structure | 7 | High/Medium |
| Typography | 4 | Medium |
| Colors & Contrast | 3 | High/Medium |
| Spacing & Alignment | 5 | Medium |
| Accessibility (a11y) | 8 | High |
| Empty States | 3 | Medium |
| Responsive Design | 4 | Medium/High |
| Form Usability | 6 | High |
| **Total** | **40** | |

---

## 1. LAYOUT & STRUCTURE

### L-01. No page-header / container wrapper class for content sections
- **Files:** `views/assignment/index.php` (L28), `views/item/index.php` (L22), `views/menu/index.php` (L14), `views/rule/index.php` (L15), `views/user/index.php` (L14), `views/route/index.php` (L24)
- **Description:** Every list page directly renders an `<h1>` inside a raw `div` (e.g. `.assignment-index`, `.role-index`, `.menu-index`). No `.page-header` class, no content card/wrapper. All pages look flat.
- **Recommendation:** Wrap each page's content in a `<div class="card shadow-sm mb-4">` with a `.card-header` containing the page title. Standardize across all index pages.

### L-02. Layout uses fixed `.container` without fluid fallback
- **File:** `views/layouts/main.php` (L48)
- **Description:** `<div class="container">` forces a fixed-width container at every breakpoint. No `.container-fluid` option, no responsive padding adaptation.
- **Recommendation:** Use `.container-fluid` or add a custom CSS class that switches between container/container-fluid based on screen width. Add responsive padding around content.

### L-03. Three layout variants are inconsistent
- **Files:** `views/layouts/left-menu.php` (L17-35), `views/layouts/right-menu.php` (L17-33), `views/layouts/top-menu.php` (L15-19)
- **Description:** `left-menu` and `right-menu` split 3/9 columns but `top-menu` uses full width with no top navigation rendering. Left-menu includes chevron icons; right-menu does not. These inconsistencies confuse users switching between layouts.
- **Recommendation:** Unify the menu structure across all layouts. If `top-menu` is intended for horizontal nav, render the menu items at the top. Ensure icon classes are consistent.

### L-04. Footer has no semantic HTML5 tag
- **File:** `views/layouts/main.php` (L52-56)
- **Description:** `<footer>` is present but uses `<p class="text-end mb-0">` with `Yii::powered()` which outputs "Powered by Yii2". No skip-to-content link, no aria-label.
- **Recommendation:** Add `role="contentinfo"` and `aria-label="Footer"`. Add a "Skip to main content" anchor link at the top of `<body>`.

### L-05. Fixed top navbar adds fixed padding but no scroll-spy or offset correction
- **File:** `views/layouts/main.php` (L31), `assets/main.css` (L2, L84-87)
- **Description:** `padding-top: 76px` on body and `60px` on mobile is a heuristic that may break if navbar height changes. Anchor links to sections will be hidden behind the fixed navbar.
- **Recommendation:** Use CSS `scroll-margin-top` on section elements or `:target { scroll-margin-top: 80px }`. Consider CSS `inset-top` on navbar instead of body padding.

### L-06. No content "card" or visual grouping for DetailView pages
- **Files:** `views/item/view.php` (L35-107), `views/menu/view.php` (L13-42), `views/rule/view.php` (L14-38), `views/user/view.php` (L16-57), `views/assignment/view.php` (L34-64)
- **Description:** All view pages render `<h1>`, action buttons, and `DetailView` / custom tables as flat content inside a named div. No card wrapper, no visual separation between sections.
- **Recommendation:** Wrap each view page's content sections in `<div class="card shadow-sm mb-3">`. Separate "Action buttons", "Details", and "Associations" into distinct cards.

### L-07. No loading state / skeleton for content areas
- **Files:** All index pages with Pjax (`views/assignment/index.php` L32, `views/menu/index.php` L23, `views/rule/index.php` L23)
- **Description:** Pjax loads content without any visual loading indicator. When data takes a moment to load, users see a blank/flash area with no feedback.
- **Recommendation:** Add a Bootstrap progress spinner or skeleton placeholder around the Pjax widget. Show a loading overlay during Pjax requests.

---

## 2. TYPOGRAPHY

### T-01. H1 elements lack visual hierarchy and font sizing
- **Files:** All index and view files (e.g., `views/item/index.php` L23, `views/menu/index.php` L16, `views/user/index.php` L16)
- **Description:** `<h1>` is used for every page title but Bootstrap's default h1 sizing (~2.5rem) is not adjusted. On smaller screens with `col-md-3` sidebar, the h1 text can overflow the sidebar width.
- **Recommendation:** Add `.fs-3` or `.text-primary` Bootstrap utility class to h1 elements. Add responsive font-size utilities (e.g., `class="fs-2 fs-md-3"`).

### T-02. No consistent font family configuration
- **Files:** `assets/main.css`
- **Description:** No `font-family` declared anywhere. The site relies on browser defaults / Bootstrap defaults, which can vary. No web font is loaded.
- **Recommendation:** Declare a consistent font stack in `body` (e.g., `font-family: 'Inter', system-ui, -apple-system, sans-serif;`) and load a Google Font or system font set.

### T-03. DetailView uses hardcoded inline style for label width
- **File:** `views/item/view.php` (L59)
- **Description:** `'template' => '<tr><th style="width:25%">{label}</th><td>{value}</td></tr>'` — inline style, not maintainable. 25% is too narrow for long label text like "Description".
- **Recommendation:** Move to CSS class. Use `min-width` instead of fixed `width`. Example: `class="label-cell"` with `min-width: 180px` in CSS.

### T-04. No text truncation for long values
- **Files:** `views/item/view.php` (L54-58), `views/user/view.php` (L46-55)
- **Description:** Long text in `DetailView` (e.g., `data:ntext`, `description:ntext`) renders full text without truncation, potentially breaking the two-column table layout.
- **Recommendation:** Apply `text-truncate` class or CSS `overflow: hidden; text-overflow: ellipsis;` with `max-width` constraints for long text fields.

---

## 3. COLORS & CONTRAST

### C-01. Body text on background may fail WCAG AA contrast
- **File:** `assets/main.css` (L3-4)
- **Description:** `body { background: #f5f7fb; color: #243247; }`. The contrast ratio of `#243247` on `#f5f7fb` is approximately **10.5:1** — this is actually fine (AA requires 4.5:1). However, the footer text `color: #65748b` on `#f5f7fb` has a ratio of ~**3.8:1**, which **fails** WCAG AA for normal text (<7px fails).
- **Recommendation:** Darken footer text to at least `#495057` (ratio ~5.1:1) or use `font-size: 125%` / `font-weight: bold` to qualify as large text.

### C-02. Action buttons rely solely on color (green/red) without patterns
- **Files:** All view files with buttons (e.g., `views/assignment/view.php` L47, `views/item/view.php` L38, `views/user/index.php` L51)
- **Description:** Success (green) and Danger (red) buttons use only color differentiation. Color-blind users may not distinguish them.
- **Recommendation:** Add `aria-label` to all buttons. Consider adding icon + text combination (e.g., `bi-trash` + "Delete") for redundant encoding. Use `btn-outline-danger` for secondary destructive actions.

### C-03. Navbar hover color may have insufficient contrast
- **File:** `assets/main.css` (L29-33)
- **Description:** Navbar hover state uses `background: #243653` with `color: #fff`. The contrast of white text on `#243653` is ~**11.2:1** — fine. But the base link color `#eaf0f8` on `#17243a` is only ~**11.1:1** — acceptable but marginal for very light text.
- **Recommendation:** No critical fix needed. Consider testing with a variety of display conditions.

---

## 4. SPACING & ALIGNMENT

### S-01. Assignment view has manual `<br>` tags for vertical spacing
- **File:** `views/assignment/view.php` (L45, L50)
- **Description:** Uses `<br><br>` for spacing between assign/remove buttons. This is fragile and not responsive.
- **Recommendation:** Replace with Bootstrap gap utilities: `class="d-flex flex-column gap-3"` on the parent div. Remove all `<br>` tags.

### S-02. No consistent padding/margin on page content sections
- **Files:** All index files, e.g., `views/item/index.php` (L22-51), `views/menu/index.php` (L14-46)
- **Description:** Content divs have no top/bottom padding. The `<h1>` touches the edge of its container. Tables sit flush against container edges.
- **Recommendation:** Add `p-3` or `p-4` padding to all content containers. Standardize spacing with a single CSS class like `.page-content`.

### S-03. Search + button input group lacks right-side padding
- **File:** `views/route/index.php` (L41-48)
- **Description:** The search input group has input + button but the text input's border may visually overlap the button border due to Bootstrap's default input-group behavior.
- **Recommendation:** Verify `input-group` rendering. Add `border-start-radius: 0` and `border-end-radius: 0` on the input, and appropriate border-radius on the button.

### S-04. Form fields lack consistent bottom spacing
- **Files:** `views/item/_form.php` (L31-38), `views/menu/_form.php` (L26-35)
- **Description:** `form->field()` calls are stacked without explicit spacing. Bootstrap's default margin may be inconsistent across browsers.
- **Recommendation:** Add `class="mb-3"` to each `field()` call via the form config: `'options' => ['class' => 'mb-3']` in `ActiveForm::begin()`.

### S-05. Footer bottom padding is excessive
- **File:** `assets/main.css` (L71)
- **Description:** `padding: 15px 0 30px` — the bottom padding of 30px creates a large dead zone at the bottom of the page with no content.
- **Recommendation:** Reduce to `padding: 10px 0 20px` for a more balanced footer.

---

## 5. ACCESSIBILITY (A11Y)

### A-01. No skip-to-content link
- **File:** `views/layouts/main.php` (L16-61)
- **Description:** Keyboard users must tab through the entire fixed navbar to reach the main content. No skip navigation link exists.
- **Recommendation:** Add `<a class="visually-hidden-focusable" href="#main-content">Skip to content</a>` as the first element after `<body>`, and add `id="main-content"` to the container div.

### A-02. ActionColumn buttons use icons without accessible text
- **Files:** `views/user/index.php` (L51), `views/route/index.php` (L44)
- **Description:** `<span class="bi bi-check"></span>` and `<i class="bi bi-arrow-repeat"></i>` are used inside `<a>` buttons without visible text labels. Screen readers will announce empty or meaningless links.
- **Recommendation:** Always include visible or `aria-label` text: `<a aria-label="Activate user">`. Better yet, use visible text like `<i class="bi bi-check"></i> Activate`.

### A-03. Missing `aria-label` and `title` on icon-only buttons
- **Files:** `views/assignment/view.php` (L46, L51), `views/item/view.php` (L87, L94)
- **Description:** Buttons use `aria-hidden="true"` on icons and `visually-hidden` spans, which is correct for icons, but the `title` attribute is set via `Yii::t()` which may not load consistently. No `aria-label` is explicitly set.
- **Recommendation:** Add explicit `aria-label="<?= Yii::t('rbac-admin', 'Assign') ?>"` to each icon-only button for full screen-reader support.

### A-04. Forms lack explicit `<label>` association
- **Files:** `views/user/_form.php` (L15), `views/user/login.php` (L20-21), `views/user/signup.php` (L20-23)
- **Description:** ActiveForm's `field()` helper generates labels, but `textInput()` and `passwordInput()` standalone calls may not. In `change-password.php` and `resetPassword.php`, the form markup relies on helper-generated labels without visible `<label>` tags.
- **Recommendation:** Ensure every input has a visible `<label for="...">`. Add `aria-required="true"` to required fields.

### A-05. Password fields lack visibility toggle
- **Files:** `views/user/login.php` (L21), `views/user/signup.php` (L22-23), `views/user/change-password.php` (L20-22), `views/user/resetPassword.php` (L20-21)
- **Description:** Password inputs have no "show/hide" toggle. Users cannot verify what they typed, increasing error rates and password resets.
- **Recommendation:** Add a Bootstrap input-group addon with a "show password" toggle button (eye icon) on every password field.

### A-06. No `role` or `aria-live` on dynamic content updates
- **Files:** `views/assignment/_script.js` (L3-L8), `views/item/_script.js` (L51-L52)
- **Description:** The `search()` function dynamically populates `<select>` elements with `<option>`s via JavaScript. Screen readers are not notified of these changes.
- **Recommendation:** Add `aria-live="polite"` to the search input so screen readers announce filtered results. Consider using an `<datalist>` or ARIA autocomplete pattern.

### A-07. Multi-select `<select>` without instructions
- **Files:** `views/assignment/view.php` (L41, L60), `views/item/view.php` (L82, L104), `views/route/index.php` (L49, L66)
- **Description:** Multi-select boxes with `size="20"` have no instruction text explaining how to select multiple items (Ctrl+Click / Cmd+Click). This is a significant UX problem.
- **Recommendation:** Add helper text below each select: `<small class="form-text text-muted">Hold Ctrl/Cmd to select multiple items</small>`. Consider replacing with a checkbox-based tag selector or dual-list component with drag-and-drop.

### A-08. Alert box has display:none but no ARIA hiding
- **File:** `views/route/index.php` (L25)
- **Description:** `<div id="route-alert" class="alert alert-danger" role="alert" style="display:none;">` is hidden via inline style but still in the DOM. When shown via JS, it gains focus but no announcement is made.
- **Recommendation:** Toggle `display:none` with `aria-hidden="true"` / `aria-hidden="false"`. Use `setTimeout` to blur after announcement, or use `toast` components for transient messages.

---

## 6. EMPTY STATES

### E-01. GridView has no empty data message
- **Files:** `views/item/index.php` (L28-49), `views/menu/index.php` (L25-43), `views/rule/index.php` (L24-36), `views/user/index.php` (L19-57)
- **Description:** When the data provider returns zero rows, the table renders empty without any instructional message. Users see a blank table and don't know if it's an error or intentional.
- **Recommendation:** Set `'emptyText' => 'No items found. Click "Create" to add one.'` and `'emptyTemplate' => '<div class="text-center p-4 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2"></i><p>No items found</p></div>'` on every GridView.

### E-02. Multi-select lists have no empty state indicator
- **Files:** `views/assignment/view.php` (L41-42, L60-61), `views/item/view.php` (L82, L104), `views/route/index.php` (L49, L66)
- **Description:** When no items are available or assigned, the `<select>` is completely empty. Users don't know why.
- **Recommendation:** Add a default option like `<option value="" disabled selected>No items available</option>` or show a descriptive message when both lists are empty.

### E-03. Search with no results shows nothing
- **Files:** `views/assignment/_script.js` (L31-52), `views/item/_script.js` (L51-72), `views/route/_script.js` (L92-102)
- **Description:** The JavaScript `search()` function filters options client-side. When a search yields zero matches, the select is simply emptied with no feedback.
- **Recommendation:** After filtering, if no options remain, insert a disabled `<option>No matches found</option>` or show a message below the search input.

---

## 7. RESPONSIVE DESIGN

### R-01. Sidebar layout breaks below 768px (no responsive class for sidebar toggle)
- **File:** `views/layouts/left-menu.php` (L17-34)
- **Description:** The `col-md-3` / `col-md-9` split pushes the sidebar above the content on mobile but the sidebar remains full-width without any collapsible behavior. Menu items with icons and labels become cramped.
- **Recommendation:** On mobile (`<768px`), collapse the sidebar into an offcanvas/drawer component or make it a toggleable accordion. Alternatively, use a vertical stacking order with proper spacing.

### R-02. The two-column form layout doesn't stack gracefully on all widths
- **Files:** `views/item/_form.php` (L29-39), `views/menu/_form.php` (L24-36)
- **Description:** The `col-md-6` / `col-md-6` split works on medium screens but on very small screens (<576px), the two columns stack and form fields may appear cramped without additional bottom margin.
- **Recommendation:** Add `g-3` (gap utility) on the row, and add explicit `mb-3` on each field. Consider `col-sm-12 col-md-6` for better mobile control.

### R-03. Multi-select column ratio (5:1:5) is too narrow on tablets
- **Files:** `views/assignment/view.php` (L37-63), `views/item/view.php` (L78-106)
- **Description:** The 5/1/5 column split on `col-md-*` leaves only `col-md-5` (~380px) for the select dropdowns on tablet (768-992px). The `size="20"` select elements are very tall and don't scale well.
- **Recommendation:** On tablet/mobile, stack the layout vertically: available list on top, buttons in the middle, assigned list on bottom. Use `col-12 col-md-5` for the selects and `col-12 col-md-1 text-center` for the button column.

### R-04. Fixed navbar height calculation may not handle all viewport sizes
- **File:** `assets/main.css` (L2, L84-87)
- **Description:** `padding-top: 76px` (desktop) and `60px` (mobile) are hardcoded. On devices with tall status bars (notches) or with rotated screens, these values may not align correctly.
- **Recommendation:** Use CSS `@supports (height: 100dvh)` for dynamic viewport height, or use CSS `position: sticky` + `margin-top` on the content instead of body padding.

---

## 8. FORM USABILITY

### F-01. No form validation feedback / inline error display customization
- **Files:** All `_form.php` files (e.g., `views/item/_form.php` L28, `views/menu/_form.php` L22)
- **Description:** ActiveForm default error rendering is basic. No custom validation summary, no real-time (on-input) validation, no character counters for maxlength fields (e.g., `name` maxlength=64 with no counter).
- **Recommendation:** Add `'validateOnSubmit' => true, 'validateOnChange' => true, 'validateOnType' => false` configuration. Add character counters to maxlength fields. Customize error summary with a prominent alert box.

### F-02. "Create" and "Update" buttons lack visual distinction
- **Files:** `views/item/_form.php` (L43-45), `views/menu/_form.php` (L41-42), `views/rule/_form.php` (L21-22)
- **Description:** Create uses `btn-success`, Update uses `btn-primary`. This is good. However, both pages use the same `_form.php` partial — so the button text depends on `$model->isNewRecord` but there's no clear header indicating "Create New X" vs "Edit X".
- **Recommendation:** Add an icon to the submit button: `<i class="bi bi-plus-lg"></i> Create` vs `<i class="bi bi-pencil"></i> Update`. Add a cancel button that returns to the list page.

### F-03. No cancel / back navigation on form pages
- **Files:** `views/item/create.php`, `views/item/update.php`, `views/menu/create.php`, `views/menu/update.php`, `views/rule/create.php`, `views/rule/update.php`
- **Description:** All form pages lack a "Cancel" or "Back" button. Users must use the browser back button or navigate manually.
- **Recommendation:** Add `<a href="<?= Url::to(['index']) ?>" class="btn btn-outline-secondary me-2"><i class="bi bi-x-lg"></i> Cancel</a>` before the submit button.

### F-04. Rule name autocomplete lacks keyboard navigation guidance
- **File:** `views/item/_form.php` (L18-24), `views/menu/_form.php` (L13-18)
- **Description:** jQuery UI Autocomplete is used for `rule_name` and `parent_name` fields but no placeholder hint or ARIA attributes guide the user. The autocomplete dropdown has no `aria-expanded` or `role="listbox"`.
- **Recommendation:** Add `aria-autocomplete="list"`, `aria-expanded="false"`, `role="combobox"` to autocomplete inputs. Add placeholder text like "Type to search rules...".

### F-05. Form submission has no loading/disabled state
- **Files:** All `_form.php` files
- **Description:** Users can double-click submit buttons, causing duplicate submissions. No button spinner or disabled state during POST.
- **Recommendation:** Add JavaScript to disable the submit button and show a spinner on form submit. Example: `$('#item-form').on('submit', function() { $('#submit-button').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...'); });`

### F-06. `status` field in user form uses plain text input
- **File:** `views/user/_form.php` (L15)
- **Description:** `$form->field($model, 'status')->textInput()` renders a free-text number input. Users could type anything. Should be a dropdown with labeled options.
- **Recommendation:** Replace with `$form->field($model, 'status')->dropDownList([10 => 'Active', 0 => 'Inactive'], ['class' => 'form-select'])`.

---

## Summary of Priority Recommendations

| Priority | Issue ID | Category | Quick Fix |
|---|---|---|---|
| **P0** | A-01 | Accessibility | Add skip-to-content link |
| **P0** | A-07 | Accessibility | Add "Ctrl+Click" instructions on multi-selects |
| **P0** | F-06 | Form Usability | Change status field to dropdown |
| **P1** | E-01 | Empty States | Add emptyText to all GridViews |
| **P1** | A-02/A-03 | Accessibility | Add aria-label to icon buttons |
| **P1** | A-05 | Accessibility | Add password visibility toggle |
| **P1** | F-03 | Form Usability | Add cancel/back button to all forms |
| **P2** | L-01/L-06 | Layout | Wrap content in card components |
| **P2** | C-01 | Colors | Darken footer text for contrast |
| **P2** | S-01 | Spacing | Replace `<br>` with flex gap |
| **P3** | R-01/R-03 | Responsive | Stack multi-select vertically on mobile |
| **P3** | T-02 | Typography | Add consistent font-family |
| **P3** | F-05 | Form Usability | Add submit-button disabled state |
| **P3** | E-03 | Empty States | Show "no matches" in search |

---

*This audit was performed by reading 32 PHP view files, 4 layout files, 3 CSS files, and 4 JavaScript files. No backend code was audited. The report focuses purely on presentation-layer, user-facing concerns.*
