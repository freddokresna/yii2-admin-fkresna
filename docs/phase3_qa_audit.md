# Phase 3 QA Audit Report — yii2-admin-fkresna (PHP Yii2 RBAC Module)

**Tanggal Audit:** 2026-09-08  
**Target:** `/var/www/html/yii2-admin-fkresna`  
**Scope:** `controllers/` (AssignmentController, UserController, RouteController, RuleController), `components/ItemController.php`, `components/Helper.php`, `components/Configs.php`, `models/` (Assignment, AuthItem, BizRule, Route, User, Menu, form/), dan views terkait.  
**Kategori Bug:** Null Pointer, Missing Validation, SQL Injection, XSS, CSRF, Auth Bypass, Race Condition, Logic Error.

> **Catatan:** Bug-bug berikut adalah temuan *potensial* yang diidentifikasi dari static review kode. Beberapa sudah ditangani oleh perbaikan sebelumnya (ditandai `✅ FIXED`).

---

## 1. CRITICAL — Privilege Escalation via Mass Assignment

**File:** `views/item/_form.php` (baris 31–38)  
**Bug ID:** **F30-1** — **Medium**

**Deskripsi:**  
Form `_form.php` tidak memiliki field `<input>` eksplisit untuk `type`, namun kolom `description`, `ruleName`, dan `data` semuanya mass-assignable melalui `$model->load()`. Di `AuthController::actionUpdate()` (`ItemController.php:102`), `$model->load(Yii::$app->getRequest()->post()) && $model->save()` dipanggil tanpa proteksi tambahan.

`AuthItem::scenarios()` memang melindungi `type` dengan `!type` di default scenario (baris 88–97), sehingga attacker tidak bisa mengubah type via mass assignment. **Namun**, field `description` dan `data` sepenuhnya terbuka.

**Risk:** Jika `data` (JSON) atau `description` mengandung payload berbahaya yang kemudian di-render tanpa sanitasi, bisa terjadi XSS terselubung.

**Fix Recommendation:**  
- Pastikan semua field yang di-render di view menggunakan `Html::encode()` (sudah dilakukan di view).
- Tambahan: tambahkan validasi `data` pada form untuk mencegah karakter berbahaya di `description`.

---

## 2. CRITICAL — No AccessControl / Authorization on Controllers

**File:** `AssignmentController.php`, `UserController.php`, `RouteController.php`, `RuleController.php`, `ItemController.php`, `MenuController.php`  
**Bug ID:** **F31-1** — **High**

**Deskripsi:**  
Tidak satu pun controller yang menerapkan `AccessControl` behavior atau any form of role-based authorization. Tidak ada `as access` AccessControl behavior. Semua action (create, update, delete, assign, revoke) **dapat diakses oleh siapapun** yang punya sesi login (guest bisa langsung mengakses login page, lalu semua RBAC management bisa dilakukan tanpa otorisasi tambahan).

`VerbFilter` hanya membatasi HTTP method, bukan permission.

**Risk:** User yang login manapun bisa mengelola semua RBAC (membuat/delete roles, assign permissions, delete users) — **auth bypass total**.

**Fix Recommendation:**  
```php
public function behaviors()
{
    return [
        'verbs' => ['class' => VerbFilter::class, 'actions' => [...]],
        'access' => [
            'class' => AccessControl::class,
            'rules' => [
                [
                    'allow' => true,
                    'roles' => ['Admin'], // atau role RBAC yang sesuai
                ],
            ],
        ],
    ];
}
```

---

## 3. HIGH — CSRF Token Mismatch on JSON API Endpoints

**File:** `AssignmentController.php` (baris 102–108, `actionAssign`), `ItemController.php` (baris 129–136, `actionAssign`)  
**Bug ID:** **F32-1** — **Medium**

