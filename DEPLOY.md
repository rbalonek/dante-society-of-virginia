# Deploying the theme: GitHub → WordPress

This repo deploys the **theme only** (`wp-theme/`) to the production WordPress
host on every push to `main`, using [`.github/workflows/deploy-theme.yml`](.github/workflows/deploy-theme.yml)
(rsync over SSH).

> **Theme vs. content:** Git is the source of truth for theme/template code.
> Page and event **content lives in the WordPress database** and is edited in
> WP Admin — it is never deployed or overwritten by this pipeline.
> `wordpress-import.xml` is a one-time seed for a fresh install, not a sync.

## What the workflow does

- Triggers on push to `main` when anything under `wp-theme/` changes (or manually
  via the **Actions** tab → *Deploy theme to WordPress* → *Run workflow*).
- rsyncs the contents of `wp-theme/` into the host's theme directory, mirroring
  deletions (`--delete`).
- Skips gracefully (no failure) if the secrets below aren't set yet.

## Current production values

The host is settled — a self-managed Oracle Cloud VM (launched 2026-08-11):

| Secret | Current value |
|--------|---------------|
| `SSH_HOST` | `159.54.174.73` |
| `SSH_USER` | `ubuntu` |
| `SSH_PRIVATE_KEY` | contents of `~/.ssh/dante-oracle-2026` |
| `REMOTE_THEME_PATH` | `/var/www/html/wp-content/themes/dante-society` |

> **⚠️ These are not what the repo secrets currently hold.** `SSH_HOST` still points
> at `167.234.212.48`, a decommissioned box, so **the Action fails and pushes do not
> reach the live site** (ClickUp 868kgagf5). Fix the secrets, or deploy by hand:
>
> ```bash
> rsync -avz --delete -e "ssh -i ~/.ssh/dante-oracle-2026" \
>   wp-theme/ ubuntu@159.54.174.73:/var/www/html/wp-content/themes/dante-society/
> ```
>
> If rsync hits permission errors, `ubuntu` doesn't own the theme dir — sync to `~`
> and `sudo rsync` into place.

## One-time setup (once you pick the permanent host)

### 1. Create an SSH deploy key
On your machine:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/dante_deploy -N "" -C "github-deploy-dante"
```

- Add the **public** key (`~/.ssh/dante_deploy.pub`) to the host — most hosts
  have an "SSH Keys" panel; otherwise append it to `~/.ssh/authorized_keys` on
  the server.
- You'll paste the **private** key (`~/.ssh/dante_deploy`) into GitHub below.

### 2. Add GitHub repo secrets
GitHub repo → **Settings → Secrets and variables → Actions → New repository secret**:

| Secret | Value | Example |
|--------|-------|---------|
| `SSH_HOST` | host's SFTP/SSH server | `ssh.yourhost.com` |
| `SSH_USER` | SFTP/SSH username | `dante` |
| `SSH_PRIVATE_KEY` | full contents of `~/.ssh/dante_deploy` | `-----BEGIN OPENSSH PRIVATE KEY----- …` |
| `REMOTE_THEME_PATH` | absolute path to the theme dir on the host | `/home/dante/public_html/wp-content/themes/dante-society` |
| `SSH_PORT` | *(optional)* SSH port if not 22 | `2222` |

> Find `REMOTE_THEME_PATH` from your host's file manager / SFTP client — it's the
> WordPress root + `/wp-content/themes/dante-society`. The folder name must match
> the activated theme (`dante-society`).

### 3. First deploy
- Merge this work into `main` (or use **Run workflow** manually).
- Watch it under the repo's **Actions** tab.
- On the host, activate **Dante Society of Virginia** under Appearance → Themes
  (first time only).

## Requirements & notes

- The host must allow **SSH access with rsync** (most hosts that offer SFTP do;
  some shared hosts are SFTP-only with no shell — if `rsync` isn't available
  there, switch the workflow to an SFTP-upload action or use WP Pusher instead).
- `--delete` mirrors the directory, so `REMOTE_THEME_PATH` must point at a folder
  used **only** by this theme.
- **Images:** the theme expects photos at the site root `/images/`. For
  production, the cleaner path is to upload event/page images to the WordPress
  **Media Library** so the board can swap them. If you instead want to keep them
  repo-managed, add a second rsync step targeting the host's web root `/images/`.
- This pipeline does not deploy `wp-config.php`, the database, or uploads — those
  are environment-specific and are gitignored.
- **Newsletter templates ride along.** `wp-theme/newsletter-templates/*.html` are
  theme files, so a new designed email reaches the live composer's dropdown through
  this same deploy — commit the file, deploy, done. Nothing to paste in wp-admin.
- **Plugins are not deployed** and can't be installed from the dashboard
  (`DISALLOW_FILE_MODS`). See "Installing a plugin on live" in
  [CLAUDE.md](CLAUDE.md).
