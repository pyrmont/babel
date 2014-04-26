<?php

class HD_Babel extends KokenPlugin {

    const TAG = 'babel';
    const DELIM = '\u200C';
    const LANG_DEFAULT = 0;

    protected $is_setup;
    protected $delim;
    protected $sep;
    protected $langs;
    protected $lang;
    protected $default_labels;
    protected $user_labels;

    function __construct()
    {
        $this->require_setup = true;

        $this->register_hook('after_opening_body', 'setup');

        $this->register_hook('after_opening_body', 'insert_controls');

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
        if ($this->is_setup) { return $data; }

        $this->delim = json_decode('"' . self::DELIM . '"');

        $this->sep = $this->data->separator;

        $this->langs = [];
        foreach ($this->data as $key => $val)
        {
            if (preg_match('/l([0-9]+)$/', $key) && strpos($val, '|') !== false)
            {
                $matches = [];
                preg_match('/l([0-9]+)$/', $key, $matches);
                $pieces = explode('|', $val);
                $lang = [];
                $lang['name'] = $pieces[0];
                $lang['code'] = $pieces[1];
                $lang['order'] = $matches[1] - 1;
                $this->langs[$lang['order']] = $lang;
            }
        }

        if ($_COOKIE['babel_lang']) { $this->lang = $this->lang_search($_COOKIE['babel_lang'], $this->langs); }

        $this->default_labels = array(
            'content_pl' => 'Contents',
            'favorite_pl' => 'Favorites',
            'album_pl' => 'Albums',
            'set_pl' => 'Sets',
            'essay_pl' => 'Essays',
            'page_pl' => 'Pages',
            'tag_pl' => 'Tags',
            'category_pl' => 'Categories',
            'timeline_pl' => 'Timelines',
            'archive_pl' => 'Archives',

            'content_sg' => 'Content',
            'favorite_sg' => 'Favorite',
            'album_sg' => 'Album',
            'set_sg' => 'Set',
            'essay_sg' => 'Essay',
            'page_sg' => 'Page',
            'tag_sg' => 'Tag',
            'category_sg' => 'Category',
            'timeline_sg' => 'Timeline',
            'archive_sg' => 'Archive',

            'home' => 'Home',
            'read_more' => 'Read more'
        );

        $this->user_labels = [];
        $keys = array_keys($this->default_labels);
        foreach ($keys as $key)
        {
            if (empty($this->data->$key)) {
                foreach ($this->langs as $lang)
                {
                    $this->user_labels[$lang['order']][$key] = $this->default_labels[$key];
                }
            }
            else
            {
                $label = $this->data->$key;
                $pieces = explode($this->sep, $label);
                foreach ($pieces as $index => $piece)
                {
                    $this->user_labels[$index][$key] = trim($piece);
                }
            }
        }

        $this->is_setup = true;

        return $data;
    }

    /**
     * Inserts controls to change the language after the opening <body> tag. Controls are wrapped in a <div>
     * for easy manipulation via CSS.
     */
    function insert_controls()
    {
        $langs = $this->langs;
        $current = $this->langs[$this->lang];

        ob_start();
        include('inc/controls.php');
        $html = ob_get_contents();
        ob_end_clean();

        echo $html;
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
        Koken::$cache_path = $this->mb_str_replace('/cache.', '/cache.' . $this->langs[$this->lang]['code'] . '.', Koken::$cache_path);

        $html = $this->translate_navigation($html);
        $html = $this->translate_labels($html);
        $html = $this->translate_title($html);

        $html = $this->filter_on_delim($html);
        $html = $this->filter_on_tag($html);
        $html = $this->filter_on_sep($html);

        return $html;
    }

    private function translate_navigation($html)
    {
        foreach ($this->default_labels as $key => $needle)
        {
            $html = preg_replace_callback('/(<a (?:[^>]*)data-koken-internal(?:[^>]*)>)(' . $needle . ')(<\/a>)/s', function($matches) use ($key) {
                $opening = $matches[1];
                $closing = $matches[3];
                $user_label = $this->user_labels[$this->lang][$key];
                return $opening . $user_label . $closing;
            }, $html);
        }

        return $html;
    }

    private function translate_labels($html)
    {
        $html = preg_replace_callback('/<' . self::TAG . ':(label)>([^<]*)<\/' . self::TAG . ':\1>/s', function($matches) {
            if ($key = array_search($matches[2], $this->default_labels)) {
                $text = $this->user_labels[$this->lang][$key];
            }
            return $text;
        }, $html);

        return $html;
    }

    private function translate_title($html)
    {
        $relative_urls = array('/contents/' => 'content_pl', '/favorites/' => 'favorite_pl', '/albums/' => 'album_pl', '/sets/' => 'set_pl', '/essays/' => 'essay_pl', '/tags/' => 'tag_pl', '/categories/' => 'category_pl', '/timeline/' => 'timeline_sg');
        $relative_url = substr(Koken::$original_url, (strpos(Koken::$original_url, '?') + 1));
        if ($relative_urls[$relative_url])
        {
            $correct_title = $this->user_labels[$this->lang][$relative_urls[$relative_url]];
            $html = preg_replace_callback('/<title>(.*)<\/title>/s', function($matches) use ($correct_title) {
                return '<title>' . $correct_title . ' ' . Koken::$the_title_separator . ' ' . Koken::$site['title'] . '</title>';
            }, $html);
        }
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
        $html = preg_replace_callback('/<' . self::TAG . ':([A-z][A-z])>([^<]*)<\/' . self::TAG . ':\1>/s', function($matches) {
            $text = ($matches[1] == $this->langs[$this->lang]['code']) ? $matches[2] : '';
            return $text;
        }, $html);

        return $html;
    }

    private function filter_on_sep($html)
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
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

    private function lang_search($code, $langs)
    {
        foreach ($langs as $lang)
        {
            if ($lang['code'] === $code) { return $lang['order']; }
        }

        return self::LANG_DEFAULT;
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