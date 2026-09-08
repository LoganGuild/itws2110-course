# Week 3 — Testing one small app, three ways

This is **Pantry**: a very small Grocy. Products, how much is on the shelf, when it goes
off. You can buy some and use some. It has a web page and a JSON API, and the rules live
in a few plain PHP classes.

It exists to be tested. Tuesday's class is five parts, in order, all in this directory:

| | | |
|---|---|---|
| 0 | [Run the app, and see how it works](0-run-the-app.md) | `docker compose watch` · click around · the API · the four layers · SQLite · find the bug by hand |
| 1 | [Read the PHPUnit tests](1-phpunit.md) | what a test says, what it does when you run it, what it does when the code is wrong |
| 2 | [Write a prompt for the test you want](2-prompt.md) | one rule has no test. Specify it well enough that an AI writes the test, then prove the test works |
| 3 | [API tests](3-api.md) | the same rules, over HTTP, no browser |
| 4 | [Browser tests with Playwright](4-playwright.md) | the same rules, as a person sees them |

One rule — *you cannot use more than you have* — runs through all five. You will find it
broken by hand, then meet it as a missing unit test, a failing API test, and a message on
a page.

## One image

Everything runs from **one Docker image**: Apache with PHP 8.3 (the same pairing as
Homework 1), PHPUnit 11, Node, Playwright 1.62 and Chromium, all in the same container.
Nobody installs PHP or Node. The `docker-compose.yml` gives that image four names, one per
job:

```
docker compose watch                  the app, for you, at http://localhost:8080 -- and live edits
docker compose run --rm unit          PHPUnit     tests/unit   (every test printed as a sentence)
docker compose run --rm api           Playwright  tests/api    (no browser)
docker compose run --rm e2e           Playwright  tests/e2e    (Chromium)
docker compose down                   stop        (-v wipes the data too)
```

The three verbs of the semester: **watch** for the thing that runs, **run --rm** for a job
that does its work and exits, **down** to stop. An edit under `src/`, `public/` or `tests/`
is live the next time you run anything — watch syncs it into the app, and the test runners
bind-mount it. The files that are baked into the image (`Dockerfile`, `composer.json`,
`package.json`, `phpunit.xml`, `playwright.config.js`, `docker/apache.conf`) are on watch's
rebuild list, so changing one of them while watch is running rebuilds the image for you.
You never type `docker compose build`.

## On Windows

Every command in this folder is the same on macOS, Linux and Windows, and each is written
on one line so it pastes identically everywhere. Use PowerShell or Windows Terminal with
Docker Desktop running. Two things to know: the two `docker compose exec` commands in Part 0
carry `-T`, which is what lets them work from Git Bash as well; and the API accepts a plain
form body (`-d amount=100`) precisely so no command here needs quoted JSON, which PowerShell
mangles. Line endings are handled by the repo's `.gitattributes` — nothing to configure.

## Before class

The image is large — the Playwright base carries three browsers. Start the app once
**before Tuesday** so the download happens at home; there is no separate build step,
`watch` builds what it needs:

```
docker compose watch
```

`Watch enabled`, <http://localhost:8080> loads, `Ctrl+C`, `docker compose down`. Then check
the three suites run. Expect the unit and browser tests to pass and the API suite to show
**one failure** — that failure is on purpose, and Part 2 is about it:

```
docker compose run --rm unit
docker compose run --rm api
docker compose run --rm e2e
```

## The app


```
pantry/
├── src/
│   ├── Stock.php            amount on the shelf: add(), consume()          <- unit tested
│   ├── DueDate.php          days left, and the label: expired / due soon / fresh   <- unit tested
│   ├── Pantry.php           the use cases: create, purchase, consume
│   └── Repository.php       the only class that touches SQLite
├── public/index.php         JSON API under /api, and the HTML page at /
├── tests/
│   ├── unit/                PHPUnit.     StockTest.php, DueDateTest.php
│   ├── api/                 Playwright.  products.spec.js   (request fixture, no browser)
│   └── e2e/                 Playwright.  pantry.spec.js     (Chromium)
├── docker/apache.conf       DocumentRoot public/, FallbackResource /index.php
├── Dockerfile               Playwright image + Apache + PHP 8.3 + Composer
├── docker-compose.yml       web · unit · api · e2e -- one image, four commands
├── phpunit.xml
└── playwright.config.js     starts Apache on a fresh database before the api/e2e tests
```

[Part 0](0-run-the-app.md) brings it up, has you use it, and then walks through how it is
put together: the front controller, the API, and SQLite.

**New to PHP?** The source is commented to teach it. Read in this order: `src/Stock.php`
(classes, types, exceptions) → `src/DueDate.php` (static methods, constants, `===`) →
`src/Pantry.php` (`??`, arrow functions, `throw` as an expression) → `src/Repository.php`
(PDO, prepared statements, associative arrays) → `public/index.php` (superglobals, routing,
escaping, mixing PHP with HTML). Then `tests/unit/StockTest.php` for how PHPUnit finds and
runs a test.
Your data ends up on a named volume, like Homework 1's database. The tests never touch it;
they get their own file, wiped clean on every run.

## Why the three suites feel so different

After you have run all three, compare the last line of each:

| | Tests | Time | What it proves |
|---|---|---|---|
| `unit` | 21 | 5 ms for all of them | a class does what its sentences say |
| `api` | 6 | 10–20 ms each | a client is given what it was promised |
| `e2e` | 2 | 200–300 ms each | a person can do the thing |

Each level catches what the level below cannot see, and costs more to run. That is the
whole argument of the pyramid reading, in one table you produced yourself.

Nothing is handed in from this directory. `ANSWERS.md` in the [week folder](../) has two
short questions — the odd behaviours you notice in Part 0, and your Part 2 prompt with its
output — filled in before you leave class.
