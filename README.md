RBAC Admin for Yii 2
====================

Admin module untuk mengelola [RBAC Yii 2](https://www.yiiframework.com/doc/guide/2.0/en/security-authorization) melalui antarmuka web. Modul ini menyediakan pengelolaan user, assignment, role, permission, route, rule, dan menu.

Repository ini adalah package `freddokresna/yii2-admin-fkresna`. Namespace PHP tetap `mdm\admin` agar kompatibel dengan konfigurasi dan kode yang sudah menggunakan ekstensi `mdmsoft/yii2-admin`.

## Persyaratan

- PHP `>= 8.4` (diuji pada PHP `8.5`)
- Yii Framework `^2.0.55`
- Database dan komponen `db` Yii yang aktif
- Komponen `authManager` Yii (`yii\rbac\DbManager` atau `yii\rbac\PhpManager`)
- `yiisoft/yii2-bootstrap5` `^2.0.51`
- `twbs/bootstrap-icons` `^1.13`

## Instalasi

Pasang menggunakan Composer:

```bash
composer require freddokresna/yii2-admin-fkresna
```

Package ini otomatis menggunakan PSR-4 namespace `mdm\admin`. Tidak perlu menambahkan alias secara manual saat dipasang dengan Composer.

## Konfigurasi

Tambahkan modul dan RBAC manager ke konfigurasi aplikasi, misalnya `config/web.php`:

```php
return [
    'modules' => [
        'admin' => [
            'class' => 'mdm\admin\Module',
            // Pilihan: left-menu, right-menu, top-menu, atau null.
            'layout' => 'left-menu',
        ],
    ],
    'components' => [
        'authManager' => [
            'class' => 'mdm\admin\components\DbManager',
        ],
    ],
];
```

`yii\rbac\DbManager` juga dapat digunakan. `mdm\admin\components\DbManager` merupakan implementasi yang mewarisi `yii\rbac\DbManager`.

Jika aplikasi belum memiliki access control, tambahkan behavior berikut pada konfigurasi aplikasi atau controller/module yang sesuai. Sesuaikan `allowActions` dengan route publik aplikasi:

```php
'as access' => [
    'class' => 'mdm\admin\components\AccessControl',
    'allowActions' => [
        'site/login',
        'site/error',
    ],
],
```

## Migrasi database

Untuk menyimpan RBAC pada database, jalankan migrasi bawaan Yii:

```bash
php yii migrate --migrationPath=@yii/rbac/migrations
```

Migrasi modul membuat tabel `menu` dan tabel `user` (jika fitur user management digunakan). Jalankan:

```bash
php yii migrate --migrationPath=@mdm/admin/migrations
```

Secara default tabel yang digunakan adalah `{{%menu}}` dan `{{%user}}`. Koneksi, nama tabel, cache, status user default, dan opsi lain dapat diubah melalui parameter aplikasi:

```php
'params' => [
    'mdm.admin.configs' => [
        'db' => 'db',
        'userDb' => 'db',
        'menuTable' => '{{%menu}}',
        'userTable' => '{{%user}}',
        'defaultUserStatus' => 10, // 0 = inactive, 10 = active
    ],
],
```

## Akses halaman admin

Dengan route standar Yii, halaman modul tersedia di:

- `/index.php?r=admin` — assignment (halaman awal)
- `/index.php?r=admin/user` — user management, jika tabel user tersedia
- `/index.php?r=admin/assignment` — assignment user
- `/index.php?r=admin/role` — role
- `/index.php?r=admin/permission` — permission
- `/index.php?r=admin/route` — route
- `/index.php?r=admin/rule` — rule
- `/index.php?r=admin/menu` — menu, jika tabel menu tersedia

Jika menggunakan pretty URL, route yang sama dapat diakses sebagai `/admin`, `/admin/role`, dan seterusnya.

## Keamanan (SECURITY)

> **Penting:** `Helper::filter()`, `Helper::filterActionColumn()`, dan `MenuHelper::getAssignedMenu()` **bukan kontrol akses**. Ketiganya hanya menyembunyikan/memfilter elemen UI (menu, tombol) berdasarkan hasil pengecekan route. Endpoint di balik elemen tersebut tetap dapat diakses langsung melalui URL oleh siapa pun yang mengetahui route-nya — menyembunyikan menu tidak pernah menggantikan penegakan akses di sisi server.

Semua endpoint (termasuk halaman modul admin ini) **WAJIB** dilindungi dengan memasang behavior `as access` pada konfigurasi aplikasi, modul, atau controller yang bersangkutan:

```php
'as access' => [
    'class' => 'mdm\admin\components\AccessControl',
    'allowActions' => [
        'site/login',   // route publik — sesuaikan dengan aplikasi Anda
        'site/error',
    ],
],
```

Tanpa `as access`, tidak ada komponen yang memeriksa izin RBAC, sehingga halaman admin dan route lain terbuka untuk siapa pun yang sudah login (atau bahkan tamu, tergantung konfigurasi).

### Peringatan opsi `onlyRegisteredRoute`

Opsi `mdm.admin.configs.onlyRegisteredRoute` (default `false`) mengubah perilaku AccessControl secara signifikan:

- Jika `true`, hanya route yang **terdaftar** pada tabel auth item/route yang diperiksa. Route yang **tidak terdaftar — termasuk route yang belum sempat di-scan/ditambahkan — dianggap SAH dan otomatis diizinkan (allow-by-default)**. Endpoint sensitif yang baru ditambahkan bisa langsung diakses tanpa izin apa pun selama route-nya belum terdaftar.
- Jika `false` (default dan **disarankan**), route yang tidak terdaftar **ditolak** oleh AccessControl kecuali permission-nya diberikan secara eksplisit; route publik cukup didaftarkan pada `allowActions`.

Jangan mengaktifkan `onlyRegisteredRoute` hanya untuk menghindari repot mendaftarkan route — akibatnya adalah celah akses yang tidak terlihat. Pertahankan `false` dan daftarkan permission/route secara eksplisit.

## Penyesuaian user model

Controller assignment dapat disesuaikan melalui `controllerMap`:

```php
'modules' => [
    'admin' => [
        'class' => 'mdm\admin\Module',
        'controllerMap' => [
            'assignment' => [
                'class' => 'mdm\admin\controllers\AssignmentController',
                'userClassName' => 'app\models\User',
                'idField' => 'id',
                'usernameField' => 'username',
                'fullnameField' => 'profile.full_name',
            ],
        ],
    ],
],
```

Fitur user management bawaan menggunakan `mdm\admin\models\User`. Jika aplikasi memiliki user model sendiri, atur `identityClass` pada komponen `user` dan gunakan `userClassName`/field yang sesuai. Detail `extraColumns`, `searchClass`, layout, menu, dan penggunaan `MenuHelper` tersedia di dokumentasi.

## Dokumentasi

- [Konfigurasi](docs/guide/configuration.md)
- [Penggunaan dasar](docs/guide/basic-usage.md)
- [User management](docs/guide/user-management.md)
- [Menu](docs/guide/using-menu.md)
- [Change log](CHANGELOG.md)
- [Panduan otorisasi Yii](https://www.yiiframework.com/doc/guide/2.0/en/security-authorization)

## Menjalankan test

Dependensi pengujian sudah didefinisikan pada `require-dev`. Dari root repository, jalankan:

```bash
composer install
vendor/bin/codecept run -c tests/codeception.yml unit
```

Suite unit memakai `yii\rbac\DbManager` sungguhan (validasi `AuthItem` + save,
filter menu `Helper::filter`) sehingga butuh database — tetapi **default-nya
SQLite** (`@runtime/mdm_admin_test.sqlite`, tabel RBAC dibuat ulang otomatis
oleh test), jadi perintah di atas langsung hijau di lingkungan bersih **tanpa
server database apa pun**. Untuk memakai MySQL/PostgreSQL lokal atau di CI,
set env `MDM_ADMIN_TEST_DB=mysql` (atau `pgsql`), buat database-nya dengan
`tests/codeception/bin/create-test-db.sh` (kredensial dibaca dari env, tidak
ada secret di-hardcode di repo), lalu jalankan perintah yang sama. Kredensial
koneksi dapat dioverride lewat `tests/codeception/config/db-local.php`
(gitignored). Detail di `tests/README.md`.

> Catatan (2026-09): suite `functional` & `acceptance` gaya codeception-v2 yang
> bergantung `yiisoft/yii2-codeception` (abandoned) telah dihapus karena tidak
> dapat dijalankan pada codeception ^5. Uji perilaku web/RBAC sebaiknya
> dilakukan lewat unit test dengan `Yii::$app` + mock, atau migration host app.

## Lisensi

BSD-3-Clause. Lihat [LICENSE](LICENSE).
