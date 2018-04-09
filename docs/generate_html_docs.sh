#!/bin/bash

SCRIPT_DIR=$(pwd)
cd $SCRIPT_DIR

HTML_DIR="$SCRIPT_DIR/html"
if ! [ -d $HTML_DIR ]; then
	mkdir -m 0755 $HTML_DIR
fi

HTML_PATH_PRIVATE="$HTML_DIR/private.html"
echo -n -e "Generation \033[33mPRIVATE\033[0m documentation... "
raml2html private.raml > $HTML_PATH_PRIVATE
echo -e "\033[32mSuccess!\033[0m"
echo -e "Documentation saved in: \033[36m$HTML_PATH_PRIVATE\033[0m"

#HTML_PATH_PUBLIC="$HTML_DIR/public.html"
#echo -n -e "Generation \033[33mPUBLIC\033[0m documentation... "
#raml2html public.raml > $HTML_PATH_PUBLIC
#echo -e "\033[32mSuccess!\033[0m"
#echo -e "Documentation saved in: \033[36m$HTML_PATH_PUBLIC\033[0m"