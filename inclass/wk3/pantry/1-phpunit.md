# Part 1 — Read the PHPUnit tests

You have used the app. Now read what someone wrote down about it. A test is a sentence
about the code that a computer can check. Before you run anything, read the sentences.

## A — Read `tests/unit/StockTest.php`

Open it. Do **not** open `src/Stock.php` yet.

Nine methods. Read the *names* as a list:

```
a new stock is empty
a stock can start with an amount
a stock cannot start below zero
buying adds to the amount
buying nothing is refused
buying a negative amount is refused
using some takes it off the shelf
using all of it leaves zero
using a negative amount is refused
```

You now know what a `Stock` is and what it does, and you have not seen a line of it.
That list is a *specification*. The file is the specification with proof attached.

Now read the body of one:

```php
public function test_buying_adds_to_the_amount(): void
{
    $stock = new Stock(2);      // arrange -- set the scene

    $stock->add(3);             // act     -- the one thing under test

    $this->assertSame(5.0, $stock->amount());   // assert -- what must now be true
}
```

Three beats, in that order, every time. **Arrange, act, assert.** The blank lines are
not decoration; they mark the beats. A test with two "act" lines is two tests.

And one that expects a *refusal*:

```php
public function test_buying_nothing_is_refused(): void
{
    $stock = new Stock(2);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('greater than zero');

    $stock->add(0);
}
```

The assertion comes *before* the act here, because the act is going to throw. PHPUnit
catches it and checks it was the right one. If `add(0)` quietly succeeds, the test fails
with *"Failed asserting that exception of type InvalidArgumentException is thrown."*

## B — Read `tests/unit/DueDateTest.php`

Two new things.

**The test decides what day it is.** Every call passes `'2026-09-08'` as today. `DueDate`
never looks at the clock. If it did, *"is zero on the day"* would pass on exactly one day a
year. Code that reaches out to the world — the clock, the database, the network — is hard
to test; code that is *handed* the world is easy. Remember this on Friday.

**One test, many rows.** Look at `shelfLabels()` and the `#[DataProvider]` attribute above
the test that uses it. The test runs once per row, and each row has a name. Six rows,
six results, one method. Look at the rows on either side of the boundary — *in five days*
is `due soon`, *in six days* is `fresh`. Boundaries are where bugs live; put a row on each
side of every one.

## C — Run them

```
docker compose run --rm unit
```

```
PHPUnit 11.x by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.x
Configuration: /app/phpunit.xml

.....................                                             21 / 21 (100%)

Time: 00:00.005, Memory: 8.00 MB

Due Date
 ✔ Days left counts forward to the date
 ✔ Days left is zero on the day
 ✔ Days left goes negative once the date has passed
 ...
 ✔ The shelf label depends on how many days are left with data set "in five days"
 ✔ The shelf label depends on how many days are left with data set "in six days"
 ...

Stock
 ✔ A new stock is empty
 ✔ A stock can start with an amount
 ✔ A stock cannot start below zero
 ✔ Buying adds to the amount
 ...

OK (21 tests, 24 assertions)
```

Twenty-one dots, five milliseconds — then the same twenty-one as sentences, because the
`unit` service runs PHPUnit with `--testdox`, which turns each method name back into
English. That list *is* the specification you read in A, now with a tick beside every line
that is true. Count them: nine from `StockTest`; six plain tests in `DueDateTest` plus six
data-provider rows, each named. (Without `--testdox` you get only the dots. Try it:
`docker compose run --rm unit ./vendor/bin/phpunit`.)

## D — Make the code wrong and watch

Open `src/Stock.php`. In `add()`, change `+=` to `-=`. Run again.

```
...............F.....                                             21 / 21 (100%)

Stock
 ✔ A new stock is empty
 ✔ A stock can start with an amount
 ✔ A stock cannot start below zero
 ✘ Buying adds to the amount
   │
   │ Failed asserting that -1.0 is identical to 5.0.
   │
   │ /app/tests/unit/StockTest.php:63
   │
 ✔ Buying nothing is refused
 ...

FAILURES!
Tests: 21, Assertions: 24, Failures: 1.
```

Read that failure the way you will read hundreds of them:

- **Which sentence** stopped being true: *buying adds to the amount*.
- **What it expected** and **what it got**: 5.0, got −1.0.
- **Where the sentence lives**: line 63. Go there and you will see the input, `new Stock(2)` then `add(3)`.

You could find this bug without reading `Stock.php` at all. Put `+=` back.

Now try a subtler one. In `DueDate::status()`, change `$days <= $warnDays` to
`$days < $warnDays`. Run. Two failures — and the first names the exact row:

```
 ✔ The shelf label depends on how many days are left with data set "today"
 ✘ The shelf label depends on how many days are left with data set "in five days"
   │
   │ Failed asserting that two strings are identical.
   │ --- Expected
   │ +++ Actual
   │ @@ @@
   │ -'due soon'
   │ +'fresh'
   │
   │ /app/tests/unit/DueDateTest.php:54
   │
 ✔ The shelf label depends on how many days are left with data set "in six days"
```

*In five days* flipped; *in six days* did not. The bug is exactly at the boundary, and the
rows on either side of it are what caught it. (The second failure is the warning-window
test, which checks the same edge with a different window.) Put it back.

## E — Now read `src/Stock.php` again

You glanced at it in Part 0. Now read `consume()` next to the list of sentences in
`StockTest`. The thing you did to the rice — using more than was there — has no sentence.
Nine tests, all green, and the bug you found in six clicks is not among them. **Green means
every sentence is true, not that every sentence was written.** Do not fix it yet. That is
Part 2.

---

## Key learnings

- **A test is a sentence a computer can check.** Read the method names and you have the specification; run them and you have proof.
- **Arrange, act, assert** — three beats, one thing per test. When a test fails, its name tells you what stopped being true.
- **Expect a refusal before the act.** `expectException` goes above the call that throws, because nothing after that line runs.
- **`assertSame` is `===`.** `5.0` is not `5`. Strict on purpose: it catches type mistakes as well as value mistakes.
- **Read a failure in three moves:** which sentence, what it expected versus what it got, which line.
- **Hand code the world instead of letting it reach for it.** `DueDate` takes today as an argument, so a test can pick the day. The same goes for the clock, the database, the network.
- **Boundaries are where bugs live.** A data provider puts a named row on each side of every edge; a one-character mistake fails exactly one row.
- **Green means every sentence written is true — not that every sentence was written.** Nine tests passed and the rice still went negative.
