# Part 0 — Run the app, and see how it works

Before you test something, use it. Fifteen minutes with the thing under test tells you
what the tests are *about*, and by the end of this part you will have found the bug the
rest of the afternoon is built around — with no test at all, just your hands. Then you
will know where in the code it lives.

## A — Bring it up

```
docker compose watch
```

This is how you start every app in this course. It builds the image if it has to (instant
if you ran this before class), starts the `web` service, and then **keeps watching your
files**: save an edit under `src/` or `public/` and Compose copies it into the running
container. The terminal prints:

```
Watch enabled
```

and then goes quiet. Apache is up and waiting — the same server as Homework 1, with PHP
inside it. No MySQL this time: the database is one SQLite file.

Open <http://localhost:8080>. The terminal answers:

```
web-1  | 192.168.65.1 - - [08/Sep/2026:09:02:11 +0000] "GET / HTTP/1.1" 200 2670 "-" "Mozilla/5.0 ..."
```

One line per request: who asked, when, for what, and the status code. Keep this terminal
where you can see it, and **open a second terminal** in the same folder for everything
else today — the test commands in Parts 1–4 all run beside this one.

Two things to know about watch, both from Homework 1:

- `Ctrl+C` stops *watching*. The container keeps running. `docker compose down` stops it.
- Edit `public/index.php` and the terminal says `Syncing service "web"`. Edit
  `composer.json` or `docker/apache.conf` and it says `Rebuilding` instead, because those
  are baked into the image. That distinction — *synced in* versus *built in* — is the
  whole of `docker-compose.yml`'s `develop.watch` block. Read it.

## B — Use it like a person

Open `ANSWERS.md` beside the browser. Question 1 asks for **behaviours that do not seem
quite right** — write them down as you find them, not from memory afterwards. There are
more than one.

Do these, in order, and watch the table:

1. **Add a product.** Name `Milk`, best before a week from today. It appears with `0` in
   stock and a `fresh` badge.
2. **Add two more.** `Eggs` with a date **yesterday**. `Rice` with **no date**. Look at the
   three badges: `fresh`, `expired`, `no date`. Something decided those three words.
3. **Buy some.** In the Milk row, type `4` in the Buy box, click **Buy**. `4`.
4. **Use some.** Type `1.5` in the Use box, click **Use**. `2.5`.
5. **Do something wrong.** Add a product with an empty name. Add `milk` again. Buy `-2` of
   something. Each time, a red message at the top says what was wrong. Read them; you will
   see the same sentences again in Part 1.
6. **Now use more than you have.** Rice has 0. Use `3`.

Look at the Rice row.

**Minus three.** No red message. The shelf holds a negative amount of rice, and the app is
fine with it. Nobody wrote the rule *you cannot use more than you have*, so nothing enforces
it.

7. **Now try to buy 1 rice.** Red message: *Stock cannot start below zero.* The app *does*
   have a rule about negative stock — it just checks at the wrong moment, when the row is
   loaded, not when it is changed. One missing rule, two symptoms: the number that should
   never have been written, and a product you can no longer touch. Bad data, once in, poisons
   everything after it.

Keep this in mind — you will see it as a missing test in Part 1, write the test for it
in Part 2, watch an API test fail on it in Part 3, and see it as a missing error message in
Part 4.

Each time you clicked a button, the browser sent a `POST`, the server changed the database,
and then **redirected** you back to `/`. Press refresh: nothing resubmits. Open your
browser's developer tools (F12, Network tab) and click Buy once more — you will see the
`POST` answered with a `303` and then a `GET /`. Your watch terminal shows the same two
lines.

## C — The same data, as JSON

Open <http://localhost:8080/api/products>.

```json
[
    {
        "id": 2,
        "name": "Eggs",
        "amount": 0,
        "best_before": "2026-09-07",
        "status": "expired"
    },
    ...
```

Same products, same amounts, same three status words — no HTML. This is the **API**: the
page is one client of it, and a test can be another. Try
<http://localhost:8080/api/products/1> and then <http://localhost:8080/api/products/999>.
Note the second one is a `404` with a JSON body, not an HTML error page.

The full contract — everything the page can do, a program can do with JSON:

| Method and path | Body | Returns | On error |
|---|---|---|---|
| `GET /api/health` | | `200` `{"ok": true}` | |
| `GET /api/products` | | `200` list of products, each with a `status` word | |
| `POST /api/products` | `{"name": "Milk", "best_before": "2026-12-01"}` | `201` the new product | `422` empty or duplicate name, bad date |
| `GET /api/products/{id}` | | `200` one product | `404` |
| `POST /api/products/{id}/purchase` | `{"amount": 6}` | `200` the updated product | `404`, `422` bad amount |
| `POST /api/products/{id}/consume` | `{"amount": 2}` | `200` the updated product | `404`, `422` bad amount — *and, after Part 2, not enough in stock* |

