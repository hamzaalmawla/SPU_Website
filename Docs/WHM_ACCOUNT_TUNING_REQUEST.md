# WHM request — PHP-FPM capacity and OPcache for the SPU cPanel account

**Status:** ready to send to the hosting provider (WHM / root).  
**Prepared:** 7 September 2026  
**Account:** `spuedu`  
**In scope:** `v2.spu.edu.sy` (primary), with the same pool cap confirmed on `spu.edu.sy`  
**Out of scope:** application deploy, nginx full-page cache, credentials, database changes  

This is an account-configuration change. It does not ask for more hardware. The server already has spare CPU and memory; the cPanel account is throttled below what that hardware can serve.

---

## 1. What to ask for

Apply the following **in this order** on WHM, then return the evidence listed in §6.

| Step | Change | Why it is first / second |
|---|---|---|
| 1 | Install and enable `ea-php84-php-opcache` on the `v2.spu.edu.sy` PHP 8.4 runtime | Highest return. Without OPcache, every request re-parses the Laravel tree (~8,000 files, measured ~333 ms boot). |
| 2 | Bounded load check on `https://v2.spu.edu.sy/ar` (`ab -n 120 -c 4`) plus `free -m` | Confirms the box has headroom before more PHP processes are allowed. |
| 3 | Raise the PHP-FPM pool for `v2.spu.edu.sy` only, after step 2 passes | Removes the five-request concurrency ceiling and the 20-hit process recycle. |

Do **not** enable nginx `fastcgi_cache`, `proxy_cache`, or full-page caching for Laravel HTML, admin, preview, forms, or JSON.

---

## 2. Current values (read from the live account, 7 September 2026)

cPanel UAPI `LangPHP::php_get_vhost_versions` on account `spuedu`:

| Vhost | PHP | FPM | `pm.max_children` | `pm.max_requests` | `pm.process_idle_timeout` |
|---|---|---|---:|---:|---:|
| `v2.spu.edu.sy` | ea-php84 | on | **5** | **20** | **10** |
| `spu.edu.sy` | ea-php83 | on | 5 | 20 | 10 |
| `webmail.spu.edu.sy` | ea-php83 | on | 5 | 20 | 10 |
| `rooms.spu.edu.sy` | ea-php84 | on | 5 | 20 | 10 |

`v2` is not uniquely capped. The whole account uses the cPanel default of five children. v2 feels it first because it is a Laravel 12 site: each request occupies a worker for hundreds of milliseconds. Five concurrent PHP requests is the hard ceiling; the sixth waits.

`pm.max_requests = 20` makes this worse. A worker is destroyed every twenty hits, so the process almost never stays warm. Combined with missing OPcache, that is a cold framework boot on a large fraction of traffic.

The account user **cannot** change these numbers. `LangPHP::php_set_vhost_versions` accepts the parameters and returns success, then leaves `/var/cpanel/userdata/spuedu/*.php_fpm.yaml` untouched (root-owned). This has to be done in WHM.

---

## 3. Requested values (v2 only)

Candidate after the bounded check passes and memory headroom is reviewed:

```ini
pm.max_children = 16
pm.max_requests = 1000
pm.process_idle_timeout = 60
```

OPcache (web FPM runtime for `ea-php84`, not CLI-only):

```ini
opcache.enable=1
opcache.memory_consumption=192
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1
opcache.revalidate_freq=60
```

Leave `spu.edu.sy` on `ea-php83`. Do not move the live domain onto PHP 8.4 as part of this ticket.

If the memory review cannot support 16 children, apply the largest safe value **above 5** (for example 8 or 12) and set `pm.max_requests` to 500 or 1000. Do not leave `pm.max_requests` at 20.

---

## 4. Why this is safe to approve

Last host-level snapshot recorded for this machine:

| Measure | Value |
|---|---|
| CPU count | 96 |
| Server load | ~3.26 (~3% of capacity) |
| Memory used | ~40% |
| Swap used | ~0.65% |

The request is not “give this account more of the server”. It is “stop limiting this account to five PHP processes on hardware that is idle”.

Observed application effect at the previous measurement: median response moved from about **1.05 s to 7.9 s** at twelve concurrent requests. That is queueing on five workers, not application code.

---

## 5. WHM execution (provider)

### 5.1 OPcache

1. WHM → EasyApache 4 → PHP Extensions → install `ea-php84-php-opcache`.
2. Apply the ini values in §3 to the **FPM** runtime used by `v2.spu.edu.sy`.
3. Reload PHP-FPM for that vhost.
4. Prove OPcache from the **web** SAPI, not only `php -m` on CLI.

### 5.2 Bounded check (before raising children)

```bash
/usr/bin/ab -n 120 -c 4 -H 'Accept-Encoding: gzip' 'https://v2.spu.edu.sy/ar'
free -m
```

Record failed requests, latency, busy/idle FPM workers, and available memory. Proceed to 5.3 only if the application returns no 5xx and memory headroom remains acceptable.

### 5.3 PHP-FPM pool

1. WHM → MultiPHP Manager (or PHP-FPM Configuration) → vhost `v2.spu.edu.sy`.
2. Set the three pool values in §3.
3. Write the root-owned yaml (`/var/cpanel/userdata/spuedu/*.php_fpm.yaml`); do not rely on the cPanel user API.
4. Reload FPM.
5. Re-read effective values. A successful reload is not proof that cPanel stored the new numbers.
6. Repeat the `ab` command from 5.2 and keep before/after files.

