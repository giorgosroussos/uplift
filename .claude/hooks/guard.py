#!/usr/bin/env python3
"""Claude Code PreToolUse hook for app-design projects.

Two jobs:
1. Protect the source of truth. Edit/Write tools and shell commands may not
   modify design/, build/ (except build/briefs/), tools/design/, CLAUDE.md or
   the hook configuration itself, unless the session was started by the
   engineer in design mode:

       APP_DESIGN_MODE=1 claude

   The variable lives in the Claude Code process environment, which the agent
   cannot change from inside the session.
2. Keep integration with the engineer: git merge / rebase / cherry-pick /
   pull and pushes to main/master or forced pushes are always blocked, in
   every mode. Agents stop at "ready to merge".

Exit code 2 blocks the tool call and shows the message to the agent.
The shell check is best effort; CI (tools/design/ci_check.py) is the backstop.
"""
import json
import os
import re
import sys

PROTECTED = [r"design/", r"build/(?!briefs/)", r"tools/design/", r"CLAUDE\.md$",
             r"\.claude/settings(\.local)?\.json$", r"\.claude/hooks/"]
DESIGN_MODE = os.environ.get("APP_DESIGN_MODE") == "1"

GIT_OPTS = r"\bgit\s+(?:(?:-C|-c|--git-dir|--work-tree)\s+\S+\s+|-\S+\s+)*"
GIT_BLOCK = re.compile(GIT_OPTS + r"(merge|rebase|cherry-pick|pull)\b")
PUSH_BLOCK = re.compile(GIT_OPTS + r"push\b.*(\b(main|master)\b|--force|\s-f\b)")
WRITE_OPS = re.compile(r"(>>?|\btee\b|\bsed\s+-i|\bmv\b|\bcp\b|\brm\b|\btruncate\b|\bchmod\b|"
                       r"\bln\b|\bperl\s+-pi|\bgit\s+(checkout|restore)\b)")
SHELL_PROTECTED = ["design/", "build/", "tools/design/", "CLAUDE.md", ".claude/settings",
                   ".claude/hooks"]
ALLOWED_CMDS = re.compile(r"^\s*(python3?|uv run python)\s+tools/design/"
                          r"(build\.py\s+brief|validate\.py|impact\.py)\b")


def block(msg):
    print(msg, file=sys.stderr)
    sys.exit(2)


def rel(path):
    root = os.environ.get("CLAUDE_PROJECT_DIR", os.getcwd())
    p = os.path.normpath(os.path.join(root, path))
    return os.path.relpath(p, root).replace(os.sep, "/")


def is_protected(path):
    r = rel(path)
    return not r.startswith("..") and any(re.match(p, r) for p in PROTECTED)


def main():
    try:
        data = json.load(sys.stdin)
    except Exception:
        sys.exit(0)
    tool = data.get("tool_name", "")
    inp = data.get("tool_input") or {}

    if tool in ("Edit", "Write", "MultiEdit", "NotebookEdit"):
        path = inp.get("file_path") or inp.get("notebook_path") or ""
        if path and is_protected(path) and not DESIGN_MODE:
            block(f"Blocked: {rel(path)} is part of the design source of truth. If the spec "
                  "needs to change, set your task to blocked and file a change request in "
                  "progress/requests/ (see CLAUDE.md).")
        sys.exit(0)

    if tool == "Bash":
        cmd = inp.get("command", "")
        m = GIT_BLOCK.search(cmd)
        if m:
            block(f"Blocked: 'git {m.group(1)}' is reserved for the engineer. Stop at "
                  "ready_to_merge and report; never integrate or resolve conflicts.")
        if PUSH_BLOCK.search(cmd):
            block("Blocked: pushing to main/master or force-pushing is reserved for the engineer.")
        if not DESIGN_MODE and not ALLOWED_CMDS.search(cmd) and WRITE_OPS.search(cmd):
            for bare in SHELL_PROTECTED:
                if bare in cmd:
                    block(f"Blocked: this command may modify '{bare}', which is protected "
                          "design source. File a change request instead.")
    sys.exit(0)


if __name__ == "__main__":
    main()
