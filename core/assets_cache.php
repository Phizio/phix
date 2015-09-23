<?php
use MatthiasMullie\Minify; 
	/************************************************************************
	 * CSS and Javascript Combinator 0.5
	 * Copyright 2006 by Niels Leenheer
	 *
	 * Permission is hereby granted, free of charge, to any person obtaining
	 * a copy of this software and associated documentation files (the
	 * "Software"), to deal in the Software without restriction, including
	 * without limitation the rights to use, copy, modify, merge, publish,
	 * distribute, sublicense, and/or sell copies of the Software, and to
	 * permit persons to whom the Software is furnished to do so, subject to
	 * the following conditions:
	 * 
	 * The above copyright notice and this permission notice shall be
	 * included in all copies or substantial portions of the Software.
	 *
	 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
	 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
	 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
	 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
	 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
	 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
	 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
	 */

function get_include_contents($filename) {
    if (is_file($filename)) {
        ob_start();
        include $filename;
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }
    return false;
}

function combine($array,$min_name,$type)
{	
    global $app;       
    $elements=$array['files'];
    
    // Determine supported compression method
        $gzip = strstr($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
        $deflate = strstr($_SERVER['HTTP_ACCEPT_ENCODING'], 'deflate');

        // Determine used compression method
        $encoding = $gzip ? 'gzip' : ($deflate ? 'deflate' : 'none');

        // Check for buggy versions of Internet Explorer
        if (!strstr($_SERVER['HTTP_USER_AGENT'], 'Opera') && 
                preg_match('/^Mozilla\/4\.0 \(compatible; MSIE ([0-9]\.[0-9])/i', $_SERVER['HTTP_USER_AGENT'], $matches)) {
                $version = floatval($matches[1]);

                if ($version < 6)
                        $encoding = 'none';

                if ($version == 6 && !strstr($_SERVER['HTTP_USER_AGENT'], 'EV1')) 
                        $encoding = 'none';
        }

        // Try the cache first to see if the combined files were already generated
        if($encoding=='gzip') $encoding='gz';
        $cachefile = CACHE_DIR . '/' . $min_name . ($encoding != 'none' ? '.' . $encoding : '');
        if($app['os_windows']) $cachefile =  str_replace ('/', '\\', $cachefile);
        
        if (file_exists(MC_ROOT.$cachefile)) {
               return str_replace('\\','/',$cachefile);
        }
        
        // Get contents of the files
        $contents = '';
        
        if($type=='javascript') $minifier = new Minify\JS();
        else $minifier = new Minify\CSS();
        
        while (list(,$element) = each($elements)) {
                $path = MC_ROOT .  $element;
                if($app['os_windows']) $path =  str_replace ('/', '\\', $path);
                //$contents .= "\n" . get_include_contents($path);
                
                $minifier->add($path);

        }       
        //$contents = call_user_func('minify_' . $type, $contents); //minification
        
        if (isset($encoding) && $encoding != 'none') 
        {
                //$contents = gzencode($contents, 9, $gzip ? FORCE_GZIP : FORCE_DEFLATE);                
                $minifier->gzip(MC_ROOT.$cachefile);
        } 
        else $minifier->minify(MC_ROOT.$cachefile);

        // Store cache
//        if ($fp = fopen(MC_ROOT.$cachefile, 'wb')) {
//                fwrite($fp, $contents);
//                fclose($fp);
//        }

        return str_replace('\\','/',$cachefile);
}