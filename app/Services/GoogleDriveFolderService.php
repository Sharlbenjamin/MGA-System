<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

class GoogleDriveFolderService
{
    public function generateGoogleDriveFolder($file)
    {
        $folderName = $file->mga_reference;

        $caseYear = $file->created_at->year;
        $caseMonth = $file->created_at->month;
        $caseDay = $file->created_at->day;

        //check if the year folder exists

        //check if the month folder exists inside the year folder

        //check if the day folder exists inside the month folder

        // create the folder

        //past the folder link in the $file->goole_drive_link

    }
}
