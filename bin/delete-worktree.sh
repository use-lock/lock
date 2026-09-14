#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 1 ]]; then
    echo "Usage: bin/delete-worktree.sh <name> [--force]" >&2
    exit 1
fi

name="$1"
force=false
if [[ "${2:-}" == "--force" ]]; then
    force=true
fi

if [[ ! "$name" =~ ^[a-zA-Z0-9._-]+$ ]]; then
    echo "error: <name> may only contain letters, numbers, dots, dashes, underscores" >&2
    exit 1
fi

repo_root="$(git rev-parse --show-toplevel)"
worktree_dir="$(dirname "$repo_root")/lock-$name"
site_name="lock-$name"

branch="$(git -C "$repo_root" worktree list --porcelain | awk -v dir="$worktree_dir" '
    $1 == "worktree" { path = $2 }
    $1 == "branch" && path == dir { sub("refs/heads/", "", $2); print $2 }
')"

if [[ -z "$branch" ]]; then
    echo "error: $worktree_dir is not a registered git worktree" >&2
    exit 1
fi

if [[ "$force" != true ]] && [[ -n "$(git -C "$worktree_dir" status --porcelain)" ]]; then
    echo "error: $worktree_dir has uncommitted changes — commit/stash them or rerun with --force" >&2
    exit 1
fi

herd unsecure "$site_name"
herd unlink "$site_name"

# Without --force the branch delete is `-d`, so git refuses to drop work that
# was never merged.
if [[ "$force" == true ]]; then
    git -C "$repo_root" worktree remove "$worktree_dir" --force
    git -C "$repo_root" branch -D "$branch"
else
    git -C "$repo_root" worktree remove "$worktree_dir"
    git -C "$repo_root" branch -d "$branch"
fi

git -C "$repo_root" worktree prune
