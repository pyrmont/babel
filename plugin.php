<?php

class HD_Babel extends KokenPlugin {

    protected $sep;
    protected $langs;
    protected $lang;

    function __construct()
    {
        $this->require_setup = true;

        $this->register_filter('api.text', 'setup');
        $this->register_filter('site.output', 'setup');

        $this->register_filter('api.text', 'wrap_text');
        $this->register_filter('site.output', 'parse_output');
    }

    function setup($data)
    {
        if (isset($this->sep, $this->langs, $this->lang) === true) { return $data; } // Setup has already been performed.

        $this->sep = $this->data->separator;
        $this->langs = [];
        array_push($this->langs, $this->data->l1);
        array_push($this->langs, $this->data->l2);

        $choice = ($_COOKIE['lang'] != '') ? $_COOKIE['lang'] : 'default';
        $this->lang = ($choice !== 'default') ? array_search($choice, $this->langs) : 1; // Uses the first language if no language has been set.
        $this->lang = ($this->lang === false) ? 0 : $this->lang;

        return $data;
    }

    function wrap_text($data)
    {
        $fields = array('title', 'excerpt', 'content');
        foreach ($fields as $field)
        {
            $data[$field] = '<babel>' . $data[$field] . '</babel>';
        }

        return $data;
    }

    function parse_output($html)
    {
        Koken::$cache_path = str_replace('/cache.', '/cache.' . $this->langs[$this->lang] . '.', Koken::$cache_path);

        $output = $html;
        $output = preg_replace("/\s+|\n+|\r/", ' ', $html);

        $dom = new DOMDocument();
        $dom->loadHTML($html);

        // Filter text wrapped in <babel> tags.

        $elements = $dom->getElementsByTagName('babel');
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

        $output = str_replace('<babel>', '', $output);
        $output = str_replace('</babel>', '', $output);

        // Filter text based on position of separator.

        $xpath = new DOMXpath($dom);
        $elements = $xpath->query("//*[text()[contains(.,'" . $this->sep . "')]]");
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
}