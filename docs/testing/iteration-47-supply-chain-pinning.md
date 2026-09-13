# Iteration 47 — Supply-chain pinning

## Scope

Container images and GitHub Actions were referenced by mutable tags (`php:8.2-apache`, `mysql:8.0`,
`composer:2`, `@v7`/`@v4`/`@v2`). A moved or compromised tag could silently change the certified build.
Pin every third-party dependency used by the build and CI to an immutable reference.

## Design

- Images are pinned by `@sha256:` digest, keeping the human-readable tag for context:
  - `docker/modx/Dockerfile` — `FROM php:8.2-apache@sha256:0970c7c1…` and
    `COPY --from=composer:2@sha256:d8f6343d…`;
  - `docker-compose.yml`, `deploy/docker/docker-compose.production.yml` — `mysql:8.0@sha256:7dcddc01…`.
- Workflows pin every `uses:` to a full 40-character commit SHA with a trailing `# <tag>` comment:

  | Action | Tag | Commit SHA |
  | --- | --- | --- |
  | `actions/checkout` | `v7` | `3d3c42e5aac5ba805825da76410c181273ba90b1` |
  | `shivammathur/setup-php` | `v2` | `f3e473d116dcccaddc5834248c87452386958240` |
  | `ramsey/composer-install` | `v4` | `26d8a556604053a9612623447203a691f406fbe6` |
  | `actions/setup-node` | `v7` | `820762786026740c76f36085b0efc47a31fe5020` |
  | `actions/upload-artifact` | `v7` | `043fb46d1a93c77aae656e7c1c64a875d1fc6a0a` |

- `.github/dependabot.yml` opens weekly PRs for the `github-actions` and `docker` ecosystems, so the pins are
  bumped deliberately instead of rotting.
- `scripts/verify-supply-chain-pins.sh` (run by `scripts/quality-gate.sh`) enforces the invariant: it fails on
  any unpinned `uses:` or any Dockerfile/compose image without an `@sha256:` digest.
- Digests were resolved with `docker buildx imagetools inspect <image> --format '{{.Manifest.Digest}}'`; the
  `mysql` digest matches the already-pulled local image exactly. Action SHAs were resolved with
  `gh api repos/<owner>/<repo>/commits/<tag> --jq .sha`.

## Evidence

```text
$ docker compose config --quiet
root-compose-exit=0

$ docker compose -f deploy/docker/docker-compose.production.yml config --quiet
prod-config-exit=0            # validated with a temporary empty env file, since .env.production is a deploy secret

$ docker compose build modx
exporting manifest list sha256:44f91f7e7c875009143f39281ee12527431ff87bbc51c262fc68e73e23962366
Image modxaibridge-modx Built   # base php/composer layers matched the cached certified toolchain

$ docker buildx imagetools inspect php:8.2-apache --format '{{.Manifest.MediaType}} {{.Manifest.Digest}}'
application/vnd.oci.image.index.v1+json sha256:0970c7c14b21003bce8178af2985b491a65b71e800ff5366053de544306ed2a1
$ docker image inspect mysql:8.0 --format '{{range .RepoDigests}}{{println .}}{{end}}'
mysql@sha256:7dcddc01f13bab2f15cde676d44d01f61fc9f99fe7785e86196dfc07d358ae2b   # pin == pulled digest

$ bash scripts/verify-supply-chain-pins.sh
SUPPLY CHAIN PINS: PASS

# negative checks (temporarily reverted to floating refs, then restored)
UNPINNED action: actions/upload-artifact@v7
SUPPLY CHAIN PINS: FAIL
UNPINNED compose image: mysql:8.0
UNPINNED Dockerfile base image: php:8.2-apache
SUPPLY CHAIN PINS: FAIL
```

## Notes

- Repository-level `sha_pinning_required` is still `false`; enabling it is a GitHub setting change, not a code
  change, and remains a follow-up (see `docs/ai-agent/HANDOFF.md`).
- Pinned digests are manifest-list (OCI index) digests, so multi-arch runners keep working.
- No application code, package contents or the transport archive change: this is a build/CI-only hardening, so
  `aibridge-0.1.1.transport.zip` and its SHA-256 stay valid. The next release still requires a full
  `AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` run on the pinned toolchain.
