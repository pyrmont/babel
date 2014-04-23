<?php

class HD_Babel extends KokenPlugin {

    const TAG = 'babel';
    const DELIM = '\u200C';
    const LANG_DEFAULT = 0;

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

        $this->register_filter('api.album', 'delimit_album');
        $this->register_filter('api.content', 'delimit_content');
        $this->register_filter('api.text', 'delimit_text');
        $this->register_filter('site.output', 'parse_output');
    }

    /**
     * Sets up various instance variables that cannot be instantiated in the constructor.
     * @param mixed $data   Not used but required to be in the function definition for Koken's register_filter to work properly.
     * @return mixed        The $data parameter, unchanged.
     */
    function setup($data)
    {
        if (isset($this->sep, $this->langs, $this->lang) === true) { return $data; }

        $this->delim = json_decode('"' . self::DELIM . '"');

        $this->sep = $this->data->separator;

        $this->langs = [];
        array_push($this->langs, $this->data->l1);
        array_push($this->langs, $this->data->l2);

        $this->lang = ($_COOKIE['lang'] && array_search($_COOKIE['lang'], $this->langs)) ? array_search($_COOKIE['lang'], $this->langs) : self::LANG_DEFAULT;
        $this->lang = ($this->lang === false) ? 0 : $this->lang;

        return $data;
    }

    /**
     * Delimits the title, summary and description fields associated with an album using Babel's delimiter.
     * @param array $data   An array containing the title, summary and description data.
     * @return array        The updated $data array.
     */
    function delimit_album($data)
    {
        $fields = array('title', 'summary', 'description');
        foreach ($fields as $field)
        {
            $data[$field] = $this->mb_str_replace($this->delim, '', $data[$field]);
            $data[$field] = $this->delim . $data[$field] . $this->delim;
        }

        return $data;
    }

    /**
     * Delimits the title and caption fields associated with a piece of content using Babel's delimiter.
     * @param array $data   An array containing the title, summary and description data.
     * @return array        The updated $data array.
     */
    function delimit_content($data)
    {
        $fields = array('title', 'caption');
        foreach ($fields as $field)
        {
            $data[$field] = $this->mb_str_replace($this->delim, '', $data[$field]);
            $data[$field] = $this->delim . $data[$field] . $this->delim;
        }

        return $data;
    }

    /**
     * Delimits the title, excerpt and content fields associated with a piece of text using Babel's delimiter.
     * @param array $data   An array containing the title, summary and description data.
     * @return array        The updated $data array.
     */
    function delimit_text($data)
    {
        $fields = array('title', 'excerpt', 'content');
        foreach ($fields as $field)
        {
            $data[$field] = $this->mb_str_replace($this->delim, '', $data[$field]);
            $data[$field] = $this->delim . $data[$field] . $this->delim;
        }

        return $data;
    }

    /**
     * Parses the HTML output prior to delivery to the browser and filters out languages other than that selected.
     * First, filters based on Babel's delimiter, then Babel's tag and finally on the user set separator.
     * @param string $html   A string containing the HTML output.
     * @return string        The filtered HTML.
     */
    function parse_output($html)
    {
        Koken::$cache_path = $this->mb_str_replace('/cache.', '/cache.' . $this->langs[$this->lang] . '.', Koken::$cache_path);

        $html = $this->filter_on_delim($html);
        $html = $this->filter_on_tag($html);
        $html = $this->filter_on_sep($html);

        return $html;
    }

    private function filter_on_delim($html, $delims = null)
    {
        $delims = $delims ?: array('open' => $this->delim, 'close' => $this->delim);

        foreach ($delims as &$delim) {
            $delim = preg_quote($delim, '/');
        }

        $html = preg_replace_callback('/' . $delims['open'] . '(.*?)' . $delims['close'] . '/s', function($matches) {
            $text = $matches[1];

            if (strpos($text, $this->sep) === false) { return $text; }

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

    private function filter_on_tag($html)
    {
        $delims = array('open' => '<' . self::TAG . '>', 'close' => '</' . self::TAG . '>');

        $html = $this->filter_on_delim($html, $delims);
        return $html;
    }

    private function filter_on_sep($html)
    {
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        $xpath = new DOMXpath($dom);

        $elements = $xpath->query("//*[text()[contains(.,'" . $this->sep . "')]]");
        foreach ($elements as $element)
        {
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
                    $html = $this->mb_str_replace($pieces[$i], '', $html);
                }
            }
        }

        $html = $this->mb_str_replace($this->sep, '', $html);

        return $html;
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