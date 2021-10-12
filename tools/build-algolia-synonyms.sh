#!/usr/bin/env bash

set -e

FILES="./synonyms/*.txt"

for file in $FILES;
do
  filename=$(basename $file .txt)
  echo "$filename"
  cat $file | jq -R '{ type: "onewaysynonym",input: split(" => ") | .[0], synonyms: split(" => ") | .[1] | split(", ") }' | jq -s > "./synonyms/algolia/${filename}.json"
done