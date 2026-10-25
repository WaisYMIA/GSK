# GSK GitHub + Claude Code setup

This document gets the prepared scaffold into a private GitHub repository and starts Claude Code safely.

## A. Create the repository

1. Sign in to GitHub and open https://github.com/new.
2. Repository name: `global-skills-kenya` (or your preferred name).
3. Description: `Global Skills Kenya website and operations platform`.
4. Choose **Private**.
5. Do not initialize with README, `.gitignore`, or license because this package already contains project files.
6. Create the repository and copy its HTTPS or SSH clone URL.

## B. Put the scaffold in the repository

1. Download `GSK_Laravel_Implementation_Scaffold.zip` from the ChatGPT conversation and extract it on your development computer.
2. Open a terminal in the extracted `GSK_Laravel_Implementation` folder.
3. Confirm `README.md`, `CLAUDE.md`, `docs/AI_DEVELOPER_MASTER_SPEC.md`, and `scripts/bootstrap-laravel.sh` are present.
4. Initialize and push the initial commit:

```bash
git init -b main
git add .
git status --short
git commit -m "chore: add GSK implementation scaffold and AI handoff"
git remote add origin YOUR_GITHUB_REPOSITORY_URL
git push -u origin main
```

Replace `YOUR_GITHUB_REPOSITORY_URL` with the repository URL copied from GitHub. Review `git status` before committing. Never add `.env`, credentials, private uploads, or production data.

## C. Install and start Claude Code

Use the official instructions for your operating system: https://docs.anthropic.com/en/docs/claude-code/getting-started

After installing and signing in, from the repository root run:

```bash
claude --version
claude
```

Paste this first task into Claude Code:

> Read `CLAUDE.md`, `docs/AI_DEVELOPER_MASTER_SPEC.md`, `docs/AI_IMPLEMENTATION_TASKS.md`, `docs/ENVIRONMENT_AND_RELEASE_RUNBOOK.md`, and `docs/OPEN_DECISIONS.md` completely. Execute Task 0 only. Inspect the full repository and workflow, then establish whether the Laravel bootstrap script and CI workflow are correct. Implement the fixes directly. Do not claim tests passed unless executed. Do not weaken or delete tests. Never use production data or credentials. Before any destructive database command, confirm that the database is disposable. At the end, report exact commands and exit codes, test counts, files changed, unresolved blockers, and the next action. Do not begin Task 1 until Task 0 is genuinely green in CI.

## D. Verify the first CI run

1. Push the changes Claude makes to GitHub.
2. Open the repository's **Actions** tab.
3. Open the workflow run triggered by the push or pull request.
4. Inspect each step. A green workflow must show successful Laravel bootstrap, fresh MySQL migrations, seeders, and automated tests—not merely a workflow file existing.
5. Save the run URL, commit SHA and test counts in `docs/IMPLEMENTATION_PLAN.md`.

If the workflow fails, give Claude the exact failed log excerpt and ask it to diagnose and fix the root cause. Do not ask it to suppress the failure.

## E. Protect the repository

After the initial repository exists, configure a ruleset or branch protection for `main`:
- Require pull requests before merging.
- Require the Laravel tests status check to pass.
- Block force pushes and deletion of `main`.
- Enable Dependabot/security alerts and secret scanning/push protection where available.
- Limit repository access to trusted collaborators.

## F. Hosting boundary

Do not deploy this scaffold directly to the live cPanel site. First complete Task 0 and the feature tasks in order, then deploy a staging environment with separate credentials and database. Production deployment requires a reviewed release, backup and restore plan, HTTPS, `APP_DEBUG=false`, protected storage, scheduler/queue configuration and a rollback procedure.
