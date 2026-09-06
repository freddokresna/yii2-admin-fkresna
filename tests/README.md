# Test (unit suite Codeception)

Konten di direktori ini adalah test-infra untuk modul ini (bukan app mandiri).
Suite yang aktif saat ini hanya **unit**; suite `functional`/`acceptance` gaya
codeception-v2 sudah dihapus (bergantung `yiisoft/yii2-codeception` yang
abandoned dan tidak jalan di Codeception ^5).

## Menjalankan suite

Dari root repository (bukan dari direktori ini):

```bash
vendor/bin/codecept run -c tests/codeception.yml unit
```

## Database test

Unit test memakai RBAC sungguhan (`yii\rbac\DbManager`), jadi butuh database:

- **Default: SQLite** — file `tests/runtime/mdm_admin_test.sqlite` (alias
  `@runtime/mdm_admin_test.sqlite`). Tabel RBAC (`auth_rule`, `auth_item`,
  `auth_item_child`, `auth_assignment`) dibuat ulang otomatis sebelum tiap test
  dari `vendor/yiisoft/yii2/rbac/migrations/schema-sqlite.sql` via
  `tests/codeception/unit/DbTestCase.php`. Tidak perlu provisioning apa pun.
- **Opsional: MySQL / PostgreSQL** — set env `MDM_ADMIN_TEST_DB=mysql` atau
  `pgsql`, lalu buat database kosong `mdm_admin_test`:
  `tests/codeception/bin/create-test-db.sh mysql` (kredensial root & user test
  lewat env: `DB_ROOT_PASS`, `TEST_DB_USER`, `TEST_DB_PASS`, ... — lihat header
  skrip; **jangan hardcode secret di repo**). Tabel RBAC tetap dibuat otomatis
  oleh test.
- Kredensial koneksi dioverride tanpa menyentuh `config/db.php` dengan membuat
  `config/db-local.php` (gitignored) yang mengubah `$databases` / `$driver`.

Konfigurasi suite: `codeception.yml`, `codeception/config/{config,unit,db}.php`,
bootstrap `codeception/unit/_bootstrap.php`.

## Cakupan test

| File | Menguji |
| --- | --- |
| `unit/models/ItemTest.php` | `mdm\admin\models\AuthItem`: validasi required & unik (role/permission satu namespace), save ke DbManager |
| `unit/models/RouteTest.php` | `mdm\admin\models\Route`: normalisasi nama permission (`/prefix`), daftar route modul admin dari scanner controller |
| `unit/components/HelperTest.php` | `mdm\admin\components\Helper::filter()`: filter menu rekursif berdasarkan route yang di-assign ke user |

`ItemTest` & `HelperTest` menurun dari `DbTestCase` (butuh DB test, default
sqlite); `RouteTest` murni tanpa DB.
