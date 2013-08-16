<?php
/**
 * Fetch the list of language names from the URL below. This script requires the PHP DOM module.
 */
 
$url = "http://meta.wikimedia.org/wiki/Template:List_of_language_names_ordered_by_code";
$out = dirname(__FILE__)."/langnames.php";

//---------

if (file_exists($out)) {
  die("File already exists: $out\n");
}

// fetch page from URL and parse it
error_reporting(E_ALL & ~E_WARNING);
$doc = new DOMDocument();
$doc->loadHTMLFile($url);
error_reporting(E_ALL);

// find the data we're interested in
$tables = $doc->getElementsByTagName('table');
if (empty($tables)) die ("No table found");
$table = $tables->item(0);
$rows = $table->childNodes;
$langs = array();
foreach ($rows as $tr) {
  if ($tr->nodeName != 'tr') continue;
  $tds = $tr->childNodes;
  if ($tds->item(0)->tagName == 'th'
      && trim($tds->item(0)->textContent) == 'Old projects') {
    // skip everything under 'old projects'
    break;
  }
  unset ($code);
  $row = array();
  foreach ($tds as $td) {
    if ($td->nodeName != 'td') continue;
    if (!isset($code)) $code = trim($td->textContent);
    else $row[] = trim($td->textContent);
  }
  if (isset($code)) $langs[$code] = $row[2];
}

// write the data to a PHP file
ob_start();
echo '<'."?php\n";
echo "# This is a generated file.\n";
echo "# Generated from URL: $url\n";
echo "# Generated date: ".date('c')."\n";
echo "# Encoding: UTF-8\n";
echo "\n";
echo '$langnames = '; var_export($langs); echo ";\n";
$php = ob_get_clean();
$ok = file_put_contents($out, $php);
if ($ok) echo "File written successfully.\n";

// vim:ex:ts=2:sw=2:enc=utf-8:

