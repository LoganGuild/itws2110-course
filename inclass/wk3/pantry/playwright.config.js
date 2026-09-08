// Playwright runs the API tests (tests/api) and the browser tests (tests/e2e).
//
// webServer: before the tests, start Apache -- the same server, same config as
// `docker compose watch` -- pointed at a FRESH database, in this same
// container. After the tests, stop it. Every run starts from nothing, so no
// test can depend on data left by an earlier run.
module.exports = {
  testDir: './tests',
  reporter: 'list',
  workers: 1, // one SQLite file, one server: run the tests one at a time
  webServer: {
    // Apache's config sends its logs to /dev/stdout and /dev/stderr, which is
    // right under Docker but not under Node (it hands the child a socket, which
    // Apache cannot re-open). So: quiet the logs here. If Apache fails to start,
    // drop the redirect to see why.
    command: 'rm -f /tmp/pantry-test.sqlite /var/run/apache2/apache2.pid && apache2ctl -D FOREGROUND >/dev/null 2>&1',
    url: 'http://127.0.0.1/api/health',
    reuseExistingServer: false,
    env: { PANTRY_DB: '/tmp/pantry-test.sqlite' },
  },
  use: {
    baseURL: 'http://127.0.0.1',
    browserName: 'chromium',
  },
};
