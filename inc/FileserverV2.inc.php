<?php

require_once "hrm_config.inc.php";
require_once "Database.inc.php";

/**
 * Fileserver version 2 class
 */
class FileserverV2
{

    /**
     * Move uploaded file to its final destination.
     *
     * If the file is an archive, it will also be decompressed.
     *
     * @param $file Full path of the file to be moved.
     * @param $destDir Full path to the destination directory.
     * @param $errorMessage Reference to a string to store error messages.
     * @return bool True if moving was successful, false otherwise.
     */
    public static function moveUploadedFile($file, $destDir, &$errorMessage) {

        // Does the file exist?
        if (!is_file($file)) {
            errorMessage("The file $file does not exist!");
            return false;
        }

        // Sanitize file name
        $baseName = str_replace(" ", "_", basename($file));

        // Full path of destination file
        $destFile = $destDir . "/" . $baseName;

        // Get the file extension
        $extension = FileserverV2::getFileNameExtension($destFile);

        // Get the file name body (base name without extension)
        $bodyName = reset(explode('.', $baseName));

        // Is the file an archive?
        if (FileserverV2::isArchiveFile($file)) {

            // Get the output folder where to store the archive
            $destDecDir = FileserverV2::getValidFileOrDirName($destDir, $bodyName);

            // Did we get a valid destination folder name?
            if ($destDecDir == "") {

                // We ran out of suffixes. We return false.
                $errorMessage = "Too many folders with the same base name. Aborting...";
                return false;
            }

            // We expand the archive
            if (! FileserverV2::decompressArchive($file, $destDecDir)) {
                $errorMessage = "Failed decompressing archive file $file.";
                return false;
            }
        }

        // The file was not an archive, check that it is a valid image
        if (! FileserverV2::isValidImage($file)) {
            $errorMessage = "The file $file is not a valid image.";
            return false;
        }

        // Now move the file over (but make sure not to overwrite anything)
        $destDecFile = FileserverV2::getValidFileOrDirName($destDir, $bodyName, $extension);

        // Did we get a valid destination file name?
        if ($destDecFile == "") {

            // We ran out of suffixes. We return false.
            $errorMessage = "Too many files with the same base name. Aborting...";
            return false;
        }

        // Move the file
        $status = rename($file, $destDecFile);

        if (! $status) {
            $errorMessage = "Failed moving file $baseName to its final destination.";
        }

        return $status;

    }

    /**
     * Extract files from supported archive.
     * @param $file Archive file with full path.
     * @param $destDir Destination folder where the archive will be extracted.
     * @return bool True if the decompression was successful, false otherwise.
     */
    public static function decompressArchive($file, $destDir) {

        global $decompressBin;

        // Create the output directory
        if (!is_dir($destDir)) {
            mkdir($destDir);
        }

        // Get the archive type (Extension)
        $extension = strtolower(FileserverV2::getFileNameExtension($file));

        // Build the system command to execute
        $command = str_replace("%DEST%", "\"" . $destDir . "\"", $decompressBin[$extension]). " \"$file\"";

        // Run the command and collect the output
        $output = array();
        $result = -1;
        exec($command, $output, $result);

        // Return the status
        return ($result == 0);
    }

    /**
     * Check whether the file name has one of the recognized archive extensions.
     *
     * The recognized extensions are defined in the hrm_{servver}client}_config.inc files as $decompressBin.
     *
     * @param $fileName Full file name of the file to check.
     * @return bool True if the file is an a supported archive, false otherwise.
     */
    public static function isArchiveFile($fileName) {

        // Get the archive file extensions from the configuration files
        global $decompressBin;

        // Get the file extension
        $extension = FileserverV2::getFileNameExtension($fileName);

        // Is the extension one of the supported archive extensions
        return array_key_exists($extension, $decompressBin);

    }

    /**
     * Return all valid extensions.
     *
     * Valid extensions and image extensions, extra extensions, archive extensions.
     * @param bool $withLeadingDot Set to true to return the extensions with a leading 'dot'.
     * @return array of extensions.
     */
    public static function getAllValidExtensions($withLeadingDot = false) {

        global $decompressBin;

        // Image extensions
        $validImageExtensions = FileserverV2::getImageExtensions();

        // Extras extensions TODO: Add them to the database
        $extrasExtensions = array(".ids", '.idx.gz');

        // Archive extensions
        $archiveExtensions = array_keys($decompressBin);

        // Merge them and return
        $allExtensions = array_merge($validImageExtensions, $extrasExtensions, $archiveExtensions);

        // Should we append the leading dot?
        if ($withLeadingDot) {
            array_walk($allExtensions, function(&$value, &$key) {
                $value = "." . $value;
            });
        }

        return $allExtensions;

    }

