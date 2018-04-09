#!/usr/bin/env php
<?php
require_once dirname(dirname(__FILE__)).'/classes/Bootstrap.php';

$conf = Config::get();
?>
var width = <?= $conf['template']['screenshot_width'] ?>;
var height = <?= round($conf['template']['preview_height'] * $conf['template']['screenshot_width'] / $conf['template']['preview_width']) ?>;
var webpage = require('webpage');

var system = require('system');
var address = system.args[1];
var output = system.args[2];

page = webpage.create();
page.viewportSize = {width: width, height: height};
page.open(address, function(status) {
	page.evaluate(function(w, h) {
		document.body.style.width = w + "px";
		document.body.style.height = h + "px";
	}, width, height);
	page.clipRect = {top: 0, left: 0, width: width, height: height};
	page.render(output);
	phantom.exit();
});