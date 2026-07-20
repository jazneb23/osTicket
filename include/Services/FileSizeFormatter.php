<?php

class FileSizeFormatter {

    static function file_size($bytes) {

        if(!is_numeric($bytes))
            return $bytes;
        if($bytes<1024)
            return $bytes.' bytes';
        if($bytes < (900<<10))
            return round(($bytes/1024),1).' kb';

        return round(($bytes/1048576),1).' mb';
    }
}
