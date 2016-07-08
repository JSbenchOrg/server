#!/usr/bin/env bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

if [ -f "$DIR/.version-bump-flag" ]; then
    rm $DIR/.version-bump-flag
else
    touch $DIR/.version-bump-flag
    export FULL_VERSION=$(cat $DIR/../VERSION)
    export VERSION=$(echo $FULL_VERSION | grep -Eo "v[[:digit:]].[[:digit:]]")
    export PATCH=${FULL_VERSION##*.}
    echo "$VERSION.$((PATCH+1))" > $DIR/../VERSION
    git add $DIR/../VERSION
    git commit --amend --no-edit
fi