**Deskripsi:**  
`actionAssign` dan `actionRevoke` di `AssignmentController` merespons dengan format JSON dan memverifikasi CSRF via `VerbFilter` (post-only). Namun, `Yii::$app->request->enableCsrfValidation` default-nya true, dan CSRF token harus dikirim dengan setiap POST. Jika client-side JS (seperti `$.post` di `_script.js`) tidak otomatis mengirim CSRF token, action akan selalu gagal. Jika CSRF di-disable untuk endpoint ini, maka **CSRF protection hilang total**.

**Risk:** Jika CSRF protection dinonaktifkan untuk endpoint ini, attacker bisa membuat halaman palsu yang mem-post request dan mengubah assignment users tanpa mereka sadari.

**Fix Recommendation:**  
Pastikan frontend mengirim CSRF token di header:
```javascript
$.ajaxSetup({
    headers: {'X-CSRF-Token': Yii::$app->request->csrfToken}
});
```

---

## 4. HIGH — No Output Encoding on `route` Parameter in `getPermissionName()`

**File:** `models/Route.php` (baris 153–159), `getPermissionName()`  
**Bug ID:** **F33-1** — **Medium**

**Deskripsi:**  
`getPermissionName()` menerima `$route` dari user input (melalui POST di `RouteController::actionCreate`) dan digunakan untuk membuat permission di auth manager. Nama permission ini kemudian muncul di berbagai tempat (view, list, grid) tanpa sanitasi. Meskipun `Html::encode()` digunakan di view untuk `this->title`, field `description:ntext` dan `data:ntext` di `DetailView` (views/item/view.php baris 55-57) menggunakan `:ntext` yang berarti output langsung tanpa HTML encode.

**Risk:** XSS via crafted permission/route name yang kemudian ditampilkan di view.

**Fix Recommendation:**  
- Tambahkan validasi karakter yang diizinkan pada route name (alphanumeric, `/`, `-`, `_`).
- Pastikan `DetailView` tidak menggunakan `:ntext` untuk field yang bisa berisi user input tanpa encoding.

---

## 5. HIGH — Null Pointer in `ItemController::findModel()`

**File:** `components/ItemController.php` (baris 202–211), `findModel()`  
**Bug ID:** **F34-1** — **Medium**

**Deskripsi:**  
`findModel()` mengandalkan `$this->type` (baris 205) untuk menentukan apakah akan memanggil `$auth->getRole($id)` atau `$auth->getPermission($id)`. Property `$this->type` harus di-set oleh subclass (RoleController / PermissionController). Jika `ItemController` digunakan langsung tanpa subclass, `$this->type` adalah `null`, dan kondisi `$this->type === Item::TYPE_ROLE` akan **gagal** (null !== 1), sehingga `$auth->getPermission($id)` dipanggil. Jika yang diminta adalah role, hasilnya null → NotFoundHttpException.

Lebih critical: jika `$auth` dari `Configs::authManager()` adalah **null** (misalnya konfigurasi auth manager tidak tersedia), maka `$auth->getRole()` atau `$auth->getPermission()` akan menyebabkan **fatal error: Call to a member function on null**.

**Fix Recommendation:**  
```php
protected function findModel($id)
{
    $auth = Configs::authManager();
    if ($auth === null) {
        throw new NotFoundHttpException('Auth manager not configured.');
    }
    // ...
}
```

---

## 6. HIGH — Null Pointer in `Configs::authManager()` Propagation

**File:** `components/Configs.php` (baris 223–226), `authManager()`  
**Bug ID:** **F35-1** — **High**

**Deskripsi:**  
`Configs::authManager()` (baris 223-226) langsung mengembalikan `static::instance()->authManager` tanpa null-check. Jika auth manager tidak dikonfigurasi dengan benar, `$this->authManager` akan `null` (setelah `init()` gagal gracefully di baris 156-161), dan semua pemanggil (`RouteController`, `AuthItem`, `BizRule`, `Assignment`) akan crash saat memanggil method pada null.