### 5.4 Rollback

Restore the previous pool (`5` / `20` / `10`), reload FPM, and repeat the bounded check. OPcache can remain enabled if it was installed successfully.

---

## 6. Evidence to return with the ticket close-out

Please attach redacted output (no tokens, passwords, or cookies):

1. Effective FPM pool for `v2.spu.edu.sy` after the change (`pm.max_children`, `pm.max_requests`, `pm.process_idle_timeout`).
2. Web-runtime OPcache probe: loaded, enabled, `memory_consumption`, `max_accelerated_files`.
3. Before and after bounded-load files.
4. `free -m` after the after-test.
5. PHP-FPM reload / config-test result.

---

## 7. Copy-paste ticket (English)

**Subject:** WHM: raise PHP-FPM pool and enable OPcache for v2.spu.edu.sy (account spuedu)

We are preparing the Syrian Private University website migration on `v2.spu.edu.sy` (cPanel account `spuedu`). We need two account-level changes that require WHM/root. They do not require extra hardware.

Verified today (7 September 2026) via cPanel UAPI `LangPHP::php_get_vhost_versions`:

- `v2.spu.edu.sy` (ea-php84): `pm.max_children=5`, `pm.max_requests=20`, `pm.process_idle_timeout=10`
- The same five-child cap is also set on `spu.edu.sy`. v2 is a Laravel application, so the cap is visible there first: a sixth concurrent PHP request waits. Recycling a worker every 20 requests also keeps processes cold.
- cPanel user API `php_set_vhost_versions` accepts new pool values, returns success, and does not change the root-owned yaml. This cannot be done from the account.

Please:

1. Install `ea-php84-php-opcache` for the v2 PHP 8.4 FPM runtime and apply: `enable=1`, `memory_consumption=192`, `max_accelerated_files=20000`, `validate_timestamps=1`, `revalidate_freq=60`. Confirm from the web SAPI, not only CLI.
2. Run a bounded check: `ab -n 120 -c 4` against `https://v2.spu.edu.sy/ar`, plus `free -m`.
3. If memory headroom is acceptable, set the v2 pool to `pm.max_children=16`, `pm.max_requests=1000`, `pm.process_idle_timeout=60`, reload FPM, and re-read the effective values. If 16 is too high, use the largest safe value above 5 and still raise `pm.max_requests` off 20.
4. Do not enable nginx full-page / fastcgi / proxy cache for Laravel HTML.
5. Leave `spu.edu.sy` on ea-php83.

Please return the effective pool values, the web OPcache probe, and before/after load output. Rollback is restore 5/20/10 and reload FPM.

---

## 8. نص التذكرة (العربية)

**الموضوع:** WHM: رفع مجمع PHP-FPM وتفعيل OPcache للنطاق v2.spu.edu.sy (الحساب spuedu)

نعمل على ترحيل موقع الجامعة السورية الخاصة على `v2.spu.edu.sy` (حساب cPanel: `spuedu`). نحتاج تغييرين على مستوى الحساب يتطلبان صلاحية WHM/root، دون طلب عتاد إضافي.

التحقق بتاريخ 7 أيلول 2026 عبر واجهة cPanel:

- `v2.spu.edu.sy` (ea-php84): `pm.max_children=5` و `pm.max_requests=20` و `pm.process_idle_timeout=10`
- الحد نفسه (خمسة عمال) مضبوط أيضاً على الموقع الحالي `spu.edu.sy`. موقع v2 تطبيق Laravel، لذلك يظهر الاختناق فيه أولاً: الطلب السادس ينتظر. إعادة إنشاء العملية كل 20 طلباً تبقي العمال باردة.
- واجهة المستخدم في cPanel تقبل القيم الجديدة وتُظهر نجاحاً دون تعديل ملف yaml المملوك لـ root. لا يمكن تنفيذ ذلك من الحساب.

المطلوب:

1. تثبيت `ea-php84-php-opcache` على تشغيل FPM لـ PHP 8.4 الخاص بـ v2 بالقيم: `enable=1`، `memory_consumption=192`، `max_accelerated_files=20000`، `validate_timestamps=1`، `revalidate_freq=60`. التحقق من SAPI الويب وليس من CLI فقط.
2. اختبار محدود: `ab -n 120 -c 4` على `https://v2.spu.edu.sy/ar` مع `free -m`.
3. إذا كانت الذاكرة كافية: ضبط مجمع v2 إلى `pm.max_children=16` و `pm.max_requests=1000` و `pm.process_idle_timeout=60`، ثم إعادة تحميل FPM وقراءة القيم الفعلية. إذا تعذّر 16 فأكبر قيمة آمنة فوق 5 مع رفع `pm.max_requests` عن 20.
4. عدم تفعيل كاش صفحة كاملة / fastcgi / proxy في nginx لصفحات Laravel.
5. إبقاء `spu.edu.sy` على ea-php83.

يرجى إرفاق قيم المجمع الفعلية، وفحص OPcache من الويب، ونتائج الاختبار قبل/بعد. التراجع: إعادة 5/20/10 وإعادة تحميل FPM.
