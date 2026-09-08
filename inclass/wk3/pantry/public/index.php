<?php
// The whole web layer: a JSON API under /api, and one HTML page that uses the
// same App\Pantry class through ordinary forms. Both are thin. The rules live
// in src/, which is where the unit tests point.
//
// How a request gets here: Apache serves files from public/ (DocumentRoot).
// docker/apache.conf says FallbackResource /index.php -- anything that is not
// a real file is handed to this one, so this one file answers every URL.
// Frameworks (Laravel, Slim) do the same thing with a lot more machinery; this
// is the idea with the machinery removed.

// Make PHP strict about types in this file: "3" is not an int, null is not a
// string. Catches a whole class of bugs at the point they happen.
declare(strict_types=1);

// Composer wrote vendor/autoload.php. Requiring it once means every class
// under App\ (and PHPUnit, etc.) loads itself the first time it is mentioned.
require __DIR__ . '/../vendor/autoload.php';

use App\InsufficientStockException;
use App\NotFoundException;
use App\Pantry;
use App\Repository;

// Build the one object everything below uses.
$pantry = new Pantry(Repository::fromEnvironment());
$today = date('Y-m-d');   // the page and the API share "today"; the tests pass their own

// $_SERVER is a "superglobal": PHP fills it with facts about the request and it
// is visible everywhere. REQUEST_METHOD is GET/POST; REQUEST_URI is the path
// plus query string. parse_url() strips the ?query=...; rtrim() removes a
// trailing slash; `?: '/'` puts the slash back if nothing was left.
$method = $_SERVER['REQUEST_METHOD'];
$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', '/') ?: '/';

// ---------------------------------------------------------------- JSON API

if (str_starts_with($path, '/api')) {
    // Tell the client what is coming. Without this, browsers assume HTML.
    header('Content-Type: application/json');

    // A JSON request body is not in $_POST -- that only holds form fields.
    // php://input is the raw body. json_decode(..., true) gives arrays, not objects.
    // `?: '{}'` handles an empty body; `?? []` handles unparseable JSON.
    $body = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
    // A plain form body (amount=100) is accepted too. Same fields, same code
    // below -- and it means a curl command needs no quotes, which matters on
    // Windows, where PowerShell mangles quoted JSON on the command line.
    if ($body === [] && $_POST !== []) {
        $body = $_POST;
    }

    try {
        // Routing by hand: match method + path, do the thing, respond. Each
        // respond() call exits, so the first match wins.
        if ($method === 'GET' && $path === '/api/health') {
            respond(200, ['ok' => true]);
        }
        if ($method === 'GET' && $path === '/api/products') {
            respond(200, $pantry->list($today));
        }
        if ($method === 'POST' && $path === '/api/products') {
            // `$body['name'] ?? ''` -- the key may not exist; ?? avoids a warning.
            respond(201, $pantry->create((string) ($body['name'] ?? ''), $body['best_before'] ?? null));
        }
        // preg_match with a capture group: (\d+) grabs the digits into $m[1].
        // The # characters are the regex delimiters (so / needs no escaping).
        if ($method === 'GET' && preg_match('#^/api/products/(\d+)$#', $path, $m)) {
            respond(200, $pantry->get((int) $m[1]));
        }
        if ($method === 'POST' && preg_match('#^/api/products/(\d+)/purchase$#', $path, $m)) {
            respond(200, $pantry->purchase((int) $m[1], quantity($body)));
        }
        if ($method === 'POST' && preg_match('#^/api/products/(\d+)/consume$#', $path, $m)) {
            respond(200, $pantry->consume((int) $m[1], quantity($body)));
        }
        respond(404, ['error' => "No route for $method $path"]);
    } catch (NotFoundException $e) {
        // Exceptions thrown anywhere inside the try land here, sorted by type.
        // This is where a domain problem becomes an HTTP status code -- and the
        // ONLY place. Pantry and Stock know nothing about 404 or 422.
        respond(404, ['error' => $e->getMessage()]);
    } catch (InsufficientStockException | \InvalidArgumentException $e) {   // `|` catches either
        respond(422, ['error' => $e->getMessage()]);
    }
}

// ---------------------------------------------------------------- HTML forms
// Each form POSTs here, then redirects back to / (so refresh does not
// resubmit). Errors travel in the query string and are shown once.
// This is the "POST-redirect-GET" pattern; you will see it in every framework.

if ($method === 'POST') {
    try {
        if ($path === '/products') {
            // $_POST holds the form fields, keyed by each input's `name`.
            $pantry->create((string) ($_POST['name'] ?? ''), $_POST['best_before'] ?? null);
        } elseif (preg_match('#^/products/(\d+)/(purchase|consume)$#', $path, $m)) {
            // $m[2] is the word "purchase" or "consume" -- and `$obj->{$var}()`
            // calls the method with that name. Two routes, one line.
            $pantry->{$m[2]}((int) $m[1], quantity($_POST));
        } else {
            http_response_code(404);
            exit('Not found');
        }
        redirect('/');
    } catch (NotFoundException | InsufficientStockException | \InvalidArgumentException $e) {
        // Same exceptions as the API, different presentation: back to the page
        // with the message in the URL. urlencode() makes it safe to put there.
        redirect('/?error=' . urlencode($e->getMessage()));
    }
}

