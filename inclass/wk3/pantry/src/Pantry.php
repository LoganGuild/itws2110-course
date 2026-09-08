<?php

namespace App;

/**
 * The application's use cases. The web page and the JSON API are two thin
 * skins over this one class, which is why one rule here shows up in both.
 *
 * Throws on bad input; the caller decides how to show the message. A class
 * like this does not know whether it is being called from a browser, a test,
 * or a command line -- and that is the point.
 */
final class Pantry
{
    // Dependency injection, the simplest form: this class needs a Repository,
    // so you hand it one. A test could hand it a Repository on ':memory:'.
    public function __construct(private Repository $products)
    {
    }

    /** @return array<int, array<string,mixed>> */
    public function list(string $today): array
    {
        // `fn (...) => ...` is an arrow function: a one-line anonymous function
        // that can see variables from around it ($today) automatically.
        // `$p + [...]` on arrays adds the keys on the right that are missing on the left.
        return array_map(
            fn (array $p) => $p + ['status' => DueDate::status($p['best_before'], $today)],
            $this->products->all()
        );
    }

    /** @return array<string,mixed> */
    public function get(int $id): array
    {
        // `??` is the null-coalescing operator: left side unless it is null,
        // then the right side. Since PHP 8, `throw` is an expression, so it can
        // BE the right side. Read: "the product, or else blow up with a 404".
        return $this->products->find($id)
            ?? throw new NotFoundException("No product with id $id");   // "..." interpolates $id
    }

    /** @return array<string,mixed> */
    public function create(string $name, ?string $bestBefore): array
    {
        // Validation lives here, once, for both the form and the API.
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Name is required');
        }
        if ($this->products->nameExists($name)) {
            throw new \InvalidArgumentException("There is already a product called $name");
        }
        // (string) casts null to '' so trim() is happy either way.
        $bestBefore = trim((string) $bestBefore);
        // preg_match: a regular expression. ^ start, \d{4} four digits, $ end.
        // Returns 1 on a match, 0 if not -- hence the `!`.
        if ($bestBefore !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $bestBefore)) {
            throw new \InvalidArgumentException('Best-before must be a date like 2026-09-30');
        }
        $id = $this->products->insert($name, $bestBefore === '' ? null : $bestBefore);
        return $this->get($id);
    }

    /** @return array<string,mixed> */
    public function purchase(int $id, float $quantity): array
    {
        // Load -> wrap in the domain object -> let IT enforce the rules -> save.
        // Pantry never does the arithmetic itself. That is Stock's job, and
        // Stock is the thing with the unit tests.
        $product = $this->get($id);
        $stock = new Stock($product['amount']);
        $stock->add($quantity);
        $this->products->setAmount($id, $stock->amount());
        return $this->get($id);
    }

    /** @return array<string,mixed> */
    public function consume(int $id, float $quantity): array
    {
        $product = $this->get($id);
        $stock = new Stock($product['amount']);
        $stock->consume($quantity);
        $this->products->setAmount($id, $stock->amount());
        return $this->get($id);
    }
}
