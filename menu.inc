<?php
/**
 * #############################################################
 * ## AI Mode Plugin for FPP (fpp-AImode)                     ##
 * ## Author: jessica12ryan                                   ##
 * ## URL: https://github.com/jessica12ryan/fpp-AImode        ##
 * ## menu.inc                                                ##
 * #############################################################
 */

$aimIconUrl = 'api/plugin/fpp-AImode/icon?v=' . @filemtime(__DIR__ . '/icon.png');
$menuEntries = Array(
    Array(
        'text' => '<img src="' . $aimIconUrl . '" width="16" height="16" class="align-middle me-1"> AI Mode',
        'type' => 'content',
        'page' => 'assistant.php',
        'wrap' => 1
    ),
    Array(
        'text' => '<img src="' . $aimIconUrl . '" width="16" height="16" class="align-middle me-1"> AI Mode',
        'type' => 'status',
        'page' => 'status.php',
        'wrap' => 1
    ),
    Array(
        'text' => '<img src="' . $aimIconUrl . '" width="16" height="16" class="align-middle me-1"> AI Mode',
        'type' => 'help',
        'page' => 'about.php',
        'wrap' => 1
    )
);

foreach ($menuEntries as $entry)
{
    if ($entry['type'] != $menu)
        continue;

    if (preg_match('/^http.?:\/\//', $entry['page']))
    {
        printf("<li><a href='%s' target='_blank'>%s</a></li>\n",
            $entry['page'], $entry['text']);
    }
    else
    {
        $nopage = '';
        if (isset($entry['wrap']) && ($entry['wrap'] == 0))
            $nopage = '&nopage=1';

        printf("<li><a href='plugin.php?plugin=%s&page=%s%s'>%s</a></li>\n",
            $plugin, $entry['page'], $nopage, $entry['text']);
    }
}
?>
