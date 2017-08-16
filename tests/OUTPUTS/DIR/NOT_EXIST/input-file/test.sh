#!/bin/sh
rm -r OUTPUT 2>/dev/null || true
rjson-compile -i config.rjson -o OUTPUT/
cat OUTPUT/config.json