Errors are always `{"error": "a sentence"}`. Status codes are the contract: `2xx` it
worked, `404` no such thing, `422` I understood you but refuse. The API tests in Part 3
assert on exactly these, and Grocy's API in Homework 2 has the same shape.

`status` is not stored. It is computed on the way out from `best_before` and today's date.
*Store facts; compute opinions.*

The API also accepts `POST`, which a browser address bar cannot send. To see one now, from
your second terminal:

```
docker compose exec -T web curl -s -X POST localhost/api/products/1/consume -d amount=100
```

`exec web` runs the command *inside* the running container, where `curl` is installed and
the server is on `localhost` — so this line is identical in PowerShell, Git Bash and a Mac
terminal, with nothing to quote. (`-T` means "no terminal needed"; without it Git Bash
complains.) The API accepts a plain form body like `amount=100` as well as JSON; the tests
send JSON. Back comes `200` and a product with a large negative amount. The bug is in the
API too, of course — it is the same code.

## D — How it is put together

Now that you know what it does, look at how little there is:

```
 browser / test ──HTTP──▶  Apache  ──▶  public/index.php  ──▶  src/Pantry.php  ──▶  src/Stock.php
                          (web server)   (front controller)     (use cases)          src/DueDate.php
                                                                     │                (the rules)
                                                                     ▼
                                                              src/Repository.php ──▶ /app/data/pantry.sqlite
                                                              (all the SQL)          (one file, on a volume)
```

| Layer | File | Job | Knows about |
|---|---|---|---|
| **Web** | `public/index.php` | Turn a URL + method into a call on `Pantry`; turn the result (or the exception) into HTML or JSON | HTTP, forms, JSON, status codes |
| **Use cases** | `src/Pantry.php` | What the app can *do*: list, get, create, purchase, consume. Validates input. | `Repository`, `Stock`, `DueDate` |
| **Rules** | `src/Stock.php`, `src/DueDate.php` | The arithmetic and the decisions. No I/O of any kind. | Nothing. Numbers and strings in, numbers and strings out. |
| **Storage** | `src/Repository.php` | Every line of SQL in the project | PDO and the one table |

The arrows only point one way. `Stock` does not know a database exists. `Repository` does
not know what a valid amount is. `index.php` does no arithmetic. That separation is not
tidiness for its own sake — it is *why the unit tests can run in five milliseconds with no
server*, and why one fix in `Stock::consume()` will repair the page and the API at once.

Every file is commented to teach the PHP in it. Read them in this order: `Stock.php` →
`DueDate.php` → `Pantry.php` → `Repository.php` → `index.php`.

**The web server.** Apache, with PHP running inside it as a module — the same arrangement
as Homework 1, installed from Ubuntu's packages this time so it can share one image with
Playwright. The whole configuration is `docker/apache.conf`, and the line that matters is:

```
FallbackResource /index.php
```

`DocumentRoot` is `public/`. A request for a file that exists there is served as-is. A
request for anything else — `/`, `/products/3/consume`, `/api/products` — falls back to
`index.php`. So **one file receives every request**. That is a *front controller*, and
every PHP framework you will meet (Laravel, Symfony, Slim) is built around exactly this
idea. Homework 1's Apache served each `.php` file by its own URL; that works until you want
URLs like `/products/3/consume` that do not correspond to a file — which is the moment every
app grows a front controller.

**`index.php`, top to bottom.**

1. **Boot.** `declare(strict_types=1)`, load Composer's autoloader, build one `Pantry`.
2. **Read the request.** `$_SERVER['REQUEST_METHOD']` is `GET` or `POST`; `$_SERVER['REQUEST_URI']` is the path.
3. **If the path starts with `/api`**: set the JSON content type, decode the JSON body, then a chain of `if`s — *this method + this path → call this on `$pantry` → `respond()`*. Exceptions are caught at the bottom and become `404` or `422`. Every branch `exit`s, so the first match wins.
4. **Otherwise, if it is a `POST`**: a form was submitted. Call the same `$pantry` method the API would, then **redirect** to `/` with a `303`. Errors redirect to `/?error=...`. This is *POST-redirect-GET*; you saw it in the Network tab.
5. **Otherwise it is `GET /`**: gather `$products` and `$error`, then fall out of PHP into the HTML at the bottom, which loops over `$products` and prints the table. Every value goes through `e()`, which is `htmlspecialchars()`, so a product named `<script>` is displayed, not run.

**One click, end to end.** You type `2` in Rice's *Use* box and press **Use**.

