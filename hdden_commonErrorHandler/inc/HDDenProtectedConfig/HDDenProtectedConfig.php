<?php
/**
 * Читает / пишет файл в .php-контейнер с защищенным началом
 * Use '...filename.json.php' as path, add .php manually!
 * 
 * 1.0.2
 */
class HDDenProtectedConfig{
    private $fileStart = '<?php die(); /*';
    private $fileExt = '.php';
    private $filePath = '';

    public function __construct($path = '', $fileStart = '', $fileExt = '')
    {
        if (!empty(trim($path))){
            $this->filePath = $path;
        }

        if (!empty(trim($fileStart))){
            $this->fileStart = $fileStart;
        }

        if (!empty(trim($fileExt))){
            if (mb_substr($fileExt, 0, 1) !== '.') $fileExt = '.'.$fileExt;
            $this->fileExt = $fileExt;
        }
    }

    /**
     * Read file, decode and return array with it
     * ['content' => '...']
     */
    public function read($onlycontent = true, $strictJson = false){

        $result = [
            'status' => '',
            'content' => '',
            'type' => '',
        ];

        $file = null;

        if (!is_readable($this->filePath)){
            $result['content'] = null;
            $result['status'] = 'File "'.$this->fileStart.'" doesnt exists';

            if ($onlycontent){
                return $result['content'];
            }

            return $result;
        }

        try {
            // get contents
            $file = file_get_contents($this->filePath);

            // remove first line, if protected
            $file = preg_split("/\r\n|\n|\r/", $file);
            if ($file[0] === $this->fileStart){
                unset($file[0]);
            }

            // imploding
            $file = implode("", $file);

            // decoding
            if ($this->is_JSON($file)){
                $file = json_decode($file, true);
                $result['type'] = 'json';
            } else {
                $result['type'] = 'text';
            }

        } catch (\Throwable $e) {
            $result['content'] = null;
            $result['status'] = 'There is error during content extraction, '.$e->getMessage();
            $result['type'] = '';

            if ($onlycontent){
                return $result['content'];
            }

            return $result;
        }

        // post-checking
        if (is_null($file)){
            $result['content'] = null;
            $result['status'] = 'There is error after content extraction';
            $result['type'] = '';
        } elseif ($result['type'] !== 'json') {
            $result['content'] = $file;
            $result['status'] = 'Success, but returned '.$result['type'];

            if ($strictJson){
                $result['content'] = null;
                $result['status'] = 'Extracted '.$result['type'].', but we are in strict json mode.';
            }
        } else {
            $result['content'] = $file;
            $result['status'] = 'Success';
        }

        if ($onlycontent){
            return $result['content'];
        }

        return $result;
    }

    public function readWithStatus(){
        return $this->read(false);
    }

    /**
     * Save result file as protected
     * Overwrites file
     */
    public function store($data, $json = null){

        if ($json === false){
            // keep content as is

        } elseif ($json === true){
            // convert to json
            $data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            // autotest, is string json
            if (!($this->is_JSON($data))){
                $data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
        }

        if (!is_string($data)) $data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // add heading
        $data = $this->fileStart.PHP_EOL.$data;

        // if file doesnt exists (we creating new one),
        // transform name to protected variant
        if (!file_exists($this->filePath)){
            $file_extension = $this->getFileExtension($this->filePath);

            if ($file_extension['parts'][0] !== trim($this->fileExt, '.')){
                $this->filePath .= '.'.trim($this->fileExt, '.');
            }
        }

        // save file
        return (file_put_contents($this->filePath, $data, LOCK_EX) === false ? false : true);
    }

    /**
     * Just appends data with glue. No encryption added
     */
    public function append($data, $glue = PHP_EOL){
        return (file_put_contents($this->filePath, $glue.$data, LOCK_EX | FILE_APPEND) === false ? false : true);
    }

    /**
     * Check, if string is correct json
     */
    private function is_JSON($str) {
        if (!is_string($str)) return false;
        json_decode($str);
        return (json_last_error()===JSON_ERROR_NONE);
    }

    /**
     * Get file extension, full ('.php') and splitted multi reversed (['php', 'inc'])
     */
    private function getFileExtension($path){
        $result = [
            'full' => '',
            'parts' => [],
        ];

        if ($path) {
            $filename = basename($path);

            if (mb_strpos($path, '.') !== false){

                $temporary = explode('.', $filename);
                unset($temporary[0]);
                
                $result['parts'] = array_reverse($temporary);
                $result['full'] = mb_substr($filename, mb_strpos($filename, '.')+1);
            }
        }

        if (!isset($result['parts'][0])) $result['parts'][0] = '';
        return $result;
    }
}