if ($path !== '/') {
    http_response_code(404);
    exit('Not found');
}

// A plain GET /. Gather what the template below needs, then fall out of PHP
// mode into HTML.
$products = $pantry->list($today);
$error = $_GET['error'] ?? null;   // $_GET: the ?key=value pairs from the URL

// ---------------------------------------------------------------- helpers
// Functions can be declared after they are used; PHP reads the whole file first.

/** `never` as a return type: this function does not return -- it exits. */
function respond(int $status, mixed $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);   // `|` combines flags
    exit;
}

function redirect(string $to): never
{
    // 303 See Other: "go GET this instead". The browser follows it, and a
    // refresh on the resulting page re-GETs rather than re-POSTing.
    header('Location: ' . $to, true, 303);
    exit;
}

/**
 * Read an amount out of either a form ($_POST) or a JSON body. Both are just
 * arrays by the time they get here.
 *
 * @param array<string,mixed> $input
 */
function quantity(array $input): float
{
    $raw = $input['amount'] ?? null;
    // Everything from a form is a string; is_numeric() accepts "2", "2.5", "-1".
    if ($raw === null || $raw === '' || !is_numeric($raw)) {
        throw new \InvalidArgumentException('Amount must be a number');
    }
    return (float) $raw;
}

/**
 * Escape for HTML. THE rule of PHP templating: every value that came from a
 * user goes through this before it is printed, or a product named
 * <script>...</script> runs in everyone's browser. Short name so it is
 * painless to use everywhere -- and it is used everywhere below.
 */
function e(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES);
}
// The closing tag on the next line: from here on, the file is HTML. PHP resumes
// inside each  <?php ...  block and each  <?= ...  block; the second form is
// shorthand for "php echo". Note the tag is not written out in this comment --
// the closing tag ends PHP mode even inside a comment.
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pantry</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 48rem; margin: 3rem auto; padding: 0 1rem; line-height: 1.5; }
    table { border-collapse: collapse; width: 100%; margin-top: 1.5rem; }
    th, td { text-align: left; padding: 0.4rem 0.6rem; border-bottom: 1px solid #ddd; vertical-align: middle; }
    th { font-weight: 600; color: #555; font-size: 0.85rem; }
    td.num { font-variant-numeric: tabular-nums; }
    .badge { font-size: 0.75rem; padding: 0.1rem 0.5rem; border-radius: 1rem; background: #eee; }
    .badge.expired { background: #fbd5d5; }
    .badge.due.soon { background: #fde9c8; }
    .badge.fresh { background: #d6f2dc; }
    .err { color: #b00020; background: #fdecec; padding: 0.6rem 0.8rem; border-radius: 4px; }
    form.inline { display: inline-flex; gap: 0.3rem; }
    input[type=number] { width: 4.5rem; }
    .meta { color: #666; font-size: 0.85rem; }
  </style>
</head>
<body>

  <h1>Pantry</h1>
  <p class="meta">Today is <?= e($today) ?>. Products, how much is on the shelf, and when it goes off.
    The same data is at <a href="/api/products">/api/products</a>.</p>

  <?php /* The "alternative syntax": if (...): ... endif; reads better than braces when wrapped around HTML. */ ?>
  <?php if ($error): ?><p class="err" role="alert"><?= e($error) ?></p><?php endif; ?>

  <?php /* method="post" + action: where the browser sends the fields. Each input's name= is the key in $_POST. */ ?>
  <form method="post" action="/products" id="new-product">
    <label>Name <input name="name" required placeholder="e.g. Milk"></label>
    <label>Best before <input name="best_before" type="date"></label>
    <button type="submit">Add product</button>
  </form>

  <table id="products">
    <thead>
      <tr><th>Product</th><th>In stock</th><th>Best before</th><th></th><th>Buy</th><th>Use</th></tr>
    </thead>
    <tbody>
    <?php if (!$products): ?>
      <tr><td colspan="6" class="meta">Nothing yet. Add a product above.</td></tr>
    <?php endif; ?>
    <?php /* foreach walks the array; $p is one product (an associative array) each time round. */ ?>
    <?php foreach ($products as $p): ?>
      <tr data-product-id="<?= e($p['id']) ?>">
        <td class="name"><?= e($p['name']) ?></td>
        <?php /* number_format then strip trailing zeros: 12.00 -> 12, 2.50 -> 2.5 */ ?>
        <td class="num amount"><?= e(rtrim(rtrim(number_format($p['amount'], 2, '.', ''), '0'), '.')) ?></td>
        <td><?= e($p['best_before'] ?? '—') ?></td>
        <td><span class="badge <?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
        <td>
          <form class="inline" method="post" action="/products/<?= e($p['id']) ?>/purchase">
            <input name="amount" type="number" step="any" value="1" aria-label="Amount to buy">
            <button type="submit">Buy</button>
          </form>
        </td>
        <td>
          <form class="inline" method="post" action="/products/<?= e($p['id']) ?>/consume">
            <input name="amount" type="number" step="any" value="1" aria-label="Amount to use">
            <button type="submit">Use</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

</body>
</html>
