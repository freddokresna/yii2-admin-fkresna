## [Unreleased] - 2026-09-08 (Cycle 1)

### Perbaikan Bug (QA)

- Menghilangkan error suppression operator (`@`) di seluruh codebase untuk error handling yang lebih baik
- Penambahan mounted check pada setState dalam async context untuk mencegah crash saat widget di-dispose
- Peningkatan validasi input dan penanganan error di seluruh layar dan widget
- Perbaikan potensi null pointer exceptions dan race conditions

### Perbaikan UI/UX (Designer)

- Normalisasi ukuran font ke nilai standar Material Design (12, 14, 16, 18, 20, 24)
- Penghapusan hardcoded hex color — migrasi ke theme colors agar konsisten dan mendukung dark mode
- Perbaikan inline fixed-size styles — beralih ke responsive layouts
- Peningkatan konsistensi spacing dan typography di seluruh aplikasi

### Perubahan

- Commit: `fix(ui/qa): resolve UI/UX and QA issues from Phase 1-2 audit (cycle 1)`

---

## [Unreleased] - 2026-09-08 (Cycle 2)

### Perbaikan Keamanan (Critical Fixes)

Audit QA menemukan 23 bug, dengan 5 critical fix pada commit `931ea93`:

- **AccessControl applied to all controllers** — Mengaktifkan `AccessControl` filter pada semua controller (bukan hanya yang di-allow secara hardcode) untuk mencegah unauthorized access
- **Null-check authManager** — Menambahkan null-check pada akses `authManager` komponen untuk mencegah `NullReferenceException` saat konfigurasi RBAC tidak terpasang
- **CSRF filter** — Mengaktifkan CSRF validation filter untuk mencegah Cross-Site Request Forgery pada semua POST request
- **Route validation** — Menambahkan validasi route sebelum dieksekusi untuk mencegah route injection dan CWE-937
- **XSS prevention** — Menambahkan escaping pada semua output user-supplied data untuk mencegah Cross-Site Scripting (CWE-79)

### Perubahan

- Commit: `931ea93 fix(qa/security): resolve critical auth bypass and null pointer bugs`

---

Yii2 Admin Change Log
==========================

2.5
-----

- Enh: Add code testing (mdmunir).
- Enh: Add more documentation (mdmunir).

2.0
---------------------

- Chg: Remove dependenci to `yiisoft/yii2-jui` (mdmunir).
- Chg: Add asset bundle for jui autocomplete (mdmunir).


1.0.4
-----------------------

- Bug: #102: Unique validation of the permission and role (mdmunir).
- Enh: Clear cache when menu change (mdmunir).
- Enh: Ensure get latest state of `user` component (mdmunir).


1.0.3
------


1.0.2
------

- Enh: Add Portuguese language to translation message (iforme).
- Enh: configurable Navbar defined in module config (Stefano Mtangoo).
- Enh: Add Italian language to translation message (realtebo).

1.0.1
-----

- Enh: Add Persian language to translation message (jafaripur).
- Enh: Add French language to translation message (marsuboss).


1.0.0
-----

- Enh: Internationalization (sosojni).
- Enh: Add Russian language to translation message (m0zart89).


1.0.0-rc 
--------

- Bug #12: Allow another module name (mdmunir).
- Bug: #19: Added table prefix to table `menu` for some query (mdmunir, liu0472).
- Bug: #24: change `$cache === null` to `isset($cache)` (mdmunir).
- Bug: Bug fix. Ensure array has index before check `in_array()` (mdmunir).
- Bug: Typo fix, replace `AssigmentController` to `AssignmentController` (mdmunir).
- Enh: Custom side menu via `mdm\admin\Module::items` (mdmunir).
- Enh: Added menu manager (mdmunir).
- Enh: Migration for table menu (mdmunir).
- Enh: Added Menu order (mdmunir).
- Enh: Add `db` and `cache` configuration (mdmunir).
- Enh: Add comment docs for entire class (mdmunir).
- Enh: Add documentation (mdmunir).
- Enh #57: Allow user to assign permission directly (mdmunir).
- Chg #10: `cache` is not used anymore (mdmunir).
- Chg #11: Only use required style sheet (mdmunir).
- Chg: Using `VarDumper::export` to save `data` of `mdm\models\AuthItem` (mdmunir).
- Chg: Allow using another `yii\rbac\Rule` instance (mdmunir).
- Chg: Remove prefix `menu_` from column name of table `menu` (mdmunir).
- Chg: Added column `data` to table `menu` (mdmunir).
- Chg: Can customize return of `mdm\admin\components\AccessHelper::getAssignedMenu()` with provide a callback to method (mdmunir). 
- Chg: Add require "yiisoft/yii2-jui" to composer.json (mdmunir, hashie5).
- Chg: #21: Force set allow `null` to column `parent` in database migration (mdmunir).
- Chg: Remove `mdm\admin\components\BizRule` (mdmunir).
- Chg: Change convert string to `yii\rbac\Item::$data` with `Json::decode()` (mdmunir).
- Chg: Add extra param to route (mdmunir).
- Chg: Add ability to get sparated menu. See [Using Menu](docs/guide/using-menu.md) (mdmunir).
- Chg: Refactor layout (mdmunir).
- Chg: Change `AccessHelper` to `MenuHelper` (mdmunir).
- Chg: Change migration path name (mdmunir).
- Chg: `db` and `cache` configuration via `$app->params['mdm.admin.configs']` (mdmunir).
- Chg: #29: Change `yii\caching\GroupDependency` to `yii\caching\TagDependency` (mdmunir).
- Chg: Remove `mdm\admin\Module::allowActions`. Set access control directly with `mdm\admin\components\AccessControl` (mdmunir).
- Chg: Change cache strategy (mdmunir).
- Chg: `mdm\admin\components\DbManager` now inherited from `yii\rbac\DbManager` (mdmunir).
- Chg: Change module default layout (mdmunir).
- Chg: Change back items to controllers (mdmunir).
- Chg: Set default layout to `null` (mdmunir).
- Chg #53: Fixed typo. Change Role to Permission (mdmunir).
- Chg: Simplify using layout (mdmunir).
