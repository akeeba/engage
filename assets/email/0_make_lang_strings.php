<?php /** @noinspection PhpComposerExtensionStubsInspection */
/*
 * @package   paddle
 * @copyright Copyright (c)2020-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * @param   string  $file
 */
function parseHtml(string $file): void
{
	$html = file_get_contents($file);

	$dom = new DOMDocument();
	$dom->loadHTML($html);

	$title    = $dom->getElementsByTagName('title')->item(0)->nodeValue;
	$bodyNode = $dom->getElementsByTagName('body')->item(0);
	$bodyHtml = trim(str_replace(['<body>', '</body>'], ['', ''], $dom->saveHTML($bodyNode)));
	$bodyText = str_replace("\n", '\\n', trim(strip_tags(str_replace('</h3>', '\n' . str_repeat('-', 70), $bodyHtml))));
	$bodyHtml = str_replace("\n", '\\n', $bodyHtml);

	$title    = str_replace('"', '\\"', $title);
	$bodyHtml = str_replace('"', '\\"', $bodyHtml);
	$bodyText = str_replace('"', '\\"', $bodyText);

	$key = str_replace('-', '_', strtoupper(basename($file, '.html')));

	//echo 'COM_ENGAGE_MAIL_' . $key . '_TITLE="Akeeba Engage:' . "\"\n";
	//echo 'COM_ENGAGE_MAIL_' . $key . '_SHORT="' . "\"\n";
	echo 'COM_ENGAGE_MAIL_' . $key . '_DESC="Sent when ' . "\"\n";
	echo 'COM_ENGAGE_MAIL_' . $key . '_SUBJECT="' . $title . "\"\n";
	echo 'COM_ENGAGE_MAIL_' . $key . '_BODY="' . $bodyText . "\"\n";
	echo 'COM_ENGAGE_MAIL_' . $key . '_BODY_HTML="' . $bodyHtml . "\"\n";
}

ob_start();

$di = new DirectoryIterator(__DIR__);

/** @var DirectoryIterator $file */
foreach ($di as $file)
{
	if (!$file->isFile())
	{
		continue;
	}

	if ($file->getExtension() != 'html')
	{
		continue;
	}

	parseHtml($file->getPathname());

	echo "\n";
}

$contents = ob_get_clean();
file_put_contents('en-GB.ini', $contents);

echo $contents;