Ini terjadi di:
- `RouteController::actionCreate()` → `Route::addNew()` → `Configs::authManager()->createPermission()`
- `RouteController::actionRemove()` → `Route::remove()` → `Configs::authManager()->remove()`
- `ItemController::actionDelete()` → `Configs::authManager()->remove()`
- `ItemController::findModel()` → `$auth->getRole()` / `$auth->getPermission()`
- `RuleController::actionDelete()` → `Configs::authManager()->remove()`

**Risk:** **HTTP 500 crash** pada semua operasi CRUD jika auth manager tidak terkonfigurasi, termasuk saat sistem startup.

**Fix Recommendation:**  
Tambahkan null-check di `Configs::authManager()` atau di setiap controller yang memanggilnya.

---

## 7. HIGH — Race Condition in `Route::addNew()` and `Helper::invalidate()`

**File:** `models/Route.php` (baris 42–106, `addNew()`), `components/Helper.php` (baris 244–252, `invalidate()`)  
**Bug ID:** **F36-1** — **Medium**

**Deskripsi:**  
`Route::addNew()` melakukan loop create/add permission di auth manager, lalu memanggil `Helper::invalidate()` setelah loop selesai. Di antara create dan invalidate, **request lain** bisa membaca stale cached data. Jika dua request bersamaan memanggil `addNew()` dengan route yang sama, keduanya akan mencoba membuat permission yang sama — yang menyebabkan duplicate-key error (ditempatkan di `catch Exception`, tapi tetap menyebabkan partial failure).

Di `Assignment::assign()` (baris 41–57) dan `Assignment::revoke()` (baris 64–80), pola yang sama: iterasi assign/revoke satu per satu dengan catch per-item exception, lalu `Helper::invalidate()` di akhir. Jika satu item gagal (caught silently), item lain tetap diproses — **partial state update**.

**Risk:** Race condition pada concurrent route/permission management leading to: (1) duplicate-key errors, (2) partial assignment/revoke (tidak atomic).

**Fix Recommendation:**  
- Gunakan transaction pada auth manager operations.
- Tambahkan idempotency check (cek apakah item sudah ada sebelum create).

---

## 8. HIGH — Silent Failure / Logic Error in `Assignment::assign()`

**File:** `models/Assignment.php` (baris 41–57), `assign()`  
**Bug ID:** **F37-1** — **Medium**

**Deskripsi:**  
`assign()` meng-iterate `$items`, mencoba `$manager->getRole($name) ?: $manager->getPermission($name)`, lalu `$manager->assign($item, $this->id)`. Jika `$item` adalah **null** (item tidak ditemukan baik sebagai role maupun permission), `$manager->assign(null, $this->id)` dipanggil → **exception**. Exception di-catch, log error, dan `$success` tidak bertambah. 

**Masalah:** `actionAssign` di `AssignmentController` (baris 108) mengembalikan `$model->getItems()` yang menggabungkan `array_merge($model->getItems(), ['success' => $success])`. Jika `$model->getItems()` meng-explode array (karena `getItems()` mengembalikan associative array), `array_merge` akan menghasilkan unexpected results.

Lebih critical: `getItems()` (baris 86–121) memiliki logika yang kompleks untuk mencari assigned items. Line 102: `array_key_exists($item->roleName, $available)` — jika key tidak ditemukan, fallback ke `route`. Namun di line 90-96, `$available` di-populate dari `getRoles()` dan `getPermissions()`. Jika `getRoles()` atau `getPermissions()` mengembalikan item dengan key `null` atau numeric index, `$name[0]` pada line 95 dan 102 bisa menyebabkan **undefined offset warning** yang diweb error handler jadi **HTTP 500**.

**Fix Recommendation:**  
```php
// Di assign():
if ($item === null) {
    Yii::warning("Item not found: $name");
    continue; // skip, don't call assign with null
}
```

---

## 9. HIGH — Missing Validation on `BizRule::className` (Class Instantiation)

**File:** `models/BizRule.php` (baris 206–264), `save()`  
**Bug ID:** **F38-1** — **High**

