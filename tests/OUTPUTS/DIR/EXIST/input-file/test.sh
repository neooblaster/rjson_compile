#!/bin/sh
mkdir OUTPUT 2>/dev/null || true
rm OUTPUT/config.json 2>/dev/null || true
rjson-compile -i config.rjson -o OUTPUT
cat OUTPUT/config.json
