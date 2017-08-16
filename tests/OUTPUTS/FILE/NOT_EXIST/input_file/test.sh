#!/bin/sh
rm compiled.json 2>/dev/null || true
rjson-compile -i config.rjson -o compiled.json
cat compiled.json