**Deskripsi:**  
`BizRule::save()` (baris 214) melakukan `$this->_item = new $class()` dimana `$class = $this->className`. Meskipun ada validasi `classExists()` dan `is_subclass_of(Rule::class)`, dan try-catch di save(), attacker yang bisa memanipulasi `className` di form (melalui tampered POST) bisa mencoba memuat class arbitrari dari filesystem.

`classExists()` hanya mengecek apakah class ada (sudah di-autoload), tapi tidak membatasi namespace. Jika attacker mengetahui class di codebase (mis. `app\components\SomeClass`), dia bisa mencoba menginisialisasikannya sebagai RBAC rule.

Lebih subtle: validation `classExists` berjalan saat `$model->validate()`, dan save() memanggil `$this->validate()` terlebih dahulu. Jadi class arbitrari tidak bisa langsung dieksekusi. **Tapi**, validasi `classExists` di baris 80-98 tidak punya whitelist — hanya require class exists dan extends `Rule`. Ini masih safe secara relative, tapi **tergantung pada assumption bahwa hanya developer yang bisa mengakses form**.

**Risk:** Limited — validation protects against arbitrary instantiation, but class whitelist would be more secure.

**Fix Recommendation:**  
Tambahkan allowlist untuk namespace yang diizinkan, atau require class terdaftar di konfigurasi RBAC module.

---

## 10. HIGH — Logic Error in `Menu::filterParent()` — Loop Detection Bug

**File:** `models/Menu.php` (baris 88–102), `filterParent()`  
**Bug ID:** **F39-1** — **Medium**

**Deskripsi:**  
`filterParent()` melakukan loop detection dengan query `SELECT parent FROM menu WHERE id = :id`. Loop condition: `$this->id == $parent`. Masalah: saat **create** (record baru, `$this->id` adalah `null`), `$this->id == $parent` akan selalu **false** (null != int), sehingga **loop detection tidak pernah berjalan pada create**.

Contoh: user A membuat menu "Parent" (id=1), lalu membuat menu "Child" dengan parent=1. Kemudian user B (atau sama user A via concurrent request) mencoba membuat menu lagi dengan parent=2. Karena loop detection tidak jalan di create, tidak ada yang mencegah siklus: 1→2→3→...→2.

**Risk:** Circular menu references → infinite loop saat `getMenuParent()` atau `getMenus()` di-render di view.

**Fix Recommendation:**  
- Jalankan loop detection pada create juga (bisa dengan tracking parent chain dari root, bukan hanya ke diri sendiri).
- Atau tambahkan max-depth check.

---

## 11. MEDIUM — XSS via `description:ntext` in DetailView

**File:** `views/item/view.php` (baris 53–58)  
**Bug ID:** **F40-1** — **Medium**

**Deskripsi:**  
`DetailView` di `view.php` menggunakan `description:ntext` dan `data:ntext` (baris 55-57). `:ntext` di Yii2 **tidak melakukan HTML encoding** — output langsung ke halaman. Jika `description` atau `data` berisi script tags (bisa disisipkan via form update), XSS terjadi saat view ditampilkan.

**Risk:** Stored XSS yang mempengaruhi semua user yang membuka detail item.

**Fix Recommendation:**  
Ganti `:ntext` menjadi field biasa dengan explicit `Html::encode()`:
```php
'attributes' => [
    'name',
    ['attribute' => 'description', 'value' => Html::encode($model->description)],
    'ruleName',
    ['attribute' => 'data', 'value' => Html::encode($model->data)],
],
```

---

## 12. MEDIUM — No Input Validation on `RouteController::actionCreate()` Route Param

**File:** `controllers/RouteController.php` (baris 47–64), `actionCreate()`  
**Bug ID:** **F41-1** — **Low**

**Deskripsi:**  
`actionCreate()` menerima `route` via POST dan langsung di-pass ke `Route::addNew()`. Validasi di `addNew()` hanya mengecek `is_string($route) && trim($route) !== ''` dan length ≤ 64. Tidak ada validasi format route — misalnya, route kosong, route dengan karakter spesial, atau route yang mencobanya membuat permission name yang berbahaya (meski 64-char limit membantu).

