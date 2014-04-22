<?php

class HD_Babel extends KokenPlugin {
    
    function __construct()
    {
        $this->register_filter('site.output', 'render');
        $this->register_filter('api.content', 'translate');
    }
    
    function translate($content)
    {
        return $content;
    }
    
    function render($html)
    {
        $sep = '|||';
        $lang = 0; // This should be the language selected by the user.
        
        $output = preg_replace('/[ \t]+/', ' ', preg_replace('/\s*$^\s*/m', "\n", $html));
        
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        
        $xpath = new DOMXpath($dom);
        $elements = $xpath->query("//*[text()[contains(.,'" . $sep . "')]]");
        
        foreach ($elements as $element)
        {
            // if (title) { ... } Title is a special case that needs to be handled differently.  
            
            $innerhtml = '';
            foreach ($element->childNodes as $childNode)
            {
                $innerhtml = $innerhtml . $dom->saveHTML($childNode);
            }

            $parts = explode($sep, $innerhtml);
            for ($i = 0; $i < sizeof($parts); $i++)
            {
                if ($i != $lang)
                {
                    $output = str_replace($parts[$i], '', $output);
                }
            }
        } // This is not the best solution. Create a new DOM element (including childnodes) and replace the existing node and then output the entire thing to HTML.
        
        $output = str_replace($sep, '', $output);
        
        return $output;
    }

}