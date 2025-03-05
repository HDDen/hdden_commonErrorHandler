<?php
// https://gist.github.com/HDDen/07deba7a4b2b1b80fca2acee1dc0628f
// 1.1.0
if (!\function_exists('hdden__minified_varexport')){
    function hdden__minified_varexport($value, $method = 'var_export'){
        $result = '';
    
        $methods = [
            'var_export',
            'print_r',
        ];
    
        if (!in_array($method, $methods)){
            $method = $methods[0];
        }

        $delimitier = ';--------------hdden-delimiter--------------;';
        $delimitier_regexp = '/(\;--------------hdden-delimiter--------------\;[\r\n]*)+/m';
    
        $result = $method($value, true);
        
        $formatted = $result;
        $formatted = preg_replace('/^Array$/m', '', $formatted); // Array (first line)
        $formatted = preg_replace('/^(\s)*\[(\d)+\]\s=>\sArray(\s)*$/m', '', $formatted); // [0] => Array
    
        $formatted = preg_replace('/^(\s)*array \($/m', '', $formatted); // array (  - addon for var_export
        $formatted = preg_replace('/^(\s)*(\d)+ =>(\s)*$/m', '', $formatted); // 0 => - addon for var_export
        $formatted = preg_replace('/^(\s)*\),(\s)*$/m', '', $formatted); // ), - addon for var_export
    
        $formatted = preg_replace('/^(\s)*\($/m', '', $formatted); // (
        $formatted = preg_replace('/^\s*\)$/m', $delimitier, $formatted); // ( and )
        $formatted = preg_replace('/^\s*\v+/m', '', $formatted); // empty newlines
    
        // format indents
        $strings_array = preg_split("/\r\n|\n|\r/", $formatted);
        $base_shift = '    ';
        $prev_shift = false;
        $current_shift = '';
        $raw_prev_shift = false;
        $raw_current_shift = '';
        $matches = [];
        foreach ($strings_array as $str_index => $str) {
            
            // skip delimitier
            if ($str === $delimitier) continue;

            preg_match('/^\s*/m', $str, $matches);
            if (isset($matches[0])){
    
                $new_current_shift = $matches[0];
                $raw_current_shift = $new_current_shift;
    
                // calc base indent
                if ($prev_shift === false){
                    // first elem, take it's indent as a base
                    if ($new_current_shift !== ''){
                        $base_shift = $new_current_shift;
                    }
                }
    
                // check if its indent is correct
                if ( $prev_shift !== false ){
                    $strlen_prev = strlen($raw_prev_shift);
                    $strlen_current = strlen($raw_current_shift);
    
                    // how much indents in prev?
                    $temp_explode = array_reverse(explode($base_shift, $prev_shift));
                    if ($temp_explode[0] !== $base_shift) unset($temp_explode[0]);
                    $prev_indents = count($temp_explode);
                    unset($temp_explode);
    
                    if ($strlen_prev < $strlen_current){
                        // new is deeper
                        $current_shift = str_repeat($base_shift, $prev_indents+1);
                    
                    } elseif ($strlen_prev > $strlen_current){
                        // new is upper
                        $current_shift = str_repeat($base_shift, $prev_indents-1);
                    } else{
                        $current_shift = $prev_shift;
                    }
    
                    // store string
                    $strings_array[$str_index] = substr_replace($str, $current_shift, 0, $strlen_current);
    
                    // remember indent
                    $prev_shift = $current_shift;
                } else {
                    $prev_shift = $new_current_shift;
                }
    
                $raw_prev_shift = $raw_current_shift;
            }
        }
        $formatted = implode(PHP_EOL, $strings_array);

        // beautify delimitiers
        $formatted = preg_replace($delimitier_regexp, PHP_EOL, $formatted);

        // pass result
        $result = $formatted;
    
        return $result;
    }
}