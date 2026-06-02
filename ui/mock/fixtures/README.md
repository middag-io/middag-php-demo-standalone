# Dev fixtures — captured page contracts

These `*.json` files are the **exact Inertia page objects** the PHP backend
emits for each route (`component` + SharedProps + `props.contract`). The
standalone dev app (`npm run dev`, via `src/app.tsx`) replays them so you can
build and preview the UI with **no PHP backend running** — and what you see is
what production renders, because it is the production contract.

One file per route:

| Fixture | Route | Shell / layout |
|---|---|---|
| `login.json` | `/login` | auth / stack — chromeless `form_panel` |
| `dashboard.json` | `/` | basic / dashboard — metrics + chart |
| `tickets.json` | `/tickets` | basic / dashboard — metrics + cell showcase |
| `ticket-detail.json` | `/tickets/1` | basic / sidebar — workflow + tabs |
| `ticket-new.json` | `/tickets/new` | basic / wizard — `form_panel` |
| `agents.json` | `/agents` | basic / dashboard — supervisor roster (full width) |
| `agent-detail.json` | `/agents/1` | basic / dashboard — metrics + detail |
| `customers.json` | `/customers` | basic / stack — card grid |
| `parity.json` | `/parity` | basic / stack — dual-ORM |
| `help.json` | `/help` | immersive / stack |
| `coverage.json` | `/coverage` | immersive / stack |

`csrf_token` is scrubbed to `"dev-csrf-token"` — the mock router never POSTs for
real, so a live token would be both useless and noise in the repo.

## Refreshing after a backend change

When a controller changes the contract it emits, re-capture from the live server:

```bash
# from the repo root
php bin/console install:db                                  # recreate + seed sqlite
php -S localhost:8090 -t public public/index.php &          # boot the backend

# log in (grab the CSRF from /login, then POST it)
CSRF=$(curl -s -c /tmp/c.txt http://localhost:8090/login \
  | grep -oE '"csrf_token":"[a-f0-9]+"' | head -1 | cut -d'"' -f4)
curl -s -b /tmp/c.txt -c /tmp/c.txt -o /dev/null \
  -d "_token=$CSRF" -d "email=demo@middag.io" -d "password=middag" \
  http://localhost:8090/login

# capture each route's page object from the bootstrap <script type="application/json">,
# scrub csrf, pretty-print into this dir. Example for one route:
route=/ ; name=dashboard
curl -s -b /tmp/c.txt "http://localhost:8090${route}" \
  | php -r '$h=file_get_contents("php://stdin");
      preg_match("/<script[^>]*application\/json[^>]*>(.*?)<\/script>/s",$h,$m);
      $p=json_decode($m[1],true); $p["props"]["csrf_token"]="dev-csrf-token";
      echo json_encode($p, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";' \
  > "ui/mock/fixtures/${name}.json"
```

Routes captured: `/login` (logged out), then authed `/`, `/tickets`, `/tickets/1`,
`/tickets/new`, `/agents`, `/agents/1`, `/customers`, `/parity`, `/help`, `/coverage`.
