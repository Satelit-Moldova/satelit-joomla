<?php
/**
 * Builds dist/pkg_satelit-<version>.zip, the file an admin uploads in
 * System → Install → Extensions. Needs PHP with the zip extension.
 *
 *   php build/build.php
 */

$root    = dirname(__DIR__);
$src     = $root . '/src';
$dist    = $root . '/dist';
$xml     = simplexml_load_file($src . '/pkg/pkg_satelit.xml');
$version = (string) $xml->version;

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "PHP zip extension missing\n");
    exit(1);
}

@mkdir($dist, 0775, true);
$tmp = sys_get_temp_dir() . '/satelit-build-' . getmypid();
@mkdir($tmp, 0775, true);

/** Zips a folder's content (no top folder), in a stable order. */
function satelit_zip($dir, $file)
{
    $zip = new ZipArchive();

    if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('cannot write ' . $file);
    }

    $it    = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    $paths = array();

    foreach ($it as $f) {
        $paths[] = $f->getPathname();
    }

    sort($paths);

    foreach ($paths as $p) {
        $rel = strtr(substr($p, strlen($dir) + 1), DIRECTORY_SEPARATOR, '/');
        $zip->addFile($p, $rel);
    }

    $zip->close();
}

foreach (array('lib_satelit', 'mod_satelit', 'plg_content_satelit') as $ext) {
    satelit_zip($src . '/' . $ext, $tmp . '/' . $ext . '.zip');
}

$out = $dist . '/pkg_satelit-' . $version . '.zip';
$zip = new ZipArchive();
$zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFile($src . '/pkg/pkg_satelit.xml', 'pkg_satelit.xml');
$zip->addFile($src . '/pkg/script.php', 'script.php');

foreach (array('en-GB', 'ro-RO', 'ru-RU') as $tag) {
    $zip->addFile($src . "/pkg/language/$tag/$tag.pkg_satelit.sys.ini", "language/$tag/$tag.pkg_satelit.sys.ini");
}

foreach (array('lib_satelit', 'mod_satelit', 'plg_content_satelit') as $ext) {
    $zip->addFile($tmp . '/' . $ext . '.zip', 'packages/' . $ext . '.zip');
}

$zip->close();

foreach (glob($tmp . '/*.zip') as $f) {
    unlink($f);
}

rmdir($tmp);
echo $out, ' (', filesize($out), " bytes)\n";
