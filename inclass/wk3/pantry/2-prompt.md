# Part 2 — Write a prompt for the test you want

`StockTest` has no sentence about using more than you have. `Stock::consume()` will take
five off a shelf that holds two and report minus three. The page shows it. The API
returns it with a `200`. Nobody wrote the rule down, so nobody enforced it.

You are going to have an AI write the test. **Not the fix — the test.** The interesting
work is the prompt, because a prompt for a test is a specification, and writing the
specification is the part that cannot be delegated.

## A — Decide what should be true

Before you type a word to any tool, write the sentence:

> Using more than is in stock is refused, and the amount does not change.

Two claims. *Refused* — how? The codebase already has an answer waiting:
`src/InsufficientStockException.php` exists and nothing throws it. So: it throws
`App\InsufficientStockException`. *Does not change* — after the exception, `amount()` still
returns what it did before.

Notice that you just made two design decisions (throw rather than clamp to zero; which
exception) that the AI would otherwise have made for you, silently, and possibly
differently from what `public/index.php` is already prepared to catch. Look — it is
already there, waiting:

```php
} catch (InsufficientStockException | \InvalidArgumentException $e) {
    respond(422, ['error' => $e->getMessage()]);
```

## B — Write the prompt

A good test-generation prompt has five parts. Write yours in `ANSWERS.md` under
question 2 as you go — the prompt itself, and then what the model sent back.

**1. The code under test.** Paste `src/Stock.php`, whole — comments included; they tell the model what
the class is *for*. Seventy lines is nothing; a summary is worse than the source.

**2. An existing test, for style.** Paste `tests/unit/StockTest.php`, whole. This does
more than any instruction could: PHPUnit 11, `final class`, `test_sentence_case` names,
arrange-act-assert with blank lines, `assertSame` not `assertEquals`, `expectException`
before the act. The model will match what it sees.

**3. The behaviour, as a sentence, with the exact values.** Your sentence from A, made
concrete:

> A `Stock` holding 2 that is asked to `consume(5)` must throw
> `App\InsufficientStockException`. After the exception, `amount()` must still return 2.0.
> Consuming exactly what is in stock (2 from 2) is allowed and leaves 0.0 — that case is
> already tested; do not duplicate it.

**4. What not to do.** These are the failure modes you would otherwise spend ten minutes
deleting:

> Write only the new test method(s) to add to `StockTest`. Do not modify `Stock.php`. Do
> not change or remove existing tests. One behaviour per test method. Do not use
> `assertTrue(true)` or any assertion that cannot fail.

**5. Where it goes and how it is run.** So the output drops in without editing:

> The tests run with `./vendor/bin/phpunit` from `phpunit.xml`, which points at
> `tests/unit`. The autoloader maps `App\` to `src/`.

Put those five together and send it. Twenty lines of prompt for ten lines of test is the
right ratio. The prompt *is* the test; the model is doing the typing.

## C — What should come back

Compare what the model gave you against this. It does not have to match word for word —
method names and the exact message check will vary — but it has to match in *shape*: two
tests, one per claim in your sentence, the right exception by name, the right values.

```php
use App\InsufficientStockException;   // add this next to `use App\Stock;` at the top

    public function test_using_more_than_is_in_stock_is_refused(): void
    {
        $stock = new Stock(2);

        $this->expectException(InsufficientStockException::class);
        $this->expectExceptionMessage('Not enough in stock');

        $stock->consume(5);
    }

    public function test_a_refused_use_leaves_the_amount_unchanged(): void
    {
        $stock = new Stock(2);

        try {
            $stock->consume(5);
        } catch (InsufficientStockException) {
        }

        $this->assertSame(2.0, $stock->amount());
    }
