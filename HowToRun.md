# how to run it


**Prerequisite:** Docker Desktop (or Docker Engine on Linux). That's it.
No PHP, no Composer, no Node on the host.

```bash
# 1. Bring up the mock and the runner
docker compose up -d

# 2. Install dependencies and run the suite (one command)
docker compose exec runner sh -c '
  cp -n .env.example .env &&
  sed -i "s|^BASE_URL=.*|BASE_URL=http://mock-api:4010|" .env &&
  composer install &&
  vendor/bin/codecept build &&
  BACKEND=mock vendor/bin/codecept run Api
'

# 3. Stop containers when done
docker compose down
```

Expected output:

```
Tests: 29, Assertions: 35, Skipped: 4.
```

The four skipped tests are real assertions that need a real backend
(request echo, boolean coercion, mbId uniqueness). They run when you
flip the env switch:

```bash
BACKEND=real BASE_URL=https://your-staging-host.example.com \
  vendor/bin/codecept run Api
```

## What to check first

If you have five minutes:

1. `README.md`, what's here and why
2. `tests/Api/MediaBuyers/CreateMediaBuyerCest.php`, the test code
3. `PART2_EVALUATION.md`, the five written answers

If you have fifteen:

4. `tests/_support/Factory/MediaBuyerFactory.php` and the two helpers in
   `tests/_support/Helper/`, the abstractions
5. `ASSUMPTIONS.md`, every place the contract was silent

## If something breaks

`composer install` should resolve cleanly. Allure
(`allure-framework/allure-codeception ^2.4`) and Qase
(`qase/codeception-reporter ^2.0`) are both installed. They're not
enabled in `codeception.yml`'s `extensions.enabled` so the docker run
stays self-contained; uncomment those two lines to wire them up in CI.

If Docker can't pull `stoplight/prism:4`, swap it for `:5` in
`docker-compose.yml` and rebuild. On macOS with the VS Code extension
host, unset `ELECTRON_RUN_AS_NODE` before running anything Electron-based.
