#!/usr/bin/env bash
echo ./extra/version_bump.sh > .git/hooks/post-commit
chmod +x .git/hooks/post-commit