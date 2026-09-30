# UTILITY.KS — E-commerce + Admin Dashboard (PHP + MySQL)

Dyqan i plotë me admin. Gjithçka ruhet në database: produktet, çmimet, stoku, fotot, kategoritë, klientët, porositë, zbritjet, reviews dhe përmbajtja e homepage.

## Kërkesat
- PHP 8.1 ose më i ri (me `pdo_mysql`, `gd` jo e detyrueshme)
- MySQL ose MariaDB
- Apache me `mod_rewrite` (çdo shared hosting / cPanel e ka)

---

## A) Provë në kompjuterin tënd (XAMPP)
1. Instalo XAMPP, hap **Apache** dhe **MySQL**.
2. Kopjo folderin `utility-ks` te `C:\xampp\htdocs\`.
3. Hap `http://localhost/utility-ks/install`
4. Databaza: host `localhost`, user `root`, fjalëkalim bosh, emri p.sh. `utility_ks` (krijohet vetë).
5. Zgjidh email + fjalëkalim për adminin, kliko **INSTALO**.
6. Admin: `http://localhost/utility-ks/admin/login`

> Kjo punon vetëm kur XAMPP është ndezur. Klientët nuk mund ta hapin localhost-in tënd.

## B) Online — që klientët të hyjnë kurdo me link (REKOMANDOHET)
Duhet **hosting** (server që është gjithmonë ndezur) dhe **domain** (emri, p.sh. `utility-ks.com`).

1. Bli hosting me **PHP 8.1+ dhe MySQL** (shared hosting me cPanel mjafton fare) dhe lidhe domain-in me të.
2. Në cPanel → **MySQL Databases**: krijo një databazë, një user dhe jepi user-it qasje (All Privileges). Shëno emrin, user-in, fjalëkalimin.
3. Në cPanel → **File Manager** → folderi `public_html`: ngarko `utility-ks.zip` dhe bëj **Extract**. Skedarët duhet të jenë drejtpërdrejt në `public_html` (jo në nënfolder), që dyqani të hapet në `https://domain-i-yt.com`.
4. Hap `https://domain-i-yt.com/install`, vendos të dhënat e databazës nga hapi 2, pastaj emailin/fjalëkalimin e adminit.
5. Aktivizo **SSL (HTTPS)** në cPanel (Let's Encrypt / AutoSSL) — falas te shumica e hosting-eve.
6. Hyr te `https://domain-i-yt.com/admin/login`.

Pas instalimit, skedari `config.php` krijohet automatikisht. Mos e ndaj me askënd.

---

## Ku menaxhohet çka (Admin)
| Duhet të bësh | Ku |
|---|---|
| Shtosh / editosh / fshish / dublikosh produkte, foto, stok, çmime | **Products** |
| Shohësh porositë dhe ndryshosh statusin | **Orders** |
| Shohësh klientët dhe historikun | **Customers** |
| Ndryshosh stokun shpejt | **Inventory** |
| Kategori dhe nënkategori | **Categories** |
| Kode zbritjeje (p.sh. UTILITY10) | **Discounts** |
| Miratosh ose fshish reviews | **Reviews** |
| Hero, banner-a, titujt e seksioneve | **Homepage** |
| Raporte shitjesh | **Analytics** |
| Transporti, emri i dyqanit, fjalëkalimi | **Settings** |

## Si funksionon logjika
- **Stoku** ulet automatikisht kur klienti bën porosi. Nëse porosia **anulohet** nga admini, stoku kthehet.
- **Total Sales** llogaritet nga porositë reale (porositë e anuluara nuk numërohen).
- Produkti me stok 0 ose status *Out of Stock* shfaqet "SOLD OUT" dhe nuk mund të blihet.
- **Low stock**: produktet me stok më të vogël se "Low Stock Threshold" (parazgjedhje 5).
- **Featured** → homepage; **NEW** → New Arrivals; **Best Sellers** = badge BEST SELLER ose produkte me X shitje (Settings).
- Produktet në **Draft** nuk shfaqen te klientët.

## Të rëndësishme
- **Pagesa:** tani ekziston vetëm *pagesa në dorëzim*. Pagesa me kartë online (Stripe, etj.) nuk është përfshirë.
- **Email:** sistemi nuk dërgon email automatik (konfirmime). Porositë i shikon te Admin → Orders.
- **Backup:** bëj eksport të databazës (phpMyAdmin → Export) dhe kopje të folderit `uploads/` rregullisht.
- **Siguria:** përdor fjalëkalim të fortë admini; hyrja bllokohet 5 minuta pas 5 përpjekjeve të dështuara.
- Nëse fotot nuk ngarkohen, kontrollo që folderi `uploads/` të jetë i shkrueshëm (chmod 755 ose 775).
- Për të riinstaluar nga zero: fshi `config.php` dhe tabelat e databazës.
