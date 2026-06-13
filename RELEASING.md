# Releasing Easy Rsync

This is the maintainer guide for cutting releases. End-user install docs live in
[README.md](README.md).

Releases are produced entirely from a **git tag**: tag a commit and push it, and the
[`Release` workflow](.github/workflows/release.yml) builds the package and publishes
a GitHub Release. There is nothing to upload by hand.

## Channels

The plugin ships two independent channels that install **side by side** on a server
(they have different plugin names, so a user can run both at once):

| | Stable | Beta |
|---|---|---|
| Plugin name | `easy.rsync` | `easy.rsync.beta` |
| `.plg` file | [`easy.rsync.plg`](easy.rsync.plg) | [`easy.rsync.beta.plg`](easy.rsync.beta.plg) |
| Branch | `main` | `dev` |
| Menu | Settings -> Easy Rsync | Settings -> Easy Rsync (Beta) |
| Release type | latest | prerelease |

Users install/update a channel from its `.plg` URL:

- Stable: `https://raw.githubusercontent.com/Teknicallity/easy-rsync/main/easy.rsync.plg`
- Beta:   `https://raw.githubusercontent.com/Teknicallity/easy-rsync/dev/easy.rsync.beta.plg`

## Version formats

The version string is also the **routing key** -- the workflow infers the channel
(and therefore the branch, `.plg` file, and build flags) from its shape. Use the
right format or the release is rejected.

| Channel | Format | Examples | Regex |
|---|---|---|---|
| Stable | `YYYY.MM.DD` + optional single letter | `2026.05.30`, `2026.05.30a`, `2026.05.30b` | `^[0-9]{4}\.[0-9]{2}\.[0-9]{2}[a-z]?$` |
| Beta | `YYYY.MM.DD.bN` | `2026.05.30.b1`, `2026.05.30.b2` | `^[0-9]{4}\.[0-9]{2}\.[0-9]{2}\.b[0-9]+$` |

The date is the release date. The suffix is only for **multiple releases on the same
day**: stable bumps a letter (`a`, `b`, `c`, ...), beta bumps the number (`b1`, `b2`, ...).

### Why the suffix rules matter: `strcmp`, not `version_compare`

Unraid decides "an update is available" with a plain lexicographic string compare --
`/usr/local/sbin/plugin` does `if (strcmp($latest, $version) > 0)`, **not** PHP's
`version_compare()`. A new version is only offered when its string sorts *after* the
installed one.

This works because the date is fixed-width (so it always dominates) and the suffixes
break same-day ties in order: `2026.05.30` < `2026.05.30a` < `2026.05.30b`, and
`2026.05.30.b1` < `2026.05.30.b2` < ... < `2026.05.30.b9`.

> **Cap beta at 9 per day (`b1`-`b9`).** Because the compare is lexicographic,
> `2026.05.30.b10` sorts *before* `2026.05.30.b2` (`'1' < '2'`), so a tester on `b2`
> would never be offered `b10`. If you somehow need a 10th beta in one day, roll to
> the next date (`2026.05.31.b1`) instead.

## Cutting a release

Pre-reqs:

- `composer test` passes locally (see [README.md#tests](README.md#tests)).
- The channel's `.plg` `<CHANGES>` block has a `### <version>` entry for the version
  you're about to tag; the release body is pulled from it (no entry -> the workflow
  falls back to GitHub's auto-generated notes).
- The commit you want to ship is already on the correct branch -- **stable on `main`,
  beta on `dev`**. The workflow enforces this (it fails if the tag commit is not
  reachable from the expected branch).

### Stable

```bash
git checkout main && git pull
git tag 2026.05.30            # or 2026.05.30a for a same-day re-spin
git push origin 2026.05.30
```

### Beta

```bash
git checkout dev && git pull
git tag 2026.05.30.b1
git push origin 2026.05.30.b1
```

### Or trigger it manually

From the Actions tab, run **Release** via `workflow_dispatch` and enter the version
(e.g. `2026.05.30`, `2026.05.30a`, or `2026.05.30.b1`). Run it from the branch that
matches the channel; it creates the tag at that branch's HEAD if it doesn't exist.

### What the workflow does

[`.github/workflows/release.yml`](.github/workflows/release.yml), in order:

1. **Resolves** the version (from the pushed tag, or the dispatch input).
2. **Classifies** it with the regexes above -> `is_beta`, target branch, `.plg` file,
   and the `-b` build flag. An unrecognized format errors out here.
3. **Checks out** the target branch and (for tag pushes) **verifies the tag commit is
   reachable from it** -- beta tags must be on `dev`, stable on `main`.
4. **Builds** the `.txz` inside the `aclemons/slackware:15.0` container:
   `./pkg_build.sh -y -V <version> [-b]`.
5. **Commits** the regenerated `.plg` back to the branch as `Release <version>`
   (only if it changed).
6. **Publishes** a GitHub Release with `archive/*.txz` attached --
   prerelease + not-latest for beta, latest for stable. The release body is the
   `### <version>` changelog section from the channel's `.plg` (falling back to
   GitHub's auto-generated notes if no matching section is found).

### What the build does

[`pkg_build.sh`](pkg_build.sh) packages [`source/`](source/) into
`usr/local/emhttp/plugins/<plugin>/`, runs `makepkg`, computes the MD5, and rewrites
the `.plg` entities (`md5`, `version`, `pluginName`, `gitBranch`). With `-b` it also
applies the beta transforms so the two channels don't collide on disk:

- renames `*.page` -> `*.Beta.page`
- `Menu="EasyRsync:` -> `Menu="EasyRsync.Beta:`
- include paths `/easy.rsync/` -> `/easy.rsync.beta/`
- `$appName = 'easy.rsync'` -> `'easy.rsync.beta'` in `ERSettings.php`

Artifacts land in `archive/<plugin>-<version>.txz` and are uploaded to the Release;
the `.plg`'s `<URL>` points at the Release asset.

## Local build & sideload (dev loop)

To test a build on your own server without publishing a release, build and rsync it
straight to the box (see the **Beta install** section of [README.md](README.md)):

```bash
sudo ./pkg_build.sh -b -u root@<unraid-host> -y -v .b1
```

- `-u <host>` rsyncs the package to the server and **comments out** the Release
  `<URL>` in the `.plg` (the package is pre-deployed, so that URL would be
  unreachable). Then install `/boot/easy.rsync.beta.plg` from the web UI or
  `plugin install`.
- `-d` is a dry run -- it builds into `tmp/` and changes nothing else.
- `-v <suffix>` appends to today's date; `-V <version>` sets it verbatim. **Beta
  builds must still be `.bN`** (e.g. `-v .b1` -> `YYYY.MM.DD.b1`, or
  `-V 2026.05.30.b1`); a letter suffix is the *stable* format and is rejected with
  `-b`.

## Gotchas

- **Wrong tag format** -> the classify step rejects it (must match a regex above).
- **Tag on the wrong branch** -> the ancestry check fails (beta on `dev`, stable on
  `main`).
- **More than 9 betas in a day** -> don't; lexicographic ordering breaks at `b10`
  (see above). Roll to the next date.
- **Don't hand-edit the `version` in a `.plg`.** The build regenerates `version` and
  `md5` together; editing one without the other desyncs them and breaks installs. The
  committed beta `.plg` `version` self-corrects on the next `.bN` release.