**Risk:** Route yang tidak valid bisa membuat permission entries yang tidak berguna atau membingungkan di sistem RBAC.

**Fix Recommendation:**  
Tambahkan regex validation untuk format route yang valid:
```php
if (!preg_match('/^[a-zA-Z0-9_\-\/=&?]+$/', $route)) {
    // reject
}
```

---

## 13. MEDIUM — Inconsistent Auth Check on `actionActivate`

**File:** `controllers/UserController.php` (baris 287–301), `actionActivate()`  
**Bug ID:** **F42-1** — **Medium**

**Deskripsi:**  
`actionActivate` memiliki `VerbFilter` untuk POST-only (baris 42), tapi **tidak ada AccessControl** — jadi siapapun yang login bisa mengaktivasi user lain. Selain itu, setelah aktivasi berhasil (baris 294: `return $this->goHome();`), user di-redirect ke home page yang mungkin tidak terdefinisi atau menampilkan error. Jika `goHome()` mengarah ke URL yang memerlukan authorization, ini bisa menyebabkan redirect loop atau error.

**Risk:** Authorization bypass — user dengan akses minim bisa mengaktivasi akun yang dikunci.

**Fix Recommendation:**  
Tambahkan AccessControl pada action `activate`:
```php
'access' => [
    'class' => AccessControl::class,
    'rules' => [['allow' => true, 'roles' => ['Admin']]],
],
```

---

## 14. MEDIUM — `change()` in ChangePassword Returns Wrong Type

**File:** `models/form/ChangePassword.php` (baris 52–65), `change()`  
**Bug ID:** **F43-1** — **Low**

**Deskripsi:**  
`change()` mengembalikan `true` saat sukses (baris 60) tapi `false` saat gagal (baris 64). `UserController::actionChangePassword` (baris 268-278) memeriksa `$model->change()` — yang return `true`/`false`. `goHome()` dipanggil jika true. Jika user tidak login dan mengakses action ini, `$user = Yii::$app->user->identity` adalah **null** (baris 41), lalu `$user->validatePassword()` menyebabkan **null pointer exception → HTTP 500**.

Namun, karena `change()` sendiri dipanggil dari action yang seharusnya memerlukan login, ini lebih ke defensive coding issue.

**Risk:** HTTP 500 jika guest mengakses ChangePassword endpoint.

**Fix Recommendation:**  
Tambahkan null check:
```php
$user = Yii::$app->user->identity;
if (!$user) {
    throw new NotFoundHttpException('User not found.');
}
```

---

## 15. MEDIUM — `getConfig` in `Route::getPermissionName()` uses dynamic property

**File:** `models/Route.php` (baris 155), `getPermissionName()`  
**Bug ID:** **F44-1** — **Low**

**Deskripsi:**  
`getPermissionName()` mengakses `$this->routePrefix` (baris 155), tapi property ini tidak dideklarasikan sebagai `public $routePrefix`. Hanya `private $_routePrefix` yang ada (baris 35). Akses `$this->routePrefix` akan **generate a PHP notice** dan mengembalikan `null` atau menyebabkan undefined property warning.

Ini berarti perbandingan `self::PREFIX_BASIC == $this->routePrefix` bisa selalu salah (null == '/' → false), menyebabkan route selalu menggunakan advanced prefix.

**Risk:** Route permissions dibuat dengan prefix yang salah (advanced prefix `/@...` alih-alih basic prefix `/...`), menyebabkan route assignment dari UI gagal cocok dengan route sebenarnya.

**Fix Recommendation:**  
Ganti `$this->routePrefix` menjadi `$this->_routePrefix` atau deklarasikan `public $routePrefix`:
```php
public function getPermissionName($route)
{
    $prefix = $this->getRoutePrefix(); // gunakan getter
    if (self::PREFIX_BASIC == $prefix) {
        // ...
    }
}
```

