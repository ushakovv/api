#!/bin/bash

DIR="$( cd -P "$( dirname "${BASH_SOURCE[0]}" )" && pwd )";

printf "\033[36mCheck server extentions:\033[0m\n"
php "$DIR/cron/_check_extentions.php"

#printf "\033[36m
#Rollback local changes:\033[0m\n"
#hg up -C

#printf "\033[36m
#Pulling and update changes:\033[0m\n"
#hg pull -u

printf "\033[36m
Check files and directories:\033[0m\n"
echo "Chmod 0755 for PhantomJs..."
PHANTOMJS="$DIR/data/phantomjs/phantomjs";
chmod 0755 $PHANTOMJS

SCREENSHOTJS="$DIR/data/phantomjs/screenshot.local.js"
if [ ! -f "$SCREENSHOTJS" ]
then {
	echo "Create screenshot.local.js"
	"$DIR/data/phantomjs/screenshot.local.js.php" >> "$SCREENSHOTJS" 2>&1 &

	echo "Chmod 0744 for screenshot.local.js"
	chmod 0744 "$SCREENSHOTJS"
}
else {
	echo "Check screenshot.local.js - Success"
}
fi;

echo "Check and create directory [tmp/log], chmod 0775 for it..."
TMPDIR="$DIR/tmp"
if [[ ! -d "$TMPDIR" && ! -L "$TMPDIR" ]] ; then
    # Создать папку, только если ее не было и не было символической ссылки
    mkdir $TMPDIR
    chmod 0775 "$TMPDIR"
fi;
LOGDIR="$TMPDIR/log";
if [[ ! -d "$LOGDIR" && ! -L "$LOGDIR" ]] ; then
    mkdir $LOGDIR
    chmod 0775 "$LOGDIR"
fi;

#printf "\033[36m
#Summary info:\033[0m\n"
#hg sum

#printf "\033[36m
#Migrations run:\033[0m\n"
#"$DIR/bin/phinx" migrate
