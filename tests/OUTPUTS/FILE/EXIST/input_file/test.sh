#!/bin/sh
echo > compiled.json
rjson-compile -i config.rjson -o compiled.json
cat compiled.json