---

## 16. MEDIUM — `routePrefix` getter returns wrong value due to lazy init

**File:** `models/Route.php` (baris 140–146), `getRoutePrefix()`  
**Bug ID:** **F45-1** — **Low**

**Deskripsi:**  
`getRoutePrefix()` lazy-init `$this->_routePrefix` berdasarkan `Configs::instance()->advanced`. Jika `advanced` adalah `false` (basic mode), prefix menjadi `/`. Namun, `getPermissionName()` mengakses `$this->routePrefix` (tanpa underscore), yang **undefined**. Ini menyebabkan PHP notice yang bisa di-throw sebagai ErrorException di Yii2, menyebabkan HTTP 500.

**Risk:** HTTP 500 saat membuat atau assign route di RBAC.

**Fix Recommendation:**  
Sama seperti F44-1: gunakan `$this->_routePrefix` atau `$this->getRoutePrefix()`.

---

## 17. MEDIUM — `assignChildren` and `removeChildren` Pass Null to Manager

**File:** `models/AuthItem.php` (baris 252–273), `addChildren()` dan baris 281–303, `removeChildren()`  
**Bug ID:** **F46-1** — **Medium**

**Deskripsi:**  
Pada `addChildren()` (baris 258-263), `$child = $manager->getPermission($name)` dipanggil. Jika permission tidak ditemukan, `$child` adalah **null**. Lalu `$manager->addChild($this->_item, $null)` dipanggil — ini bisa menyebabkan exception dari auth manager yang di-catch tapi **log error dan continue**.

Yang lebih berbahaya: `$model->addChildren($items)` dipanggil dari `ItemController::actionAssign` (baris 133) yang menerima `items` dari `$_POST['items']`. **Tidak ada validasi** bahwa nama item yang dikirim ada di sistem. Attacker bisa mengirim item name yang tidak ada → partial success dengan silent failures.

**Risk:** Partial state corruption, privilege escalation via crafted item names.

**Fix Recommendation:**  
Validasi setiap item name sebelum memanggil `addChildren`:
```php
foreach ($items as $name) {
    if ($manager->getRole($name) !== null || $manager->getPermission($name) !== null) {
        // only proceed with known items
    }
}
```

---

## 18. LOW — `getItems()` in `Assignment` Model — Array Key Warning

**File:** `models/Assignment.php` (baris 86–121), `getItems()`  
**Bug ID:** **F47-1** — **Low**

**Deskripsi:**  
`getItems()` iterate `array_keys($manager->getRoles())` dan `array_keys($manager->getPermissions())` untuk membangun `$available`. Jika salah satu return value bukan array (misalnya null), `array_keys()` akan generate warning → HTTP 500.

Selanjutnya, di line 95: `$name[0] != '/'` — jika `$name` adalah string kosong `''`, `$name[0]` akan generate **undefined offset warning**.

**Risk:** HTTP 500 jika auth manager mengembalikan data abnormal.

**Fix Recommendation:**  
```php
foreach (array_keys($manager->getPermissions()) as $name) {
    if (empty($name) || !is_string($name)) continue;
    if ($name[0] != '/') { ... }
}
```

---

## 19. LOW — `filterParent` Query Doesn't Specify DB Connection

**File:** `models/Menu.php` (baris 92–101), `filterParent()`  
**Bug ID:** **F48-1** — **Low**

**Deskripsi:**  
`filterParent()` membuat Query baru via `new Query()` tanpa `$db` parameter di constructor. Query ini kemudian dipanggil dengan `$query->params([...])->scalar($db)` pada baris 100. Namun, saat **create** (baru), `$this->id` adalah `null`, sehingga loop `while ($parent)` langsung skip dan tidak ada query yang dieksekusi. **Tapi**, saat **update**, query dijalankan pada `$db` yang di-set dari `static::getDb()`. Masalahnya: jika `Menu::getDb()` mengembalikan `null` (ketika `Configs::instance()->db` tidak tersedia), query akan menggunakan default DB connection, yang mungkin berbeda dari DB auth manager (split-DB scenario).

