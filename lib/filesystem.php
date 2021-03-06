<?php
namespace framework\lib;

//a simple wrapper around the filesystem to be able to use files in the right directory
use framework\core\Loader;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Filesystem extends \framework\core\Lib
{

    var $customExtensions = array("css" => "text/css", "svg" => "image/svg+xml");

    //generate a full path
    var $ignores = false;

    var $debugLevel = -1;//filter logging to avoid big overhead when logging is turned off.


    function getDebugLevel()
    {
        if ($this->debugLevel == -1) {
            $this->debugLevel = $this->debug->getLevel();
        }
        return $this->debugLevel;
    }

    function getFile($file)
    {
        if ($fullPath = $this->findRightPath($file)) {
            if ($this->getDebugLevel() <= 2) {
                $this->debug->info("read file", array("file" => $fullPath, "relativePath" => $file), "file");
            }
            return file_get_contents($fullPath);
        }
        return false;
    }

    //read a file entirely

    function findRightPath($file)
    {
        if (!(substr($file, 0, 1) == "/" || substr($file, 0, 2) == "~/")) {
            $fullPath = $this->calculatePath("project/modules/" . Loader::$module . "/" . $file);
            if (file_exists($fullPath)) {
                return $fullPath;
            }

            $fullPath = $this->calculatePath("modules/" . Loader::$module . "/" . $file);
            if (file_exists($fullPath)) {
                return $fullPath;
            }

            $fullPath = $this->calculatePath("project/" . $file);
            if (file_exists($fullPath)) {
                return $fullPath;
            }

        }

        $fullPath = $this->calculatePath($file);
        if (file_exists($fullPath)) {
            return $fullPath;
        }


        $this->debug->error("File not found:", array("file" => $file), "file");

        return false;
    }

    function calculatePath($file)
    {
        //if the string doesnt start with / or ~/ it will be considered a project file
        if (!(substr($file, 0, 1) == "/" || substr($file, 0, 2) == "~/" || substr($file, 1, 2) == ":\\")) {
            $exp = explode("/", str_replace("\\", "/", $file));
            if ($exp[0] == "project") {
                $path = DIR_PROJECT;
                unset($exp[0]);
            } elseif ($exp[0] == "modules" && @$exp[1] == "developer") {
                $path = DIR_FRAMEWORK . "/modules/";
                unset($exp[0]);
            } elseif ($exp[0] == "modules") {
                $path = DIR_MODULES;
                unset($exp[0]);
            } elseif ($exp[0] == "data") {
                $path = DIR_DATA;
                unset($exp[0]);
            } elseif ($exp[0] == "framework") {
                $path = DIR_FRAMEWORK;
                unset($exp[0]);
            } elseif ($exp[0] == "temp") {
                $path = sys_get_temp_dir()."/";
                unset($exp[0]);
            } else {
                $path = DIR_FRAMEWORK;
            }

            $file = $path . implode("/", $exp);
        }

        //if it starts with ~/ it will be considered a developer file..
        if (substr($file, 0, 2) == "~/") {
            $file = str_replace("~/", $_SERVER["HOME"] . "/", $file);
        }
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $file = str_replace("/", "\\", $file);
        }
        return $file;
    }

    //getContentType
    function getContentType($file)
    {
        $exp = explode(".", $file);
        $ext = $exp[count($exp) - 1];
        if (isset($this->customExtensions[$ext])) {
            return $this->customExtensions[$exp[count($exp) - 1]];
        } elseif ($fullPath = $this->findRightPath($file)) {
            if (function_exists("mime_content_type")) {
                return mime_content_type($fullPath);
            } else {
                $this->mimeContentTypeFallback($ext);
            }
        }
        return false;
    }

    function mimeContentTypeFallback($ext)
    {
        //1 day cache refresh

        //time()-86400 means a cache refresh every 24 hours.
        $mimeTypes = $this->cache->runCached("mimeTypes", [], time() - 86400, function ($data) {
            $url = "http://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types";
            $mimeTypes = [];
            foreach (@explode("\n", @file_get_contents($url)) as $x)
                if (isset($x[0]) && $x[0] !== '#' && preg_match_all('#([^\s]+)#', $x, $out) && isset($out[1]) && ($c = count($out[1])) > 1)
                    for ($i = 1; $i < $c; $i++)
                        $mimeTypes[$out[1][$i]] = $out[1][0];

            return $mimeTypes;
        });

        return @$mimeTypes[$ext];
    }

    //create a directory
    function mkDir($folder)
    {
        $path = $this->calculatePath($folder);
        if (!$this->exists($path)) {
            if ($this->getDebugLevel() <= 2) {
                $this->debug->info("Directory created", array("folder" => $path, "relativePath" => $folder), "file");
            }
            if (mkdir($path, 0744, true)) {
                $this->changeOwner($path);
                return true;
            }
            return false;
        }
    }

    function exists($path)
    {
        $path = $this->calculatePath($path);
        return file_exists($path);
    }

    function getDirs($dir, $useIgnores = true)
    {
        static $dirResults;

        if (!$dirResults) {
            $dirResults = array();
        }

        $ignores = $this->getIgnores();
        $path = $this->calculatePath($dir);
        $dirs = array();
        if (!isset($dirResults[$path])) {
            if ($this->exists($path)) {
                if ($handle = opendir($path)) {
                    while (false !== ($entry = readdir($handle))) {
                        if ($entry != "." && $entry != ".." && is_dir($path . "/" . $entry)) {
                            if (!is_array($ignores) || !in_array($entry, $ignores)) {
                                $dirs[] = $entry;
                            }
                        }
                    }
                    closedir($handle);
                }
            }
            $dirResults[$path] = $dirs;
        }

        if ($this->getDebugLevel() <= 2) {
            $this->debug->info("Search for directories", array("folder" => $dir, "resultCount" => count(@$dirResults["path"])), "file");
        }

        return $dirResults[$path];
    }

    //create an array of all directories

    function getIgnores($useIgnores = true)
    {
        if (!$useIgnores) {
            return false;
        }
        if ($this->ignores === false) {
            $this->ignores = $this->config->get("filesystem.ignore", "server");
            if (!$this->ignores) {
                $this->ignores = array();
            }
        }
        return $this->ignores;

    }

    function getFilesRecursive($dir, $type = false,$filter=false)
    {
        if (!is_array($dir)) {
            $dir = array($dir);
        }
        $ignores = $this->getIgnores();
        $files = array();
        foreach ($dir as $currentDir) {
            if ($this->exists($currentDir)) {
                $path = $this->calculatePath($currentDir);
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS));
                foreach ($it as $path) {
                    if (
                        (!is_array($ignores) || !in_array($path, $ignores)) && (!$type || substr($path, -strlen($type)) == $type) &&
                        (!$filter || strpos($path->getFilename(), $filter) !== false)
                    ) {
                        $files[] = $path->getRealPath();
                    }
                }
            }
        }

        if ($this->getDebugLevel() <= 2) {
            $this->debug->info("Search for files recursively", array("folder" => $dir, "resultCount" => count($files), "filter" => $type ?: "no"), "file");
        }

        return $files;
    }


    function getProjectFiles($dir, $recursive = false, $prefix = true)
    {
        $projectDir = 'project/' . $dir;
        $modulesDir = 'project/modules/';

        $files = $this->getFiles($projectDir, false, false, $prefix);
        foreach ($this->getDirs($projectDir) as $folder) {
            $folderFiles = $this->getFiles($projectDir . $folder . "/", false ,false,true);
            $files = array_merge($files, $folderFiles);
        }

        foreach ($this->getDirs($modulesDir) as $moduleFolder) {
            $projectDir = $modulesDir . $moduleFolder . "/" . $dir;
            $moduleFiles = $this->getFiles($projectDir, false, false, true);
            $files = array_merge($files, $moduleFiles);

            if ($recursive) {
                foreach ($this->getDirs($projectDir) as $folder) {
                    $folderFiles = $this->getFiles($projectDir . $folder . "/", false, false, true);
                    $files = array_merge($files, $folderFiles);
                }
            }
        }

        return $files;
    }

    function getFilesRecursiveWithInfo($dir, $type = false)
    {
        if (!is_array($dir)) {
            $dir = array($dir);
        }
        $ignores = $this->getIgnores();
        $files = array();
        foreach ($dir as $currentDir) {
            if ($this->exists($currentDir)) {
                $path = $this->calculatePath($currentDir);
                $realPath = $path;
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS));
                foreach ($it as $path) {
                    if ((!is_array($ignores) || !in_array($path, $ignores)) && (!$type || substr($path, -strlen($type)) == $type)) {
                        $addFile["absolutePath"] = $path->getRealPath();
                        $addFile["relativePath"] = str_replace($realPath, $currentDir, $addFile["absolutePath"]);
                        $addFile["path"] = str_replace($realPath, "", $path->getRealPath());
                        $files[] = $addFile;
                    }
                }
            }
        }


        if ($this->getDebugLevel() <= 2) {
            $this->debug->info("Search for files recursively", array("folder" => $dir, "resultCount" => count($files), "filter" => $type ?: "no"), "file");
        }

        return $files;
    }

    //create an array of all files
    function getFiles($dir, $type = false, $useIgnores = false, $prefix = false)
    {
        if ($prefix === true) {
            $prefix = substr($dir, -1) == '/' ? $dir : $dir . '/';
        }
        $ignores = $this->getIgnores();
        $path = $this->calculatePath($dir);
        $files = array();
        if ($this->exists($path)) {
            if ($handle = opendir($path)) {
                while (false !== ($entry = readdir($handle))) {
                    if ($entry != "." && $entry != ".." && !is_dir($path . "/" . $entry) && (!$type || substr($entry, -strlen($type)) == $type)) {
                        if ((!is_array($ignores) || !in_array($entry, $ignores))) {
                            $files[] = $prefix ? $prefix . $entry : $entry;
                        }
                    }
                }
                sort($files, SORT_NATURAL);
                closedir($handle);
            }
        }

        if ($this->getDebugLevel() <= 2) {
            $this->debug->info("Search for files", array("folder" => $dir, "resultCount" => count($files), "filter" => $type ?: "no"), "file");
        }

        return $files;
    }

    //write to content file if file exists clear it first

    function getArray($file, $noDebug = false)
    {
        if ($path = $this->findRightPath($file)) {

            if (!$noDebug && $this->getDebugLevel() <= 2) { //to avoid infinite loop with config.
                $this->debug->info("Read array from file", array("file" => $path, "relativePath" => $file), "file");
            }

            return include $path;
        }
        return array();

    }

    function writeArray($file, $data)
    {
        if ($this->getDebugLevel() <= 2) {
            $this->debug->info("Write array to file", array("relativePath" => $file), "file");
        }

        $serialized = "<?php return " . var_export($data, true) . ";";
        $this->clearWrite($file, $serialized);
    }

    function clearWrite($path, $content)
    {
        $fullPath = $this->calculatePath($path);
        $handle = fopen($fullPath, "w+");
        if (fwrite($handle, $content)) {
            if ($this->getDebugLevel() <= 2) {
                $this->debug->info("Write to file", array("file" => $fullPath, "relativePath" => $path), "file");
            }
        } else {
            $this->debug->error("Writing to file failed", array("file" => $fullPath, "relativePath" => $path), "file");

            return false;
        }
        fclose($handle);

        $this->changeOwner($fullPath);
        return true;
    }

    function append($path, $content)
    {
        $fullPath = $this->calculatePath($path);
        $handle = fopen($fullPath, "a");
        if (fwrite($handle, $content)) {
            if ($this->getDebugLevel() <= 2) {
                $this->debug->info("Append to file", array("file" => $fullPath, "relativePath" => $path), "file");
            }
        } else {
            $this->debug->error("Appending to file failed", array("file" => $fullPath, "relativePath" => $path), "file");
        }
        fclose($handle);
    }

    function getModified($file)
    {
        $path = $this->findRightPath($file);
        if ($path) {
            return filemtime($path);
        }
        return -1;
    }

    function rm($file)
    {
        $relativePath = $file;
        $file = $this->calculatePath($file);
        if ($this->exists($file)) {
            if (is_dir($file)) {
                $dir = $file;
                $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
                $files = new RecursiveIteratorIterator($it,
                    RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($files as $currentFile) {
                    if ($currentFile->isDir()) {
                        rmdir($currentFile->getRealPath());
                    } else {
                        unlink($currentFile->getRealPath());
                    }
                }
                rmdir($dir);

                if ($this->getDebugLevel() <= 2) {
                    $this->debug->info("Removed folder", array("file" => $file, "relativePath" => $relativePath), "file");
                }
            } else {
                unlink($file);
                if ($this->getDebugLevel() <= 2) {
                    $this->debug->info("Removed file", array("file" => $file, "relativePath" => $relativePath), "file");
                }
            }

        } else {
            $this->debug->error("Failed to remove file", array("file" => $file, "relativePath" => $relativePath), "file");
        }
    }

    function md5($file)
    {
        $path = $this->calculatePath($file);
        if ($this->exists($path)) {
            return md5_file($path);
        }
        return false;
    }

    function isSame($file1, $file2)
    {
        return $this->md5($file1) == $this->md5($file2);
    }

    function codeSize($directory)
    {
        $directory = $this->calculatePath($directory);
        if ($directory && $this->exists($directory)) {
            $size = 0;
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
                $ext = $file->getExtension();
                if ($ext == "php" || $ext == "tpl") {
                    $size += $file->getSize();
                }
            }
            return $size;
        }
        return 0;
    }

    function dirSize($directory)
    {
        $directory = $this->calculatePath($directory);
        if ($directory && $this->exists($directory)) {
            $size = 0;
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
                $ext = $file->getExtension();
                $size += $file->getSize();
            }
            return $size;
        }
        return 0;
    }

    function prefixFilesWithFolder($files, $folder)
    {
        foreach ($files as $key => $file) {
            $files[$key] = $folder . $file;
        }
        return $files;
    }

    function cp($from, $to)
    {
        $from = $this->calculatePath($from);
        if ($this->exists($from)) {

            $to = $this->calculatePath($to);
            if (is_dir($from)) {
                $dir_handle=opendir($from);
                while($file=readdir($dir_handle)) {
                    if ($file != "." && $file != "..") {
                        if (is_dir($from . "/" . $file)) {
                            if (!is_dir($to . "/" . $file)) {
                                $this->mkDir($to . "/" . $file);
                            }
                        }
                        $this->cp($from . "/" . $file, $to . "/" . $file);
                    }
                }
            } else {

                if (copy($from, $to)) {
                    if ($this->getDebugLevel() <= 2) {
                        $this->debug->info("Copied file", array("from" => $from, "to" => $to), "file");
                    }
                } else {
                    $this->debug->error("Failed to copy file", array("from" => $from, "to" => $to), "file");
                }
            }

        }
    }

    function changeOwner($file, $owner = false, $group = false)
    {
        if (!$owner) {
            if(!($owner = $this->config->get('filesystem.owner','server'))) {
                return false;
            }
        }
        if (!$group) {
            $group = $this->config->get('filesystem.group','server') ?: $owner;
        }

        $filePath = $this->calculatePath($file);

        return $this->chown($filePath, $owner, $group);
    }

    function chown($filePath, $owner, $group)
    {
        if (!is_dir($filePath)) {
            return chown($filePath, $owner) && chgrp($filePath, $group);
        } else {
            chown($filePath, $owner);
            chgrp($filePath, $group);
        }

        $directory = new \DirectoryIterator($filePath);
        $success = true;

        foreach ($directory as $item) {
            if (!$item->isDot()) {
                if (!$this->chown($item->getPathname(), $owner, $group)) {
                    $success = false;
                }
            }
        }

        return $success;
    }
}