    /**
     * Return all valid image extensions.
     * @return array of image extensions.
     */
    public static function getImageExtensions() {

        // Instantiate a new database connection
        $db = new DatabaseConnection();

        // Return the image extensions
        return $db->allFileExtensions();
    }

    /**
     * Check whether the file is of supported format.
     * @param $filename File name to be checked.
     * @param bool $alsoExtras If true consider also "ids" and "ids.gx" as supported formats.
     * @return bool True if the file name is supported, false otherwise.
     */
    public static function isValidImage($filename, $alsoExtras = false) {

        // Extract the file extension
        $extension = strtolower(FileserverV2::getFileNameExtension($filename));

        // Retrieve the valid file extensions from the database
        $validImageExtensions = FileserverV2::getImageExtensions();
        $status = in_array($extension, $validImageExtensions);

        // If the extension is in the standard formats, we can return
        if ($status) {
            return true;
        }

        // If the extension is not in the standard formats, we might have to check the extras.
        if ($alsoExtras) {
            $extras = array(".ids", '.idx.gz');
            $status = in_array($extension, $extras);
        }

        // Return
        return $status;

    }

    /**
     * Get file extension in a robust way.
     *
     * Double extensions (as in .ome.tif) are correctly returned. There
     * is no support for longer, composite extensions because they do not
     * occur in practice.
     *
     * @param $filename Filename to be processed.
     * @return String Complete extension.
     *
     */
    public static function getFileNameExtension($filename) {

        // Process the path information
        $info = pathinfo($filename);
        $info_ext = pathinfo($info["filename"], PATHINFO_EXTENSION);
        if ($info_ext == "") {
            return $info["extension"];
        } else {
            if (strlen($info_ext) > 4) {
                // Avoid pathological cases with dots somewhere in the file name.
                return $info["extension"];
            }
            return $info_ext . "." . $info["extension"];
        }
    }

    /**
     * Recursively delete a folder and all its content.
     */
    public static function removeDirAndContent($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object))
                        rrmdir($dir . "/" . $object);
                    else
                        unlink($dir . "/" . $object);
                }
            }
            rmdir($dir);
        }
    }

    /**
     * Get a file or directory name in the destination folder that does not point to an already existing object.
     *
     * The function returns the full path to a file or folder that is contained in the given destination directory
     * $destDir. If an object with that name already exists, a numeric suffix is added and the test is repeated.
     * If also the modified name exists, the suffix is incremented and tested again. The first name that does not
     * point to an already existing file or folder is returned.
     *
     * The input argument $fileOrDirBodyName is the base body name, i.e. without path and (in case of a file) extension.
     * If you want to get the full path to a file, please specify the third input argument $extension. Omit for folders.
     *
     * Please mind that there is cap of 1000 attempts.
     *
     * @param $destDir Parent directory for the file or folder to be tested.
     * @param $fileOrDirBodyName Base body name for the file (i.e. without extension) or folder.
     * @param $extension (optional) Extension for a file; omit for a folder.
     * @return string full path to the file or folder or "" if all attempts failed.
     */
    private static function getValidFileOrDirName($destDir, $fileOrDirBodyName, $extension = "") {

        // Make the function robust against $extension with or without a leading dot.
        if ($extension != "") {
            if (substr($extension, 0, 1) !== '.') {
                $extension = "." . $extension;
            }
        }

        // In case a file or folder with that name already exists, we append a numeric suffix.
        // We stop after $xMaxSuffix trials.
        $zSuffix = 0;
        $zMaxSuffix = 1000;

        // Tentative (full) file or folder name
        $testExpand = $destDir . "/" . $fileOrDirBodyName . $extension;

        // If the file/folder already exists, we try with incremental numeric suffixes
        while (file_exists($testExpand)) {

            // Increment the suffix
            $zSuffix++;

            // File name to test for existence
            $testExpand = $destDir . "/" . $fileOrDirBodyName . "_$zSuffix" . $extension;

            // Have we tried too many suffixes?
            if ($zSuffix > $zMaxSuffix) {

                // We ran out of suffixes. We return an empty string.
                return "";
            }
        }

        // If we need to add a suffix, we do it now
        if ($zSuffix > 0) {
            return $destDir . "/" . $fileOrDirBodyName . "_$zSuffix" . $extension;
        }

        // Complete destination folder
        return $destDir . "/" . $fileOrDirBodyName . $extension;

    }
}