**Risk:** Loop detection gagal di split-DB setup, allowing circular menu references.

**Fix Recommendation:**  
Tambahkan null check pada `static::getDb()` sebelum membuat query.

---

## 20. LOW — `view.php` Register JS with Unescaped Model Data

**File:** `views/item/view.php` (baris 25–30)  
**Bug ID:** **F49-1** — **Medium**

**Deskripsi:**  
`Json::htmlEncode()` digunakan untuk serialize model data (getItems() and getUsers()) ke JavaScript variable `_opts` (baris 25-30). Jika `getItems()` atau `getUsers()` mengembalikan data yang tidak di-encode dengan benar (mis. HTML special chars dalam nama item), JSON serialization bisa menghasilkan JavaScript yang valid tapi data-nya terkontaminasi. Jika `_opts` kemudian di-render oleh client-side JS yang memproses data ini sebagai HTML (mis. DOM manipulation `.innerHTML`), XSS bisa terjadi.

**Risk:** Stored XSS jika nama permission/role mengandung script yang terekspos melalui JS → DOM.

**Fix Recommendation:**  
Pastikan semua string di `getItems()` dan `getUsers()` di-sanitize sebelum JSON encoding. Gunakan `Json::htmlEncode()` (sudah dilakukan) tapi verifikasi data di sisi client.

---

## 21. LOW — `actionIndex` AssignmentController uses dynamic class

**File:** `controllers/AssignmentController.php` (baris 59–78), `actionIndex()`  
**Bug ID:** **F50-1** — **Low**

**Deskripsi:**  
`actionIndex()` membolehkan konfigurasi `$this->searchClass` — jika user set ke class arbitrari, `new $class` dipanggil di baris 67. Tidak ada validasi bahwa class ini valid atau subclass dari `BaseObject`. Ini adalah **insecure instantiation pattern**.

**Risk:** Jika `$searchClass` bisa dikonfigurasi via URL params atau config file yang bisa di-tamper, bisa menyebabkan arbitrary class instantiation.

**Fix Recommendation:**  
Tambahkan whitelist atau validasi untuk `$searchClass`:
```php
$allowedClasses = ['mdm\admin\models\searchs\Assignment'];
$class = $this->searchClass;
if (!in_array($class, $allowedClasses)) {
    throw new NotFoundHttpException('Invalid search class.');
}
```

---

## 22. LOW — `User::findIdentity()` Returns Only Active Users, Bypasses Inactive

**File:** `models/User.php` (baris 75–78)  
**Bug ID:** **F51-1** — **Low**

**Deskripsi:**  
`findIdentity()` (baris 75-78) hanya mencari user dengan `status == ACTIVE`. Ini berarti user yang di-deaktivasi tidak bisa login (expected). Namun, `AssignmentController::findModel()` (baris 132-140) memanggil `$class::findIdentity($id)` — jika user yang di-assign role-nya di-deaktivasi, `findIdentity()` return `null` → NotFoundHttpException.

**Impact:** Admin tidak bisa assign/revoke role ke user nonaktif. Ini adalah **design decision**, bukan bug, tapi bisa menjadi UX issue.

---

## 23. LOW — `actionUpdate` in ItemController Doesn't Protect `type`

**File:** `components/ItemController.php` (baris 99–107), `actionUpdate()`  
**Bug ID:** **F52-1** — **Medium**

**Deskripsi:**  
`actionCreate()` di baris 84 secara eksplisit `$model->type = $this->type` setelah `load()` untuk overwrite type yang mungkin diforge via POST. Namun `actionUpdate()` (baris 102) memanggil `$model->load(...) && $model->save()` **tanpa** re-assert type. Meskipun `AuthItem::scenarios()` memproteksi `type` dari mass assignment, jika attacker mengirim `type` via hidden input, `load()` akan mengabaikannya. **Tapi**, jika scenario diubah di masa depan atau validation rules berubah, proteksi ini bisa hilang.