1. The browser sends `POST /products/3/consume` with the body `amount=2`.
2. Apache finds no file at that path and falls back to `index.php`. Not `/api`; it is a `POST`; the regex matches and captures `3` and `consume`.
3. `$pantry->consume(3, 2.0)`: `Repository::find(3)` reads the row, `new Stock(0.0)`, `->consume(2.0)` — and, as shipped, this happily produces `-2.0`. `Repository::setAmount(3, -2.0)` writes it back.
4. `redirect('/')` sends a `303`. The browser makes a fresh `GET /`.
5. `index.php` runs again from the top and prints the table with Rice at `-2`.

After Part 2, step 3 throws `InsufficientStockException` instead. The `catch` in the form
section turns it into `redirect('/?error=Not+enough+in+stock')` and step 5 prints the red
message. Through the API, the same throw becomes a `422`. Two front doors, one rule, one
place it lives.

Open `src/Stock.php` now. Twenty lines of logic under the comments. `consume()` subtracts and
never checks. That is the whole bug, and it sits in the one class that the page and the API
both go through. **Do not fix it yet.** Part 2 has you write the test first.

## E — Where the data is: SQLite

SQLite is a complete SQL database **in a single file**, with no server process. The
database *is* `/app/data/pantry.sqlite` inside the container. Copy the file and you have
copied the database.

| | MySQL (Homework 1) | SQLite (here) |
|---|---|---|
| What it is | a server process, its own container, port 3306 | a file the app opens directly |
| Connection string | `mysql:host=db;dbname=app` + user + password | `sqlite:/app/data/pantry.sqlite` |
| Several apps at once | yes, that is the point | one writer at a time |
| Good for | anything shared, anything big | one app, tests, prototypes, phones (it is on yours) |

Everything else — `SELECT`, `INSERT`, `prepare()`/`execute()`, `fetchAll()` — is the same,
because PHP talks to both through **PDO**. `Repository.php` would need one line changed to
run on MySQL. The whole schema is one table, created on first use:

```sql
CREATE TABLE IF NOT EXISTS products (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT    NOT NULL UNIQUE,
    amount      REAL    NOT NULL DEFAULT 0,
    best_before TEXT    NULL
)
```

Where does that file live? Not in this folder. `Ctrl+C` your watch terminal, then
`docker compose down`, then `docker compose watch` again. Your products are still there. Run:

```
docker volume ls
```

`pantry_pantry_data` is a **named volume**, the same mechanism Homework 1 uses for MySQL.
Homework 1 question 3 applies here word for word: `down` keeps it, `down -v` deletes it.
(Why a volume and not a folder on your machine? Apache's workers run as `www-data`, and a
folder bind-mounted from your laptop is owned by you. The week-2 guestbook had the same
problem and the same fix.)

To look inside without any extra tools, while the app is up:

```
docker compose exec -T web php -r "print_r((new PDO('sqlite:/app/data/pantry.sqlite'))->query('SELECT * FROM products')->fetchAll(PDO::FETCH_ASSOC));"
```

That is the same call `Repository::all()` makes, typed by hand.

**The tests never touch this file.** `playwright.config.js` starts Apache on a *different*
one, `/tmp/pantry-test.sqlite`, deleted before every run. Which file is in use is decided
by the `PANTRY_DB` environment variable and nothing else. Your Milk and Eggs are safe, and
the tests always begin from an empty pantry.

## F — What is deliberately not here

No framework, no router library, no ORM, no sessions, no users, no CSS framework, no
JavaScript. Each of those is a real thing you will add later in the semester, and each one
replaces something you can point at in this codebase. When Laravel appears, you will
recognise `index.php` (routes), `Pantry` (a service, or a controller's guts), `Repository`
(Eloquent), and `Stock` (a plain model). The shape is the same; the machinery is bigger.

Leave the app running. Parts 1–4 use their own containers and do not need it, but you will
want to click things.

---

## Key learnings

- **Three verbs, all semester.** `docker compose watch` runs the app and syncs your edits in; `run --rm` runs a one-shot job; `down` stops it and `down -v` wipes its data. `Ctrl+C` only stops the watching.
- **One file answers every URL.** Apache's `FallbackResource /index.php` sends anything that is not a real file to the front controller. Every PHP framework is built on this.
- **Two front doors, one set of rules.** The HTML page and the JSON API both call `Pantry`, which calls `Stock`. Fix a rule in one class and both doors get it.
- **The layers point one way.** `Stock` does not know a database exists; `Repository` does not know what a valid amount is. That is why unit tests need no server.
- **Status codes are the contract.** `2xx` it worked, `404` no such thing, `422` I understood you but refuse. A client can act on the number without reading the message.
- **SQLite is a file; MySQL is a server.** Same SQL, same PDO calls, one connection string apart. The file lives on a named volume, like Homework 1's database.
- **Bad data poisons what follows.** One missing check let rice go to minus three, and then nothing could touch rice at all. Rules belong at the moment of change.
