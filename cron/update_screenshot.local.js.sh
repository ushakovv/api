#!/bin/bash

DIR="$( cd -P "../$( dirname "${BASH_SOURCE[0]}" )" && pwd )";

SCREENSHOTJSNEW="$DIR/data/phantomjs/screenshot.local.js.new"
if [ -f "$SCREENSHOTJSNEW" ]
then {
	echo "Remove old screenshot.local.js.new"
	rm "$SCREENSHOTJSNEW"
}
fi;

echo "Create screenshot.local.js.new"
"$DIR/data/phantomjs/screenshot.local.js.php" >> "$SCREENSHOTJSNEW" 2>&1 &
echo "Chmod 0744 for screenshot.local.js.new"
chmod 0744 "$SCREENSHOTJSNEW"

SCREENSHOTJS="$DIR/data/phantomjs/screenshot.local.js"
if [ -f "$SCREENSHOTJS" ]
then {
	echo "Remove old screenshot.local.js"
	rm "$SCREENSHOTJS"
}
fi;

echo "Rename screenshot.local.js.new to screenshot.local.js"
mv "$SCREENSHOTJSNEW" "$SCREENSHOTJS"