```

What to check, line by line:

| Yours should have | Because |
|---|---|
| `use App\InsufficientStockException;` at the top | Without it PHP looks for `InsufficientStockException` in the global namespace and the test *errors* instead of failing |
| `new Stock(2)` then `consume(5)` — the values from your sentence | A test with `consume(1)` on a stock of 2 passes today and proves nothing |
| `expectException(InsufficientStockException::class)` — this class, by name | `\Exception` or `\RuntimeException` would also accept a typo that throws `TypeError` |
| `expectException` *before* `consume()` | After it, the line is never reached |
| A second test that asserts `amount()` is still `2.0` after the refusal | That is the second claim in your sentence. If the model gave you one test with both, split it: one behaviour per test |
| The `try`/`catch` in the second test has an assertion *outside* it | An assertion inside the `catch` never runs when nothing is thrown — the test would pass against the bug |
| Nothing else changed | No edits to `Stock.php`, no rewritten existing tests |

If yours is missing one of these, do not fix the test by hand yet — fix the *prompt*,
resend it, and see whether the model gets there. That is the skill being practised.
(The `expectExceptionMessage` line is optional; it pins the wording you chose in A.)

## D — Put the test in, and make sure it fails

Paste the method(s) into `tests/unit/StockTest.php` where the comment says something is
missing, and the `use` line at the top. Run:

```
docker compose run --rm unit
```

**It must be red.** You have not fixed `consume()`, so the sentence is not yet true:

```
 ✘ Using more than is in stock is refused
   │
   │ Failed asserting that exception of type "App\InsufficientStockException" is thrown.
   │
 ✘ A refused use leaves the amount unchanged
   │
   │ Failed asserting that -3.0 is identical to 2.0.
   │

FAILURES!
Tests: 23, Assertions: 26, Failures: 2.
```

If it is *green* right now, stop. A test that passes against code you know is wrong is not
testing the thing you asked for. Read what came back. Common ways an AI-written test
passes when it should not:

- it asserts the *current* behaviour (`assertSame(-3.0, ...)`) — it read the code instead of your sentence
- it expects `\Exception` or `\RuntimeException`, which anything satisfies
- it wraps the call in a `try` and asserts inside the `catch`, so when nothing is thrown, nothing is asserted
- it is a *second* copy of an existing test with a new name

Fix the test until it is red for the right reason. Only then move on.

## E — Make it green

Now — and only now — fix `Stock::consume()`. Three lines, between the positivity check and
the subtraction:

```php
        $this->assertPositive($quantity);
        if ($quantity > $this->amount) {
            throw new InsufficientStockException('Not enough in stock');
        }
        $this->amount -= $quantity;
```

(No `use` line needed here: `Stock` and `InsufficientStockException` are in the same
namespace.) Run again:

```
OK (23 tests, 27 assertions)
```

Then break the fix — comment out the `throw` — run, confirm both tests go red again, and
restore it. You have a test you can trust: you watched it catch the bug both ways.

## F — Try the lazy version

If you have time: open a fresh chat and ask only

> Write PHPUnit tests for this class.

with `Stock.php` pasted and nothing else. Look at what it writes for `consume()`. Does it
assert that the amount can go negative — documenting the bug as if it were the design?
Does it match the style of the file it will live in? Count the lines you would have to
change.

That difference is the whole point. **The model tests what the code does. Only you can say
what the code should do.** The prompt is where you say it.

---

## Key learnings

- **The prompt is the specification.** Writing the sentence — with exact values and the exact exception — is the work. The model does the typing.
- **Separate facts from decisions.** Framework, file names and style are facts the model could read from the code. What *should* happen is a decision only you can make.
- **Paste the code and an existing test, whole.** The model matches what it sees, so style, naming and imports come free.
- **Say what not to do.** No editing `src/`, no changing other tests, no assertion that cannot fail, one behaviour per test.
- **Red first, or it is not a test.** A generated test that passes against code you know is wrong is testing the wrong thing. Common causes: it asserts the current (buggy) behaviour, it expects a too-general exception, it asserts inside a `catch`, or it duplicates an existing test.
- **Then green, then red again.** Fix the code, watch it pass, break the fix, watch it fail. Now you trust the test.
- **The model tests what the code does. Only you can say what it should do.**
