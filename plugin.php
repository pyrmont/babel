<?php

class HD_Babel extends KokenPlugin {

    const TAG = 'babel';

    protected $delim;
    protected $sep;
    protected $langs;
    protected $lang;

    function __construct()
    {
        $this->require_setup = true;

        $this->register_filter('api.album', 'setup');
        $this->register_filter('api.content', 'setup');
        $this->register_filter('api.text', 'setup');
        $this->register_filter('site.output', 'setup');

        $this->register_filter('api.album', 'tag_album');
        $this->register_filter('api.content', 'tag_content');
        $this->register_filter('api.text', 'tag_text');
        $this->register_filter('site.output', 'parse_output');
    }

    function setup($data)
    {
        if (isset($this->sep, $this->langs, $this->lang) === true) { return $data; } // Setup has already been performed.

        $this->delim = json_decode('"' . '\u200C' . '"');

        $this->sep = $this->data->separator;

        $this->langs = [];
        array_push($this->langs, $this->data->l1);
        array_push($this->langs, $this->data->l2);

        $choice = ($_COOKIE['lang'] != '') ? $_COOKIE['lang'] : 'default';
        $this->lang = ($choice !== 'default') ? array_search($choice, $this->langs) : 1; // Uses the first language if no language has been set.
        $this->lang = ($this->lang === false) ? 0 : $this->lang;

        return $data;
    }

    function tag_album($data)
    {
        $fields = array('title', 'summary', 'description');
        foreach ($fields as $field)
        {
            $data[$field] = $this->mb_str_replace($this->delim, '', $data[$field]);
            $data[$field] = $this->delim . $data[$field] . $this->delim;
        }

        return $data;
    }

    function tag_content($data)
    {
        $fields = array('title', 'caption');
        foreach ($fields as $field)
        {
            $data[$field] = $this->mb_str_replace($this->delim, '', $data[$field]);
            $data[$field] = $this->delim . $data[$field] . $this->delim;
        }

        return $data;
    }

    function tag_text($data)
    {
        $fields = array('title', 'excerpt', 'content');
        foreach ($fields as $field)
        {
            $data[$field] = $this->mb_str_replace($this->delim, '', $data[$field]);
            $data[$field] = $this->delim . $data[$field] . $this->delim;
        }

        return $data;
    }

    function parse_output($html)
    {
        Koken::$cache_path = str_replace('/cache.', '/cache.' . $this->langs[$this->lang] . '.', Koken::$cache_path);

        $dom = new DOMDocument();
        $dom->loadHTML($html);

        $output = $html;
        $output = $this->filter_on_delim($dom, $output);
        // $output = $this->filter_on_tag($dom, $output);
        $output = $this->filter_on_sep($dom, $output);

        return $output;
    }

    private function filter_on_delim($html)
    {
        $html = preg_replace_callback("/" . $this->delim . "(.*?)" . $this->delim . "/s", function($matches) {
            $text = $matches[1];

            if (strpos($text, $this->sep) === false)
            {
                return $text;
            }

            if (strpos($text, '<') !== false)
            {
                $dom = new DOMDocument();
                $dom->loadHTML(mb_convert_encoding($text, 'HTML-ENTITIES', 'UTF-8'));

                $text = '';

                $elements = $dom->getElementsByTagName('*');
                foreach ($elements as $element)
                {
                    if ($element->nodeName !== 'html' && $element->nodeName !== 'body' )
                    {
                        $text = ($element->nodeValue === $this->sep) ? $text . $this->sep : $text . $dom->saveHTML($element);
                    }
                }
            }

            $pieces = explode($this->sep, $text);

            for ($i = 0; $i < sizeof($pieces); $i++)
            {
                $pieces[$i] = ($i == $this->lang) ? $pieces[$i] : '';
            }

            $text = implode($pieces);
            return $text;
        }, $html);

        return $html;
    }

    private function filter_on_tag($dom, $html)
    {
        $elements = $dom->getElementsByTagName(self::TAG);
        $output = mb_ereg_replace("/\s+|\n+|\r/", ' ', $html);

        foreach ($elements as $element)
        {
            $needle = '';
            $inner_html = '';
            foreach ($element->childNodes as $childNode)
            {
                $needle = $needle . $dom->saveHTML($childNode);
                $inner_html = ($childNode->nodeValue === $this->sep) ? $inner_html . $this->sep : $inner_html . $dom->saveHTML($childNode);
            }
            $needle = str_replace('> <', '><', preg_replace("/\s+|\n+|\r/", ' ', $needle));

            $pieces = explode($this->sep, $inner_html);
            $replacement = '';
            for ($i = 0; $i < sizeof($pieces); $i++)
            {
                $replacement = ($i == $this->lang) ? $replacement . $pieces[$i] : $replacement . '';
            }

            $output = str_replace($needle, $replacement, $output);
        }

        $output = str_ireplace('<' . self::TAG . '>', '', $output);
        $output = str_ireplace('</' . self::TAG . '>', '', $output);

        return $output;
    }

    private function filter_on_sep($dom, $html)
    {
        $xpath = new DOMXpath($dom);
        $elements = $xpath->query("//*[text()[contains(.,'" . $this->sep . "')]]");
        $output = $html;

        foreach ($elements as $element)
        {
            // if (title) { ... } Title is a special case that needs to be handled differently.

            $inner_html = '';
            foreach ($element->childNodes as $childNode)
            {
                $inner_html = $inner_html . $dom->saveHTML($childNode);
            }

            $pieces = explode($this->sep, $inner_html);
            for ($i = 0; $i < sizeof($pieces); $i++)
            {
                if ($i != $this->lang)
                {
                    $output = str_replace($pieces[$i], '', $output);
                }
            }
        }

        $output = str_replace($this->sep, '', $output);

        return $output;
    }

    private function mb_str_replace($needle, $replacement, $haystack)
    {
        $needle_len = mb_strlen($needle);
        $replacement_len = mb_strlen($replacement);
        $pos = mb_strpos($haystack, $needle);
        while ($pos !== false)
        {
            $haystack = mb_substr($haystack, 0, $pos) . $replacement . mb_substr($haystack, $pos + $needle_len);
            $pos = mb_strpos($haystack, $needle, $pos + $replacement_len);
        }
        return $haystack;
    }
}