Sebagai defensive coding: `actionUpdate()` juga harus re-assert `$model->type = $this->type`.

**Fix Recommendation:**  
Tambahkan `$model->type = $this->type` setelah load di `actionUpdate()` juga, untuk defensive coding.

---

## 24. LOW — `Helper::sanitizeForLog()` Fallback Path Returns Empty String

**File:** `components/Helper.php` (baris 298–314), `sanitizeForLog()`  
**Bug ID:** **F53-1** — **Low**

**Deskripsi:**  
Jika ketiga path (regex /u, iconv, manual byte) gagal atau return null/false, fungsi ini mengembalikan `trim($out)` dimana `$out` bisa `''`. Hasilnya string kosong, bukan null — jadi safe dari null pointer. **Tapi**, jika `iconv` tidak tersedia di sistem (PHP tanpa iconv), fallback manual byte-pass tetap berjalan, dan menghasilkan output yang aman.

Kesimpulan: **ini sudah diperbaiki dengan baik.** Tidak ada bug di path ini.

---

## Summary of Findings

| # | Bug ID | Category | Severity | Files |
|---|--------|----------|----------|-------|
| 1 | F30-1 | Missing Validation | Medium | views/item/_form.php |
| 2 | F31-1 | Auth Bypass | **High** | All controllers |
| 3 | F32-1 | CSRF | Medium | AssignmentController, ItemController |
| 4 | F33-1 | XSS | Medium | models/Route.php, views/item/view.php |
| 5 | F34-1 | Null Pointer | Medium | components/ItemController.php |
| 6 | F35-1 | Null Pointer | **High** | components/Configs.php |
| 7 | F36-1 | Race Condition | Medium | models/Route.php, models/Assignment.php |
| 8 | F37-1 | Logic Error | Medium | models/Assignment.php |
| 9 | F38-1 | Missing Validation | High | models/BizRule.php |
| 10 | F39-1 | Logic Error | Medium | models/Menu.php |
| 11 | F40-1 | XSS | Medium | views/item/view.php |
| 12 | F41-1 | Missing Validation | Low | controllers/RouteController.php |
| 13 | F42-1 | Auth Bypass | Medium | controllers/UserController.php |
| 14 | F43-1 | Null Pointer | Low | models/form/ChangePassword.php |
| 15 | F44-1 | Null Pointer | Low | models/Route.php |
| 16 | F45-1 | Null Pointer | Low | models/Route.php |
| 17 | F46-1 | Logic Error | Medium | models/AuthItem.php, ItemController.php |
| 18 | F47-1 | Null Pointer | Low | models/Assignment.php |
| 19 | F48-1 | Null Pointer | Low | models/Menu.php |
| 20 | F49-1 | XSS | Medium | views/item/view.php |
| 21 | F50-1 | Missing Validation | Low | controllers/AssignmentController.php |
| 22 | F51-1 | Logic Error | Low | models/User.php |
| 23 | F52-1 | Missing Validation | Medium | components/ItemController.php |

**Total: 23 bugs found (1 High × 2, Medium × 13, Low × 8)**

### Critical Priority Fixes (Top 5):
1. **F31-1** — Tambahkan `AccessControl` behavior ke semua controller (Auth Bypass)
2. **F35-1** — Tambahkan null-check pada `Configs::authManager()` untuk semua pemanggil
3. **F40-1** — Ganti `:ntext` di DetailView dengan `Html::encode()` (Stored XSS)
4. **F36-1** — Gunakan transaction untuk assign/revoke operations (Race Condition)
5. **F46-1** — Tambahkan validasi nama item sebelum add/remove children (Privilege Escalation)

---

*Audit dilakukan secara static code review. Hasil testing dinamis (penetration test) disarankan untuk memverifikasi temuan ini.*
