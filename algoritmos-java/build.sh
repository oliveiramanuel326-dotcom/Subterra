#!/bin/sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
BUILD="$ROOT/build"
MAIN="$ROOT/src/main/java"
TEST="$ROOT/src/test/java"

rm -rf "$BUILD"
mkdir -p "$BUILD/classes" "$BUILD/test-classes"

javac --release 17 --add-modules jdk.httpserver -encoding UTF-8 \
    -d "$BUILD/classes" "$MAIN"/subterra/algoritmos/*.java
javac --release 17 --add-modules jdk.httpserver -encoding UTF-8 \
    -cp "$BUILD/classes" -d "$BUILD/test-classes" \
    "$TEST"/subterra/algoritmos/*.java

java --add-modules jdk.httpserver -ea \
    -cp "$BUILD/classes:$BUILD/test-classes" \
    subterra.algoritmos.AlgorithmTests

jar --create --file "$BUILD/subterra-algoritmos.jar" \
    --main-class subterra.algoritmos.SubterraAlgorithmService \
    -C "$BUILD/classes" .
printf 'Built %s\n' "$BUILD/subterra-algoritmos.jar"
