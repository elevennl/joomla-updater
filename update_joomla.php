<?php

/**
 * Joomla-updater
 * Copyright (C) 2015 Eleven BV
 * www.eleven.nl
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

ini_set('display_errors', 'on');
error_reporting(E_ALL);

// defines needed for Joomla includes
define('_JEXEC', 1);
define('JPATH_PLATFORM', 1);
define('JPATH_BASE', dirname(__FILE__));

parse_str(implode('&', array_slice($argv, 1)), $_GET);
main();

function main($path = null)
{
    $logfile = '../../' . date('Y-m-d') . '.log';

    if ($path == null) {
        $path = $_GET['path'];
    }
    echo $path . "\n";

    $updateURL = getUpdateSite('lts');
    $updates = findUpdates($updateURL);
    $current = getCurrent($path);

    if ($current !== false) {
        $version_current = $current->release . '.' . $current->dev_level;
        echo "Current Joomla version: " . $version_current . "\n";

        if ((int)$current->release < 2) {
            echo "Cannot update this Joomla version, exiting...\n\n";
            return;
        }

        if (isset($updates[$current->release]['version'])) {
            $version_latest = $updates[$current->release]['version'];
            echo "Latest Joomla version: " . $version_latest . "\n";
        } else {
            echo "Latest Joomla version: (not found), exiting...\n\n";
            exit;
        }

        if (version_compare($version_latest, $version_current) > 0) {
            $detailsurl = $updates[$current->release]['detailsurl'];

            //determine target version: x.x from x.x.x
            $target_arr = explode('.', $version_latest);
            $target = $target_arr[0] . '.' . $target_arr[1];

            // get xml from detailsurl
            try {
                $xml = simplexml_load_file($detailsurl);
            } catch (Exception $exc) {
                echo "Empty parsing url: " . $detailsurl . ", exiting...\n\n";
                exit;
            }

            // get the update with the correct targetplatform
            $downloadurl = getDownloadURL($xml, $target);
            if ($downloadurl) {
                $download_arr = explode('/', $downloadurl);
                $filename = '../../../' . $download_arr[count($download_arr) - 1];
                echo $filename . "\n";
                $dir = str_replace('.zip', '', $filename);
                $fh = fopen($logfile, 'a');
                fwrite($fh, $path . ': ' . $version_current . ' -> ' . $version_latest . "\n");
                fclose($fh);
                downloadAndExtract($filename, $dir, $downloadurl);
                copyAllFiles($dir, $path);
                echo "Joomla update is done!\n\n";
            } else {
                echo "No download url found\n\n";
            }
        } else {
            // already up to date
            // no action needed
        }
    } else {
        echo "No Joomla >= 1.5 detected\n\n";
    }
}

function getUpdateSite($type)
{
    switch ($type) {
        // "Long Term Support (LTS) branch - Recommended"
        case 'lts':
            $updateURL = 'http://update.joomla.org/core/list.xml';
            break;

        // "Short term support (STS) branch"
        case 'sts':
            $updateURL = 'http://update.joomla.org/core/sts/list_sts.xml';
            break;

        // "Testing"
        case 'testing':
            $updateURL = 'http://update.joomla.org/core/test/list_test.xml';
            break;
    }
    return $updateURL;
}

function findUpdates($url)
{
    try {
        $xml = simplexml_load_file($url);
    } catch (Exception $exc) {
        echo "Empty parsing url: " . $url . ", exiting...\n\n";
        exit;
    }


    foreach ($xml->children() as $child) {
        $result[(string)$child['targetplatformversion']]['version'] = (string)$child['version'];
        $result[(string)$child['targetplatformversion']]['detailsurl'] = (string)$child['detailsurl'];
    }
    return $result;

}

function getCurrent($path)
{
    $file15 = $path . '/libraries/joomla/version.php';
    $file25 = $path . '/libraries/cms/version/version.php'; // for 2.5 and 3.x
    if (file_exists($file25)) {
        require_once $file25;
    } else if (file_exists($file15)) {
        require_once $file15;
    } else {
        return false;
    }
    $jversion = new JVersion;
    $current = new stdClass();
    $current->release = $jversion->RELEASE;
    $current->dev_level = $jversion->DEV_LEVEL;
    unset($jversion);
    return $current;
}

function getDownloadURL($xml, $target)
{
    $targetplatform_version = '';
    $downloadurl = '';

    foreach ($xml->children() as $child) {
        foreach ($child->children() as $key => $grandchild) {
            if ($key == 'downloads') {
                $url = (string)$grandchild->downloadurl;
            }
            if ($key == 'targetplatform') {
                $targetplatform_version = (string)$grandchild['version'];
            }

            if ($targetplatform_version == $target) {
                $downloadurl = $url;
                return $downloadurl;
            }
        }
    }
    return $downloadurl;
}

function copyAllFiles($source, $dest)
{
    foreach (
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST) as $item
    ) {
        if ($item->isDir()) {
            @mkdir($dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
        } else {
            copy($item, $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
        }
    }
}

function downloadAndExtract($filename, $dir, $downloadurl)
{
    if (!file_exists($filename)) {
        // download update package
        $return = file_put_contents($filename, file_get_contents($downloadurl));
        if ($return == false) {
            echo "Error while downloading: " . $downloadurl . ", exiting...\n\n";
            exit;
        }
        echo "Downloaded size: " . $return . "\n";

        extractZip($filename, $dir);
    }
}

function extractZip($filename, $dir)
{
    $zip = new ZipArchive;
    if ($zip->open($filename) === TRUE) {
        $zip->extractTo($dir);
        $zip->close();
    } else {
        echo "Failed extracting zip " . $filename . ", exiting\n\n";
        exit;
    }
}
