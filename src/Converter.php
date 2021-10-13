<?php

namespace Awssat\Tailwindo;

class Converter
{
    protected $givenContent = '';

    protected $isCssClassesOnly = false;

    protected $changes = 0;

    protected $lastSearches = [];

    /** @var \Awssat\Tailwindo\Framework */
    protected $framework;


    public function __construct(?string $content = null)
    {
        if (!empty($content)) {
            $this->givenContent = $content;
        }

        return $this;
    }

    public function setContent(string $content): self
    {
        $this->givenContent = $content;

        return $this;
    }

    public function setFramework(string $framework): self
    {
        $framework = 'Awssat\\Tailwindo\\Framework\\' . ucfirst($framework).'Framework';

        $this->framework = new $framework;

        return $this;
    }

    /**
     * Is the given content a CSS content or HTML content.
     */
    public function classesOnly(bool $value): self
    {
        $this->isCssClassesOnly = $value;

        return $this;
    }

    public function convert(): self
    {
        foreach($this->framework->get() as $item) {
           foreach ($item as $search => $replace) {
               $this->searchAndReplace($search, $replace);
           }
        }

        return $this;
    }

    /**
     * Get the converted content.
     */
    public function get(): string
    {
        $this->givenContent = preg_replace('/\{tailwindo\|([^\}]+)\}/', '$1', $this->givenContent);

        return $this->givenContent;
    }

    /**
     * Get the number of committed changes.
     */
    public function changes(): int
    {
        return $this->changes;
    }

    /**
     * search for a word in the last searches.
     */
    protected function isInLastSearches(string $searchFor, int $limit = 0): bool
    {
        $i = 0;

        foreach ($this->lastSearches as $search) {
            if (strpos($search, $searchFor) !== false) {
                return true;
            }

            if ($i++ >= $limit && $limit > 0) {
                return false;
            }
        }

        return false;
    }

    protected function addToLastSearches($search)
    {
        $this->changes++;

        $this->lastSearches[] = stripslashes($search);

        if (count($this->lastSearches) >= 10) {
            $this->lastSearches = array_slice($this->lastSearches, -10, 10, true);
        }
    }

    /**
     * Search the given content and replace.
     *
     * @param string $search
     * @param string|\Closure $replace
     */
    protected function searchAndReplace($search, $replace): void
    {
        if($replace instanceof \Closure) {
            $callableReplace = \Closure::bind($replace, $this, self::class);
            $replace = $callableReplace();
        }

        $regexStart = !$this->isCssClassesOnly ? '(?<start>class\s*=\s*(?<quotation>["\'])((?!\k<quotation>).)*)' : '(?<start>\s*)';
        $regexEnd = !$this->isCssClassesOnly ? '(?<end>((?!\k<quotation>).)*\k<quotation>)' : '(?<end>\s*)';

        $search = preg_quote($search);

        $currentSubstitute = 0;

        while (true) {
            if (strpos($search, '\{regex_string\}') !== false || strpos($search, '\{regex_number\}') !== false) {
                $currentSubstitute++;
                foreach (['regex_string'=> '[a-zA-Z0-9]+', 'regex_number' => '[0-9]+'] as $regeName => $regexValue) {
                    $regexMatchCount = preg_match_all('/\\\\?\{'.$regeName.'\\\\?\}/', $search);
                    $search = preg_replace('/\\\\?\{'.$regeName.'\\\\?\}/', '(?<'.$regeName.'_'.$currentSubstitute.'>'.$regexValue.')', $search, 1);
                    $replace = preg_replace('/\\\\?\{'.$regeName.'\\\\?\}/', '${'.$regeName.'_'.$currentSubstitute.'}', $replace, $regexMatchCount > 1 ? 1 : -1);
                }

                continue;
            }

            break;
        }

        if(! preg_match_all('/'.$regexStart.'(?<given>(?<![\-_.\w\d])'.$search.'(?![\-_.\w\d]))'.$regexEnd.'/i', $this->givenContent, $matches, PREG_SET_ORDER)) {
            return;
        }
  
        foreach ($matches as $match) {
            $result = preg_replace_callback(
                '/(?<given>(?<![\-_.\w\d])'.$search.'(?![\-_.\w\d]))/',
                function ($match) use ($replace) {
                 return preg_replace_callback('/\$\{regex_(string|number)_(\d+)\}/', function ($m) use ($match) {
                     return $match['regex_'.$m[1].'_'.$m[2]];
                 }, $replace);
             },
                $match[0]
            );

            if (strcmp($match[0], $result) !== 0) {
    
                if ($count = preg_match_all('/\{tailwindo\|.*?\}/', $result)) {
                    if ($count > 1) {
                        $result = preg_replace('/\{tailwindo\|.*?\}/', '', $result, $count - 1);
                    }
                }
    
                $this->givenContent = str_replace($match[0], $result, $this->givenContent);
                $this->addToLastSearches($search);
            }
        }
    }
}
